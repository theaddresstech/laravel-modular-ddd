<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Contracts;

use Illuminate\Support\Collection;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;

interface ModuleDiscoveryInterface
{
    public function discover(): Collection;

    public function findModule(string $moduleName): ?ModuleInfo;

    public function getModulesPath(): string;

    public function validateModuleStructure(string $modulePath): bool;

    public function loadManifest(string $modulePath): array;
}
