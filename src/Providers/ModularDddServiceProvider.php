<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Providers;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Contracts\ModuleDiscoveryInterface;
use TaiCrm\LaravelModularDdd\Contracts\DependencyResolverInterface;
use TaiCrm\LaravelModularDdd\ModuleManager\ModuleManager;
use TaiCrm\LaravelModularDdd\ModuleManager\ModuleDiscovery;
use TaiCrm\LaravelModularDdd\ModuleManager\DependencyResolver;
use TaiCrm\LaravelModularDdd\ModuleManager\ModuleRegistry;
use TaiCrm\LaravelModularDdd\Commands\ModuleListCommand;
use TaiCrm\LaravelModularDdd\Commands\ModuleInstallCommand;
use TaiCrm\LaravelModularDdd\Commands\ModuleMakeCommand;
use TaiCrm\LaravelModularDdd\Communication\Contracts\ServiceRegistryInterface;
use TaiCrm\LaravelModularDdd\Communication\ServiceRegistry;
use TaiCrm\LaravelModularDdd\Communication\EventBus;
use TaiCrm\LaravelModularDdd\Health\ModuleHealthChecker;
use TaiCrm\LaravelModularDdd\Monitoring\ModulePerformanceMonitor;
use TaiCrm\LaravelModularDdd\Monitoring\MetricsCollector;
use TaiCrm\LaravelModularDdd\Monitoring\PerformanceMiddleware;
use TaiCrm\LaravelModularDdd\Visualization\DependencyGraphGenerator;
use TaiCrm\LaravelModularDdd\Security\ModuleSecurityScanner;
use TaiCrm\LaravelModularDdd\Compilation\ModuleCompiler;
use TaiCrm\LaravelModularDdd\Compilation\CompiledRegistry;
use TaiCrm\LaravelModularDdd\Compilation\Contracts\ModuleCompilerInterface;
use TaiCrm\LaravelModularDdd\Compilation\Contracts\CompiledRegistryInterface;
use TaiCrm\LaravelModularDdd\Loading\ParallelModuleLoader;
use TaiCrm\LaravelModularDdd\Context\ContextAnalyzer;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Container\Container;

class ModularDddServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/modular-ddd.php', 'modular-ddd');

        $this->registerCoreServices();
        $this->registerCompilationServices();
        $this->registerMonitoringServices();
        $this->registerVisualizationServices();
        $this->registerSecurityServices();
        $this->registerCommands();
    }

    public function boot(): void
    {
        $this->publishConfiguration();
        $this->bootModuleAutoDiscovery();
    }

    private function registerCoreServices(): void
    {
        // Module Registry
        $this->app->singleton(ModuleRegistry::class, function (Container $app) {
            return new ModuleRegistry(
                $app['files'],
                $app['cache.store'],
                config('modular-ddd.registry_storage', storage_path('app/modules'))
            );
        });

        // Dependency Resolver
        $this->app->singleton(DependencyResolverInterface::class, DependencyResolver::class);

        // Module Discovery
        $this->app->singleton(ModuleDiscoveryInterface::class, function (Container $app) {
            $modulesPath = config('modular-ddd.modules_path', base_path('modules'));

            // Ensure absolute path - convert relative paths to be relative to Laravel base path
            if (!str_starts_with($modulesPath, '/') && !str_contains($modulesPath, ':/')) {
                $modulesPath = base_path($modulesPath);
            }

            return new ModuleDiscovery(
                $app['files'],
                $app['validator'],
                $app[ModuleRegistry::class],
                $modulesPath
            );
        });

        // Service Registry
        $this->app->singleton(ServiceRegistryInterface::class, function (Container $app) {
            return new ServiceRegistry(
                $app,
                $app['log']
            );
        });

        // Event Bus
        $this->app->singleton(EventBus::class, function (Container $app) {
            return new EventBus(
                $app['events'],
                $app['log']
            );
        });

        // Module Health Checker
        $this->app->singleton(ModuleHealthChecker::class, function (Container $app) {
            return new ModuleHealthChecker(
                $app[ModuleManagerInterface::class],
                $app['log']
            );
        });

        // Module Manager
        $this->app->singleton(ModuleManagerInterface::class, function (Container $app) {
            return new ModuleManager(
                $app[ModuleDiscoveryInterface::class],
                $app[DependencyResolverInterface::class],
                $app['cache.store'],
                $app['events'],
                $app['log'],
                $app[ModuleRegistry::class],
                $app,
                $app['files'],
                $app['router']
            );
        });
    }

    private function registerCompilationServices(): void
    {
        // Compiled Registry
        $this->app->singleton(CompiledRegistryInterface::class, function (Container $app) {
            return new CompiledRegistry(
                $app['files'],
                $app['log'],
                config('modular-ddd.registry_storage', storage_path('framework/modular-ddd'))
            );
        });

        // Context Analyzer
        $this->app->singleton(ContextAnalyzer::class, function (Container $app) {
            return new ContextAnalyzer(
                $app['files'],
                $app['log']
            );
        });

        // Module Compiler
        $this->app->singleton(ModuleCompilerInterface::class, function (Container $app) {
            return new ModuleCompiler(
                $app[ModuleDiscoveryInterface::class],
                $app[DependencyResolverInterface::class],
                $app['files'],
                $app['log'],
                config('modular-ddd.registry_storage', storage_path('framework/modular-ddd'))
            );
        });

        // Parallel Module Loader
        $this->app->singleton(ParallelModuleLoader::class, function (Container $app) {
            return new ParallelModuleLoader(
                $app[CompiledRegistryInterface::class],
                $app[ContextAnalyzer::class],
                $app,
                $app['log']
            );
        });
    }

    private function registerMonitoringServices(): void
    {
        if (!config('modular-ddd.monitoring.enabled', true)) {
            return;
        }

        // Performance Monitor
        $this->app->singleton(ModulePerformanceMonitor::class, function (Container $app) {
            return new ModulePerformanceMonitor(
                $app[ModuleManagerInterface::class],
                $app['cache'],
                $app['log']
            );
        });

        // Metrics Collector
        $this->app->singleton(MetricsCollector::class, function (Container $app) {
            return new MetricsCollector(
                $app[ModuleManagerInterface::class],
                $app['db'],
                $app['cache'],
                $app['queue'],
                $app['log']
            );
        });

        // Performance Middleware
        $this->app->singleton(PerformanceMiddleware::class, function (Container $app) {
            return new PerformanceMiddleware(
                $app[ModulePerformanceMonitor::class]
            );
        });
    }

    private function registerVisualizationServices(): void
    {
        // Dependency Graph Generator
        $this->app->singleton(DependencyGraphGenerator::class, function (Container $app) {
            return new DependencyGraphGenerator(
                $app[ModuleManagerInterface::class]
            );
        });
    }

    private function registerSecurityServices(): void
    {
        // Security Scanner
        $this->app->singleton(ModuleSecurityScanner::class, function (Container $app) {
            return new ModuleSecurityScanner(
                $app[ModuleManagerInterface::class],
                $app['files'],
                $app['log']
            );
        });
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleListCommand::class,
                ModuleInstallCommand::class,
                ModuleMakeCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleEnableCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleDisableCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleRemoveCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleStatusCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleCacheCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleHealthCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleMigrateCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleSeedCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleUpdateCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleBackupCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleRestoreCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleDevCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleStubCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleMetricsCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleVisualizationCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleSecurityCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleCompileCommand::class,
            ]);

            // Register command dependencies
            $this->app->when(ModuleMakeCommand::class)
                ->needs('$modulesPath')
                ->give(config('modular-ddd.modules_path', base_path('modules')));

            $this->app->when(ModuleMakeCommand::class)
                ->needs('$stubPath')
                ->give(__DIR__ . '/../../stubs');

            $this->app->when(\TaiCrm\LaravelModularDdd\Commands\ModuleStubCommand::class)
                ->needs('$stubPath')
                ->give(__DIR__ . '/../../stubs');

            $this->app->when(\TaiCrm\LaravelModularDdd\Commands\ModuleDevCommand::class)
                ->needs('$stubPath')
                ->give(__DIR__ . '/../../stubs');
        }
    }

    private function publishConfiguration(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/modular-ddd.php' => config_path('modular-ddd.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../../stubs' => resource_path('stubs/modular-ddd'),
            ], 'stubs');
        }
    }

    private function bootModuleAutoDiscovery(): void
    {
        if (config('modular-ddd.auto_discovery', true)) {
            $moduleManager = $this->app[ModuleManagerInterface::class];

            // Auto-discover and load enabled modules
            $this->loadEnabledModules($moduleManager);
        }
    }

    private function loadEnabledModules(ModuleManagerInterface $moduleManager): void
    {
        try {
            $modules = $moduleManager->list();

            foreach ($modules as $module) {
                if ($module->isEnabled()) {
                    $this->loadModule($module);
                }
            }
        } catch (\Exception $e) {
            // Log error but don't break the application
            logger()->error('Failed to load modules: ' . $e->getMessage());
        }
    }

    private function loadModule($module): void
    {
        // Load module service provider if it exists
        $providerClass = "Modules\\{$module->name}\\Providers\\{$module->name}ServiceProvider";

        if (class_exists($providerClass)) {
            $this->app->register($providerClass);
        }

        // Load module routes
        $this->loadModuleRoutes($module);
    }

    private function loadModuleRoutes($module): void
    {
        $routesPath = $module->path . '/Routes';

        // Load API routes
        if (file_exists($routesPath . '/api.php')) {
            $this->loadRoutesFrom($routesPath . '/api.php');
        }

        // Load web routes
        if (file_exists($routesPath . '/web.php')) {
            $this->loadRoutesFrom($routesPath . '/web.php');
        }
    }

    public function provides(): array
    {
        return [
            ModuleManagerInterface::class,
            ModuleDiscoveryInterface::class,
            DependencyResolverInterface::class,
            ModuleRegistry::class,
            ServiceRegistryInterface::class,
            EventBus::class,
            ModuleHealthChecker::class,
            ModulePerformanceMonitor::class,
            MetricsCollector::class,
            PerformanceMiddleware::class,
            DependencyGraphGenerator::class,
            ModuleSecurityScanner::class,
            ModuleCompilerInterface::class,
            CompiledRegistryInterface::class,
            ParallelModuleLoader::class,
            ContextAnalyzer::class,
        ];
    }
}