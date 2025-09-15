<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Compilation\Contracts;

use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;
use Illuminate\Support\Collection;

/**
 * Interface for ultra-fast compiled module registry
 */
interface CompiledRegistryInterface
{
    /**
     * Get all compiled modules
     */
    public function getAllModules(): Collection;

    /**
     * Get modules by context (api, web, cli, etc.)
     */
    public function getModulesByContext(string $context): Collection;

    /**
     * Get module by name
     */
    public function getModule(string $name): ?ModuleInfo;

    /**
     * Get modules by loading wave
     */
    public function getModulesByWave(int $wave): Collection;

    /**
     * Get dependency graph
     */
    public function getDependencyGraph(): array;

    /**
     * Get service bindings for module
     */
    public function getServiceBindings(string $moduleName): array;

    /**
     * Get route manifest for module
     */
    public function getRouteManifest(string $moduleName): array;

    /**
     * Check if compiled registry is valid
     */
    public function isValid(): bool;

    /**
     * Get compilation metadata
     */
    public function getMetadata(): array;

    /**
     * Refresh from compiled files
     */
    public function refresh(): bool;
}