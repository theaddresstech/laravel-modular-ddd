<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Loading;

use TaiCrm\LaravelModularDdd\Compilation\Contracts\CompiledRegistryInterface;
use TaiCrm\LaravelModularDdd\Context\ModuleContext;
use TaiCrm\LaravelModularDdd\Context\ContextAnalyzer;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;

/**
 * Ultra-performance parallel module loader
 */
class ParallelModuleLoader
{
    private array $loadedModules = [];
    private array $loadingWaves = [];
    private array $currentContext;

    public function __construct(
        private CompiledRegistryInterface $registry,
        private ContextAnalyzer $contextAnalyzer,
        private Container $container,
        private LoggerInterface $logger
    ) {
        $this->currentContext = ModuleContext::detect();
    }

    /**
     * Load modules optimized for current context
     */
    public function loadModules(): LoadingResult
    {
        $startTime = microtime(true);
        $this->logger->info('Starting ultra-parallel module loading...');

        try {
            // Get loading strategy for current context
            $strategy = $this->contextAnalyzer->getLoadingStrategy($this->currentContext);
            $memoryConfig = $this->contextAnalyzer->getMemoryOptimizedConfig($this->currentContext);

            // Load modules based on strategy
            $result = $this->executeLoadingStrategy($strategy, $memoryConfig);

            $loadingTime = (microtime(true) - $startTime) * 1000;

            return new LoadingResult(
                success: true,
                modulesLoaded: count($this->loadedModules),
                loadingTimeMs: $loadingTime,
                strategy: $strategy,
                memoryUsage: memory_get_peak_usage(true),
                context: $this->currentContext
            );

        } catch (\Exception $e) {
            $this->logger->error('Module loading failed: ' . $e->getMessage());

            return new LoadingResult(
                success: false,
                error: $e->getMessage(),
                loadingTimeMs: (microtime(true) - $startTime) * 1000,
                context: $this->currentContext
            );
        }
    }

    /**
     * Load specific modules by context
     */
    public function loadModulesByContext(string $context): LoadingResult
    {
        $startTime = microtime(true);

        try {
            $modules = $this->registry->getModulesByContext($context);
            $loadedCount = 0;

            if ($this->shouldUseParallelLoading($context)) {
                $loadedCount = $this->loadModulesParallel($modules);
            } else {
                $loadedCount = $this->loadModulesSequential($modules);
            }

            return new LoadingResult(
                success: true,
                modulesLoaded: $loadedCount,
                loadingTimeMs: (microtime(true) - $startTime) * 1000,
                context: [$context]
            );

        } catch (\Exception $e) {
            return new LoadingResult(
                success: false,
                error: $e->getMessage(),
                loadingTimeMs: (microtime(true) - $startTime) * 1000,
                context: [$context]
            );
        }
    }

    /**
     * Load modules in dependency waves
     */
    public function loadModulesByWaves(): LoadingResult
    {
        $startTime = microtime(true);
        $totalLoaded = 0;

        try {
            $dependencyGraph = $this->registry->getDependencyGraph();
            $waves = $dependencyGraph['loading_waves'] ?? [];

            foreach ($waves as $waveIndex => $moduleNames) {
                $this->logger->debug("Loading wave {$waveIndex} with " . count($moduleNames) . " modules");

                $waveModules = collect($moduleNames)
                    ->map(fn($name) => $this->registry->getModule($name))
                    ->filter();

                if ($this->canLoadWaveInParallel($waveIndex)) {
                    $loaded = $this->loadModulesParallel($waveModules);
                } else {
                    $loaded = $this->loadModulesSequential($waveModules);
                }

                $totalLoaded += $loaded;
                $this->logger->debug("Wave {$waveIndex} loaded {$loaded} modules");
            }

            return new LoadingResult(
                success: true,
                modulesLoaded: $totalLoaded,
                loadingTimeMs: (microtime(true) - $startTime) * 1000,
                context: $this->currentContext
            );

        } catch (\Exception $e) {
            return new LoadingResult(
                success: false,
                error: $e->getMessage(),
                loadingTimeMs: (microtime(true) - $startTime) * 1000,
                context: $this->currentContext
            );
        }
    }

    /**
     * Execute loading strategy
     */
    private function executeLoadingStrategy(array $strategy, array $memoryConfig): bool
    {
        // Eager loading
        if (!empty($strategy['eager_modules'])) {
            $eagerModules = collect($strategy['eager_modules'])
                ->map(fn($name) => $this->registry->getModule($name))
                ->filter();

            $this->loadModulesSequential($eagerModules);
        }

        // Lazy loading
        if (!empty($strategy['lazy_modules'])) {
            $lazyModules = collect($strategy['lazy_modules'])
                ->map(fn($name) => $this->registry->getModule($name))
                ->filter();

            if ($memoryConfig['parallel_loading']) {
                $this->loadModulesParallel($lazyModules, $memoryConfig['chunk_size']);
            } else {
                $this->loadModulesSequential($lazyModules);
            }
        }

        // Deferred modules are registered for lazy loading
        if (!empty($strategy['deferred_modules'])) {
            $this->registerDeferredModules($strategy['deferred_modules']);
        }

        return true;
    }

    /**
     * Load modules in parallel using process forking
     */
    private function loadModulesParallel(Collection $modules, int $chunkSize = 5): int
    {
        if ($modules->isEmpty()) {
            return 0;
        }

        $chunks = $modules->chunk($chunkSize);
        $loadedCount = 0;

        foreach ($chunks as $chunk) {
            $processes = [];

            // Start parallel processes
            foreach ($chunk as $module) {
                if ($this->isModuleAlreadyLoaded($module->name)) {
                    continue;
                }

                $processes[$module->name] = $this->startModuleLoadingProcess($module);
            }

            // Wait for processes to complete
            foreach ($processes as $moduleName => $process) {
                if ($this->waitForProcessCompletion($process)) {
                    $this->markModuleAsLoaded($moduleName);
                    $loadedCount++;
                } else {
                    $this->logger->warning("Failed to load module: {$moduleName}");
                }
            }
        }

        return $loadedCount;
    }

    /**
     * Load modules sequentially
     */
    private function loadModulesSequential(Collection $modules): int
    {
        $loadedCount = 0;

        foreach ($modules as $module) {
            if ($this->isModuleAlreadyLoaded($module->name)) {
                continue;
            }

            if ($this->loadModule($module)) {
                $this->markModuleAsLoaded($module->name);
                $loadedCount++;
            }
        }

        return $loadedCount;
    }

    /**
     * Load individual module
     */
    private function loadModule(ModuleInfo $module): bool
    {
        try {
            $this->logger->debug("Loading module: {$module->name}");

            // Pre-register service bindings
            $this->registerServiceBindings($module);

            // Load service provider
            $this->loadServiceProvider($module);

            // Register routes
            $this->registerRoutes($module);

            return true;

        } catch (\Exception $e) {
            $this->logger->error("Failed to load module {$module->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Register pre-compiled service bindings
     */
    private function registerServiceBindings(ModuleInfo $module): void
    {
        $bindings = $this->registry->getServiceBindings($module->name);

        // Register bindings
        foreach ($bindings['bindings'] as $abstract => $concrete) {
            $this->container->bind($abstract, $concrete);
        }

        // Register singletons
        foreach ($bindings['singletons'] as $abstract => $concrete) {
            $this->container->singleton($abstract, $concrete);
        }

        // Register aliases
        foreach ($bindings['aliases'] as $alias => $abstract) {
            $this->container->alias($abstract, $alias);
        }
    }

    /**
     * Load module service provider
     */
    private function loadServiceProvider(ModuleInfo $module): void
    {
        $providerClass = "Modules\\{$module->name}\\Providers\\{$module->name}ServiceProvider";

        if (class_exists($providerClass)) {
            $this->container->register($providerClass);
        }
    }

    /**
     * Register module routes
     */
    private function registerRoutes(ModuleInfo $module): void
    {
        $routeManifest = $this->registry->getRouteManifest($module->name);

        foreach ($routeManifest['routes'] as $type => $routes) {
            $this->registerRouteGroup($type, $routes, $routeManifest['middleware'][$type] ?? []);
        }
    }

    /**
     * Register route group
     */
    private function registerRouteGroup(string $type, array $routes, array $middleware): void
    {
        // Implementation would register routes based on compiled manifest
        // This is a simplified version
    }

    /**
     * Register deferred modules for lazy loading
     */
    private function registerDeferredModules(array $moduleNames): void
    {
        foreach ($moduleNames as $moduleName) {
            Cache::put("deferred_module:{$moduleName}", true, 3600);
        }
    }

    /**
     * Start module loading process (simulated)
     */
    private function startModuleLoadingProcess(ModuleInfo $module): array
    {
        // In a real implementation, this would use process forking
        // For now, simulate with array
        return [
            'module' => $module,
            'started_at' => microtime(true),
            'pid' => getmypid(),
        ];
    }

    /**
     * Wait for process completion (simulated)
     */
    private function waitForProcessCompletion(array $process): bool
    {
        // Simulate process completion by loading the module
        return $this->loadModule($process['module']);
    }

    private function shouldUseParallelLoading(string $context): bool
    {
        return ModuleContext::from($context)->getPriority() <= 2; // CLI and API contexts
    }

    private function canLoadWaveInParallel(int $waveIndex): bool
    {
        // Wave 0 (no dependencies) can always be parallel
        // Later waves might have interdependencies
        return $waveIndex === 0;
    }

    private function isModuleAlreadyLoaded(string $moduleName): bool
    {
        return in_array($moduleName, $this->loadedModules);
    }

    private function markModuleAsLoaded(string $moduleName): void
    {
        $this->loadedModules[] = $moduleName;
    }
}