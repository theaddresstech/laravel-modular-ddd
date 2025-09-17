<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use TaiCrm\LaravelModularDdd\Foundation\Contracts\QueryHandlerInterface;
use TaiCrm\LaravelModularDdd\Foundation\Contracts\QueryInterface;

class QueryBus
{
    private array $handlers = [];

    public function __construct(
        private Container $container,
    ) {}

    public function register(string $queryClass, string $handlerClass): void
    {
        if (!is_subclass_of($queryClass, QueryInterface::class)) {
            throw new InvalidArgumentException('Query class must implement QueryInterface');
        }

        if (!is_subclass_of($handlerClass, QueryHandlerInterface::class)) {
            throw new InvalidArgumentException('Handler class must implement QueryHandlerInterface');
        }

        $this->handlers[$queryClass] = $handlerClass;
    }

    public function ask(QueryInterface $query): mixed
    {
        $cacheKey = $query->getCacheKey();

        if ($cacheKey && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $queryClass = $query::class;

        if (!isset($this->handlers[$queryClass])) {
            throw new InvalidArgumentException("No handler registered for query: {$queryClass}");
        }

        $handlerClass = $this->handlers[$queryClass];
        $handler = $this->container->make($handlerClass);

        $result = $handler->handle($query);

        if ($cacheKey && $query->getCacheTtl()) {
            Cache::put($cacheKey, $result, $query->getCacheTtl());
        }

        return $result;
    }

    public function getRegisteredHandlers(): array
    {
        return $this->handlers;
    }
}
