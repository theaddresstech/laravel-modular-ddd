<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Communication\Contracts;

use Illuminate\Support\Collection;

interface ServiceRegistryInterface
{
    public function register(string $serviceName, string $implementation, string $module): void;

    public function unregister(string $serviceName, string $module): void;

    public function resolve(string $serviceName): ?object;

    public function exists(string $serviceName): bool;

    public function getImplementation(string $serviceName): ?string;

    public function getModule(string $serviceName): ?string;

    public function getServices(): Collection;

    public function getServicesByModule(string $module): Collection;

    public function clearModule(string $module): void;

    public function clear(): void;
}