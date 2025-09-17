<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Http\Compatibility\Contracts;

interface ResponseTransformerInterface
{
    /**
     * Transform response data from one API version to another.
     */
    public function transform(array $data, string $fromVersion, string $toVersion): array;

    /**
     * Check if this transformer can handle the given version transformation.
     */
    public function canTransform(string $fromVersion, string $toVersion): bool;

    /**
     * Get the priority of this transformer (higher = more priority).
     */
    public function getPriority(): int;

    /**
     * Get transformer metadata.
     */
    public function getMetadata(): array;
}
