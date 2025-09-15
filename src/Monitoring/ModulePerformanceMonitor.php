<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Monitoring;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use Illuminate\Support\Collection;
use Illuminate\Cache\CacheManager;
use Psr\Log\LoggerInterface;

class ModulePerformanceMonitor
{
    private array $metrics = [];
    private array $timers = [];

    public function __construct(
        private ModuleManagerInterface $moduleManager,
        private CacheManager $cache,
        private LoggerInterface $logger
    ) {}

    public function startTimer(string $operation, array $context = []): string
    {
        $timerId = uniqid($operation . '_', true);

        $this->timers[$timerId] = [
            'operation' => $operation,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'context' => $context,
        ];

        return $timerId;
    }

    public function endTimer(string $timerId): array
    {
        if (!isset($this->timers[$timerId])) {
            throw new \InvalidArgumentException("Timer {$timerId} not found");
        }

        $timer = $this->timers[$timerId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $metrics = [
            'operation' => $timer['operation'],
            'duration' => $endTime - $timer['start_time'],
            'memory_used' => $endMemory - $timer['start_memory'],
            'peak_memory' => memory_get_peak_usage(true),
            'context' => $timer['context'],
            'timestamp' => now(),
        ];

        $this->recordMetric($metrics);
        unset($this->timers[$timerId]);

        return $metrics;
    }

    public function recordMetric(array $metric): void
    {
        $metric['id'] = uniqid('metric_', true);
        $this->metrics[] = $metric;

        // Store in cache for aggregation
        $cacheKey = $this->getCacheKey($metric['operation']);
        $cachedMetrics = $this->cache->get($cacheKey, []);
        $cachedMetrics[] = $metric;

        // Keep only last 1000 metrics per operation
        if (count($cachedMetrics) > 1000) {
            $cachedMetrics = array_slice($cachedMetrics, -1000);
        }

        $this->cache->put($cacheKey, $cachedMetrics, now()->addHours(24));

        // Log slow operations
        if ($metric['duration'] > config('modular-ddd.monitoring.slow_operation_threshold', 1.0)) {
            $this->logger->warning('Slow module operation detected', $metric);
        }
    }

    public function getMetrics(string $operation = null, int $limit = 100): Collection
    {
        if ($operation) {
            $cacheKey = $this->getCacheKey($operation);
            $metrics = $this->cache->get($cacheKey, []);
            return collect($metrics)->take($limit);
        }

        return collect($this->metrics)->take($limit);
    }

    public function getAggregatedMetrics(string $operation, string $period = '1hour'): array
    {
        $cacheKey = $this->getCacheKey($operation);
        $metrics = $this->cache->get($cacheKey, []);

        if (empty($metrics)) {
            return [];
        }

        $filtered = $this->filterMetricsByPeriod($metrics, $period);

        return [
            'operation' => $operation,
            'period' => $period,
            'total_operations' => count($filtered),
            'average_duration' => $this->calculateAverage($filtered, 'duration'),
            'min_duration' => min(array_column($filtered, 'duration')),
            'max_duration' => max(array_column($filtered, 'duration')),
            'average_memory' => $this->calculateAverage($filtered, 'memory_used'),
            'total_memory' => array_sum(array_column($filtered, 'memory_used')),
            'operations_per_second' => $this->calculateOperationsPerSecond($filtered),
            'percentiles' => $this->calculatePercentiles($filtered, 'duration'),
        ];
    }

    public function getModuleHealth(): Collection
    {
        $modules = $this->moduleManager->list();

        return $modules->map(function ($module) {
            $metrics = $this->getModuleMetrics($module->name);

            return [
                'module' => $module->name,
                'status' => $module->status,
                'is_enabled' => $module->isEnabled(),
                'performance' => [
                    'average_response_time' => $metrics['avg_duration'] ?? 0,
                    'total_operations' => $metrics['total_operations'] ?? 0,
                    'error_rate' => $metrics['error_rate'] ?? 0,
                    'memory_usage' => $metrics['avg_memory'] ?? 0,
                ],
                'health_score' => $this->calculateHealthScore($metrics),
                'last_activity' => $metrics['last_activity'] ?? null,
            ];
        });
    }

    public function getSystemMetrics(): array
    {
        $allMetrics = [];
        $operations = $this->getAllOperations();

        foreach ($operations as $operation) {
            $allMetrics[$operation] = $this->getAggregatedMetrics($operation, '1hour');
        }

        return [
            'timestamp' => now(),
            'total_modules' => $this->moduleManager->list()->count(),
            'enabled_modules' => $this->moduleManager->list()->filter->isEnabled()->count(),
            'total_operations' => array_sum(array_column($allMetrics, 'total_operations')),
            'average_response_time' => $this->calculateOverallAverage($allMetrics, 'average_duration'),
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit'),
            ],
            'operations_summary' => $allMetrics,
        ];
    }

    public function exportMetrics(string $format = 'json', string $operation = null): string
    {
        $metrics = $operation
            ? $this->getMetrics($operation, 1000)->toArray()
            : $this->getSystemMetrics();

        return match ($format) {
            'json' => json_encode($metrics, JSON_PRETTY_PRINT),
            'csv' => $this->metricsToCSV($metrics),
            'xml' => $this->metricsToXML($metrics),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}")
        };
    }

    public function clearMetrics(string $operation = null): void
    {
        if ($operation) {
            $cacheKey = $this->getCacheKey($operation);
            $this->cache->forget($cacheKey);
        } else {
            $operations = $this->getAllOperations();
            foreach ($operations as $op) {
                $this->cache->forget($this->getCacheKey($op));
            }
            $this->metrics = [];
        }

        $this->logger->info('Performance metrics cleared', ['operation' => $operation ?? 'all']);
    }

    private function getCacheKey(string $operation): string
    {
        return "module_performance_metrics:{$operation}";
    }

    private function getModuleMetrics(string $moduleName): array
    {
        $metrics = collect();
        $operations = $this->getAllOperations();

        foreach ($operations as $operation) {
            if (str_contains($operation, $moduleName)) {
                $operationMetrics = $this->getMetrics($operation, 100);
                $metrics = $metrics->merge($operationMetrics);
            }
        }

        if ($metrics->isEmpty()) {
            return [];
        }

        return [
            'total_operations' => $metrics->count(),
            'avg_duration' => $metrics->avg('duration'),
            'avg_memory' => $metrics->avg('memory_used'),
            'error_rate' => $this->calculateErrorRate($metrics),
            'last_activity' => $metrics->max('timestamp'),
        ];
    }

    private function calculateHealthScore(array $metrics): int
    {
        if (empty($metrics)) {
            return 100; // No data means no problems
        }

        $score = 100;

        // Penalize slow response times
        $avgDuration = $metrics['avg_duration'] ?? 0;
        if ($avgDuration > 2.0) {
            $score -= 30;
        } elseif ($avgDuration > 1.0) {
            $score -= 15;
        }

        // Penalize high error rates
        $errorRate = $metrics['error_rate'] ?? 0;
        if ($errorRate > 0.1) {
            $score -= 40;
        } elseif ($errorRate > 0.05) {
            $score -= 20;
        }

        // Penalize high memory usage
        $avgMemory = $metrics['avg_memory'] ?? 0;
        if ($avgMemory > 50 * 1024 * 1024) { // 50MB
            $score -= 20;
        }

        return max(0, $score);
    }

    private function filterMetricsByPeriod(array $metrics, string $period): array
    {
        $cutoff = match ($period) {
            '1hour' => now()->subHour(),
            '1day' => now()->subDay(),
            '1week' => now()->subWeek(),
            '1month' => now()->subMonth(),
            default => now()->subHour(),
        };

        return array_filter($metrics, function ($metric) use ($cutoff) {
            return isset($metric['timestamp']) && $metric['timestamp'] >= $cutoff;
        });
    }

    private function calculateAverage(array $metrics, string $field): float
    {
        if (empty($metrics)) {
            return 0;
        }

        $values = array_column($metrics, $field);
        return array_sum($values) / count($values);
    }

    private function calculateOperationsPerSecond(array $metrics): float
    {
        if (count($metrics) < 2) {
            return 0;
        }

        $timestamps = array_map(fn($m) => $m['timestamp']->timestamp, $metrics);
        $duration = max($timestamps) - min($timestamps);

        return $duration > 0 ? count($metrics) / $duration : 0;
    }

    private function calculatePercentiles(array $metrics, string $field): array
    {
        if (empty($metrics)) {
            return [];
        }

        $values = array_column($metrics, $field);
        sort($values);
        $count = count($values);

        return [
            'p50' => $values[(int)($count * 0.5)] ?? 0,
            'p90' => $values[(int)($count * 0.9)] ?? 0,
            'p95' => $values[(int)($count * 0.95)] ?? 0,
            'p99' => $values[(int)($count * 0.99)] ?? 0,
        ];
    }

    private function calculateErrorRate(Collection $metrics): float
    {
        $total = $metrics->count();
        if ($total === 0) {
            return 0;
        }

        $errors = $metrics->where('context.error', true)->count();
        return $errors / $total;
    }

    private function calculateOverallAverage(array $allMetrics, string $field): float
    {
        $values = array_column($allMetrics, $field);
        $nonZeroValues = array_filter($values, fn($v) => $v > 0);

        return empty($nonZeroValues) ? 0 : array_sum($nonZeroValues) / count($nonZeroValues);
    }

    private function getAllOperations(): array
    {
        $pattern = "module_performance_metrics:*";

        try {
            $store = $this->cache->getStore();

            // Handle different cache drivers
            if (method_exists($store, 'getRedis')) {
                $keys = $store->getRedis()->keys($pattern);
            } elseif (method_exists($store, 'getConnection')) {
                // Handle database cache
                return $this->getDatabaseCacheKeys($store, $pattern);
            } else {
                // Fallback for other cache drivers
                return [];
            }

            return array_map(
                fn($key) => str_replace('module_performance_metrics:', '', $key),
                $keys
            );
        } catch (\Exception $e) {
            // Return empty array if cache operations fail
            return [];
        }
    }

    private function metricsToCSV(array $metrics): string
    {
        if (empty($metrics)) {
            return '';
        }

        $csv = "Operation,Duration,Memory,Timestamp\n";

        if (isset($metrics['operations_summary'])) {
            foreach ($metrics['operations_summary'] as $operation => $data) {
                $csv .= sprintf(
                    "%s,%.4f,%d,%s\n",
                    $operation,
                    $data['average_duration'] ?? 0,
                    $data['average_memory'] ?? 0,
                    now()->toDateTimeString()
                );
            }
        }

        return $csv;
    }

    private function metricsToXML(array $metrics): string
    {
        $xml = new \SimpleXMLElement('<metrics/>');
        $xml->addAttribute('timestamp', now()->toISOString());

        $this->arrayToXML($metrics, $xml);

        return $xml->asXML();
    }

    private function arrayToXML(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $subNode = $xml->addChild($key);
                $this->arrayToXML($value, $subNode);
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }

    private function getDatabaseCacheKeys($store, string $pattern): array
    {
        try {
            $connection = $store->getConnection();

            // Convert Redis-style pattern to SQL LIKE pattern
            $likePattern = str_replace('*', '%', $pattern);

            // Get the cache table name (default is 'cache')
            $table = $store->getTable() ?? 'cache';

            // Query cache keys from database
            $cacheEntries = $connection
                ->table($table)
                ->where('key', 'like', $likePattern)
                ->where('expiration', '>', time()) // Only non-expired entries
                ->select(['key', 'expiration'])
                ->get();

            return array_map(
                fn($entry) => str_replace('module_performance_metrics:', '', $entry->key),
                $cacheEntries->toArray()
            );

        } catch (\Exception $e) {
            $this->logger->warning('Failed to retrieve database cache keys', [
                'pattern' => $pattern,
                'exception' => $e->getMessage(),
            ]);
            return [];
        }
    }
}