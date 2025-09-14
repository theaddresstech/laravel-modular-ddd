<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use TaiCrm\LaravelModularDdd\Exceptions\UnsupportedApiVersionException;

class VersionNegotiator
{
    private const VERSION_HEADER_NAMES = [
        'Accept-Version',
        'X-API-Version',
        'Api-Version',
    ];

    private const VERSION_QUERY_PARAMS = [
        'api_version',
        'version',
        'v',
    ];

    public function negotiate(Request $request, ?string $module = null): string
    {
        // Priority order: URL path -> Headers -> Query params -> Default
        $version = $this->getVersionFromUrl($request)
            ?? $this->getVersionFromHeaders($request)
            ?? $this->getVersionFromQuery($request)
            ?? $this->getDefaultVersion($module);

        $this->validateVersion($version, $module, $request);

        return $version;
    }

    public function getVersionFromUrl(Request $request): ?string
    {
        // Extract version from URL patterns like: /api/v1/users, /api/v2.1/users
        $path = $request->path();
        $apiPrefix = Config::get('modular-ddd.api.prefix', 'api');

        // Pattern: api/v{major}.{minor}/... or api/v{major}/...
        if (preg_match("#^{$apiPrefix}/v(\d+(?:\.\d+)?)/.*#", $path, $matches)) {
            return 'v' . $matches[1];
        }

        // Pattern: api/{version}/... where version matches configured format
        if (preg_match("#^{$apiPrefix}/([^/]+)/.*#", $path, $matches)) {
            $potentialVersion = $matches[1];
            if ($this->isValidVersionFormat($potentialVersion)) {
                return $potentialVersion;
            }
        }

        return null;
    }

    public function getVersionFromHeaders(Request $request): ?string
    {
        foreach (self::VERSION_HEADER_NAMES as $headerName) {
            $version = $request->header($headerName);
            if ($version && $this->isValidVersionFormat($version)) {
                return $this->normalizeVersion($version);
            }
        }

        // Check Accept header for version (e.g., application/vnd.api+json;version=2)
        $accept = $request->header('Accept');
        if ($accept && preg_match('/version=([^;,\s]+)/i', $accept, $matches)) {
            return $this->normalizeVersion($matches[1]);
        }

        return null;
    }

    public function getVersionFromQuery(Request $request): ?string
    {
        foreach (self::VERSION_QUERY_PARAMS as $param) {
            $version = $request->query($param);
            if ($version && $this->isValidVersionFormat($version)) {
                return $this->normalizeVersion($version);
            }
        }

        return null;
    }

    public function getDefaultVersion(?string $module = null): string
    {
        // Module-specific default version
        if ($module) {
            $moduleDefault = Config::get("modular-ddd.modules.{$module}.api.default_version");
            if ($moduleDefault) {
                return $moduleDefault;
            }
        }

        // Global default version
        return Config::get('modular-ddd.api.versions.default')
            ?? Config::get('modular-ddd.api.version', 'v1');
    }

    public function getSupportedVersions(?string $module = null): array
    {
        // Module-specific supported versions
        if ($module) {
            $moduleVersions = Config::get("modular-ddd.modules.{$module}.api.supported_versions");
            if ($moduleVersions) {
                return $moduleVersions;
            }
        }

        // Global supported versions
        return Config::get('modular-ddd.api.versions.supported', ['v1']);
    }

    private function validateVersion(string $version, ?string $module, Request $request): void
    {
        $supportedVersions = $this->getSupportedVersions($module);

        if (!in_array($version, $supportedVersions)) {
            throw new UnsupportedApiVersionException(
                "API version '{$version}' is not supported. Supported versions: " . implode(', ', $supportedVersions),
                $version,
                $supportedVersions
            );
        }

        // Check if version is sunset
        $sunsetDates = Config::get('modular-ddd.api.versions.sunset_dates', []);
        if (isset($sunsetDates[$version])) {
            $sunsetDate = $sunsetDates[$version];
            if (now()->isAfter($sunsetDate)) {
                throw new UnsupportedApiVersionException(
                    "API version '{$version}' has been sunset as of {$sunsetDate}",
                    $version,
                    $supportedVersions
                );
            }
        }
    }

    private function isValidVersionFormat(string $version): bool
    {
        // Support formats: v1, v1.0, v2.1, 1, 1.0, 2.1
        return preg_match('/^v?\d+(\.\d+)?$/', $version);
    }

    private function normalizeVersion(string $version): string
    {
        // Ensure version starts with 'v'
        return str_starts_with($version, 'v') ? $version : 'v' . $version;
    }

    public function getVersionInfo(string $version, ?string $module = null): array
    {
        $supportedVersions = $this->getSupportedVersions($module);
        $deprecatedVersions = Config::get('modular-ddd.api.versions.deprecated', []);
        $sunsetDates = Config::get('modular-ddd.api.versions.sunset_dates', []);
        $latestVersion = Config::get('modular-ddd.api.versions.latest', 'v1');

        return [
            'version' => $version,
            'is_supported' => in_array($version, $supportedVersions),
            'is_deprecated' => in_array($version, $deprecatedVersions),
            'is_latest' => $version === $latestVersion,
            'sunset_date' => $sunsetDates[$version] ?? null,
            'supported_versions' => $supportedVersions,
            'latest_version' => $latestVersion,
        ];
    }
}