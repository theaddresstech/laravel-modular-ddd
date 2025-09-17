<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation\Contracts;

interface QueryInterface
{
    /**
     * Get the query identifier for logging and tracing.
     */
    public function getQueryId(): string;

    /**
     * Get the query type/name.
     */
    public function getQueryType(): string;

    /**
     * Get query parameters for logging and caching.
     */
    public function getParameters(): array;

    /**
     * Get cache key for this query (if cacheable).
     */
    public function getCacheKey(): ?string;

    /**
     * Get cache TTL in seconds (if cacheable).
     */
    public function getCacheTtl(): ?int;
}
