<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\ModuleManager;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleState;

class ModuleRegistry
{
    private const REGISTRY_FILE = 'modules.json';
    private const CACHE_KEY = 'modular_ddd_registry';
    private array $registry = [];

    public function __construct(
        private Filesystem $files,
        private CacheRepository $cache,
        private string $storagePath,
    ) {
        $this->loadRegistry();
    }

    public function isInstalled(string $moduleName): bool
    {
        return isset($this->registry[$moduleName])
               && !$this->getModuleState($moduleName)->equals(ModuleState::NotInstalled);
    }

    public function isEnabled(string $moduleName): bool
    {
        return $this->getModuleState($moduleName)->equals(ModuleState::Enabled);
    }

    public function getModuleState(string $moduleName): ModuleState
    {
        return ModuleState::from($this->registry[$moduleName]['state'] ?? 'not_installed');
    }

    public function setModuleState(string $moduleName, ModuleState $state): void
    {
        if (!isset($this->registry[$moduleName])) {
            $this->registry[$moduleName] = [
                'name' => $moduleName,
                'installed_at' => now()->toDateTimeString(),
            ];
        }

        $this->registry[$moduleName]['state'] = $state->value;
        $this->registry[$moduleName]['updated_at'] = now()->toDateTimeString();

        $this->saveRegistry();
        $this->cache->forget(self::CACHE_KEY);
    }

    public function getModuleData(string $moduleName): array
    {
        return $this->registry[$moduleName] ?? [];
    }

    public function setModuleData(string $moduleName, array $data): void
    {
        if (!isset($this->registry[$moduleName])) {
            $this->registry[$moduleName] = [
                'name' => $moduleName,
                'installed_at' => now()->toDateTimeString(),
            ];
        }

        $this->registry[$moduleName] = array_merge($this->registry[$moduleName], $data);
        $this->registry[$moduleName]['updated_at'] = now()->toDateTimeString();

        $this->saveRegistry();
        $this->cache->forget(self::CACHE_KEY);
    }

    public function removeModule(string $moduleName): void
    {
        unset($this->registry[$moduleName]);
        $this->saveRegistry();
        $this->cache->forget(self::CACHE_KEY);
    }

    public function getAllModules(): array
    {
        return $this->registry;
    }

    private function loadRegistry(): void
    {
        $this->registry = $this->cache->remember(self::CACHE_KEY, 3600, function () {
            $registryPath = $this->getRegistryPath();

            if (!$this->files->exists($registryPath)) {
                return [];
            }

            $content = $this->files->get($registryPath);
            $data = json_decode($content, true);

            return is_array($data) ? $data : [];
        });
    }

    private function saveRegistry(): void
    {
        $registryPath = $this->getRegistryPath();
        $directory = dirname($registryPath);

        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0o755, true);
        }

        $this->files->put(
            $registryPath,
            json_encode($this->registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    private function getRegistryPath(): string
    {
        return $this->storagePath . '/' . self::REGISTRY_FILE;
    }
}
