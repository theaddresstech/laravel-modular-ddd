<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Documentation\SwaggerAnnotationScanner;

class SwaggerDocumentationController extends Controller
{
    public function __construct(
        private ModuleManagerInterface $moduleManager,
        private SwaggerAnnotationScanner $scanner,
    ) {}

    /**
     * Serve the main API documentation.
     */
    public function index(Request $request): Response
    {
        $spec = $this->generateMainApiSpec();

        return $this->renderSwaggerUI($spec, 'API Documentation');
    }

    /**
     * Serve version-specific API documentation.
     */
    public function version(Request $request, string $version): Response
    {
        $spec = $this->generateVersionApiSpec($version);

        return $this->renderSwaggerUI($spec, "API Documentation - {$version}");
    }

    /**
     * Serve module-specific API documentation.
     */
    public function module(Request $request, string $module): Response
    {
        $spec = $this->generateModuleApiSpec($module);

        return $this->renderSwaggerUI($spec, "{$module} Module Documentation");
    }

    /**
     * Serve version and module-specific API documentation.
     */
    public function versionModule(Request $request, string $version, string $module): Response
    {
        $spec = $this->generateVersionModuleApiSpec($version, $module);

        return $this->renderSwaggerUI($spec, "{$module} Module Documentation - {$version}");
    }

    /**
     * Get the OpenAPI specification as JSON.
     */
    public function spec(Request $request): JsonResponse
    {
        $version = $request->query('version');
        $module = $request->query('module');

        if ($version && $module) {
            $spec = $this->generateVersionModuleApiSpec($version, $module);
        } elseif ($version) {
            $spec = $this->generateVersionApiSpec($version);
        } elseif ($module) {
            $spec = $this->generateModuleApiSpec($module);
        } else {
            $spec = $this->generateMainApiSpec();
        }

        return response()->json($spec);
    }

    /**
     * Generate main API specification.
     */
    private function generateMainApiSpec(): array
    {
        $modules = $this->moduleManager->list();

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => Config::get('modular-ddd.api.documentation.swagger.title', Config::get('app.name', 'Laravel Modular DDD') . ' API'),
                'description' => Config::get('modular-ddd.api.documentation.swagger.description', 'Comprehensive API documentation for all modules'),
                'version' => 'latest',
                'contact' => [
                    'name' => Config::get('modular-ddd.api.documentation.swagger.contact.name', 'API Support'),
                    'url' => Config::get('modular-ddd.api.documentation.swagger.contact.url', Config::get('app.url')),
                    'email' => Config::get('modular-ddd.api.documentation.swagger.contact.email', ''),
                ],
                'license' => [
                    'name' => Config::get('modular-ddd.api.documentation.swagger.license.name', 'MIT'),
                    'url' => Config::get('modular-ddd.api.documentation.swagger.license.url', ''),
                ],
            ],
            'servers' => $this->getServers(),
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => $this->getSecuritySchemes(),
            ],
        ];

        // Scan all modules for annotations
        foreach ($modules as $module) {
            $modulePaths = $this->scanner->scanModule($module->name);
            $spec['paths'] = array_merge($spec['paths'], $modulePaths['paths']);
            $spec['components']['schemas'] = array_merge(
                $spec['components']['schemas'],
                $modulePaths['components']['schemas'] ?? [],
            );
        }

        return $spec;
    }

    /**
     * Generate version-specific API specification.
     */
    private function generateVersionApiSpec(string $version): array
    {
        $modules = $this->moduleManager->list();

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => Config::get('app.name', 'Laravel Modular DDD') . ' API',
                'description' => "API documentation for version {$version}",
                'version' => $version,
                'contact' => [
                    'name' => 'API Support',
                    'url' => Config::get('app.url'),
                ],
            ],
            'servers' => $this->getServers($version),
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => $this->getSecuritySchemes(),
            ],
        ];

        // Scan modules for version-specific annotations
        foreach ($modules as $module) {
            $modulePaths = $this->scanner->scanModule($module->name, $version);
            $spec['paths'] = array_merge($spec['paths'], $modulePaths['paths']);
            $spec['components']['schemas'] = array_merge(
                $spec['components']['schemas'],
                $modulePaths['components']['schemas'] ?? [],
            );
        }

        return $spec;
    }

    /**
     * Generate module-specific API specification.
     */
    private function generateModuleApiSpec(string $module): array
    {
        $moduleInfo = $this->moduleManager->get($module);

        if (!$moduleInfo) {
            abort(404, "Module '{$module}' not found");
        }

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => "{$module} Module API",
                'description' => "API documentation for the {$module} module",
                'version' => $moduleInfo->manifest['version'] ?? '1.0.0',
                'contact' => [
                    'name' => 'API Support',
                    'url' => Config::get('app.url'),
                ],
            ],
            'servers' => $this->getServers(),
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => $this->getSecuritySchemes(),
            ],
        ];

        // Scan specific module for annotations
        $modulePaths = $this->scanner->scanModule($module);
        $spec['paths'] = $modulePaths['paths'];
        $spec['components']['schemas'] = $modulePaths['components']['schemas'] ?? [];

        return $spec;
    }

    /**
     * Generate version and module-specific API specification.
     */
    private function generateVersionModuleApiSpec(string $version, string $module): array
    {
        $moduleInfo = $this->moduleManager->get($module);

        if (!$moduleInfo) {
            abort(404, "Module '{$module}' not found");
        }

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => "{$module} Module API",
                'description' => "API documentation for the {$module} module - version {$version}",
                'version' => "{$version}",
                'contact' => [
                    'name' => 'API Support',
                    'url' => Config::get('app.url'),
                ],
            ],
            'servers' => $this->getServers($version),
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => $this->getSecuritySchemes(),
            ],
        ];

        // Scan specific module for version-specific annotations
        $modulePaths = $this->scanner->scanModule($module, $version);
        $spec['paths'] = $modulePaths['paths'];
        $spec['components']['schemas'] = $modulePaths['components']['schemas'] ?? [];

        return $spec;
    }

    /**
     * Get servers configuration.
     */
    private function getServers(?string $version = null): array
    {
        $baseUrl = Config::get('app.url');

        return [
            [
                'url' => $version
                    ? "{$baseUrl}/{$version}"
                    : "{$baseUrl}",
                'description' => $version
                    ? "Production server - {$version}"
                    : 'Production server',
            ],
        ];
    }

    /**
     * Get security schemes configuration.
     */
    private function getSecuritySchemes(): array
    {
        $schemes = [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => 'Bearer token authentication (JWT or Laravel Passport)',
            ],
            'apiKey' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-API-Key',
                'description' => 'API key authentication',
            ],
        ];

        // Add Laravel Passport OAuth2 if configured
        if (Config::get('passport.client_id') || class_exists('Laravel\Passport\Passport')) {
            $baseUrl = Config::get('app.url');

            $schemes['oauth2'] = [
                'type' => 'oauth2',
                'description' => 'Laravel Passport OAuth2 authentication',
                'flows' => [
                    'authorizationCode' => [
                        'authorizationUrl' => $baseUrl . '/oauth/authorize',
                        'tokenUrl' => $baseUrl . '/oauth/token',
                        'scopes' => $this->getOAuth2Scopes(),
                    ],
                    'clientCredentials' => [
                        'tokenUrl' => $baseUrl . '/oauth/token',
                        'scopes' => $this->getOAuth2Scopes(),
                    ],
                ],
            ];
        }

        return $schemes;
    }

    /**
     * Get OAuth2 scopes for Passport.
     */
    private function getOAuth2Scopes(): array
    {
        // Default scopes - can be customized based on your application
        return [
            '*' => 'Full access',
            'read' => 'Read access',
            'write' => 'Write access',
        ];
    }

    /**
     * Render Swagger UI.
     */
    private function renderSwaggerUI(array $spec, string $title): Response
    {
        $specJson = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $html = view('modular-ddd::swagger-ui', [
            'title' => $title,
            'spec' => $specJson,
        ])->render();

        return response($html)->header('Content-Type', 'text/html');
    }
}
