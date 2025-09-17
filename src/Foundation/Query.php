<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation;

use Ramsey\Uuid\Uuid;
use ReflectionClass;
use TaiCrm\LaravelModularDdd\Foundation\Contracts\QueryInterface;

abstract class Query implements QueryInterface
{
    private string $queryId;

    public function __construct()
    {
        $this->queryId = Uuid::uuid4()->toString();
    }

    public function getQueryId(): string
    {
        return $this->queryId;
    }

    public function getQueryType(): string
    {
        $reflection = new ReflectionClass($this);

        return $reflection->getShortName();
    }

    public function getParameters(): array
    {
        return [
            'query_id' => $this->queryId,
            'query_type' => $this->getQueryType(),
            'params' => $this->toArray(),
        ];
    }

    public function getCacheKey(): ?string
    {
        if (!$this->isCacheable()) {
            return null;
        }

        return sprintf(
            'query:%s:%s',
            $this->getQueryType(),
            md5(serialize($this->toArray())),
        );
    }

    public function getCacheTtl(): ?int
    {
        return $this->isCacheable() ? $this->getDefaultCacheTtl() : null;
    }

    protected function isCacheable(): bool
    {
        return false;
    }

    protected function getDefaultCacheTtl(): int
    {
        return 300; // 5 minutes
    }

    abstract protected function toArray(): array;
}
