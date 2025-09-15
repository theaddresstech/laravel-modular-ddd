<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Compilation;

/**
 * Value object representing the result of module compilation
 */
readonly class CompilationResult
{
    public function __construct(
        public bool $success,
        public ?int $modulesCompiled = null,
        public ?float $compilationTimeMs = null,
        public ?array $optimizations = null,
        public ?array $cacheKeys = null,
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

    public function getCompilationTimeSeconds(): float
    {
        return ($this->compilationTimeMs ?? 0) / 1000;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'modules_compiled' => $this->modulesCompiled,
            'compilation_time_ms' => $this->compilationTimeMs,
            'compilation_time_seconds' => $this->getCompilationTimeSeconds(),
            'optimizations' => $this->optimizations,
            'cache_keys' => $this->cacheKeys,
            'error' => $this->error,
            'warnings' => $this->warnings,
            'metrics' => $this->metrics,
        ];
    }
}