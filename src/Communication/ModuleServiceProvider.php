<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Communication;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use TaiCrm\LaravelModularDdd\Communication\Contracts\ServiceRegistryInterface;

abstract class ModuleServiceProvider extends ServiceProvider
{
    protected string $moduleName;
    protected array $services = [];
    protected array $contracts = [];
    protected array $eventListeners = [];

    public function register(): void
    {
        $this->registerContracts();
        $this->registerServices();
    }

    public function boot(): void
    {
        $this->registerInServiceRegistry();
        $this->registerEventListeners();
        $this->bootModule();
    }

    public function provides(): array
    {
        return array_merge(
            array_keys($this->contracts),
            is_array($this->services) ? array_keys($this->services) : $this->services,
        );
    }

    protected function registerContracts(): void
    {
        foreach ($this->contracts as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }
    }

    protected function registerServices(): void
    {
        foreach ($this->services as $service => $implementation) {
            if (is_string($service)) {
                $this->app->bind($service, $implementation);
            } else {
                $this->app->singleton($implementation);
            }
        }
    }

    protected function registerInServiceRegistry(): void
    {
        if (!$this->app->bound(ServiceRegistryInterface::class)) {
            return;
        }

        $registry = $this->app->make(ServiceRegistryInterface::class);

        // Register contracts
        foreach ($this->contracts as $contract => $implementation) {
            $registry->register($contract, $implementation, $this->getModuleName());
        }

        // Register services
        foreach ($this->services as $service => $implementation) {
            $serviceName = is_string($service) ? $service : $implementation;
            $registry->register($serviceName, $implementation, $this->getModuleName());
        }
    }

    protected function registerEventListeners(): void
    {
        if (!$this->app->bound(EventBus::class)) {
            return;
        }

        $eventBus = $this->app->make(EventBus::class);

        foreach ($this->eventListeners as $event => $listeners) {
            if (!is_array($listeners)) {
                $listeners = [$listeners];
            }

            foreach ($listeners as $listener) {
                $eventBus->subscribe($event, $this->resolveEventListener($listener));
            }
        }
    }

    protected function resolveEventListener($listener): callable
    {
        if (is_callable($listener)) {
            return $listener;
        }

        if (is_string($listener) && class_exists($listener)) {
            return function ($event) use ($listener) {
                $instance = $this->app->make($listener);

                if (method_exists($instance, 'handle')) {
                    return $instance->handle($event);
                }

                if (method_exists($instance, '__invoke')) {
                    return $instance($event);
                }

                throw new InvalidArgumentException("Event listener {$listener} must have handle() or __invoke() method");
            };
        }

        throw new InvalidArgumentException('Invalid event listener: ' . print_r($listener, true));
    }

    protected function bootModule(): void
    {
        // Override in child classes for custom boot logic
    }

    protected function getModuleName(): string
    {
        if (isset($this->moduleName)) {
            return $this->moduleName;
        }

        // Extract module name from provider class name
        $className = class_basename(static::class);

        return str_replace('ServiceProvider', '', $className);
    }
}
