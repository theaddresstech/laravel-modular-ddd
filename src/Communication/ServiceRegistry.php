<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Communication;

use Exception;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use TaiCrm\LaravelModularDdd\Communication\Contracts\ServiceRegistryInterface;

class ServiceRegistry implements ServiceRegistryInterface
{
    private Collection $services;

    public function __construct(
        private Container $container,
        private LoggerInterface $logger,
    ) {
        $this->services = collect();
    }

    public function register(string $serviceName, string $implementation, string $module): void
    {
        $this->logger->info("Registering service: {$serviceName}", [
            'service' => $serviceName,
            'implementation' => $implementation,
            'module' => $module,
        ]);

        $this->services->put($serviceName, [
            'implementation' => $implementation,
            'module' => $module,
            'registered_at' => now(),
        ]);

        // Register in Laravel's container if not already bound
        if (!$this->container->bound($serviceName)) {
            $this->container->bind($serviceName, $implementation);
        }
    }

    public function unregister(string $serviceName, string $module): void
    {
        $service = $this->services->get($serviceName);

        if (!$service) {
            return;
        }

        // Only unregister if it belongs to the specified module
        if ($service['module'] !== $module) {
            $this->logger->warning("Cannot unregister service {$serviceName}: belongs to different module", [
                'service' => $serviceName,
                'requested_module' => $module,
                'actual_module' => $service['module'],
            ]);

            return;
        }

        $this->logger->info("Unregistering service: {$serviceName}", [
            'service' => $serviceName,
            'module' => $module,
        ]);

        $this->services->forget($serviceName);

        // Note: We don't unbind from Laravel's container as other services might depend on it
    }

    public function resolve(string $serviceName): ?object
    {
        if (!$this->exists($serviceName)) {
            return null;
        }

        try {
            return $this->container->make($serviceName);
        } catch (Exception $e) {
            $this->logger->error("Failed to resolve service: {$serviceName}", [
                'service' => $serviceName,
                'exception' => $e,
            ]);

            return null;
        }
    }

    public function exists(string $serviceName): bool
    {
        return $this->services->has($serviceName);
    }

    public function getImplementation(string $serviceName): ?string
    {
        $service = $this->services->get($serviceName);

        return $service['implementation'] ?? null;
    }

    public function getModule(string $serviceName): ?string
    {
        $service = $this->services->get($serviceName);

        return $service['module'] ?? null;
    }

    public function getServices(): Collection
    {
        return $this->services->map(static fn (array $service, string $name) => [
            'name' => $name,
            'implementation' => $service['implementation'],
            'module' => $service['module'],
            'registered_at' => $service['registered_at'],
        ]);
    }

    public function getServicesByModule(string $module): Collection
    {
        return $this->services
            ->filter(static fn (array $service) => $service['module'] === $module)
            ->map(static fn (array $service, string $name) => [
                'name' => $name,
                'implementation' => $service['implementation'],
                'module' => $service['module'],
                'registered_at' => $service['registered_at'],
            ]);
    }

    public function clearModule(string $module): void
    {
        $this->logger->info("Clearing services for module: {$module}");

        $servicesToRemove = $this->services
            ->filter(static fn (array $service) => $service['module'] === $module)
            ->keys();

        foreach ($servicesToRemove as $serviceName) {
            $this->services->forget($serviceName);
        }

        $this->logger->info("Cleared {$servicesToRemove->count()} services for module: {$module}");
    }

    public function clear(): void
    {
        $this->logger->info('Clearing all registered services');
        $this->services = collect();
    }
}
