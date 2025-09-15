<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Http\VersionNegotiator;

class VersionDiscoveryController extends Controller
{
    public function __construct(
        private ModuleManagerInterface $moduleManager,
        private VersionNegotiator $versionNegotiator
    ) {}

    public function index(Request $request): JsonResponse
    {
        $versions = $this->getGlobalVersionInfo();
        $modules = $this->getModulesVersionInfo();

        return response()->json([
            'api' => [
                'name' => Config::get('app.name', 'Laravel Modular DDD'),
                'description' => 'Modular Domain-Driven Design API',
                'documentation_url' => Config::get('modular-ddd.api.documentation.url'),
            ],
            'versions' => $versions,
            'modules' => $modules,
            'negotiation' => [
                'strategies' => $this->getNegotiationStrategies(),
                'headers' => Config::get('modular-ddd.api.negotiation.headers', []),
                'query_parameters' => Config::get('modular-ddd.api.negotiation.url_patterns.query_parameter', []),
            ],
            'capabilities' => [
                'version_negotiation' => true,
                'backward_compatibility' => Config::get('modular-ddd.api.compatibility.auto_transform', false),
                'deprecation_warnings' => Config::get('modular-ddd.api.documentation.include_deprecation_notices', true),
                'content_negotiation' => Config::get('modular-ddd.api.negotiation.accept_header_parsing', true),
            ],
            'links' => [
                'self' => url(Config::get('modular-ddd.api.documentation.discovery_endpoint', '/api/versions')),
                'documentation' => url(Config::get('modular-ddd.api.documentation.url', '/api/docs')),
                'health' => url('/api/health'),
            ],
        ]);
    }

    public function module(Request $request, string $module): JsonResponse
    {
        $moduleInfo = $this->moduleManager->getInfo($module);

        if (!$moduleInfo || !$this->moduleManager->isInstalled($module)) {
            return response()->json([
                'error' => 'Module not found',
                'message' => "Module '{$module}' is not installed or does not exist",
                'available_modules' => array_keys($this->getModulesVersionInfo()),
            ], 404);
        }

        $versions = $this->getModuleVersionInfo($module);

        return response()->json([
            'module' => [
                'name' => $module,
                'display_name' => $moduleInfo->displayName,
                'description' => $moduleInfo->description,
                'version' => $moduleInfo->version,
                'status' => $this->moduleManager->isEnabled($module) ? 'enabled' : 'disabled',
            ],
            'api_versions' => $versions,
            'endpoints' => $this->getModuleEndpoints($module),
            'capabilities' => $this->getModuleCapabilities($module),
            'dependencies' => $moduleInfo->dependencies,
            'links' => [
                'self' => url("/api/modules/{$module}/versions"),
                'module_documentation' => url("/api/docs/modules/{$module}"),
                'all_versions' => url('/api/versions'),
            ],
        ]);
    }

    private function getGlobalVersionInfo(): array
    {
        $supportedVersions = Config::get('modular-ddd.api.versions.supported', ['v1']);
        $deprecatedVersions = Config::get('modular-ddd.api.versions.deprecated', []);
        $sunsetDates = Config::get('modular-ddd.api.versions.sunset_dates', []);

        return [
            'current' => Config::get('modular-ddd.api.versions.default', 'v1'),
            'latest' => Config::get('modular-ddd.api.versions.latest', 'v1'),
            'supported' => array_map(function ($version) use ($deprecatedVersions, $sunsetDates) {
                return [
                    'version' => $version,
                    'status' => in_array($version, $deprecatedVersions) ? 'deprecated' : 'active',
                    'sunset_date' => $sunsetDates[$version] ?? null,
                    'documentation_url' => url("/api/docs/{$version}"),
                    'base_url' => url("/api/{$version}"),
                ];
            }, $supportedVersions),
            'deprecated' => $deprecatedVersions,
            'sunset_dates' => $sunsetDates,
        ];
    }

    private function getModulesVersionInfo(): array
    {
        $modules = [];
        $installedModules = $this->moduleManager->list();

        foreach ($installedModules as $moduleInfo) {
            if ($this->moduleManager->isEnabled($moduleInfo->name)) {
                $modules[$moduleInfo->name] = [
                    'name' => $moduleInfo->name,
                    'version' => $moduleInfo->version,
                    'status' => 'enabled',
                    'api_versions' => $this->getModuleVersionInfo($moduleInfo->name),
                    'endpoints_count' => count($this->getModuleEndpoints($moduleInfo->name)),
                    'links' => [
                        'versions' => url("/api/modules/{$moduleInfo->name}/versions"),
                        'documentation' => url("/api/docs/modules/{$moduleInfo->name}"),
                    ],
                ];
            }
        }

        return $modules;
    }

    private function getModuleVersionInfo(string $module): array
    {
        // Get module-specific API versions if configured
        $moduleVersions = Config::get("modular-ddd.modules.{$module}.api.supported_versions");

        if ($moduleVersions) {
            return array_map(function ($version) use ($module) {
                return [
                    'version' => $version,
                    'status' => 'active', // Module-specific deprecation logic can be added here
                    'base_url' => url("/api/{$version}/{$module}"),
                    'documentation_url' => url("/api/docs/{$version}/modules/{$module}"),
                ];
            }, $moduleVersions);
        }

        // Fallback to global versions
        $supportedVersions = Config::get('modular-ddd.api.versions.supported', ['v1']);
        return array_map(function ($version) use ($module) {
            return [
                'version' => $version,
                'status' => 'active',
                'base_url' => url("/api/{$version}/{$module}"),
                'documentation_url' => url("/api/docs/{$version}/modules/{$module}"),
            ];
        }, $supportedVersions);
    }

    private function getModuleEndpoints(string $module): array
    {
        // This would ideally scan the module's route files and extract endpoint information
        // For now, return a placeholder structure
        return [
            'count' => 0,
            'categories' => [],
            'discovery_url' => url("/api/docs/modules/{$module}/endpoints"),
        ];
    }

    private function getModuleCapabilities(string $module): array
    {
        // Module-specific capabilities
        return [
            'real_time_events' => false,
            'bulk_operations' => false,
            'file_uploads' => false,
            'webhooks' => false,
            'caching' => true,
            'rate_limiting' => true,
        ];
    }

    private function getNegotiationStrategies(): array
    {
        $strategy = Config::get('modular-ddd.api.negotiation.strategy', 'url,header,query,default');
        $strategies = explode(',', $strategy);

        return array_map(function ($s) {
            return trim($s);
        }, $strategies);
    }
}