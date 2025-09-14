<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd;

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
use TaiCrm\LaravelModularDdd\Monitoring\QueryPerformanceAnalyzer;
use TaiCrm\LaravelModularDdd\Monitoring\CachePerformanceMonitor;
use TaiCrm\LaravelModularDdd\Monitoring\ModuleResourceMonitor;
use TaiCrm\LaravelModularDdd\Monitoring\EnhancedPerformanceMiddleware;
use TaiCrm\LaravelModularDdd\Visualization\DependencyGraphGenerator;
use TaiCrm\LaravelModularDdd\Security\ModuleSecurityScanner;
use TaiCrm\LaravelModularDdd\Foundation\CqrsServiceProvider;
use TaiCrm\LaravelModularDdd\Authorization\ModuleAuthorizationManager;
use TaiCrm\LaravelModularDdd\Authorization\Middleware\ModulePermissionMiddleware;
use TaiCrm\LaravelModularDdd\Authorization\Middleware\ModuleRoleMiddleware;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Container\Container;

class ModularDddServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/modular-ddd.php', 'modular-ddd');

        $this->registerCoreServices();
        $this->registerCqrsServices();
        $this->registerAuthorizationServices();
        $this->registerVersioningServices();
        $this->registerMonitoringServices();
        $this->registerVisualizationServices();
        $this->registerSecurityServices();
        $this->registerCommands();
    }

    public function boot(): void
    {
        $this->publishConfiguration();
        $this->bootModuleAutoDiscovery();
        $this->bootVersioningServices();
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
            return new ModuleDiscovery(
                $app['files'],
                $app['validator'],
                $app[ModuleRegistry::class],
                config('modular-ddd.modules_path', base_path('modules'))
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
                $app[ModuleRegistry::class]
            );
        });
    }

    private function registerCqrsServices(): void
    {
        $this->app->register(CqrsServiceProvider::class);
    }

    private function registerAuthorizationServices(): void
    {
        // Module Authorization Manager
        $this->app->singleton(ModuleAuthorizationManager::class, function (Container $app) {
            return new ModuleAuthorizationManager(
                $app[ModuleManagerInterface::class]
            );
        });

        // Authorization Middleware
        $this->app->singleton(ModulePermissionMiddleware::class);
        $this->app->singleton(ModuleRoleMiddleware::class);
    }

    private function registerVersioningServices(): void
    {
        // Version Negotiator
        $this->app->singleton(\TaiCrm\LaravelModularDdd\Http\VersionNegotiator::class);

        // Version Transformer and Registry
        $this->app->singleton(\TaiCrm\LaravelModularDdd\Http\Compatibility\TransformationRegistry::class);
        $this->app->singleton(\TaiCrm\LaravelModularDdd\Http\Compatibility\VersionTransformer::class);

        // Version-aware Router
        $this->app->singleton(\TaiCrm\LaravelModularDdd\Http\VersionAwareRouter::class, function (Container $app) {
            return new \TaiCrm\LaravelModularDdd\Http\VersionAwareRouter(
                $app['router'],
                $app[\TaiCrm\LaravelModularDdd\Http\VersionNegotiator::class]
            );
        });

        // API Version Middleware
        $this->app->singleton(\TaiCrm\LaravelModularDdd\Http\Middleware\ApiVersionMiddleware::class, function (Container $app) {
            return new \TaiCrm\LaravelModularDdd\Http\Middleware\ApiVersionMiddleware(
                $app[\TaiCrm\LaravelModularDdd\Http\VersionNegotiator::class]
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

        // Enhanced Performance Monitoring Components
        $this->app->singleton(QueryPerformanceAnalyzer::class);
        $this->app->singleton(CachePerformanceMonitor::class);
        $this->app->singleton(ModuleResourceMonitor::class);

        // Performance Middleware
        $this->app->singleton(PerformanceMiddleware::class, function (Container $app) {
            return new PerformanceMiddleware(
                $app[ModulePerformanceMonitor::class]
            );
        });

        // Enhanced Performance Middleware
        $this->app->singleton(EnhancedPerformanceMiddleware::class, function (Container $app) {
            return new EnhancedPerformanceMiddleware(
                $app[QueryPerformanceAnalyzer::class],
                $app[CachePerformanceMonitor::class],
                $app[ModuleResourceMonitor::class]
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
        if ($this->app->runningInConsole() && !$this->app->environment('testing')) {
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
                \TaiCrm\LaravelModularDdd\Commands\ModuleMakeEventCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleMakeListenerCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleMakeTestCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleMakeFactoryCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleMakeCommandCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleMakeQueryCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleMakeApiCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleMakeControllerCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleMakeMiddlewareCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleMakeRequestCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleMakeResourceCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModulePerformanceAnalyzeCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModuleMakePolicyCommand::class,
                \TaiCrm\LaravelModularDdd\Commands\ModulePermissionCommand::class,
            ]);

            // Register command dependencies
            $this->app->when(ModuleMakeCommand::class)
                ->needs('$modulesPath')
                ->give(config('modular-ddd.modules_path', base_path('modules')));

            $this->app->when(ModuleMakeCommand::class)
                ->needs('$stubPath')
                ->give(__DIR__ . '/../stubs');

            $this->app->when(\TaiCrm\LaravelModularDdd\Commands\ModuleStubCommand::class)
                ->needs('$stubPath')
                ->give(__DIR__ . '/../stubs');

            $this->app->when(\TaiCrm\LaravelModularDdd\Commands\ModuleDevCommand::class)
                ->needs('$stubPath')
                ->give(__DIR__ . '/../stubs');

            $this->app->when(\TaiCrm\LaravelModularDdd\Commands\ModuleMakeEventCommand::class)
                ->needs('$modulesPath')
                ->give(config('modular-ddd.modules_path', base_path('modules')));

            $this->app->when(\TaiCrm\LaravelModularDdd\Commands\ModuleMakeEventCommand::class)
                ->needs('$stubPath')
                ->give(__DIR__ . '/../stubs');

            $this->app->when(\TaiCrm\LaravelModularDdd\Commands\ModuleMakeListenerCommand::class)
                ->needs('$modulesPath')
                ->give(config('modular-ddd.modules_path', base_path('modules')));

            $this->app->when(\TaiCrm\LaravelModularDdd\Commands\ModuleMakeListenerCommand::class)
                ->needs('$stubPath')
                ->give(__DIR__ . '/../stubs');

            $this->app->when(\TaiCrm\LaravelModularDdd\Commands\ModuleMakeTestCommand::class)
                ->needs('$modulesPath')
                ->give(config('modular-ddd.modules_path', base_path('modules')));

            $this->app->when(\TaiCrm\LaravelModularDdd\Commands\ModuleMakeTestCommand::class)
                ->needs('$stubPath')
                ->give(__DIR__ . '/../stubs');

            $this->app->when(\TaiCrm\LaravelModularDdd\Commands\ModuleMakeFactoryCommand::class)
                ->needs('$modulesPath')
                ->give(config('modular-ddd.modules_path', base_path('modules')));

            $this->app->when(\TaiCrm\LaravelModularDdd\Commands\ModuleMakeFactoryCommand::class)
                ->needs('$stubPath')
                ->give(__DIR__ . '/../stubs');
        }
    }

    private function publishConfiguration(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/modular-ddd.php' => config_path('modular-ddd.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../stubs' => resource_path('stubs/modular-ddd'),
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

        // Load API routes with versioning support
        if (file_exists($routesPath . '/api.php')) {
            $this->loadRoutesFrom($routesPath . '/api.php');
        }

        // Load web routes
        if (file_exists($routesPath . '/web.php')) {
            $this->loadRoutesFrom($routesPath . '/web.php');
        }
    }

    private function bootVersioningServices(): void
    {
        // Register API version middleware
        $router = $this->app['router'];
        $router->aliasMiddleware('api.version', \TaiCrm\LaravelModularDdd\Http\Middleware\ApiVersionMiddleware::class);

        // Register version discovery routes
        if ($this->app->routesAreCached()) {
            return;
        }

        $versionAwareRouter = $this->app[\TaiCrm\LaravelModularDdd\Http\VersionAwareRouter::class];
        $versionAwareRouter->registerVersionDiscoveryRoutes();
        $versionAwareRouter->registerVersionConstraints();
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
        ];
    }
}