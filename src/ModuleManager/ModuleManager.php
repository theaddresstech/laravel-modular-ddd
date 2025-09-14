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
        private ModuleRegistry $registry
    ) {}

    public function list(): Collection
    {
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

            $this->validateDependencies($moduleName);

            // Install dependencies first
            $dependencies = $this->getDependencies($moduleName);
            foreach ($dependencies as $dependency) {
                if (!$this->isInstalled($dependency)) {
                    $this->install($dependency);
                }
            }

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
        // Implementation for module updates
        throw new \BadMethodCallException('Update functionality not yet implemented');
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

    public function clearCache(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    public function rebuildCache(): void
    {
        $this->clearCache();
        $this->list();
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
                'namespace' => $module->namespace,
                'dependencies' => $module->dependencies,
            ];
        })->values()->toArray();
    }

    private function performInstallation(ModuleInfo $module): void
    {
        // Run migrations, copy assets, etc.
        // This would be implemented based on specific requirements
    }

    private function performEnabling(string $moduleName): void
    {
        // Load service providers, register routes, etc.
        // This would be implemented based on specific requirements
    }

    private function performDisabling(string $moduleName): void
    {
        // Unload service providers, clear routes, etc.
        // This would be implemented based on specific requirements
    }

    private function performRemoval(string $moduleName): void
    {
        // Rollback migrations, remove files, etc.
        // This would be implemented based on specific requirements
    }
}