<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Documentation;

use RuntimeException;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;

class SwaggerDocumentationGenerator
{
    public function __construct(
        private SwaggerAnnotationScanner $scanner,
        private ModuleManagerInterface $moduleManager,
    ) {}

    /**
     * Generate Swagger documentation for all modules.
     */
    public function generateAllModulesDocumentation(): array
    {
        $modules = $this->moduleManager->list();
        $documentation = [];

        foreach ($modules as $module) {
            if (!$module->isEnabled()) {
                continue;
            }

            $moduleDoc = $this->generateModuleDocumentation($module->getName());
            if (!empty($moduleDoc)) {
                $documentation[$module->getName()] = $moduleDoc;
            }
        }

        return $documentation;
    }

    /**
     * Generate Swagger documentation for a specific module.
     */
    public function generateModuleDocumentation(string $moduleName): array
    {
        return $this->scanner->generateModuleDocumentation($moduleName);
    }

    /**
     * Generate and save Swagger JSON files for all modules.
     */
    public function generateSwaggerFiles(?string $outputPath = null): array
    {
        $outputPath = $outputPath ?: base_path('public/api-docs');
        $this->ensureDirectoryExists($outputPath);

        $allDocumentation = $this->generateAllModulesDocumentation();
        $generatedFiles = [];

        foreach ($allDocumentation as $moduleName => $documentation) {
            $filename = "{$outputPath}/{$moduleName}.json";
            file_put_contents($filename, json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $generatedFiles[] = $filename;
        }

        // Generate combined documentation
        $combinedDoc = $this->generateCombinedDocumentation($allDocumentation);
        $combinedFilename = "{$outputPath}/api-documentation.json";
        file_put_contents($combinedFilename, json_encode($combinedDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $generatedFiles[] = $combinedFilename;

        return $generatedFiles;
    }

    /**
     * Generate combined documentation for all modules.
     */
    public function generateCombinedDocumentation(array $moduleDocs): array
    {
        if (empty($moduleDocs)) {
            return $this->getBaseDocumentation();
        }

        $combined = $this->getBaseDocumentation();
        $combined['paths'] = [];
        $combined['components']['schemas'] = [];
        $combined['tags'] = [];

        foreach ($moduleDocs as $moduleName => $doc) {
            // Merge paths
            if (isset($doc['paths'])) {
                $combined['paths'] = array_merge($combined['paths'], $doc['paths']);
            }

            // Merge schemas
            if (isset($doc['components']['schemas'])) {
                $combined['components']['schemas'] = array_merge(
                    $combined['components']['schemas'],
                    $doc['components']['schemas'],
                );
            }

            // Merge tags
            if (isset($doc['tags'])) {
                $combined['tags'] = array_merge($combined['tags'], $doc['tags']);
            }
        }

        return $combined;
    }

    /**
     * Generate Swagger UI HTML for a module.
     */
    public function generateSwaggerUI(string $moduleName, ?string $outputPath = null): string
    {
        $outputPath = $outputPath ?: base_path('public/api-docs');
        $this->ensureDirectoryExists($outputPath);

        $htmlContent = $this->getSwaggerUITemplate($moduleName);
        $filename = "{$outputPath}/{$moduleName}-ui.html";
        file_put_contents($filename, $htmlContent);

        return $filename;
    }

    /**
     * Generate comprehensive Swagger documentation with real-time scanning.
     */
    public function generateLiveDocumentation(): array
    {
        $allModules = $this->scanner->scanAllModules();
        $documentation = [];

        foreach ($allModules as $moduleName => $moduleData) {
            $documentation[$moduleName] = $this->processModuleData($moduleData);
        }

        return $documentation;
    }

    /**
     * Get base OpenAPI documentation structure.
     */
    private function getBaseDocumentation(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => config('app.name', 'Laravel') . ' API',
                'version' => '1.0.0',
                'description' => 'Comprehensive API documentation for all modules',
                'contact' => [
                    'name' => 'API Support',
                    'email' => config('mail.from.address', 'support@example.com'),
                ],
                'license' => [
                    'name' => 'MIT',
                    'url' => 'https://opensource.org/licenses/MIT',
                ],
            ],
            'servers' => [
                [
                    'url' => config('app.url', 'http://localhost') . '/v1',
                    'description' => 'v1 API Server',
                ],
                [
                    'url' => config('app.url', 'http://localhost') . '/v2',
                    'description' => 'v2 API Server',
                ],
            ],
            'paths' => [],
            'components' => [
                'schemas' => $this->getCommonSchemas(),
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'Bearer token authentication (JWT or Laravel Passport)',
                    ],
                    'oauth2' => [
                        'type' => 'oauth2',
                        'description' => 'OAuth2 authentication via Laravel Passport',
                        'flows' => [
                            'authorizationCode' => [
                                'authorizationUrl' => '/oauth/authorize',
                                'tokenUrl' => '/oauth/token',
                                'scopes' => [],
                            ],
                        ],
                    ],
                ],
                'responses' => $this->getCommonResponses(),
                'parameters' => $this->getCommonParameters(),
            ],
            'security' => [
                ['bearerAuth' => []],
                ['oauth2' => []],
            ],
            'tags' => [],
        ];
    }

    /**
     * Get common schema definitions.
     */
    private function getCommonSchemas(): array
    {
        return [
            'ErrorResponse' => [
                'type' => 'object',
                'title' => 'Error Response',
                'description' => 'Standard error response',
                'properties' => [
                    'message' => [
                        'type' => 'string',
                        'description' => 'Error message',
                        'example' => 'Resource not found',
                    ],
                    'code' => [
                        'type' => 'string',
                        'description' => 'Error code',
                        'example' => 'RESOURCE_NOT_FOUND',
                    ],
                    'timestamp' => [
                        'type' => 'string',
                        'format' => 'date-time',
                        'description' => 'Error timestamp',
                        'example' => '2024-01-15T10:30:00Z',
                    ],
                ],
                'required' => ['message'],
            ],
            'ValidationErrorResponse' => [
                'type' => 'object',
                'title' => 'Validation Error Response',
                'description' => 'Validation error response',
                'properties' => [
                    'message' => [
                        'type' => 'string',
                        'description' => 'Validation error message',
                        'example' => 'The given data was invalid.',
                    ],
                    'errors' => [
                        'type' => 'object',
                        'description' => 'Field-specific validation errors',
                        'additionalProperties' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'example' => [
                            'name' => ['The name field is required.'],
                            'email' => ['The email must be a valid email address.'],
                        ],
                    ],
                ],
                'required' => ['message', 'errors'],
            ],
            'PaginationMeta' => [
                'type' => 'object',
                'title' => 'Pagination Metadata',
                'description' => 'Pagination information',
                'properties' => [
                    'current_page' => ['type' => 'integer', 'example' => 1],
                    'from' => ['type' => 'integer', 'example' => 1],
                    'last_page' => ['type' => 'integer', 'example' => 10],
                    'path' => ['type' => 'string', 'example' => '/api/v1/resources'],
                    'per_page' => ['type' => 'integer', 'example' => 15],
                    'to' => ['type' => 'integer', 'example' => 15],
                    'total' => ['type' => 'integer', 'example' => 150],
                ],
            ],
            'PaginationLinks' => [
                'type' => 'object',
                'title' => 'Pagination Links',
                'description' => 'Pagination navigation links',
                'properties' => [
                    'first' => ['type' => 'string', 'format' => 'uri', 'example' => '/api/v1/resources?page=1'],
                    'last' => ['type' => 'string', 'format' => 'uri', 'example' => '/api/v1/resources?page=10'],
                    'prev' => ['type' => 'string', 'format' => 'uri', 'nullable' => true, 'example' => null],
                    'next' => ['type' => 'string', 'format' => 'uri', 'example' => '/api/v1/resources?page=2'],
                ],
            ],
        ];
    }

    /**
     * Get common response definitions.
     */
    private function getCommonResponses(): array
    {
        return [
            'UnauthorizedError' => [
                'description' => 'Unauthorized',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                    ],
                ],
            ],
            'ForbiddenError' => [
                'description' => 'Forbidden',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                    ],
                ],
            ],
            'NotFoundError' => [
                'description' => 'Resource not found',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                    ],
                ],
            ],
            'ValidationError' => [
                'description' => 'Validation error',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ValidationErrorResponse'],
                    ],
                ],
            ],
            'InternalServerError' => [
                'description' => 'Internal server error',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get common parameter definitions.
     */
    private function getCommonParameters(): array
    {
        return [
            'PageParameter' => [
                'name' => 'page',
                'in' => 'query',
                'description' => 'Page number for pagination',
                'required' => false,
                'schema' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'default' => 1,
                    'example' => 1,
                ],
            ],
            'PerPageParameter' => [
                'name' => 'per_page',
                'in' => 'query',
                'description' => 'Number of items per page',
                'required' => false,
                'schema' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 15,
                    'example' => 15,
                ],
            ],
            'SortParameter' => [
                'name' => 'sort',
                'in' => 'query',
                'description' => 'Field to sort by',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'default' => 'created_at',
                    'example' => 'created_at',
                ],
            ],
            'DirectionParameter' => [
                'name' => 'direction',
                'in' => 'query',
                'description' => 'Sort direction',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'enum' => ['asc', 'desc'],
                    'default' => 'desc',
                    'example' => 'desc',
                ],
            ],
        ];
    }

    /**
     * Get Swagger UI HTML template.
     */
    private function getSwaggerUITemplate(string $moduleName): string
    {
        $appName = config('app.name', 'Laravel');
        $jsonUrl = "/{$moduleName}.json";

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>{$appName} - {$moduleName} API Documentation</title>
                <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui.css" />
                <style>
                    html {
                        box-sizing: border-box;
                        overflow: -moz-scrollbars-vertical;
                        overflow-y: scroll;
                    }
                    *, *:before, *:after {
                        box-sizing: inherit;
                    }
                    body {
                        margin:0;
                        background: #fafafa;
                    }
                </style>
            </head>
            <body>
                <div id="swagger-ui"></div>
                <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-bundle.js"></script>
                <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-standalone-preset.js"></script>
                <script>
                    window.onload = function() {
                        const ui = SwaggerUIBundle({
                            url: '{$jsonUrl}',
                            dom_id: '#swagger-ui',
                            deepLinking: true,
                            presets: [
                                SwaggerUIBundle.presets.apis,
                                SwaggerUIStandalonePreset
                            ],
                            plugins: [
                                SwaggerUIBundle.plugins.DownloadUrl
                            ],
                            layout: "StandaloneLayout",
                            tryItOutEnabled: true,
                            supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch'],
                            onComplete: function() {
                                console.log('Swagger UI loaded for {$moduleName} module');
                            },
                            persistAuthorization: true,
                            displayRequestDuration: true,
                            docExpansion: 'list',
                            filter: true,
                            showExtensions: true,
                            showCommonExtensions: true,
                            defaultModelsExpandDepth: 2,
                            defaultModelExpandDepth: 2
                        });
                    };
                </script>
            </body>
            </html>
            HTML;
    }

    /**
     * Process module data into OpenAPI format.
     */
    private function processModuleData(array $moduleData): array
    {
        $baseDoc = $this->getBaseDocumentation();
        $baseDoc['info'] = $moduleData['info'] ?? $baseDoc['info'];

        // Process controllers to generate paths
        $paths = [];
        foreach ($moduleData['controllers'] ?? [] as $controller) {
            foreach ($controller['methods'] ?? [] as $methodName => $method) {
                foreach ($method['paths'] ?? [] as $path) {
                    foreach ($method['http_methods'] ?? [] as $httpMethod) {
                        $paths[$path][strtolower($httpMethod)] = $this->generatePathOperation($method, $methodName);
                    }
                }
            }
        }
        $baseDoc['paths'] = $paths;

        // Process schemas
        $schemas = [];
        foreach ($moduleData['schemas'] ?? [] as $schemaName => $schema) {
            $schemas[$schemaName] = $this->processSchemaData($schema);
        }
        $baseDoc['components']['schemas'] = array_merge($baseDoc['components']['schemas'], $schemas);

        return $baseDoc;
    }

    /**
     * Generate path operation from method data.
     */
    private function generatePathOperation(array $method, string $methodName): array
    {
        return [
            'summary' => ucfirst($methodName),
            'operationId' => $methodName,
            'responses' => $this->processResponses($method['responses'] ?? []),
            'parameters' => $this->processParameters($method['parameters'] ?? []),
        ];
    }

    /**
     * Process response data.
     */
    private function processResponses(array $responses): array
    {
        $processed = [];

        foreach ($responses as $statusCode => $response) {
            $processed[$statusCode] = [
                'description' => $response['description'] ?? 'Response',
            ];
        }

        // Add default responses if not present
        if (empty($processed)) {
            $processed['200'] = ['description' => 'Successful operation'];
        }

        return $processed;
    }

    /**
     * Process parameter data.
     */
    private function processParameters(array $parameters): array
    {
        $processed = [];

        foreach ($parameters as $param) {
            $processed[] = [
                'name' => $param['name'],
                'in' => 'query', // Default to query parameter
                'required' => $param['required'] ?? false,
                'schema' => [
                    'type' => $this->mapPhpTypeToOpenApi($param['type'] ?? 'string'),
                ],
            ];
        }

        return $processed;
    }

    /**
     * Process schema data.
     */
    private function processSchemaData(array $schema): array
    {
        return [
            'type' => 'object',
            'title' => $schema['name'] ?? 'Schema',
            'description' => 'Auto-generated schema',
        ];
    }

    /**
     * Map PHP types to OpenAPI types.
     */
    private function mapPhpTypeToOpenApi(string $phpType): string
    {
        return match ($phpType) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            default => 'string',
        };
    }

    /**
     * Ensure directory exists.
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0o755, true) && !is_dir($path)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $path));
            }
        }
    }
}
