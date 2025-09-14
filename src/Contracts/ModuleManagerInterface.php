<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Contracts;

use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleState;
use Illuminate\Support\Collection;

interface ModuleManagerInterface
{
    public function list(): Collection;

    public function install(string $moduleName): bool;

    public function enable(string $moduleName): bool;

    public function disable(string $moduleName): bool;

    public function remove(string $moduleName): bool;

    public function update(string $moduleName, ?string $version = null): bool;

    public function isInstalled(string $moduleName): bool;

    public function isEnabled(string $moduleName): bool;

    public function getInfo(string $moduleName): ?ModuleInfo;

    public function getState(string $moduleName): ModuleState;

    public function getDependencies(string $moduleName): Collection;

    public function getDependents(string $moduleName): Collection;

    public function validateDependencies(string $moduleName): bool;

    public function clearCache(): void;

    public function rebuildCache(): void;

    public function getActiveModules(): array;
}