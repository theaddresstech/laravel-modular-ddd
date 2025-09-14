<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Http\Compatibility\Contracts;

use Illuminate\Http\Request;

interface RequestTransformerInterface
{
    /**
     * Transform a request from one API version to another
     */
    public function transform(Request $request, string $fromVersion, string $toVersion): Request;

    /**
     * Check if this transformer can handle the given version transformation
     */
    public function canTransform(string $fromVersion, string $toVersion): bool;

    /**
     * Get the priority of this transformer (higher = more priority)
     */
    public function getPriority(): int;

    /**
     * Get transformer metadata
     */
    public function getMetadata(): array;
}