<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Contracts;

use Illuminate\Support\Collection;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;

interface DependencyResolverInterface
{
    public function resolve(Collection $modules): Collection;

    public function validateDependencies(ModuleInfo $module, Collection $availableModules): array;

    public function hasCircularDependency(ModuleInfo $module, Collection $modules): bool;

    public function getInstallOrder(Collection $modules): Collection;

    public function canRemove(string $moduleName, Collection $modules): bool;

    public function getDependents(string $moduleName, Collection $modules): Collection;
}
