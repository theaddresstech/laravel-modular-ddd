<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Loading;

/**
 * Value object representing module loading results
 */
readonly class LoadingResult
{
    public function __construct(
        public bool $success,
        public ?int $modulesLoaded = null,
        public ?float $loadingTimeMs = null,
        public ?array $strategy = null,
        public ?int $memoryUsage = null,
        public ?array $context = null,
        public ?string $error = null,
        public ?array $warnings = null,
        public ?array $metrics = null
    ) {}

    public function hasErrors(): bool
    {
        return !$this->success || $this->error !== null;
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    public function getLoadingTimeSeconds(): float
    {
        return ($this->loadingTimeMs ?? 0) / 1000;
    }

    public function getMemoryUsageMB(): float
    {
        return ($this->memoryUsage ?? 0) / 1024 / 1024;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'modules_loaded' => $this->modulesLoaded,
            'loading_time_ms' => $this->loadingTimeMs,
            'loading_time_seconds' => $this->getLoadingTimeSeconds(),
            'memory_usage_bytes' => $this->memoryUsage,
            'memory_usage_mb' => $this->getMemoryUsageMB(),
            'strategy' => $this->strategy,
            'context' => $this->context,
            'error' => $this->error,
            'warnings' => $this->warnings,
            'metrics' => $this->metrics,
        ];
    }
}