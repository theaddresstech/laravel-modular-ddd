<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\ModuleManager;

use TaiCrm\LaravelModularDdd\Contracts\ModuleDiscoveryInterface;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleState;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Factory as ValidationFactory;

class ModuleDiscovery implements ModuleDiscoveryInterface
{
    public function __construct(
        private Filesystem $files,
        private ValidationFactory $validator,
        private ModuleRegistry $registry,
        private string $modulesPath
    ) {}

    public function discover(): Collection
    {
        $modules = collect();

        if (!$this->files->isDirectory($this->modulesPath)) {
            return $modules;
        }

        $directories = $this->files->directories($this->modulesPath);

        foreach ($directories as $directory) {
            $moduleName = basename($directory);
            $moduleInfo = $this->findModule($moduleName);

            if ($moduleInfo) {
                $modules->put($moduleName, $moduleInfo);
            }
        }

        return $modules;
    }

    public function findModule(string $moduleName): ?ModuleInfo
    {
        $modulePath = $this->getModulePath($moduleName);

        if (!$this->files->isDirectory($modulePath)) {
            return null;
        }

        if (!$this->validateModuleStructure($modulePath)) {
            return null;
        }

        try {
            $manifest = $this->loadManifest($modulePath);
            $state = $this->registry->getModuleState($moduleName);

            return ModuleInfo::fromArray([
                'name' => $moduleName,
                'display_name' => $manifest['display_name'] ?? $moduleName,
                'description' => $manifest['description'] ?? '',
                'version' => $manifest['version'] ?? '1.0.0',
                'author' => $manifest['author'] ?? '',
                'dependencies' => $manifest['dependencies'] ?? [],
                'optional_dependencies' => $manifest['optional_dependencies'] ?? [],
                'conflicts' => $manifest['conflicts'] ?? [],
                'provides' => $manifest['provides'] ?? [],
                'path' => $modulePath,
                'state' => $state,
                'config' => $manifest['config'] ?? [],
            ]);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getModulesPath(): string
    {
        return $this->modulesPath;
    }

    public function validateModuleStructure(string $modulePath): bool
    {
        $requiredPaths = [
            'manifest.json',
            'Domain',
            'Application',
            'Infrastructure',
            'Presentation',
        ];

        foreach ($requiredPaths as $path) {
            $fullPath = $modulePath . '/' . $path;

            if ($path === 'manifest.json') {
                if (!$this->files->isFile($fullPath)) {
                    return false;
                }
            } else {
                if (!$this->files->isDirectory($fullPath)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function loadManifest(string $modulePath): array
    {
        $manifestPath = $modulePath . '/manifest.json';

        if (!$this->files->exists($manifestPath)) {
            throw new \InvalidArgumentException("Manifest file not found at: {$manifestPath}");
        }

        $content = $this->files->get($manifestPath);
        $manifest = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Invalid JSON in manifest file: " . json_last_error_msg());
        }

        $this->validateManifest($manifest);

        return $manifest;
    }

    private function validateManifest(array $manifest): void
    {
        $rules = [
            'name' => 'required|string|max:255',
            'display_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'version' => 'sometimes|string|max:50',
            'author' => 'sometimes|string|max:255',
            'dependencies' => 'sometimes|array',
            'dependencies.*' => 'string|max:255',
            'optional_dependencies' => 'sometimes|array',
            'optional_dependencies.*' => 'string|max:255',
            'conflicts' => 'sometimes|array',
            'conflicts.*' => 'string|max:255',
            'provides' => 'sometimes|array',
            'provides.services' => 'sometimes|array',
            'provides.services.*' => 'string|max:255',
            'provides.contracts' => 'sometimes|array',
            'provides.contracts.*' => 'string|max:255',
            'provides.events' => 'sometimes|array',
            'provides.events.*' => 'string|max:255',
            'config' => 'sometimes|array',
        ];

        $validator = $this->validator->make($manifest, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function getModulePath(string $moduleName): string
    {
        return $this->modulesPath . '/' . $moduleName;
    }
}