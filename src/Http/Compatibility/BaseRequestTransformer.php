<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Http\Compatibility;

use Illuminate\Http\Request;
use TaiCrm\LaravelModularDdd\Http\Compatibility\Contracts\RequestTransformerInterface;

abstract class BaseRequestTransformer implements RequestTransformerInterface
{
    protected string $fromVersion;
    protected string $toVersion;
    protected int $priority;
    protected array $metadata;

    public function __construct(string $fromVersion, string $toVersion, int $priority = 100)
    {
        $this->fromVersion = $fromVersion;
        $this->toVersion = $toVersion;
        $this->priority = $priority;
        $this->metadata = $this->buildMetadata();
    }

    public function canTransform(string $fromVersion, string $toVersion): bool
    {
        return $this->fromVersion === $fromVersion && $this->toVersion === $toVersion;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    protected function buildMetadata(): array
    {
        return [
            'transformer_class' => static::class,
            'from_version' => $this->fromVersion,
            'to_version' => $this->toVersion,
            'description' => $this->getDescription(),
            'transformations' => $this->getTransformationDescription(),
        ];
    }

    abstract protected function getDescription(): string;

    abstract protected function getTransformationDescription(): array;

    protected function transformRequestData(array $data, array $transformations): array
    {
        foreach ($transformations as $transformation) {
            $data = $this->applyTransformation($data, $transformation);
        }

        return $data;
    }

    protected function applyTransformation(array $data, array $transformation): array
    {
        switch ($transformation['type']) {
            case 'rename_field':
                return $this->renameField($data, $transformation['from'], $transformation['to']);

            case 'remove_field':
                return $this->removeField($data, $transformation['field']);

            case 'add_field':
                return $this->addField($data, $transformation['field'], $transformation['value']);

            case 'transform_value':
                return $this->transformValue(
                    $data,
                    $transformation['field'],
                    $transformation['transformer']
                );

            case 'restructure':
                return $this->restructureData($data, $transformation['mapping']);

            default:
                return $data;
        }
    }

    protected function renameField(array $data, string $from, string $to): array
    {
        if (isset($data[$from])) {
            $data[$to] = $data[$from];
            unset($data[$from]);
        }

        return $data;
    }

    protected function removeField(array $data, string $field): array
    {
        unset($data[$field]);
        return $data;
    }

    protected function addField(array $data, string $field, $value): array
    {
        $data[$field] = $value;
        return $data;
    }

    protected function transformValue(array $data, string $field, callable $transformer): array
    {
        if (isset($data[$field])) {
            $data[$field] = $transformer($data[$field]);
        }

        return $data;
    }

    protected function restructureData(array $data, array $mapping): array
    {
        $result = [];

        foreach ($mapping as $newKey => $oldKey) {
            if (is_array($oldKey)) {
                // Complex mapping
                $result[$newKey] = $this->extractNestedValue($data, $oldKey);
            } else {
                // Simple mapping
                if (isset($data[$oldKey])) {
                    $result[$newKey] = $data[$oldKey];
                }
            }
        }

        return $result;
    }

    protected function extractNestedValue(array $data, array $path)
    {
        $current = $data;

        foreach ($path as $key) {
            if (!isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    protected function cloneRequest(Request $request): Request
    {
        return Request::create(
            $request->url(),
            $request->method(),
            $request->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            $request->getContent()
        );
    }
}