<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Compilation;

use TaiCrm\LaravelModularDdd\Compilation\Contracts\CompiledRegistryInterface;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;

/**
 * Ultra-fast compiled module registry
 */
class CompiledRegistry implements CompiledRegistryInterface
{
    private const COMPILED_REGISTRY_PATH = 'framework/modular-ddd/compiled-registry.php';
    private const DEPENDENCY_GRAPH_PATH = 'framework/modular-ddd/dependency-graph.php';
    private const SERVICE_BINDINGS_PATH = 'framework/modular-ddd/service-bindings.php';
    private const ROUTE_MANIFEST_PATH = 'framework/modular-ddd/route-manifest.php';
    private const CONTEXT_MAP_PATH = 'framework/modular-ddd/context-map.php';

    private ?array $compiledData = null;
    private ?array $dependencyGraph = null;
    private ?array $serviceBindings = null;
    private ?array $routeManifest = null;
    private ?array $contextMaps = null;
    private bool $loaded = false;

    public function __construct(
        private Filesystem $files,
        private LoggerInterface $logger,
        private string $storagePath
    ) {}

    public function getAllModules(): Collection
    {
        $this->ensureLoaded();

        if (!$this->isValid()) {
            return collect();
        }

        return collect($this->compiledData['modules'] ?? [])
            ->map(fn($data) => $this->hydrateModuleInfo($data));
    }

    public function getModulesByContext(string $context): Collection
    {
        $this->ensureLoaded();

        $contextModules = $this->contextMaps[$context] ?? [];

        return $this->getAllModules()
            ->filter(fn($module) => in_array($module->name, $contextModules));
    }

    public function getModule(string $name): ?ModuleInfo
    {
        $this->ensureLoaded();

        $moduleData = $this->compiledData['modules'][$name] ?? null;

        return $moduleData ? $this->hydrateModuleInfo($moduleData) : null;
    }

    public function getModulesByWave(int $wave): Collection
    {
        $this->ensureLoaded();

        $waveModules = $this->dependencyGraph['loading_waves'][$wave] ?? [];

        return $this->getAllModules()
            ->filter(fn($module) => in_array($module->name, $waveModules));
    }

    public function getDependencyGraph(): array
    {
        $this->ensureLoaded();
        return $this->dependencyGraph ?? [];
    }

    public function getServiceBindings(string $moduleName): array
    {
        $this->ensureLoaded();

        return [
            'bindings' => $this->serviceBindings['bindings'][$moduleName] ?? [],
            'singletons' => $this->serviceBindings['singletons'][$moduleName] ?? [],
            'aliases' => $this->serviceBindings['aliases'][$moduleName] ?? [],
        ];
    }

    public function getRouteManifest(string $moduleName): array
    {
        $this->ensureLoaded();

        return [
            'routes' => $this->routeManifest['routes'][$moduleName] ?? [],
            'middleware' => $this->routeManifest['middleware'][$moduleName] ?? [],
            'patterns' => $this->routeManifest['patterns'][$moduleName] ?? [],
        ];
    }

    public function isValid(): bool
    {
        $this->ensureLoaded();

        return $this->compiledData !== null
            && isset($this->compiledData['compiled_at'])
            && isset($this->compiledData['version'])
            && isset($this->compiledData['modules']);
    }

    public function getMetadata(): array
    {
        $this->ensureLoaded();

        return [
            'compiled_at' => $this->compiledData['compiled_at'] ?? null,
            'version' => $this->compiledData['version'] ?? null,
            'modules_count' => $this->compiledData['modules_count'] ?? 0,
            'optimization_level' => $this->compiledData['optimization_level'] ?? 'none',
            'performance_hints' => $this->compiledData['performance_hints'] ?? [],
        ];
    }

    public function refresh(): bool
    {
        $this->loaded = false;
        $this->compiledData = null;
        $this->dependencyGraph = null;
        $this->serviceBindings = null;
        $this->routeManifest = null;
        $this->contextMaps = null;

        Cache::forget('modular_ddd:compiled_registry');

        return $this->load();
    }

    private function ensureLoaded(): void
    {
        if (!$this->loaded) {
            $this->load();
        }
    }

    private function load(): bool
    {
        try {
            // Try cache first for ultra-fast access
            $cached = Cache::get('modular_ddd:compiled_registry');
            if ($cached && $this->validateCachedData($cached)) {
                $this->loadFromCache($cached);
                $this->loaded = true;
                return true;
            }

            // Load from compiled files
            $this->loadFromFiles();

            // Cache for future ultra-fast access
            if ($this->isValid()) {
                $this->cacheCompiledData();
            }

            $this->loaded = true;
            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to load compiled registry: ' . $e->getMessage());
            $this->loaded = true; // Set to true to prevent infinite retry
            return false;
        }
    }

    private function loadFromFiles(): void
    {
        $registryPath = $this->getCompiledFilePath('registry');

        if (!$this->files->exists($registryPath)) {
            $this->logger->debug('Compiled registry not found, falling back to runtime discovery');
            return;
        }

        $this->compiledData = require $registryPath;

        // Load dependency graph
        $dependencyPath = $this->getCompiledFilePath('dependency_graph');
        if ($this->files->exists($dependencyPath)) {
            $this->dependencyGraph = require $dependencyPath;
        }

        // Load service bindings
        $bindingsPath = $this->getCompiledFilePath('service_bindings');
        if ($this->files->exists($bindingsPath)) {
            $this->serviceBindings = require $bindingsPath;
        }

        // Load route manifest
        $routesPath = $this->getCompiledFilePath('route_manifest');
        if ($this->files->exists($routesPath)) {
            $this->routeManifest = require $routesPath;
        }

        // Load context maps
        $contextPath = $this->getCompiledFilePath('context_maps');
        if ($this->files->exists($contextPath)) {
            $this->contextMaps = require $contextPath;
        }
    }

    private function loadFromCache(array $cached): void
    {
        $this->compiledData = $cached['registry'];
        $this->dependencyGraph = $cached['dependency_graph'];
        $this->serviceBindings = $cached['service_bindings'];
        $this->routeManifest = $cached['route_manifest'];
        $this->contextMaps = $cached['context_maps'];
    }

    private function cacheCompiledData(): void
    {
        Cache::put('modular_ddd:compiled_registry', [
            'registry' => $this->compiledData,
            'dependency_graph' => $this->dependencyGraph,
            'service_bindings' => $this->serviceBindings,
            'route_manifest' => $this->routeManifest,
            'context_maps' => $this->contextMaps,
            'cached_at' => time(),
        ], 3600); // Cache for 1 hour
    }

    private function validateCachedData(array $cached): bool
    {
        return isset($cached['registry'])
            && isset($cached['cached_at'])
            && (time() - $cached['cached_at']) < 3600; // Valid for 1 hour
    }

    private function hydrateModuleInfo(array $data): ModuleInfo
    {
        return new ModuleInfo(
            name: $data['name'],
            path: $data['path'],
            version: $data['version'] ?? '1.0.0',
            dependencies: $data['dependencies'] ?? [],
            enabled: $data['enabled'] ?? true,
            installed: $data['installed'] ?? true
        );
    }

    private function getCompiledFilePath(string $type): string
    {
        $paths = [
            'registry' => self::COMPILED_REGISTRY_PATH,
            'dependency_graph' => self::DEPENDENCY_GRAPH_PATH,
            'service_bindings' => self::SERVICE_BINDINGS_PATH,
            'route_manifest' => self::ROUTE_MANIFEST_PATH,
            'context_maps' => self::CONTEXT_MAP_PATH,
        ];

        return $this->storagePath . '/' . ($paths[$type] ?? "compiled-{$type}.php");
    }
}