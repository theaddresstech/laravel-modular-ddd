<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Compilation\Contracts;

use TaiCrm\LaravelModularDdd\Compilation\CompilationResult;

/**
 * Interface for ultra-performance module compilation
 */
interface ModuleCompilerInterface
{
    /**
     * Compile all modules into optimized registry files
     */
    public function compile(array $options = []): CompilationResult;

    /**
     * Check if compilation is needed
     */
    public function isCompilationNeeded(): bool;

    /**
     * Get compilation timestamp
     */
    public function getCompilationTimestamp(): ?int;

    /**
     * Clear compiled cache
     */
    public function clearCompiledCache(): bool;

    /**
     * Validate compiled files integrity
     */
    public function validateCompiledFiles(): bool;
}