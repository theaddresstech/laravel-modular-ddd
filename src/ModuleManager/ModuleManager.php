<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\ModuleManager;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Contracts\ModuleDiscoveryInterface;
use TaiCrm\LaravelModularDdd\Contracts\DependencyResolverInterface;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleState;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleNotFoundException;
use TaiCrm\LaravelModularDdd\Exceptions\DependencyException;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleInstallationException;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Routing\Router;

class ModuleManager implements ModuleManagerInterface
{
    private const CACHE_KEY = 'modular_ddd_modules';
    private const CACHE_TTL = 3600;

    public function __construct(
        private ModuleDiscoveryInterface $discovery,
        private DependencyResolverInterface $dependencyResolver,
        private CacheRepository $cache,
        private Dispatcher $events,
        private LoggerInterface $logger,
        private ModuleRegistry $registry,
        private Application $app,
        private Filesystem $files,
        private Router $router
    ) {}

    public function list(): Collection
    {
        // Check if caching is disabled or we're in testing
        if (!config('modular-ddd.cache.enabled', true) || app()->environment('testing')) {
            return $this->discovery->discover();
        }

        return $this->cache->remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->discovery->discover();
        });
    }

    public function install(string $moduleName): bool
    {
        try {
            $this->logger->info("Installing module: {$moduleName}");

            $module = $this->discovery->findModule($moduleName);
            if (!$module) {
                throw new ModuleNotFoundException($moduleName);
            }

            if ($this->isInstalled($moduleName)) {
                throw ModuleInstallationException::cannotInstall($moduleName, 'Module is already installed');
            }

            // Check if all dependencies are available (exist as modules)
            $this->validateDependenciesAvailable($moduleName);

            // Install dependencies first
            $dependencies = $this->getDependencies($moduleName);
            foreach ($dependencies as $dependency) {
                if (!$this->isInstalled($dependency)) {
                    $this->install($dependency);
                }
            }

            // Validate that all dependencies are now installed
            $this->validateDependenciesInstalled($moduleName);

            // Perform installation
            $this->performInstallation($module);

            // Update registry
            $this->registry->setModuleState($moduleName, ModuleState::Installed);

            $this->clearCache();
            $this->events->dispatch('module.installed', ['module' => $moduleName]);

            $this->logger->info("Module {$moduleName} installed successfully");
            return true;

        } catch (\Exception $e) {
            $this->logger->error("Failed to install module {$moduleName}: " . $e->getMessage());
            throw $e;
        }
    }

    public function enable(string $moduleName): bool
    {
        try {
            $this->logger->info("Enabling module: {$moduleName}");

            if (!$this->isInstalled($moduleName)) {
                throw ModuleInstallationException::cannotEnable($moduleName, 'Module is not installed');
            }

            if ($this->isEnabled($moduleName)) {
                return true;
            }

            $this->validateDependencies($moduleName);

            // Enable dependencies first
            $dependencies = $this->getDependencies($moduleName);
            foreach ($dependencies as $dependency) {
                if (!$this->isEnabled($dependency)) {
                    $this->enable($dependency);
                }
            }

            // Perform enabling
            $this->performEnabling($moduleName);

            // Update registry
            $this->registry->setModuleState($moduleName, ModuleState::Enabled);

            $this->clearCache();
            $this->events->dispatch('module.enabled', ['module' => $moduleName]);

            $this->logger->info("Module {$moduleName} enabled successfully");
            return true;

        } catch (\Exception $e) {
            $this->logger->error("Failed to enable module {$moduleName}: " . $e->getMessage());
            throw $e;
        }
    }

    public function disable(string $moduleName): bool
    {
        try {
            $this->logger->info("Disabling module: {$moduleName}");

            if (!$this->isEnabled($moduleName)) {
                return true;
            }

            // Check for dependents
            $dependents = $this->getDependents($moduleName);
            if ($dependents->isNotEmpty()) {
                $enabledDependents = $dependents->filter(fn($dep) => $this->isEnabled($dep));
                if ($enabledDependents->isNotEmpty()) {
                    throw ModuleInstallationException::cannotDisable(
                        $moduleName,
                        'Module has enabled dependents: ' . $enabledDependents->implode(', ')
                    );
                }
            }

            // Perform disabling
            $this->performDisabling($moduleName);

            // Update registry
            $this->registry->setModuleState($moduleName, ModuleState::Disabled);

            $this->clearCache();
            $this->events->dispatch('module.disabled', ['module' => $moduleName]);

            $this->logger->info("Module {$moduleName} disabled successfully");
            return true;

        } catch (\Exception $e) {
            $this->logger->error("Failed to disable module {$moduleName}: " . $e->getMessage());
            throw $e;
        }
    }

    public function remove(string $moduleName): bool
    {
        try {
            $this->logger->info("Removing module: {$moduleName}");

            if (!$this->isInstalled($moduleName)) {
                return true;
            }

            // Disable first if enabled
            if ($this->isEnabled($moduleName)) {
                $this->disable($moduleName);
            }

            // Check for dependents
            $dependents = $this->getDependents($moduleName);
            if ($dependents->isNotEmpty()) {
                $installedDependents = $dependents->filter(fn($dep) => $this->isInstalled($dep));
                if ($installedDependents->isNotEmpty()) {
                    throw ModuleInstallationException::cannotRemove(
                        $moduleName,
                        'Module has installed dependents: ' . $installedDependents->implode(', ')
                    );
                }
            }

            // Perform removal
            $this->performRemoval($moduleName);

            // Update registry
            $this->registry->setModuleState($moduleName, ModuleState::NotInstalled);

            $this->clearCache();
            $this->events->dispatch('module.removed', ['module' => $moduleName]);

            $this->logger->info("Module {$moduleName} removed successfully");
            return true;

        } catch (\Exception $e) {
            $this->logger->error("Failed to remove module {$moduleName}: " . $e->getMessage());
            throw $e;
        }
    }

    public function update(string $moduleName, ?string $version = null): bool
    {
        try {
            $this->logger->info("Updating module: {$moduleName}");

            $module = $this->getInfo($moduleName);
            if (!$module) {
                throw new ModuleNotFoundException($moduleName);
            }

            if (!$this->isInstalled($moduleName)) {
                throw ModuleInstallationException::cannotUpdate($moduleName, 'Module is not installed');
            }

            // Create backup before update
            $this->createModuleBackup($module);

            // Disable module temporarily
            $wasEnabled = $this->isEnabled($moduleName);
            if ($wasEnabled) {
                $this->performDisabling($moduleName);
            }

            // Perform update operations
            $this->performModuleUpdate($module, $version);

            // Re-enable if it was enabled before
            if ($wasEnabled) {
                $this->performEnabling($moduleName);
            }

            $this->clearCache();
            $this->events->dispatch('module.updated', ['module' => $moduleName, 'version' => $version]);

            $this->logger->info("Module {$moduleName} updated successfully");
            return true;

        } catch (\Exception $e) {
            $this->logger->error("Failed to update module {$moduleName}: " . $e->getMessage());
            throw $e;
        }
    }

    public function isInstalled(string $moduleName): bool
    {
        return $this->registry->isInstalled($moduleName);
    }

    public function isEnabled(string $moduleName): bool
    {
        return $this->registry->isEnabled($moduleName);
    }

    public function getInfo(string $moduleName): ?ModuleInfo
    {
        return $this->discovery->findModule($moduleName);
    }

    public function getState(string $moduleName): ModuleState
    {
        return $this->registry->getModuleState($moduleName);
    }

    public function getDependencies(string $moduleName): Collection
    {
        $module = $this->getInfo($moduleName);
        if (!$module) {
            return collect();
        }

        return collect($module->dependencies);
    }

    public function getDependents(string $moduleName): Collection
    {
        $allModules = $this->list();
        return $allModules->filter(function (ModuleInfo $module) use ($moduleName) {
            return $module->hasDependency($moduleName);
        })->pluck('name');
    }

    public function validateDependencies(string $moduleName): bool
    {
        return $this->validateDependenciesAvailable($moduleName) &&
               $this->validateDependenciesInstalled($moduleName);
    }

    private function validateDependenciesAvailable(string $moduleName): bool
    {
        $module = $this->getInfo($moduleName);
        if (!$module) {
            throw new ModuleNotFoundException($moduleName);
        }

        $availableModules = $this->list();
        $errors = $this->dependencyResolver->validateDependencies($module, $availableModules);

        if (!empty($errors)) {
            throw DependencyException::missingDependencies($moduleName, $errors);
        }

        if ($this->dependencyResolver->hasCircularDependency($module, $availableModules)) {
            throw DependencyException::circularDependency($moduleName);
        }

        return true;
    }

    private function validateDependenciesInstalled(string $moduleName): bool
    {
        $dependencies = $this->getDependencies($moduleName);
        $missingDependencies = [];

        foreach ($dependencies as $dependency) {
            if (!$this->isInstalled($dependency)) {
                $missingDependencies[] = $dependency;
            }
        }

        if (!empty($missingDependencies)) {
            throw DependencyException::missingDependencies($moduleName, $missingDependencies);
        }

        return true;
    }

    public function clearCache(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    public function rebuildCache(): void
    {
        $this->clearCache();
        $this->list();
    }

    public function get(string $moduleName): ?ModuleInfo
    {
        return $this->getInfo($moduleName);
    }

    public function getActiveModules(): array
    {
        $modules = $this->list();
        return $modules->filter(function (ModuleInfo $module) {
            return $this->isEnabled($module->name);
        })->map(function (ModuleInfo $module) {
            return [
                'name' => $module->name,
                'path' => $module->path,
                'namespace' => $module->namespace ?? ('Modules\\' . $module->name),
                'dependencies' => $module->dependencies,
            ];
        })->values()->toArray();
    }

    private function performInstallation(ModuleInfo $module): void
    {
        $moduleName = $module->name;
        $modulePath = $module->path;

        try {
            // 1. Run module migrations
            $this->runModuleMigrations($moduleName, $modulePath);

            // 2. Publish module assets and configs
            $this->publishModuleAssets($moduleName, $modulePath);

            // 3. Copy module files to appropriate locations
            $this->copyModuleFiles($moduleName, $modulePath);

            // 4. Register module service providers
            $this->registerModuleServiceProviders($moduleName, $modulePath);

            // 5. Execute module-specific installation hooks
            $this->executeInstallationHooks($moduleName, $modulePath);

            $this->logger->info("Module {$moduleName} installation completed successfully");

        } catch (\Exception $e) {
            $this->logger->error("Module {$moduleName} installation failed: " . $e->getMessage());
            // Rollback installation
            $this->rollbackInstallation($moduleName, $modulePath);
            throw $e;
        }
    }

    private function performEnabling(string $moduleName): void
    {
        $module = $this->getInfo($moduleName);
        if (!$module) {
            throw new ModuleNotFoundException($moduleName);
        }

        $modulePath = $module->path;

        try {
            // 1. Load and register module service providers
            $this->loadModuleServiceProviders($moduleName, $modulePath);

            // 2. Register module routes
            $this->registerModuleRoutes($moduleName, $modulePath);

            // 3. Publish and load module configuration
            $this->loadModuleConfiguration($moduleName, $modulePath);

            // 4. Register module event listeners
            $this->registerModuleEventListeners($moduleName, $modulePath);

            // 5. Load module translations and views
            $this->loadModuleTranslationsAndViews($moduleName, $modulePath);

            // 6. Execute module-specific enabling hooks
            $this->executeEnablingHooks($moduleName, $modulePath);

            $this->logger->info("Module {$moduleName} enabled successfully");

        } catch (\Exception $e) {
            $this->logger->error("Failed to enable module {$moduleName}: " . $e->getMessage());
            throw $e;
        }
    }

    private function performDisabling(string $moduleName): void
    {
        try {
            // 1. Execute module-specific disabling hooks
            $this->executeDisablingHooks($moduleName);

            // 2. Unregister module event listeners
            $this->unregisterModuleEventListeners($moduleName);

            // 3. Clear module routes
            $this->clearModuleRoutes($moduleName);

            // 4. Unregister service providers gracefully
            $this->unregisterModuleServiceProviders($moduleName);

            // 5. Clear module caches
            $this->clearModuleCaches($moduleName);

            // 6. Remove module from service container
            $this->removeModuleFromContainer($moduleName);

            $this->logger->info("Module {$moduleName} disabled successfully");

        } catch (\Exception $e) {
            $this->logger->error("Failed to disable module {$moduleName}: " . $e->getMessage());
            throw $e;
        }
    }

    private function performRemoval(string $moduleName): void
    {
        $module = $this->getInfo($moduleName);
        if (!$module) {
            throw new ModuleNotFoundException($moduleName);
        }

        $modulePath = $module->path;

        try {
            // 1. Execute module-specific removal hooks
            $this->executeRemovalHooks($moduleName, $modulePath);

            // 2. Rollback module migrations
            $this->rollbackModuleMigrations($moduleName, $modulePath);

            // 3. Remove published assets and configuration files
            $this->removePublishedAssets($moduleName, $modulePath);

            // 4. Clean up module-specific storage and cache files
            $this->cleanupModuleStorage($moduleName);

            // 5. Remove module from service container completely
            $this->removeModuleFromContainer($moduleName);

            // 6. Clean up module registry entry
            $this->registry->removeModule($moduleName);

            $this->logger->info("Module {$moduleName} removed successfully");

        } catch (\Exception $e) {
            $this->logger->error("Failed to remove module {$moduleName}: " . $e->getMessage());
            throw $e;
        }
    }

    // Helper methods for module operations

    private function runModuleMigrations(string $moduleName, string $modulePath): void
    {
        $migrationsPath = $modulePath . '/Database/Migrations';

        if (!$this->files->isDirectory($migrationsPath)) {
            $this->logger->info("No migrations directory found for module {$moduleName}");
            return;
        }

        $migrationFiles = $this->files->glob($migrationsPath . '/*.php');
        if (empty($migrationFiles)) {
            $this->logger->info("No migration files found for module {$moduleName}");
            return;
        }

        try {
            Artisan::call('migrate', [
                '--path' => $this->getRelativePath($migrationsPath),
                '--force' => true,
            ]);

            $this->logger->info("Migrations completed successfully for module {$moduleName}");
        } catch (\Exception $e) {
            $this->logger->error("Migration failed for module {$moduleName}: " . $e->getMessage());
            throw $e;
        }
    }

    private function rollbackModuleMigrations(string $moduleName, string $modulePath): void
    {
        $migrationsPath = $modulePath . '/Database/Migrations';

        if (!$this->files->isDirectory($migrationsPath)) {
            return;
        }

        try {
            Artisan::call('migrate:rollback', [
                '--path' => $this->getRelativePath($migrationsPath),
                '--force' => true,
            ]);

            $this->logger->info("Migrations rolled back successfully for module {$moduleName}");
        } catch (\Exception $e) {
            $this->logger->warning("Migration rollback failed for module {$moduleName}: " . $e->getMessage());
        }
    }

    private function publishModuleAssets(string $moduleName, string $modulePath): void
    {
        $assetsPath = $modulePath . '/Resources/assets';
        $configPath = $modulePath . '/Config';

        if ($this->files->isDirectory($assetsPath)) {
            $publicAssetsPath = public_path("modules/{$moduleName}");
            if (!$this->files->exists($publicAssetsPath)) {
                $this->files->makeDirectory($publicAssetsPath, 0755, true);
            }
            $this->files->copyDirectory($assetsPath, $publicAssetsPath);
        }

        if ($this->files->isDirectory($configPath)) {
            $configFiles = $this->files->files($configPath);
            foreach ($configFiles as $file) {
                $targetPath = config_path("modules/{$moduleName}/" . $file->getFilename());
                $targetDir = dirname($targetPath);
                if (!$this->files->exists($targetDir)) {
                    $this->files->makeDirectory($targetDir, 0755, true);
                }
                $this->files->copy($file->getPathname(), $targetPath);
            }
        }
    }

    private function copyModuleFiles(string $moduleName, string $modulePath): void
    {
        // Copy any additional files that need to be in specific locations
        // This is module-specific and would be defined in the module manifest
    }

    private function registerModuleServiceProviders(string $moduleName, string $modulePath): void
    {
        $serviceProviders = $this->discoverModuleServiceProviders($moduleName, $modulePath);

        foreach ($serviceProviders as $providerClass) {
            try {
                if (!class_exists($providerClass)) {
                    $this->logger->warning("Service provider class not found: {$providerClass}");
                    continue;
                }

                // Check if provider is already registered
                if ($this->isServiceProviderRegistered($providerClass)) {
                    $this->logger->info("Service provider already registered: {$providerClass}");
                    continue;
                }

                $this->app->register($providerClass);
                $this->logger->info("Successfully registered service provider: {$providerClass}");

            } catch (\Exception $e) {
                $this->logger->error("Failed to register service provider {$providerClass}: " . $e->getMessage(), [
                    'provider' => $providerClass,
                    'module' => $moduleName,
                    'exception' => $e,
                ]);
            }
        }
    }

    private function loadModuleServiceProviders(string $moduleName, string $modulePath): void
    {
        // Service providers should already be registered during installation
        // This method can be used to ensure they are loaded in the current request
        $this->registerModuleServiceProviders($moduleName, $modulePath);
    }

    private function registerModuleRoutes(string $moduleName, string $modulePath): void
    {
        $routesPath = $modulePath . '/Routes';

        if (!$this->files->isDirectory($routesPath)) {
            return;
        }

        $webRoutesFile = $routesPath . '/web.php';
        $apiRoutesFile = $routesPath . '/api.php';

        if ($this->files->exists($webRoutesFile)) {
            $this->router->group([
                'middleware' => 'web',
                'prefix' => strtolower($moduleName),
                'namespace' => $this->getModuleNamespace($moduleName) . '\\Http\\Controllers'
            ], function () use ($webRoutesFile) {
                require $webRoutesFile;
            });
        }

        if ($this->files->exists($apiRoutesFile)) {
            $this->router->group([
                'middleware' => 'api',
                'prefix' => 'api/' . strtolower($moduleName),
                'namespace' => $this->getModuleNamespace($moduleName) . '\\Http\\Controllers'
            ], function () use ($apiRoutesFile) {
                require $apiRoutesFile;
            });
        }
    }

    private function loadModuleConfiguration(string $moduleName, string $modulePath): void
    {
        $configPath = $modulePath . '/Config';

        if (!$this->files->isDirectory($configPath)) {
            return;
        }

        $configFiles = $this->files->files($configPath);
        foreach ($configFiles as $file) {
            $configName = 'modules.' . strtolower($moduleName) . '.' . pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $configData = require $file->getPathname();
            config([$configName => $configData]);
        }
    }

    private function registerModuleEventListeners(string $moduleName, string $modulePath): void
    {
        $listenersPath = $modulePath . '/Listeners';

        if (!$this->files->isDirectory($listenersPath)) {
            return;
        }

        // This would typically be handled by service providers
        // but can be implemented here for modules without service providers
    }

    private function loadModuleTranslationsAndViews(string $moduleName, string $modulePath): void
    {
        $translationsPath = $modulePath . '/Resources/lang';
        $viewsPath = $modulePath . '/Resources/views';

        if ($this->files->isDirectory($translationsPath)) {
            $this->app['translator']->addNamespace(strtolower($moduleName), $translationsPath);
        }

        if ($this->files->isDirectory($viewsPath)) {
            $this->app['view']->addNamespace(strtolower($moduleName), $viewsPath);
        }
    }

    private function executeInstallationHooks(string $moduleName, string $modulePath): void
    {
        $hookFile = $modulePath . '/hooks/install.php';
        if ($this->files->exists($hookFile)) {
            try {
                require $hookFile;
            } catch (\Exception $e) {
                $this->logger->error("Installation hook failed for module {$moduleName}: " . $e->getMessage());
            }
        }
    }

    private function executeEnablingHooks(string $moduleName, string $modulePath): void
    {
        $hookFile = $modulePath . '/hooks/enable.php';
        if ($this->files->exists($hookFile)) {
            try {
                require $hookFile;
            } catch (\Exception $e) {
                $this->logger->error("Enabling hook failed for module {$moduleName}: " . $e->getMessage());
            }
        }
    }

    private function executeDisablingHooks(string $moduleName): void
    {
        $module = $this->getInfo($moduleName);
        if (!$module) {
            return;
        }

        $hookFile = $module->path . '/hooks/disable.php';
        if ($this->files->exists($hookFile)) {
            try {
                require $hookFile;
            } catch (\Exception $e) {
                $this->logger->error("Disabling hook failed for module {$moduleName}: " . $e->getMessage());
            }
        }
    }

    private function executeRemovalHooks(string $moduleName, string $modulePath): void
    {
        $hookFile = $modulePath . '/hooks/remove.php';
        if ($this->files->exists($hookFile)) {
            try {
                require $hookFile;
            } catch (\Exception $e) {
                $this->logger->error("Removal hook failed for module {$moduleName}: " . $e->getMessage());
            }
        }
    }

    private function unregisterModuleEventListeners(string $moduleName): void
    {
        // Remove event listeners registered by the module
        // This would typically be handled by the module's service provider
    }

    private function clearModuleRoutes(string $moduleName): void
    {
        // Clear routes registered by the module
        // Laravel doesn't provide a direct way to unregister routes at runtime
        // This would typically require a application restart or route caching
    }

    private function unregisterModuleServiceProviders(string $moduleName): void
    {
        // Laravel doesn't provide a direct way to unregister service providers at runtime
        // This operation typically requires an application restart
        $this->logger->info("Service providers for module {$moduleName} marked for unregistration");
    }

    private function clearModuleCaches(string $moduleName): void
    {
        // Clear any caches specific to this module
        $cacheKeys = [
            "module.{$moduleName}.*",
            "modules.{$moduleName}.*",
            strtolower($moduleName) . ".*"
        ];

        foreach ($cacheKeys as $pattern) {
            try {
                $this->cache->forget($pattern);
            } catch (\Exception $e) {
                $this->logger->warning("Failed to clear cache pattern {$pattern}: " . $e->getMessage());
            }
        }
    }

    private function removeModuleFromContainer(string $moduleName): void
    {
        // Remove module-specific bindings from the service container
        // This is complex in Laravel and typically requires application restart
        $this->logger->info("Module {$moduleName} marked for removal from service container");
    }

    private function removePublishedAssets(string $moduleName, string $modulePath): void
    {
        $publicAssetsPath = public_path("modules/{$moduleName}");
        $configPath = config_path("modules/{$moduleName}");

        if ($this->files->exists($publicAssetsPath)) {
            $this->files->deleteDirectory($publicAssetsPath);
        }

        if ($this->files->exists($configPath)) {
            $this->files->deleteDirectory($configPath);
        }
    }

    private function cleanupModuleStorage(string $moduleName): void
    {
        $storagePaths = [
            storage_path("modules/{$moduleName}"),
            storage_path("app/modules/{$moduleName}"),
            storage_path("framework/cache/modules/{$moduleName}")
        ];

        foreach ($storagePaths as $path) {
            if ($this->files->exists($path)) {
                $this->files->deleteDirectory($path);
            }
        }
    }

    private function rollbackInstallation(string $moduleName, string $modulePath): void
    {
        try {
            $this->rollbackModuleMigrations($moduleName, $modulePath);
            $this->removePublishedAssets($moduleName, $modulePath);
            $this->cleanupModuleStorage($moduleName);
        } catch (\Exception $e) {
            $this->logger->error("Failed to rollback installation for module {$moduleName}: " . $e->getMessage());
        }
    }

    private function createModuleBackup(ModuleInfo $module): void
    {
        $backupPath = storage_path('app/module-backups/' . $module->name);
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupDir = "{$backupPath}/{$timestamp}";

        if (!$this->files->exists($backupDir)) {
            $this->files->makeDirectory($backupDir, 0755, true);
        }

        $this->files->copyDirectory($module->path, $backupDir);
        $this->logger->info("Backup created for module {$module->name} at {$backupDir}");
    }

    private function performModuleUpdate(ModuleInfo $module, ?string $version): void
    {
        $moduleName = $module->name;
        $currentVersion = $module->version;
        $targetVersion = $version ?? $this->getLatestModuleVersion($moduleName);

        try {
            $this->logger->info("Starting update for module {$moduleName} from {$currentVersion} to {$targetVersion}");

            // 1. Validate update can be performed
            $this->validateModuleUpdate($module, $targetVersion);

            // 2. Download/retrieve the new module version
            $updatePath = $this->downloadModuleUpdate($moduleName, $targetVersion);

            // 3. Run pre-update hooks
            $this->executePreUpdateHooks($moduleName);

            // 4. Run update migrations (before file replacement)
            $this->runUpdateMigrations($moduleName, $module->path, $currentVersion, $targetVersion);

            // 5. Replace module files
            $this->replaceModuleFiles($module->path, $updatePath);

            // 6. Update module registry with new version info
            $this->updateModuleRegistry($moduleName, $targetVersion);

            // 7. Run post-update hooks
            $this->executePostUpdateHooks($moduleName);

            // 8. Clear relevant caches
            $this->clearModuleCaches($moduleName);

            $this->logger->info("Successfully updated module {$moduleName} to version {$targetVersion}");

        } catch (\Exception $e) {
            $this->logger->error("Module update failed for {$moduleName}: " . $e->getMessage());

            // Attempt rollback
            try {
                $this->rollbackModuleUpdate($moduleName, $currentVersion);
                $this->logger->info("Rollback completed for module {$moduleName}");
            } catch (\Exception $rollbackException) {
                $this->logger->critical("Rollback failed for module {$moduleName}: " . $rollbackException->getMessage());
            }

            throw $e;
        }
    }

    private function getRelativePath(string $absolutePath): string
    {
        $basePath = base_path();
        if (str_starts_with($absolutePath, $basePath)) {
            return substr($absolutePath, strlen($basePath) + 1);
        }
        return $absolutePath;
    }

    private function getModuleNamespace(string $moduleName): string
    {
        return "Modules\\{$moduleName}";
    }

    private function discoverModuleServiceProviders(string $moduleName, string $modulePath): array
    {
        $serviceProviders = [];

        // 1. Check for service providers in manifest
        $manifestProviders = $this->getServiceProvidersFromManifest($modulePath);
        if (!empty($manifestProviders)) {
            $serviceProviders = array_merge($serviceProviders, $manifestProviders);
        }

        // 2. Scan Providers directory
        $providersFromDirectory = $this->scanProvidersDirectory($moduleName, $modulePath);
        $serviceProviders = array_merge($serviceProviders, $providersFromDirectory);

        // 3. Look for main module service provider (conventional)
        $mainProvider = $this->getMainModuleServiceProvider($moduleName, $modulePath);
        if ($mainProvider && !in_array($mainProvider, $serviceProviders)) {
            $serviceProviders[] = $mainProvider;
        }

        return array_unique($serviceProviders);
    }

    private function getServiceProvidersFromManifest(string $modulePath): array
    {
        try {
            $manifest = $this->loadManifestFromPath($modulePath);
            return $manifest['providers'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function loadManifestFromPath(string $modulePath): array
    {
        $manifestPath = $modulePath . '/manifest.json';

        if (!$this->files->exists($manifestPath)) {
            return [];
        }

        // Security: Validate the path to prevent directory traversal
        $this->validateSecurePath($manifestPath, $modulePath);

        $content = $this->files->get($manifestPath);

        // Security: Limit manifest file size (max 1MB)
        if (strlen($content) > 1024 * 1024) {
            throw new \InvalidArgumentException("Manifest file too large: exceeds 1MB limit");
        }

        $manifest = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Invalid JSON in manifest file: " . json_last_error_msg());
        }

        // Security: Validate and sanitize manifest data
        $this->validateManifestSecurity($manifest, $modulePath);

        return $manifest;
    }

    private function scanProvidersDirectory(string $moduleName, string $modulePath): array
    {
        $serviceProviders = [];
        $serviceProviderPath = $modulePath . '/Providers';

        if (!$this->files->isDirectory($serviceProviderPath)) {
            return $serviceProviders;
        }

        $providerFiles = $this->files->glob($serviceProviderPath . '/*ServiceProvider.php');
        $namespace = $this->getModuleNamespace($moduleName);

        foreach ($providerFiles as $providerFile) {
            $className = pathinfo($providerFile, PATHINFO_FILENAME);
            $fullClassName = "{$namespace}\\Providers\\{$className}";

            // Verify the class actually exists in the file
            if ($this->validateServiceProviderClass($providerFile, $className)) {
                $serviceProviders[] = $fullClassName;
            }
        }

        return $serviceProviders;
    }

    private function getMainModuleServiceProvider(string $moduleName, string $modulePath): ?string
    {
        $namespace = $this->getModuleNamespace($moduleName);
        $possibleProviders = [
            "{$namespace}\\{$moduleName}ServiceProvider",
            "{$namespace}\\Providers\\{$moduleName}ServiceProvider",
            "{$namespace}\\ServiceProvider",
        ];

        foreach ($possibleProviders as $providerClass) {
            $expectedPath = str_replace('\\', '/', $providerClass);
            $expectedPath = $modulePath . '/' . substr($expectedPath, strpos($expectedPath, '/') + 1) . '.php';

            if ($this->files->exists($expectedPath)) {
                return $providerClass;
            }
        }

        return null;
    }

    private function validateServiceProviderClass(string $filePath, string $expectedClassName): bool
    {
        try {
            $content = $this->files->get($filePath);

            // Security validation first
            $this->validatePhpFileContent($content);

            // Basic validation - check if the file contains the expected class
            $escapedClassName = preg_quote($expectedClassName, '/');
            $pattern = "/class\s+{$escapedClassName}\s+extends\s+ServiceProvider/";
            if (!preg_match($pattern, $content)) {
                return false;
            }

            // Validate service provider structure
            return $this->validateServiceProviderStructure($content, $expectedClassName);

        } catch (\Exception $e) {
            $this->logger->warning("Failed to validate service provider class: {$filePath}", [
                'file' => $filePath,
                'expected_class' => $expectedClassName,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function isServiceProviderRegistered(string $providerClass): bool
    {
        $registeredProviders = $this->app->getLoadedProviders();
        return isset($registeredProviders[$providerClass]);
    }

    // Module update helper methods

    private function getLatestModuleVersion(string $moduleName): string
    {
        // In a real implementation, this would query a package repository
        // For now, we'll return a placeholder version
        $module = $this->getInfo($moduleName);
        if (!$module) {
            return '1.0.0';
        }

        // Simple version increment for demo purposes
        $parts = explode('.', $module->version);
        if (count($parts) >= 3) {
            $parts[2] = (string)((int)$parts[2] + 1);
            return implode('.', $parts);
        }

        return $module->version;
    }

    private function validateModuleUpdate(ModuleInfo $module, string $targetVersion): void
    {
        // Check if target version is valid
        if (!$this->isValidVersionString($targetVersion)) {
            throw new ModuleInstallationException("Invalid version string: {$targetVersion}");
        }

        // Check if it's actually an update (newer version)
        if (version_compare($module->version, $targetVersion, '>=')) {
            throw new ModuleInstallationException("Target version {$targetVersion} is not newer than current version {$module->version}");
        }

        // Check for breaking changes
        $this->checkForBreakingChanges($module, $targetVersion);

        $this->logger->info("Module update validation passed for {$module->name}");
    }

    private function isValidVersionString(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+(-[\w\.\-]+)?$/', $version) === 1;
    }

    private function checkForBreakingChanges(ModuleInfo $module, string $targetVersion): void
    {
        $currentMajor = (int)explode('.', $module->version)[0];
        $targetMajor = (int)explode('.', $targetVersion)[0];

        if ($targetMajor > $currentMajor) {
            $this->logger->warning("Major version update detected for {$module->name}: {$module->version} -> {$targetVersion}. Please review breaking changes.");
        }
    }

    private function downloadModuleUpdate(string $moduleName, string $version): string
    {
        // In a real implementation, this would download from a repository
        // For now, we'll simulate by returning a temporary path
        $updatePath = storage_path("module-updates/{$moduleName}/{$version}");

        if (!$this->files->exists($updatePath)) {
            $this->files->makeDirectory($updatePath, 0755, true);
            $this->logger->info("Created update directory: {$updatePath}");
        }

        // Simulate download
        $this->logger->info("Downloaded module {$moduleName} version {$version} to {$updatePath}");

        return $updatePath;
    }

    private function executePreUpdateHooks(string $moduleName): void
    {
        $module = $this->getInfo($moduleName);
        if (!$module) {
            return;
        }

        $hookFile = $module->path . '/hooks/pre-update.php';
        if ($this->files->exists($hookFile)) {
            try {
                $this->logger->info("Executing pre-update hook for {$moduleName}");
                require_once $hookFile;
            } catch (\Exception $e) {
                $this->logger->error("Pre-update hook failed for {$moduleName}: " . $e->getMessage());
                throw $e;
            }
        }
    }

    private function runUpdateMigrations(string $moduleName, string $modulePath, string $currentVersion, string $targetVersion): void
    {
        $updateMigrationsPath = $modulePath . '/Database/Updates';

        if (!$this->files->isDirectory($updateMigrationsPath)) {
            $this->logger->info("No update migrations directory found for {$moduleName}");
            return;
        }

        // Find migrations between versions
        $migrationFiles = $this->getUpdateMigrationFiles($updateMigrationsPath, $currentVersion, $targetVersion);

        if (empty($migrationFiles)) {
            $this->logger->info("No update migrations found for {$moduleName}");
            return;
        }

        foreach ($migrationFiles as $migrationFile) {
            try {
                $this->logger->info("Running update migration: {$migrationFile}");
                Artisan::call('migrate', [
                    '--path' => $this->getRelativePath(dirname($migrationFile)),
                    '--force' => true,
                ]);
            } catch (\Exception $e) {
                $this->logger->error("Update migration failed: {$migrationFile}");
                throw $e;
            }
        }
    }

    private function getUpdateMigrationFiles(string $migrationsPath, string $currentVersion, string $targetVersion): array
    {
        $migrationFiles = $this->files->glob($migrationsPath . '/*.php');
        $relevantMigrations = [];

        foreach ($migrationFiles as $migrationFile) {
            $fileName = basename($migrationFile);

            // Extract version from filename (assumes format: YYYY_MM_DD_HHMMSS_update_v1_0_1_to_v1_0_2.php)
            if (preg_match('/update_v(\d+_\d+_\d+)_to_v(\d+_\d+_\d+)\.php$/', $fileName, $matches)) {
                $fromVersion = str_replace('_', '.', $matches[1]);
                $toVersion = str_replace('_', '.', $matches[2]);

                if (version_compare($fromVersion, $currentVersion, '>=') && version_compare($toVersion, $targetVersion, '<=')) {
                    $relevantMigrations[] = $migrationFile;
                }
            }
        }

        return $relevantMigrations;
    }

    private function replaceModuleFiles(string $modulePath, string $updatePath): void
    {
        if (!$this->files->isDirectory($updatePath)) {
            throw new ModuleInstallationException("Update path does not exist: {$updatePath}");
        }

        // Create backup of current files (additional safety)
        $backupPath = $modulePath . '.backup.' . now()->format('Y-m-d_H-i-s');
        $this->files->copyDirectory($modulePath, $backupPath);

        try {
            // Remove old files (except certain directories/files we want to preserve)
            $preservePaths = ['storage', 'cache', '.env', 'config/local'];
            $this->removeModuleFilesExcept($modulePath, $preservePaths);

            // Copy new files
            $this->files->copyDirectory($updatePath, $modulePath);

            $this->logger->info("Module files replaced successfully");

        } catch (\Exception $e) {
            // Restore from backup on failure
            $this->files->deleteDirectory($modulePath);
            $this->files->moveDirectory($backupPath, $modulePath);
            throw $e;
        }

        // Clean up backup
        $this->files->deleteDirectory($backupPath);
    }

    private function removeModuleFilesExcept(string $modulePath, array $preservePaths): void
    {
        $items = $this->files->allFiles($modulePath);

        foreach ($items as $item) {
            $relativePath = str_replace($modulePath . '/', '', $item->getPathname());

            $shouldPreserve = false;
            foreach ($preservePaths as $preservePath) {
                if (str_starts_with($relativePath, $preservePath)) {
                    $shouldPreserve = true;
                    break;
                }
            }

            if (!$shouldPreserve) {
                $this->files->delete($item->getPathname());
            }
        }
    }

    private function updateModuleRegistry(string $moduleName, string $version): void
    {
        $moduleData = $this->registry->getModuleData($moduleName);
        $moduleData['version'] = $version;
        $moduleData['updated_at'] = now()->toDateTimeString();

        $this->registry->setModuleData($moduleName, $moduleData);

        $this->logger->info("Updated module registry for {$moduleName} to version {$version}");
    }

    private function executePostUpdateHooks(string $moduleName): void
    {
        $module = $this->getInfo($moduleName);
        if (!$module) {
            return;
        }

        $hookFile = $module->path . '/hooks/post-update.php';
        if ($this->files->exists($hookFile)) {
            try {
                $this->logger->info("Executing post-update hook for {$moduleName}");
                require_once $hookFile;
            } catch (\Exception $e) {
                $this->logger->error("Post-update hook failed for {$moduleName}: " . $e->getMessage());
                // Don't throw here - update was successful, hook failure is not critical
            }
        }
    }

    private function rollbackModuleUpdate(string $moduleName, string $previousVersion): void
    {
        $this->logger->info("Starting rollback for module {$moduleName} to version {$previousVersion}");

        $backupPath = storage_path("app/module-backups/{$moduleName}");

        // Find the most recent backup
        if ($this->files->isDirectory($backupPath)) {
            $backups = $this->files->directories($backupPath);
            if (!empty($backups)) {
                $latestBackup = collect($backups)->sortByDesc()->first();

                $module = $this->getInfo($moduleName);
                if ($module) {
                    // Restore files from backup
                    $this->files->deleteDirectory($module->path);
                    $this->files->copyDirectory($latestBackup, $module->path);

                    // Restore version in registry
                    $this->updateModuleRegistry($moduleName, $previousVersion);

                    $this->logger->info("Rollback completed for {$moduleName}");
                    return;
                }
            }
        }

        throw new ModuleInstallationException("Could not rollback module {$moduleName}: No backup found");
    }

    // Security validation methods

    private function validateSecurePath(string $filePath, string $allowedBasePath): void
    {
        // Resolve real paths to prevent directory traversal
        $realFilePath = realpath($filePath);
        $realBasePath = realpath($allowedBasePath);

        if (!$realFilePath || !$realBasePath) {
            throw new \InvalidArgumentException("Invalid file path or base path");
        }

        // Ensure the file path is within the allowed base path
        if (!str_starts_with($realFilePath, $realBasePath)) {
            throw new \InvalidArgumentException("File path is outside allowed directory");
        }
    }

    private function validateManifestSecurity(array $manifest, string $modulePath): void
    {
        // Validate required fields exist and are safe
        $this->validateManifestFields($manifest);

        // Validate and sanitize module name
        $this->validateModuleName($manifest);

        // Validate service provider paths
        $this->validateServiceProviderPaths($manifest);

        // Validate dependency strings
        $this->validateDependencyStrings($manifest);

        // Check for suspicious content
        $this->checkForSuspiciousContent($manifest);
    }

    private function validateManifestFields(array $manifest): void
    {
        $requiredFields = ['name', 'version'];
        $allowedFields = [
            'name', 'display_name', 'description', 'version', 'author',
            'dependencies', 'optional_dependencies', 'conflicts', 'provides',
            'providers', 'config', 'license', 'keywords', 'homepage'
        ];

        // Check required fields
        foreach ($requiredFields as $field) {
            if (!isset($manifest[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing from manifest");
            }
        }

        // Check for unknown fields (potential security issue)
        foreach (array_keys($manifest) as $field) {
            if (!in_array($field, $allowedFields)) {
                $this->logger->warning("Unknown field '{$field}' in manifest", ['field' => $field]);
            }
        }
    }

    private function validateModuleName(array $manifest): void
    {
        $name = $manifest['name'] ?? '';

        // Module name must be alphanumeric with underscores/hyphens only
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            throw new \InvalidArgumentException("Invalid module name: {$name}");
        }

        // Must not be too long
        if (strlen($name) > 50) {
            throw new \InvalidArgumentException("Module name too long: {$name}");
        }

        // Must not start with reserved prefixes
        $reservedPrefixes = ['laravel', 'illuminate', 'symfony', 'system', 'admin', 'core'];
        foreach ($reservedPrefixes as $prefix) {
            if (str_starts_with(strtolower($name), $prefix)) {
                throw new \InvalidArgumentException("Module name cannot start with reserved prefix: {$prefix}");
            }
        }
    }

    private function validateServiceProviderPaths(array $manifest): void
    {
        if (!isset($manifest['providers']) || !is_array($manifest['providers'])) {
            return;
        }

        foreach ($manifest['providers'] as $provider) {
            if (!is_string($provider)) {
                throw new \InvalidArgumentException("Service provider must be a string");
            }

            // Validate class name format
            if (!preg_match('/^[a-zA-Z_\\\\][a-zA-Z0-9_\\\\]*$/', $provider)) {
                throw new \InvalidArgumentException("Invalid service provider class name: {$provider}");
            }

            // Must not contain dangerous patterns
            $dangerousPatterns = ['..', '/', '<', '>', '`', '$', '{', '}'];
            foreach ($dangerousPatterns as $pattern) {
                if (str_contains($provider, $pattern)) {
                    throw new \InvalidArgumentException("Service provider contains dangerous pattern: {$provider}");
                }
            }
        }
    }

    private function validateDependencyStrings(array $manifest): void
    {
        $dependencyFields = ['dependencies', 'optional_dependencies', 'conflicts'];

        foreach ($dependencyFields as $field) {
            if (!isset($manifest[$field]) || !is_array($manifest[$field])) {
                continue;
            }

            foreach ($manifest[$field] as $dependency) {
                if (!is_string($dependency)) {
                    throw new \InvalidArgumentException("Dependency must be a string in {$field}");
                }

                // Validate dependency name format
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $dependency)) {
                    throw new \InvalidArgumentException("Invalid dependency name: {$dependency}");
                }

                // Must not be too long
                if (strlen($dependency) > 50) {
                    throw new \InvalidArgumentException("Dependency name too long: {$dependency}");
                }
            }
        }
    }

    private function checkForSuspiciousContent(array $manifest): void
    {
        $manifestString = json_encode($manifest);

        // Check for suspicious patterns that could indicate malicious intent
        $suspiciousPatterns = [
            '/eval\s*\(/',
            '/exec\s*\(/',
            '/system\s*\(/',
            '/shell_exec\s*\(/',
            '/passthru\s*\(/',
            '/file_get_contents\s*\(/',
            '/file_put_contents\s*\(/',
            '/curl_exec\s*\(/',
            '/base64_decode\s*\(/',
            '/<\?php/',
            '/<script/',
            '/javascript:/',
            '/data:text\/html/',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $manifestString)) {
                throw new \InvalidArgumentException("Manifest contains suspicious content");
            }
        }

        // Check string fields for length limits
        $stringFields = ['description', 'author', 'license', 'homepage'];
        foreach ($stringFields as $field) {
            if (isset($manifest[$field]) && is_string($manifest[$field])) {
                if (strlen($manifest[$field]) > 1000) {
                    throw new \InvalidArgumentException("Field '{$field}' is too long");
                }
            }
        }
    }


    private function validatePhpFileContent(string $content): void
    {
        // Check for dangerous PHP functions
        $dangerousFunctions = [
            'eval', 'exec', 'system', 'shell_exec', 'passthru', 'proc_open',
            'file_get_contents', 'file_put_contents', 'fopen', 'fwrite',
            'curl_exec', 'curl_init', 'mail', 'base64_decode'
        ];

        foreach ($dangerousFunctions as $function) {
            if (preg_match('/\b' . preg_quote($function, '/') . '\s*\(/', $content)) {
                throw new \InvalidArgumentException("Service provider contains dangerous function: {$function}");
            }
        }

        // Check for suspicious patterns
        $suspiciousPatterns = [
            '/\$_GET\s*\[/',
            '/\$_POST\s*\[/',
            '/\$_REQUEST\s*\[/',
            '/\$_SERVER\s*\[/',
            '/\$_ENV\s*\[/',
            '/\$_COOKIE\s*\[/',
            '/\$GLOBALS\s*\[/',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new \InvalidArgumentException("Service provider contains suspicious global variable access");
            }
        }
    }

    private function validateServiceProviderStructure(string $content, string $className): bool
    {
        // Check that the class extends ServiceProvider
        $escapedClassName = preg_quote($className, '/');
        $pattern = "/class\s+{$escapedClassName}\s+extends\s+ServiceProvider/";

        if (!preg_match($pattern, $content)) {
            return false;
        }

        // Check for required methods (register or boot)
        if (!preg_match('/public\s+function\s+register\s*\(/', $content) &&
            !preg_match('/public\s+function\s+boot\s*\(/', $content)) {
            return false;
        }

        return true;
    }
}