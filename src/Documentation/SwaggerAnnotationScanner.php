<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Documentation;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
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
     * Scan all modules for Swagger annotations.
     */
    public function scanAllModules(): array
    {
        $modules = [];
        $moduleDirs = File::directories($this->modulesPath);

        foreach ($moduleDirs as $moduleDir) {
            $moduleName = basename($moduleDir);
            $moduleDoc = $this->scanModule($moduleName);

            if (!empty($moduleDoc['paths']) || !empty($moduleDoc['components']['schemas'])) {
                $modules[$moduleName] = $moduleDoc;
            }
        }

        return $modules;
    }

    /**
     * Extract comprehensive Swagger annotations from controllers.
     */
    public function extractComprehensiveAnnotations(string $filePath): array
    {
        $content = File::get($filePath);
        $annotations = [];

        // Extract all @OA annotations
        preg_match_all('/@OA\\\\(\w+)\s*\((.*?)\)/s', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $annotationType = $match[1];
            $annotationContent = $match[2];

            $annotations[] = [
                'type' => $annotationType,
                'content' => $annotationContent,
                'full' => $match[0],
            ];
        }

        return $annotations;
    }

    /**
     * Parse comprehensive schema definitions from annotations.
     */
    public function parseSchemaAnnotations(array $annotations): array
    {
        $schemas = [];

        foreach ($annotations as $annotation) {
            if ($annotation['type'] === 'Schema') {
                $schema = $this->parseSchemaContent($annotation['content']);
                if (isset($schema['name'])) {
                    $schemas[$schema['name']] = $schema;
                }
            }
        }

        return $schemas;
    }

    /**
     * Generate comprehensive OpenAPI documentation for a module.
     */
    public function generateModuleDocumentation(string $moduleName): array
    {
        $moduleData = $this->scanModule($moduleName);

        if (empty($moduleData)) {
            return [];
        }

        return [
            'openapi' => '3.0.3',
            'info' => $this->extractModuleInfo($moduleName),
            'servers' => $this->generateServers(),
            'paths' => $moduleData['paths'] ?? [],
            'components' => [
                'schemas' => $moduleData['components']['schemas'] ?? [],
                'securitySchemes' => $this->generateSecuritySchemes(),
            ],
            'tags' => $this->generateTags($moduleName),
        ];
    }

    /**
     * Scan a module for Swagger annotations.
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
                'schemas' => $schemas,
            ],
        ];
    }

    /**
     * Parse schema content from annotation.
     */
    private function parseSchemaContent(string $content): array
    {
        $schema = [];

        // Extract schema name
        if (preg_match('/schema="([^"]+)"/', $content, $matches)) {
            $schema['name'] = $matches[1];
        }

        // Extract type
        if (preg_match('/type="([^"]+)"/', $content, $matches)) {
            $schema['type'] = $matches[1];
        }

        // Extract title
        if (preg_match('/title="([^"]+)"/', $content, $matches)) {
            $schema['title'] = $matches[1];
        }

        // Extract description
        if (preg_match('/description="([^"]+)"/', $content, $matches)) {
            $schema['description'] = $matches[1];
        }

        // Extract required fields
        if (preg_match('/required=\{([^}]+)\}/', $content, $matches)) {
            $requiredStr = $matches[1];
            $required = array_map('trim', explode(',', str_replace(['"', '"'], '', $requiredStr)));
            $schema['required'] = $required;
        }

        // Extract properties (basic extraction)
        $properties = [];
        preg_match_all('/@OA\\\\Property\s*\((.*?)\)/s', $content, $propMatches, PREG_SET_ORDER);

        foreach ($propMatches as $propMatch) {
            $property = $this->parsePropertyContent($propMatch[1]);
            if (isset($property['name'])) {
                $properties[$property['name']] = $property;
            }
        }

        if (!empty($properties)) {
            $schema['properties'] = $properties;
        }

        return $schema;
    }

    /**
     * Parse property content from annotation.
     */
    private function parsePropertyContent(string $content): array
    {
        $property = [];

        // Extract property name
        if (preg_match('/property="([^"]+)"/', $content, $matches)) {
            $property['name'] = $matches[1];
        }

        // Extract type
        if (preg_match('/type="([^"]+)"/', $content, $matches)) {
            $property['type'] = $matches[1];
        }

        // Extract format
        if (preg_match('/format="([^"]+)"/', $content, $matches)) {
            $property['format'] = $matches[1];
        }

        // Extract description
        if (preg_match('/description="([^"]+)"/', $content, $matches)) {
            $property['description'] = $matches[1];
        }

        // Extract example
        if (preg_match('/example="([^"]+)"/', $content, $matches)) {
            $property['example'] = $matches[1];
        } elseif (preg_match('/example=([^,)]+)/', $content, $matches)) {
            $value = trim($matches[1]);
            // Convert based on type
            if ($value === 'true') {
                $property['example'] = true;
            } elseif ($value === 'false') {
                $property['example'] = false;
            } elseif (is_numeric($value)) {
                $property['example'] = (int) $value;
            } else {
                $property['example'] = $value;
            }
        }

        // Extract nullable
        if (preg_match('/nullable=true/', $content)) {
            $property['nullable'] = true;
        }

        // Extract maxLength
        if (preg_match('/maxLength=(\d+)/', $content, $matches)) {
            $property['maxLength'] = (int) $matches[1];
        }

        // Extract default
        if (preg_match('/default=([^,)]+)/', $content, $matches)) {
            $value = trim($matches[1]);
            if ($value === 'true') {
                $property['default'] = true;
            } elseif ($value === 'false') {
                $property['default'] = false;
            } elseif (is_numeric($value)) {
                $property['default'] = (int) $value;
            } else {
                $property['default'] = $value;
            }
        }

        return $property;
    }

    /**
     * Extract module info from manifest.
     */
    private function extractModuleInfo(string $moduleName): array
    {
        $manifestPath = base_path("modules/{$moduleName}/manifest.json");
        $info = [
            'title' => "{$moduleName} API",
            'version' => '1.0.0',
            'description' => "API documentation for {$moduleName} module",
        ];

        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            if ($manifest) {
                $info['title'] = ($manifest['name'] ?? $moduleName) . ' API';
                $info['version'] = $manifest['version'] ?? '1.0.0';
                $info['description'] = $manifest['description'] ?? $info['description'];
            }
        }

        return $info;
    }

    /**
     * Generate server configurations.
     */
    private function generateServers(): array
    {
        return [
            [
                'url' => config('app.url', 'http://localhost') . '/v1',
                'description' => 'v1 API Server',
            ],
            [
                'url' => config('app.url', 'http://localhost') . '/v2',
                'description' => 'v2 API Server',
            ],
        ];
    }

    /**
     * Generate security schemes.
     */
    private function generateSecuritySchemes(): array
    {
        return [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
            ],
            'oauth2' => [
                'type' => 'oauth2',
                'flows' => [
                    'authorizationCode' => [
                        'authorizationUrl' => '/oauth/authorize',
                        'tokenUrl' => '/oauth/token',
                        'scopes' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate tags for the module.
     */
    private function generateTags(string $moduleName): array
    {
        return [
            [
                'name' => $moduleName,
                'description' => "{$moduleName} management endpoints",
            ],
        ];
    }

    /**
     * Scan controllers for Swagger annotations.
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
     * Parse a controller file for Swagger annotations.
     */
    private function parseControllerFile(string $filePath, string $moduleName, ?string $version = null): array
    {
        $content = File::get($filePath);
        $paths = [];
        $schemas = [];

        // Extract comprehensive annotations first
        $annotations = $this->extractComprehensiveAnnotations($filePath);
        $extractedSchemas = $this->parseSchemaAnnotations($annotations);
        $schemas = array_merge($schemas, $extractedSchemas);

        // Extract paths from comprehensive annotations
        $extractedPaths = $this->extractPathsFromAnnotations($annotations, $moduleName, $version);
        $paths = array_merge($paths, $extractedPaths);

        // Fallback to basic extraction if no comprehensive annotations found
        if (empty($paths)) {
            // Extract namespace and class name
            $namespace = $this->extractNamespace($content);
            $className = $this->extractClassName($content);

            if ($namespace && $className) {
                $fullClassName = $namespace . '\\' . $className;

                // Try to create reflection class
                try {
                    if (class_exists($fullClassName)) {
                        $reflectionClass = new ReflectionClass($fullClassName);
                        $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

                        foreach ($methods as $method) {
                            if ($method->getDeclaringClass()->getName() === $fullClassName) {
                                $methodPaths = $this->parseMethodAnnotations($method, $content, $moduleName, $version);
                                $paths = array_merge($paths, $methodPaths);
                            }
                        }
                    }
                } catch (Exception) {
                    // Skip if class can't be loaded
                }
            }
        }

        return ['paths' => $paths, 'schemas' => $schemas];
    }

    /**
     * Extract paths from comprehensive annotations.
     */
    private function extractPathsFromAnnotations(array $annotations, string $moduleName, ?string $version = null): array
    {
        $paths = [];
        $currentPath = null;
        $currentMethod = null;

        foreach ($annotations as $annotation) {
            $type = $annotation['type'];
            $content = $annotation['content'];

            // Check for HTTP method annotations
            if (in_array($type, ['Get', 'Post', 'Put', 'Delete', 'Patch', 'Options', 'Head'])) {
                $httpMethod = strtolower($type);

                // Extract path
                if (preg_match('/path="([^"]+)"/', $content, $matches)) {
                    $currentPath = $matches[1];
                    $currentMethod = $httpMethod;

                    if (!isset($paths[$currentPath])) {
                        $paths[$currentPath] = [];
                    }

                    $paths[$currentPath][$httpMethod] = [
                        'tags' => [$moduleName],
                        'responses' => ['200' => ['description' => 'Successful operation']],
                    ];

                    // Extract operation details
                    if (preg_match('/operationId="([^"]+)"/', $content, $matches)) {
                        $paths[$currentPath][$httpMethod]['operationId'] = $matches[1];
                    }

                    if (preg_match('/summary="([^"]+)"/', $content, $matches)) {
                        $paths[$currentPath][$httpMethod]['summary'] = $matches[1];
                    }

                    if (preg_match('/description="([^"]+)"/', $content, $matches)) {
                        $paths[$currentPath][$httpMethod]['description'] = $matches[1];
                    }

                    // Extract security
                    if (preg_match('/security=\{([^}]+)\}/', $content, $matches)) {
                        $securityStr = $matches[1];
                        $paths[$currentPath][$httpMethod]['security'] = $this->parseSecurityAnnotation($securityStr);
                    }
                }
            }

            // Handle Response annotations
            if ($type === 'Response' && $currentPath && $currentMethod) {
                if (preg_match('/response=(\d+)/', $content, $matches)) {
                    $statusCode = $matches[1];
                    $response = ['description' => 'Response'];

                    if (preg_match('/description="([^"]+)"/', $content, $descMatches)) {
                        $response['description'] = $descMatches[1];
                    }

                    $paths[$currentPath][$currentMethod]['responses'][$statusCode] = $response;
                }
            }

            // Handle Parameter annotations
            if ($type === 'Parameter' && $currentPath && $currentMethod) {
                if (!isset($paths[$currentPath][$currentMethod]['parameters'])) {
                    $paths[$currentPath][$currentMethod]['parameters'] = [];
                }

                $parameter = [];
                if (preg_match('/name="([^"]+)"/', $content, $matches)) {
                    $parameter['name'] = $matches[1];
                }
                if (preg_match('/in="([^"]+)"/', $content, $matches)) {
                    $parameter['in'] = $matches[1];
                }
                if (preg_match('/required=true/', $content)) {
                    $parameter['required'] = true;
                } elseif (preg_match('/required=false/', $content)) {
                    $parameter['required'] = false;
                }
                if (preg_match('/description="([^"]+)"/', $content, $matches)) {
                    $parameter['description'] = $matches[1];
                }

                if (!empty($parameter)) {
                    $paths[$currentPath][$currentMethod]['parameters'][] = $parameter;
                }
            }

            // Handle RequestBody annotations
            if ($type === 'RequestBody' && $currentPath && $currentMethod) {
                $requestBody = [];
                if (preg_match('/required=true/', $content)) {
                    $requestBody['required'] = true;
                }
                if (preg_match('/description="([^"]+)"/', $content, $matches)) {
                    $requestBody['description'] = $matches[1];
                }

                $paths[$currentPath][$currentMethod]['requestBody'] = $requestBody;
            }
        }

        return $paths;
    }

    /**
     * Parse security annotation.
     */
    private function parseSecurityAnnotation(string $securityStr): array
    {
        $security = [];

        // Parse {"bearerAuth": {}}, {"oauth2": {}}
        preg_match_all('/\{"([^"]+)":\s*\{[^}]*\}\}/', $securityStr, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $securityScheme = $match[1];
            $security[] = [$securityScheme => []];
        }

        return $security;
    }

    /**
     * Parse method annotations for Swagger.
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

        $pathPrefix = $version ? "/api/{$version}" : '/api';
        $path = $this->generatePathFromMethod($methodName, $resourceName, $pathPrefix);

        if ($path) {
            $paths[$path] = [
                $httpMethod => [
                    'tags' => [$moduleName],
                    'summary' => $this->generateSummary($methodName, $resourceName),
                    'description' => $this->generateDescription($methodName, $resourceName),
                    'responses' => $this->generateDefaultResponses($methodName),
                ],
            ];

            // Add parameters for show, update, destroy methods
            if (in_array($methodName, ['show', 'update', 'destroy'])) {
                $paths[$path][$httpMethod]['parameters'] = [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                        'description' => ucfirst($resourceName) . ' ID',
                    ],
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
                                        'description' => 'Name field',
                                    ],
                                ],
                                'required' => ['name'],
                            ],
                        ],
                    ],
                ];
            }

            // Add security if not a public endpoint
            if (!in_array($methodName, ['index', 'show'])) {
                $paths[$path][$httpMethod]['security'] = [
                    ['bearerAuth' => []],
                ];
            }
        }

        return $paths;
    }

    /**
     * Scan resources for schema definitions.
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
                            'description' => 'Unique identifier',
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name field',
                        ],
                        'created_at' => [
                            'type' => 'string',
                            'format' => 'date-time',
                            'description' => 'Creation timestamp',
                        ],
                        'updated_at' => [
                            'type' => 'string',
                            'format' => 'date-time',
                            'description' => 'Last update timestamp',
                        ],
                    ],
                ];
            }
        }

        return $schemas;
    }

    /**
     * Extract namespace from file content.
     */
    private function extractNamespace(string $content): ?string
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Extract class name from file content.
     */
    private function extractClassName(string $content): ?string
    {
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Get HTTP method from method name.
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
     * Generate path from method name.
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
     * Generate summary for method.
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
     * Generate description for method.
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
     * Generate default responses for method.
     */
    private function generateDefaultResponses(string $methodName): array
    {
        $responses = [
            '400' => [
                'description' => 'Bad Request',
            ],
            '500' => [
                'description' => 'Internal Server Error',
            ],
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
                                        'items' => ['type' => 'object'],
                                    ],
                                    'meta' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'current_page' => ['type' => 'integer'],
                                            'last_page' => ['type' => 'integer'],
                                            'per_page' => ['type' => 'integer'],
                                            'total' => ['type' => 'integer'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
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
