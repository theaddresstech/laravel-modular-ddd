<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\ModuleManager;

use TaiCrm\LaravelModularDdd\Contracts\DependencyResolverInterface;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;
use Illuminate\Support\Collection;

class DependencyResolver implements DependencyResolverInterface
{
    public function resolve(Collection $modules): Collection
    {
        return $this->getInstallOrder($modules);
    }

    public function validateDependencies(ModuleInfo $module, Collection $availableModules): array
    {
        $missing = [];
        $available = $availableModules->pluck('name')->toArray();

        foreach ($module->dependencies as $dependency) {
            if (!in_array($dependency, $available, true)) {
                $missing[] = $dependency;
            }
        }

        // Check for conflicts
        foreach ($module->conflicts as $conflict) {
            if (in_array($conflict, $available, true)) {
                $missing[] = "conflicts with {$conflict}";
            }
        }

        return $missing;
    }

    public function hasCircularDependency(ModuleInfo $module, Collection $modules): bool
    {
        $visited = [];
        $recursionStack = [];

        return $this->detectCycle($module->name, $modules, $visited, $recursionStack);
    }

    public function getInstallOrder(Collection $modules): Collection
    {
        $sorted = collect();
        $visiting = [];
        $visited = [];

        foreach ($modules as $module) {
            if (!isset($visited[$module->name])) {
                $this->topologicalSort($module, $modules, $visited, $visiting, $sorted);
            }
        }

        return $sorted;
    }

    public function canRemove(string $moduleName, Collection $modules): bool
    {
        $dependents = $this->getDependents($moduleName, $modules);

        foreach ($dependents as $dependent) {
            $dependentModule = $modules->firstWhere('name', $dependent);
            if ($dependentModule && $dependentModule->isEnabled()) {
                return false;
            }
        }

        return true;
    }

    public function getDependents(string $moduleName, Collection $modules): Collection
    {
        return $modules->filter(function (ModuleInfo $module) use ($moduleName) {
            return $module->hasDependency($moduleName);
        })->pluck('name');
    }

    private function detectCycle(
        string $moduleName,
        Collection $modules,
        array &$visited,
        array &$recursionStack
    ): bool {
        $visited[$moduleName] = true;
        $recursionStack[$moduleName] = true;

        $module = $modules->firstWhere('name', $moduleName);
        if (!$module) {
            return false;
        }

        foreach ($module->dependencies as $dependency) {
            if (!isset($visited[$dependency])) {
                if ($this->detectCycle($dependency, $modules, $visited, $recursionStack)) {
                    return true;
                }
            } elseif (isset($recursionStack[$dependency]) && $recursionStack[$dependency]) {
                return true;
            }
        }

        unset($recursionStack[$moduleName]);
        return false;
    }

    private function topologicalSort(
        ModuleInfo $module,
        Collection $modules,
        array &$visited,
        array &$visiting,
        Collection $sorted
    ): void {
        if (isset($visiting[$module->name])) {
            throw new \InvalidArgumentException("Circular dependency detected involving module: {$module->name}");
        }

        if (isset($visited[$module->name])) {
            return;
        }

        $visiting[$module->name] = true;

        foreach ($module->dependencies as $dependencyName) {
            $dependency = $modules->firstWhere('name', $dependencyName);
            if ($dependency) {
                $this->topologicalSort($dependency, $modules, $visited, $visiting, $sorted);
            }
        }

        unset($visiting[$module->name]);
        $visited[$module->name] = true;
        $sorted->push($module);
    }
}