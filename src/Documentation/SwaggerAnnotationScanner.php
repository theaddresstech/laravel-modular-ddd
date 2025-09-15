<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Documentation;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class SwaggerAnnotationScanner
{
    private string $modulesPath;

    public function __construct()
    {
        $this->modulesPath = Config::get('modular-ddd.modules_path');
    }

    /**
     * Scan a module for Swagger annotations
     */
    public function scanModule(string $moduleName, ?string $version = null): array
    {
        $modulePath = $this->modulesPath . '/' . $moduleName;

        if (!File::exists($modulePath)) {
            return ['paths' => [], 'components' => ['schemas' => []]];
        }

        $paths = [];
        $schemas = [];

        // Scan controllers for API routes
        $controllersPath = $version
            ? $modulePath . '/Http/Controllers/Api/' . $version
            : $modulePath . '/Http/Controllers/Api/v1'; // Default to v1

        if (File::exists($controllersPath)) {
            $controllerResults = $this->scanControllers($controllersPath, $moduleName, $version);
            $paths = array_merge($paths, $controllerResults['paths']);
            $schemas = array_merge($schemas, $controllerResults['schemas']);
        }

        // Also scan Presentation layer controllers
        $presentationPath = $modulePath . '/Presentation/Http/Controllers';
        if (File::exists($presentationPath)) {
            $presentationResults = $this->scanControllers($presentationPath, $moduleName, $version);
            $paths = array_merge($paths, $presentationResults['paths']);
            $schemas = array_merge($schemas, $presentationResults['schemas']);
        }

        // Scan resources for schema definitions
        $resourcesPath = $modulePath . '/Http/Resources';
        if (File::exists($resourcesPath)) {
            $resourceSchemas = $this->scanResources($resourcesPath, $moduleName);
            $schemas = array_merge($schemas, $resourceSchemas);
        }

        // Also scan Presentation layer resources
        $presentationResourcesPath = $modulePath . '/Presentation/Http/Resources';
        if (File::exists($presentationResourcesPath)) {
            $presentationResourceSchemas = $this->scanResources($presentationResourcesPath, $moduleName);
            $schemas = array_merge($schemas, $presentationResourceSchemas);
        }

        return [
            'paths' => $paths,
            'components' => [
                'schemas' => $schemas
            ]
        ];
    }

    /**
     * Scan controllers for Swagger annotations
     */
    private function scanControllers(string $controllersPath, string $moduleName, ?string $version = null): array
    {
        $paths = [];
        $schemas = [];

        $controllerFiles = File::allFiles($controllersPath);

        foreach ($controllerFiles as $file) {
            if ($file->getExtension() === 'php') {
                $controllerResult = $this->parseControllerFile($file->getRealPath(), $moduleName, $version);
                $paths = array_merge($paths, $controllerResult['paths']);
                $schemas = array_merge($schemas, $controllerResult['schemas']);
            }
        }

        return ['paths' => $paths, 'schemas' => $schemas];
    }

    /**
     * Parse a controller file for Swagger annotations
     */
    private function parseControllerFile(string $filePath, string $moduleName, ?string $version = null): array
    {
        $content = File::get($filePath);
        $paths = [];
        $schemas = [];

        // Extract namespace and class name
        $namespace = $this->extractNamespace($content);
        $className = $this->extractClassName($content);

        if (!$namespace || !$className) {
            return ['paths' => [], 'schemas' => []];
        }

        $fullClassName = $namespace . '\\' . $className;

        // Try to create reflection class
        try {
            if (!class_exists($fullClassName)) {
                return ['paths' => [], 'schemas' => []];
            }

            $reflectionClass = new ReflectionClass($fullClassName);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                if ($method->getDeclaringClass()->getName() === $fullClassName) {
                    $methodPaths = $this->parseMethodAnnotations($method, $content, $moduleName, $version);
                    $paths = array_merge($paths, $methodPaths);
                }
            }
        } catch (\Exception $e) {
            // Skip if class can't be loaded
        }

        return ['paths' => $paths, 'schemas' => $schemas];
    }

    /**
     * Parse method annotations for Swagger
     */
    private function parseMethodAnnotations(ReflectionMethod $method, string $content, string $moduleName, ?string $version = null): array
    {
        $methodName = $method->getName();
        $paths = [];

        // Generate basic path information based on method name and module
        $httpMethod = $this->getHttpMethodFromMethodName($methodName);
        if (!$httpMethod) {
            return [];
        }

        $resourceName = strtolower(str_replace('Controller', '', $method->getDeclaringClass()->getShortName()));

        $pathPrefix = $version ? "/api/{$version}" : "/api";
        $path = $this->generatePathFromMethod($methodName, $resourceName, $pathPrefix);

        if ($path) {
            $paths[$path] = [
                $httpMethod => [
                    'tags' => [$moduleName],
                    'summary' => $this->generateSummary($methodName, $resourceName),
                    'description' => $this->generateDescription($methodName, $resourceName),
                    'responses' => $this->generateDefaultResponses($methodName),
                ]
            ];

            // Add parameters for show, update, destroy methods
            if (in_array($methodName, ['show', 'update', 'destroy'])) {
                $paths[$path][$httpMethod]['parameters'] = [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                        'description' => ucfirst($resourceName) . ' ID'
                    ]
                ];
            }

            // Add request body for store and update methods
            if (in_array($methodName, ['store', 'update'])) {
                $paths[$path][$httpMethod]['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => [
                                        'type' => 'string',
                                        'description' => 'Name field'
                                    ]
                                ],
                                'required' => ['name']
                            ]
                        ]
                    ]
                ];
            }

            // Add security if not a public endpoint
            if (!in_array($methodName, ['index', 'show'])) {
                $paths[$path][$httpMethod]['security'] = [
                    ['bearerAuth' => []]
                ];
            }
        }

        return $paths;
    }

    /**
     * Scan resources for schema definitions
     */
    private function scanResources(string $resourcesPath, string $moduleName): array
    {
        $schemas = [];

        $resourceFiles = File::allFiles($resourcesPath);

        foreach ($resourceFiles as $file) {
            if ($file->getExtension() === 'php') {
                $resourceName = str_replace('.php', '', $file->getFilename());
                $schemaName = str_replace('Resource', '', $resourceName);

                $schemas[$schemaName] = [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Unique identifier'
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name field'
                        ],
                        'created_at' => [
                            'type' => 'string',
                            'format' => 'date-time',
                            'description' => 'Creation timestamp'
                        ],
                        'updated_at' => [
                            'type' => 'string',
                            'format' => 'date-time',
                            'description' => 'Last update timestamp'
                        ]
                    ]
                ];
            }
        }

        return $schemas;
    }

    /**
     * Extract namespace from file content
     */
    private function extractNamespace(string $content): ?string
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Extract class name from file content
     */
    private function extractClassName(string $content): ?string
    {
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Get HTTP method from method name
     */
    private function getHttpMethodFromMethodName(string $methodName): ?string
    {
        $mapping = [
            'index' => 'get',
            'show' => 'get',
            'store' => 'post',
            'update' => 'put',
            'destroy' => 'delete',
        ];

        return $mapping[$methodName] ?? null;
    }

    /**
     * Generate path from method name
     */
    private function generatePathFromMethod(string $methodName, string $resourceName, string $prefix = '/api'): ?string
    {
        $resourcePath = Str::plural($resourceName);

        switch ($methodName) {
            case 'index':
            case 'store':
                return "{$prefix}/{$resourcePath}";
            case 'show':
            case 'update':
            case 'destroy':
                return "{$prefix}/{$resourcePath}/{id}";
            default:
                return null;
        }
    }

    /**
     * Generate summary for method
     */
    private function generateSummary(string $methodName, string $resourceName): string
    {
        $resource = ucfirst($resourceName);

        switch ($methodName) {
            case 'index':
                return "List all {$resource}s";
            case 'show':
                return "Get a specific {$resource}";
            case 'store':
                return "Create a new {$resource}";
            case 'update':
                return "Update a {$resource}";
            case 'destroy':
                return "Delete a {$resource}";
            default:
                return ucfirst($methodName) . " {$resource}";
        }
    }

    /**
     * Generate description for method
     */
    private function generateDescription(string $methodName, string $resourceName): string
    {
        $resource = ucfirst($resourceName);

        switch ($methodName) {
            case 'index':
                return "Retrieve a paginated list of all {$resource} records";
            case 'show':
                return "Retrieve detailed information about a specific {$resource}";
            case 'store':
                return "Create a new {$resource} record with the provided data";
            case 'update':
                return "Update an existing {$resource} record with new data";
            case 'destroy':
                return "Permanently delete a {$resource} record";
            default:
                return "Perform {$methodName} operation on {$resource}";
        }
    }

    /**
     * Generate default responses for method
     */
    private function generateDefaultResponses(string $methodName): array
    {
        $responses = [
            '400' => [
                'description' => 'Bad Request'
            ],
            '500' => [
                'description' => 'Internal Server Error'
            ]
        ];

        switch ($methodName) {
            case 'index':
                $responses['200'] = [
                    'description' => 'Successful response with paginated data',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'object']
                                    ],
                                    'meta' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'current_page' => ['type' => 'integer'],
                                            'last_page' => ['type' => 'integer'],
                                            'per_page' => ['type' => 'integer'],
                                            'total' => ['type' => 'integer']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
                break;
            case 'show':
                $responses['200'] = ['description' => 'Successful response'];
                $responses['404'] = ['description' => 'Resource not found'];
                break;
            case 'store':
                $responses['201'] = ['description' => 'Resource created successfully'];
                $responses['422'] = ['description' => 'Validation error'];
                break;
            case 'update':
                $responses['200'] = ['description' => 'Resource updated successfully'];
                $responses['404'] = ['description' => 'Resource not found'];
                $responses['422'] = ['description' => 'Validation error'];
                break;
            case 'destroy':
                $responses['204'] = ['description' => 'Resource deleted successfully'];
                $responses['404'] = ['description' => 'Resource not found'];
                break;
        }

        return $responses;
    }
}