<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Monitoring;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QueryPerformanceAnalyzer
{
    private array $queries = [];
    private array $slowQueries = [];
    private float $slowQueryThreshold;
    private bool $enabled;

    public function __construct(
        float $slowQueryThreshold = 1000.0, // milliseconds
        bool $enabled = true
    ) {
        $this->slowQueryThreshold = $slowQueryThreshold;
        $this->enabled = $enabled;

        if ($this->enabled) {
            $this->registerListeners();
        }
    }

    public function enable(): void
    {
        $this->enabled = true;
        $this->registerListeners();
    }

    public function disable(): void
    {
        $this->enabled = false;
        $this->queries = [];
        $this->slowQueries = [];
    }

    public function startMonitoring(): void
    {
        if (!$this->enabled) {
            return;
        }

        DB::enableQueryLog();
        $this->queries = [];
        $this->slowQueries = [];
    }

    public function stopMonitoring(): array
    {
        if (!$this->enabled) {
            return [];
        }

        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        return $this->analyzeQueries($queryLog);
    }

    public function getSlowQueries(): array
    {
        return $this->slowQueries;
    }

    public function getQueryCount(): int
    {
        return count($this->queries);
    }

    public function getTotalQueryTime(): float
    {
        return array_sum(array_column($this->queries, 'time'));
    }

    public function getAverageQueryTime(): float
    {
        $count = count($this->queries);
        return $count > 0 ? $this->getTotalQueryTime() / $count : 0;
    }

    public function getQueryStats(): array
    {
        return [
            'total_queries' => $this->getQueryCount(),
            'total_time' => $this->getTotalQueryTime(),
            'average_time' => $this->getAverageQueryTime(),
            'slow_queries' => count($this->slowQueries),
            'slow_query_threshold' => $this->slowQueryThreshold,
        ];
    }

    public function detectNPlusOneQueries(): array
    {
        $patterns = [];
        $suspiciousQueries = [];

        foreach ($this->queries as $query) {
            $normalizedSql = $this->normalizeSql($query['query']);

            if (!isset($patterns[$normalizedSql])) {
                $patterns[$normalizedSql] = [];
            }

            $patterns[$normalizedSql][] = $query;
        }

        foreach ($patterns as $sql => $queries) {
            if (count($queries) > 10) { // Threshold for suspicious repetition
                $suspiciousQueries[] = [
                    'sql' => $sql,
                    'count' => count($queries),
                    'total_time' => array_sum(array_column($queries, 'time')),
                    'queries' => $queries,
                ];
            }
        }

        return $suspiciousQueries;
    }

    public function generatePerformanceReport(): array
    {
        $nPlusOneQueries = $this->detectNPlusOneQueries();
        $stats = $this->getQueryStats();

        return [
            'summary' => $stats,
            'slow_queries' => $this->slowQueries,
            'n_plus_one_queries' => $nPlusOneQueries,
            'recommendations' => $this->generateRecommendations($stats, $nPlusOneQueries),
            'timestamp' => now()->toISOString(),
        ];
    }

    public function cacheReport(string $key, array $report): void
    {
        Cache::put("query_performance:{$key}", $report, 3600); // Cache for 1 hour
    }

    public function getCachedReport(string $key): ?array
    {
        return Cache::get("query_performance:{$key}");
    }

    private function registerListeners(): void
    {
        DB::listen(function (QueryExecuted $query) {
            $this->handleQueryExecuted($query);
        });
    }

    private function handleQueryExecuted(QueryExecuted $query): void
    {
        if (!$this->enabled) {
            return;
        }

        $queryData = [
            'query' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time,
            'connection' => $query->connectionName,
            'timestamp' => microtime(true),
        ];

        $this->queries[] = $queryData;

        if ($query->time > $this->slowQueryThreshold) {
            $this->slowQueries[] = $queryData;

            Log::warning('Slow query detected', [
                'sql' => $query->sql,
                'time' => $query->time,
                'bindings' => $query->bindings,
                'threshold' => $this->slowQueryThreshold,
            ]);
        }
    }

    private function analyzeQueries(array $queryLog): array
    {
        foreach ($queryLog as $query) {
            $queryData = [
                'query' => $query['query'],
                'bindings' => $query['bindings'],
                'time' => $query['time'],
                'connection' => 'default',
                'timestamp' => microtime(true),
            ];

            $this->queries[] = $queryData;

            if ($query['time'] > $this->slowQueryThreshold) {
                $this->slowQueries[] = $queryData;
            }
        }

        return $this->generatePerformanceReport();
    }

    private function normalizeSql(string $sql): string
    {
        // Remove specific values and normalize SQL for pattern detection
        $normalized = preg_replace('/\?/', 'PLACEHOLDER', $sql);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = preg_replace('/PLACEHOLDER\s*,\s*PLACEHOLDER/', 'PLACEHOLDER_LIST', $normalized);

        return trim($normalized);
    }

    private function generateRecommendations(array $stats, array $nPlusOneQueries): array
    {
        $recommendations = [];

        if ($stats['slow_queries'] > 0) {
            $recommendations[] = [
                'type' => 'slow_queries',
                'severity' => 'high',
                'message' => "Found {$stats['slow_queries']} slow queries. Consider adding indexes or optimizing query structure.",
                'action' => 'Review slow queries and add appropriate database indexes.',
            ];
        }

        if (count($nPlusOneQueries) > 0) {
            $recommendations[] = [
                'type' => 'n_plus_one',
                'severity' => 'high',
                'message' => "Detected " . count($nPlusOneQueries) . " potential N+1 query patterns.",
                'action' => 'Use eager loading (with()) to reduce query count.',
            ];
        }

        if ($stats['total_queries'] > 50) {
            $recommendations[] = [
                'type' => 'query_count',
                'severity' => 'medium',
                'message' => "High query count ({$stats['total_queries']}) detected.",
                'action' => 'Consider implementing query optimization or caching strategies.',
            ];
        }

        if ($stats['average_time'] > 100) {
            $recommendations[] = [
                'type' => 'average_time',
                'severity' => 'medium',
                'message' => "Average query time is {$stats['average_time']}ms.",
                'action' => 'Optimize frequently used queries and consider database tuning.',
            ];
        }

        return $recommendations;
    }
}