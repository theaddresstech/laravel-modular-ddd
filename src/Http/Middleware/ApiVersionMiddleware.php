<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use TaiCrm\LaravelModularDdd\Exceptions\UnsupportedApiVersionException;
use TaiCrm\LaravelModularDdd\Http\VersionNegotiator;

class ApiVersionMiddleware
{
    public function __construct(
        private VersionNegotiator $versionNegotiator
    ) {}

    public function handle(Request $request, Closure $next, string $module = null): SymfonyResponse
    {
        // Skip version negotiation for non-API routes
        if (!$this->isApiRoute($request)) {
            return $next($request);
        }

        try {
            // Negotiate API version
            $version = $this->versionNegotiator->negotiate($request, $module);

            // Set version context
            $this->setVersionContext($request, $version, $module);

            // Handle deprecated versions
            $response = $next($request);

            // Add version headers to response
            $this->addVersionHeaders($response, $version);

            // Add deprecation warnings if needed
            $this->addDeprecationWarnings($response, $version);

            return $response;

        } catch (UnsupportedApiVersionException $e) {
            return $this->createVersionErrorResponse($e, $request);
        }
    }

    private function isApiRoute(Request $request): bool
    {
        $path = $request->path();
        $apiPrefix = Config::get('modular-ddd.api.prefix', 'api');

        return str_starts_with($path, $apiPrefix . '/');
    }

    private function setVersionContext(Request $request, string $version, ?string $module): void
    {
        // Set version in request attributes
        $request->attributes->set('api_version', $version);
        $request->attributes->set('api_module', $module);

        // Set global version context
        app()->instance('api.version', $version);
        app()->instance('api.module', $module);

        // Update route parameters for version-aware routing
        $request->route()?->setParameter('version', $version);
    }

    private function addVersionHeaders(SymfonyResponse $response, string $version): void
    {
        $response->headers->set('X-API-Version', $version);
        $response->headers->set('X-API-Module', app('api.module'));

        // Add supported versions
        $supportedVersions = Config::get('modular-ddd.api.versions.supported', ['v1']);
        $response->headers->set('X-API-Supported-Versions', implode(', ', $supportedVersions));
    }

    private function addDeprecationWarnings(SymfonyResponse $response, string $version): void
    {
        $deprecatedVersions = Config::get('modular-ddd.api.versions.deprecated', []);

        if (in_array($version, $deprecatedVersions)) {
            $sunsetDates = Config::get('modular-ddd.api.versions.sunset_dates', []);
            $sunsetDate = $sunsetDates[$version] ?? null;

            $warning = "This API version ({$version}) is deprecated.";
            if ($sunsetDate) {
                $warning .= " It will be sunset on {$sunsetDate}.";
            }

            $response->headers->set('Warning', '299 - "' . $warning . '"');
            $response->headers->set('Sunset', $sunsetDate ?? '');

            // Add migration information
            $latestVersion = Config::get('modular-ddd.api.versions.latest', 'v1');
            $response->headers->set('X-API-Latest-Version', $latestVersion);
        }
    }

    private function createVersionErrorResponse(UnsupportedApiVersionException $e, Request $request): Response
    {
        $supportedVersions = Config::get('modular-ddd.api.versions.supported', ['v1']);
        $latestVersion = Config::get('modular-ddd.api.versions.latest', 'v1');

        $error = [
            'error' => 'Unsupported API Version',
            'message' => $e->getMessage(),
            'requested_version' => $e->getRequestedVersion(),
            'supported_versions' => $supportedVersions,
            'latest_version' => $latestVersion,
            'documentation' => Config::get('modular-ddd.api.documentation_url'),
        ];

        return response()->json($error, 406, [
            'X-API-Supported-Versions' => implode(', ', $supportedVersions),
            'X-API-Latest-Version' => $latestVersion,
        ]);
    }
}