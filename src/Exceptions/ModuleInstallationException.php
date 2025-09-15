<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Exceptions;

use Exception;

class ModuleInstallationException extends Exception
{
    public static function cannotInstall(string $moduleName, string $reason): self
    {
        return new self("Cannot install module '{$moduleName}': {$reason}");
    }

    public static function cannotEnable(string $moduleName, string $reason): self
    {
        return new self("Cannot enable module '{$moduleName}': {$reason}");
    }

    public static function cannotDisable(string $moduleName, string $reason): self
    {
        return new self("Cannot disable module '{$moduleName}': {$reason}");
    }

    public static function cannotRemove(string $moduleName, string $reason): self
    {
        return new self("Cannot remove module '{$moduleName}': {$reason}");
    }

    public static function cannotUpdate(string $moduleName, string $reason): self
    {
        return new self("Cannot update module '{$moduleName}': {$reason}");
    }
}