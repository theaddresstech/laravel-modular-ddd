<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Exceptions;

use Exception;

class DependencyException extends Exception
{
    public static function missingDependencies(string $moduleName, array $missing): self
    {
        $dependencies = implode(', ', $missing);
        return new self("Module '{$moduleName}' has missing dependencies: {$dependencies}");
    }

    public static function circularDependency(string $moduleName): self
    {
        return new self("Circular dependency detected for module '{$moduleName}'");
    }

    public static function conflictingModules(string $moduleName, array $conflicts): self
    {
        $conflictList = implode(', ', $conflicts);
        return new self("Module '{$moduleName}' conflicts with: {$conflictList}");
    }
}