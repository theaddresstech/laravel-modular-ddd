<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Http\Compatibility;

use Illuminate\Support\Collection;
use TaiCrm\LaravelModularDdd\Http\Compatibility\Contracts\RequestTransformerInterface;
use TaiCrm\LaravelModularDdd\Http\Compatibility\Contracts\ResponseTransformerInterface;

class TransformationRegistry
{
    private Collection $requestTransformers;
    private Collection $responseTransformers;

    public function __construct()
    {
        $this->requestTransformers = collect();
        $this->responseTransformers = collect();
    }

    public function registerRequestTransformer(
        string $fromVersion,
        string $toVersion,
        RequestTransformerInterface $transformer,
        ?string $module = null
    ): void {
        $this->requestTransformers->push([
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'module' => $module,
            'instance' => $transformer,
            'type' => 'request',
            'priority' => $transformer->getPriority(),
            'metadata' => $transformer->getMetadata(),
        ]);

        // Sort by priority (highest first)
        $this->requestTransformers = $this->requestTransformers->sortByDesc('priority');
    }

    public function registerResponseTransformer(
        string $fromVersion,
        string $toVersion,
        ResponseTransformerInterface $transformer,
        ?string $module = null
    ): void {
        $this->responseTransformers->push([
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'module' => $module,
            'instance' => $transformer,
            'type' => 'response',
            'priority' => $transformer->getPriority(),
            'metadata' => $transformer->getMetadata(),
        ]);

        // Sort by priority (highest first)
        $this->responseTransformers = $this->responseTransformers->sortByDesc('priority');
    }

    public function getRequestTransformer(string $fromVersion, string $toVersion, ?string $module = null): ?RequestTransformerInterface
    {
        $transformer = $this->requestTransformers
            ->filter(function ($item) use ($fromVersion, $toVersion, $module) {
                $versionMatch = $item['from_version'] === $fromVersion && $item['to_version'] === $toVersion;
                $moduleMatch = $module ? $item['module'] === $module : true;

                return $versionMatch && $moduleMatch && $item['instance']->canTransform($fromVersion, $toVersion);
            })
            ->first();

        return $transformer['instance'] ?? null;
    }

    public function getResponseTransformer(string $fromVersion, string $toVersion, ?string $module = null): ?ResponseTransformerInterface
    {
        $transformer = $this->responseTransformers
            ->filter(function ($item) use ($fromVersion, $toVersion, $module) {
                $versionMatch = $item['from_version'] === $fromVersion && $item['to_version'] === $toVersion;
                $moduleMatch = $module ? $item['module'] === $module : true;

                return $versionMatch && $moduleMatch && $item['instance']->canTransform($fromVersion, $toVersion);
            })
            ->first();

        return $transformer['instance'] ?? null;
    }

    public function getAllTransformers(): array
    {
        return $this->requestTransformers->merge($this->responseTransformers)->toArray();
    }

    public function getTransformersForModule(string $module): array
    {
        $moduleTransformers = $this->requestTransformers
            ->merge($this->responseTransformers)
            ->filter(function ($item) use ($module) {
                return $item['module'] === $module;
            });

        return $moduleTransformers->toArray();
    }

    public function getTransformationPaths(string $fromVersion, string $toVersion): array
    {
        // Find direct transformations
        $direct = $this->findDirectTransformation($fromVersion, $toVersion);
        if ($direct) {
            return [$direct];
        }

        // Find multi-step transformations
        return $this->findMultiStepTransformation($fromVersion, $toVersion);
    }

    public function getAvailableVersions(): array
    {
        $allTransformers = $this->getAllTransformers();
        $versions = collect();

        foreach ($allTransformers as $transformer) {
            $versions->push($transformer['from_version']);
            $versions->push($transformer['to_version']);
        }

        return $versions->unique()->sort()->values()->toArray();
    }

    public function hasTransformationPath(string $fromVersion, string $toVersion): bool
    {
        return !empty($this->getTransformationPaths($fromVersion, $toVersion));
    }

    public function getTransformationMatrix(): array
    {
        $versions = $this->getAvailableVersions();
        $matrix = [];

        foreach ($versions as $from) {
            foreach ($versions as $to) {
                $matrix[$from][$to] = [
                    'direct' => $this->findDirectTransformation($from, $to) !== null,
                    'multi_step' => !empty($this->findMultiStepTransformation($from, $to)),
                    'bidirectional' => $this->findDirectTransformation($from, $to) !== null &&
                                     $this->findDirectTransformation($to, $from) !== null,
                ];
            }
        }

        return $matrix;
    }

    private function findDirectTransformation(string $fromVersion, string $toVersion): ?array
    {
        $requestTransformer = $this->requestTransformers
            ->filter(function ($item) use ($fromVersion, $toVersion) {
                return $item['from_version'] === $fromVersion && $item['to_version'] === $toVersion;
            })
            ->first();

        $responseTransformer = $this->responseTransformers
            ->filter(function ($item) use ($fromVersion, $toVersion) {
                return $item['from_version'] === $fromVersion && $item['to_version'] === $toVersion;
            })
            ->first();

        if ($requestTransformer || $responseTransformer) {
            return [
                'from' => $fromVersion,
                'to' => $toVersion,
                'steps' => 1,
                'request_transformer' => $requestTransformer,
                'response_transformer' => $responseTransformer,
            ];
        }

        return null;
    }

    private function findMultiStepTransformation(string $fromVersion, string $toVersion): array
    {
        // Simple implementation - could be enhanced with more sophisticated pathfinding
        $paths = [];
        $visited = [];

        $this->findPaths($fromVersion, $toVersion, [$fromVersion], $visited, $paths, 3); // Max 3 steps

        return $paths;
    }

    private function findPaths(string $current, string $target, array $path, array &$visited, array &$paths, int $maxDepth): void
    {
        if ($maxDepth <= 0 || in_array($current, $visited)) {
            return;
        }

        if ($current === $target && count($path) > 1) {
            $paths[] = [
                'path' => $path,
                'steps' => count($path) - 1,
            ];
            return;
        }

        $visited[] = $current;

        // Find all transformers that start from current version
        $nextTransformers = $this->requestTransformers
            ->merge($this->responseTransformers)
            ->filter(function ($item) use ($current) {
                return $item['from_version'] === $current;
            })
            ->unique('to_version');

        foreach ($nextTransformers as $transformer) {
            $nextVersion = $transformer['to_version'];
            if (!in_array($nextVersion, $path)) {
                $this->findPaths(
                    $nextVersion,
                    $target,
                    array_merge($path, [$nextVersion]),
                    $visited,
                    $paths,
                    $maxDepth - 1
                );
            }
        }

        array_pop($visited);
    }
}