<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Http;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

class VersionAwareRouter
{
    public function __construct(
        private Router $router,
        private VersionNegotiator $versionNegotiator,
    ) {}

    public function registerVersionedRoutes(string $moduleName, string $routeFile): void
    {
        $supportedVersions = Config::get('modular-ddd.api.versions.supported', ['v1']);
        $apiPrefix = Config::get('modular-ddd.api.prefix', 'api');

        foreach ($supportedVersions as $version) {
            $this->registerVersionRoutes($moduleName, $version, $routeFile, $apiPrefix);
        }
    }

    public function getVersionedRoute(string $name, ?string $version = null, array $parameters = []): string
    {
        $version ??= app('api.version') ?? Config::get('modular-ddd.api.versions.default', 'v1');
        $routeName = "api.{$version}.{$name}";

        if (Route::has($routeName)) {
            return route($routeName, $parameters);
        }

        // Fallback to default version
        $defaultVersion = Config::get('modular-ddd.api.versions.default', 'v1');
        $fallbackRouteName = "api.{$defaultVersion}.{$name}";

        if (Route::has($fallbackRouteName)) {
            return route($fallbackRouteName, $parameters);
        }

        // Fallback to unversioned route
        return route("api.default.{$name}", $parameters);
    }

    public function registerVersionDiscoveryRoutes(): void
    {
        $apiPrefix = Config::get('modular-ddd.api.prefix', 'api');
        $discoveryEndpoint = Config::get('modular-ddd.api.documentation.discovery_endpoint', '/api/versions');

        Route::get($discoveryEndpoint, [
            'uses' => '\TaiCrm\LaravelModularDdd\Http\Controllers\VersionDiscoveryController@index',
            'middleware' => ['api'],
            'as' => 'api.versions.index',
        ]);

        // Module-specific version discovery
        Route::get("{$apiPrefix}/modules/{module}/versions", [
            'uses' => '\TaiCrm\LaravelModularDdd\Http\Controllers\VersionDiscoveryController@module',
            'middleware' => ['api'],
            'as' => 'api.versions.module',
        ]);
    }

    /**
     * Register documentation routes.
     */
    public function registerDocumentationRoutes(): void
    {
        $apiPrefix = Config::get('modular-ddd.api.prefix', 'api');
        $docsUrl = Config::get('modular-ddd.api.documentation.url', '/api/docs');

        // Main API documentation
        Route::get($docsUrl, [
            'uses' => '\TaiCrm\LaravelModularDdd\Http\Controllers\SwaggerDocumentationController@index',
            'middleware' => ['api'],
            'as' => 'api.docs.index',
        ]);

        // Version-specific documentation
        Route::get("{$docsUrl}/{version}", [
            'uses' => '\TaiCrm\LaravelModularDdd\Http\Controllers\SwaggerDocumentationController@version',
            'middleware' => ['api'],
            'as' => 'api.docs.version',
        ])->where('version', '^(v[0-9]+)$');

        // Module-specific documentation
        Route::get("{$docsUrl}/modules/{module}", [
            'uses' => '\TaiCrm\LaravelModularDdd\Http\Controllers\SwaggerDocumentationController@module',
            'middleware' => ['api'],
            'as' => 'api.docs.module',
        ]);

        // Version and module-specific documentation
        Route::get("{$docsUrl}/{version}/modules/{module}", [
            'uses' => '\TaiCrm\LaravelModularDdd\Http\Controllers\SwaggerDocumentationController@versionModule',
            'middleware' => ['api'],
            'as' => 'api.docs.version.module',
        ])->where('version', '^(v[0-9]+)$');

        // OpenAPI specification endpoint
        Route::get("{$apiPrefix}/openapi.json", [
            'uses' => '\TaiCrm\LaravelModularDdd\Http\Controllers\SwaggerDocumentationController@spec',
            'middleware' => ['api'],
            'as' => 'api.docs.spec',
        ]);
    }

    public function getVersionedControllerClass(string $baseController, string $version): string
    {
        // Try version-specific controller first
        $versionedController = str_replace('\\Controllers\\', "\\Controllers\\Api\\{$version}\\", $baseController);

        if (class_exists($versionedController)) {
            return $versionedController;
        }

        // Fallback to default version
        $defaultVersion = Config::get('modular-ddd.api.versions.default', 'v1');
        $defaultController = str_replace('\\Controllers\\', "\\Controllers\\Api\\{$defaultVersion}\\", $baseController);

        if (class_exists($defaultController)) {
            return $defaultController;
        }

        // Final fallback to base controller
        return $baseController;
    }

    public function getCurrentApiVersion(): string
    {
        return app('api.version') ?? Config::get('modular-ddd.api.versions.default', 'v1');
    }

    public function isVersionSupported(string $version): bool
    {
        $supportedVersions = Config::get('modular-ddd.api.versions.supported', ['v1']);

        return in_array($version, $supportedVersions);
    }

    public function isVersionDeprecated(string $version): bool
    {
        $deprecatedVersions = Config::get('modular-ddd.api.versions.deprecated', []);

        return in_array($version, $deprecatedVersions);
    }

    public function getVersionRoutePattern(?string $version = null): string
    {
        if ($version) {
            return "api/{$version}";
        }

        $supportedVersions = Config::get('modular-ddd.api.versions.supported', ['v1']);
        $versionPattern = implode('|', $supportedVersions);

        return 'api/{version}';
    }

    public function registerVersionConstraints(): void
    {
        $supportedVersions = Config::get('modular-ddd.api.versions.supported', ['v1']);
        $versionPattern = '^(' . implode('|', $supportedVersions) . ')$';

        Route::pattern('version', $versionPattern);
    }

    private function registerVersionRoutes(string $moduleName, string $version, string $routeFile, string $apiPrefix): void
    {
        // Version-specific route group
        Route::group([
            'prefix' => "{$apiPrefix}/{$version}",
            'middleware' => ['api', 'api.version'],
            'namespace' => $this->getVersionNamespace($moduleName, $version),
            'as' => "api.{$version}.",
        ], function () use ($routeFile, $version, $moduleName): void {
            // Set context for the route group
            app()->instance('current.api.version', $version);
            app()->instance('current.api.module', $moduleName);

            if (file_exists($routeFile)) {
                require $routeFile;
            }
        });

        // Fallback routes without version prefix (uses default version)
        $defaultVersion = Config::get('modular-ddd.api.versions.default', 'v1');
        if ($version === $defaultVersion) {
            Route::group([
                'prefix' => $apiPrefix,
                'middleware' => ['api', 'api.version'],
                'namespace' => $this->getVersionNamespace($moduleName, $version),
                'as' => 'api.default.',
            ], function () use ($routeFile, $version, $moduleName): void {
                app()->instance('current.api.version', $version);
                app()->instance('current.api.module', $moduleName);

                if (file_exists($routeFile)) {
                    require $routeFile;
                }
            });
        }
    }

    private function getVersionNamespace(string $moduleName, string $version): string
    {
        return "Modules\\{$moduleName}\\Http\\Controllers\\Api\\" . ucfirst($version);
    }
}
