<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Health\Contracts;

use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;

interface HealthCheckInterface
{
    public function check(ModuleInfo $module): array;

    public function getName(): string;

    public function getDescription(): string;
}