<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Http\Compatibility;

use TaiCrm\LaravelModularDdd\Http\Compatibility\Contracts\ResponseTransformerInterface;

abstract class BaseResponseTransformer implements ResponseTransformerInterface
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

    protected function transformResponseData(array $data, array $transformations): array
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

            case 'wrap_data':
                return $this->wrapData($data, $transformation['wrapper']);

            case 'unwrap_data':
                return $this->unwrapData($data, $transformation['wrapper']);

            case 'paginate_format':
                return $this->transformPaginationFormat($data, $transformation['format']);

            case 'date_format':
                return $this->transformDateFormat($data, $transformation['field'], $transformation['format']);

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

        // Handle nested arrays
        if (isset($data['data']) && is_array($data['data'])) {
            if (is_array($data['data']) && isset($data['data'][0])) {
                // Collection of items
                foreach ($data['data'] as $index => $item) {
                    if (is_array($item) && isset($item[$from])) {
                        $data['data'][$index][$to] = $item[$from];
                        unset($data['data'][$index][$from]);
                    }
                }
            } elseif (isset($data['data'][$from])) {
                // Single item
                $data['data'][$to] = $data['data'][$from];
                unset($data['data'][$from]);
            }
        }

        return $data;
    }

    protected function removeField(array $data, string $field): array
    {
        unset($data[$field]);

        // Handle nested data
        if (isset($data['data'])) {
            if (is_array($data['data']) && isset($data['data'][0])) {
                // Collection
                foreach ($data['data'] as $index => $item) {
                    if (is_array($item)) {
                        unset($data['data'][$index][$field]);
                    }
                }
            } elseif (is_array($data['data'])) {
                // Single item
                unset($data['data'][$field]);
            }
        }

        return $data;
    }

    protected function addField(array $data, string $field, $value): array
    {
        $data[$field] = $value;

        // Handle nested data
        if (isset($data['data'])) {
            if (is_array($data['data']) && isset($data['data'][0])) {
                // Collection
                foreach ($data['data'] as $index => $item) {
                    if (is_array($item)) {
                        $data['data'][$index][$field] = is_callable($value) ? $value($item) : $value;
                    }
                }
            } elseif (is_array($data['data'])) {
                // Single item
                $data['data'][$field] = is_callable($value) ? $value($data['data']) : $value;
            }
        }

        return $data;
    }

    protected function transformValue(array $data, string $field, callable $transformer): array
    {
        if (isset($data[$field])) {
            $data[$field] = $transformer($data[$field]);
        }

        // Handle nested data
        if (isset($data['data'])) {
            if (is_array($data['data']) && isset($data['data'][0])) {
                // Collection
                foreach ($data['data'] as $index => $item) {
                    if (is_array($item) && isset($item[$field])) {
                        $data['data'][$index][$field] = $transformer($item[$field]);
                    }
                }
            } elseif (is_array($data['data']) && isset($data['data'][$field])) {
                // Single item
                $data['data'][$field] = $transformer($data['data'][$field]);
            }
        }

        return $data;
    }

    protected function restructureData(array $data, array $mapping): array
    {
        $result = [];

        foreach ($mapping as $newKey => $oldKey) {
            if (is_array($oldKey)) {
                $result[$newKey] = $this->extractNestedValue($data, $oldKey);
            } else {
                if (isset($data[$oldKey])) {
                    $result[$newKey] = $data[$oldKey];
                }
            }
        }

        return $result;
    }

    protected function wrapData(array $data, string $wrapper): array
    {
        return [$wrapper => $data];
    }

    protected function unwrapData(array $data, string $wrapper): array
    {
        return $data[$wrapper] ?? $data;
    }

    protected function transformPaginationFormat(array $data, string $format): array
    {
        if (!isset($data['meta'])) {
            return $data;
        }

        switch ($format) {
            case 'laravel_legacy':
                return $this->transformToLaravelLegacyPagination($data);
            case 'simple':
                return $this->transformToSimplePagination($data);
            case 'cursor':
                return $this->transformToCursorPagination($data);
            default:
                return $data;
        }
    }

    protected function transformDateFormat(array $data, string $field, string $format): array
    {
        if (isset($data[$field])) {
            $data[$field] = $this->formatDate($data[$field], $format);
        }

        // Handle nested data
        if (isset($data['data'])) {
            if (is_array($data['data']) && isset($data['data'][0])) {
                // Collection
                foreach ($data['data'] as $index => $item) {
                    if (is_array($item) && isset($item[$field])) {
                        $data['data'][$index][$field] = $this->formatDate($item[$field], $format);
                    }
                }
            } elseif (is_array($data['data']) && isset($data['data'][$field])) {
                // Single item
                $data['data'][$field] = $this->formatDate($data['data'][$field], $format);
            }
        }

        return $data;
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

    protected function transformToLaravelLegacyPagination(array $data): array
    {
        if (!isset($data['meta'])) {
            return $data;
        }

        $meta = $data['meta'];

        return [
            'data' => $data['data'],
            'current_page' => $meta['current_page'] ?? 1,
            'last_page' => $meta['last_page'] ?? 1,
            'per_page' => $meta['per_page'] ?? 15,
            'total' => $meta['total'] ?? 0,
            'from' => $meta['from'] ?? null,
            'to' => $meta['to'] ?? null,
        ];
    }

    protected function transformToSimplePagination(array $data): array
    {
        return [
            'data' => $data['data'],
            'has_more' => isset($data['meta']['next_page_url']),
            'next_cursor' => $data['meta']['next_cursor'] ?? null,
        ];
    }

    protected function transformToCursorPagination(array $data): array
    {
        return [
            'data' => $data['data'],
            'pagination' => [
                'cursor' => $data['meta']['cursor'] ?? null,
                'next_cursor' => $data['meta']['next_cursor'] ?? null,
                'prev_cursor' => $data['meta']['prev_cursor'] ?? null,
            ],
        ];
    }

    protected function formatDate(string $date, string $format): string
    {
        try {
            $dateTime = new \DateTime($date);
            return $dateTime->format($format);
        } catch (\Exception $e) {
            return $date; // Return original if parsing fails
        }
    }
}