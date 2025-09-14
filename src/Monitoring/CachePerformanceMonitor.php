<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\KeyDeleted;

class CachePerformanceMonitor
{
    private array $metrics = [];
    private bool $enabled = false;
    private array $patterns = [];

    public function __construct()
    {
        $this->resetMetrics();
    }

    public function enable(): void
    {
        if ($this->enabled) {
            return;
        }

        $this->enabled = true;
        $this->registerEventListeners();
        $this->resetMetrics();
    }

    public function disable(): void
    {
        $this->enabled = false;
        $this->resetMetrics();
    }

    public function startMonitoring(array $patterns = []): void
    {
        $this->patterns = $patterns;
        $this->enable();
    }

    public function stopMonitoring(): array
    {
        $report = $this->generateReport();
        $this->disable();
        return $report;
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getHitRate(): float
    {
        $total = $this->metrics['hits'] + $this->metrics['misses'];
        return $total > 0 ? ($this->metrics['hits'] / $total) * 100 : 0;
    }

    public function getMissRate(): float
    {
        return 100 - $this->getHitRate();
    }

    public function getTopMissedKeys(int $limit = 10): array
    {
        arsort($this->metrics['missed_keys']);
        return array_slice($this->metrics['missed_keys'], 0, $limit, true);
    }

    public function getTopHitKeys(int $limit = 10): array
    {
        arsort($this->metrics['hit_keys']);
        return array_slice($this->metrics['hit_keys'], 0, $limit, true);
    }

    public function generateReport(): array
    {
        $hitRate = $this->getHitRate();
        $recommendations = $this->generateRecommendations();

        return [
            'summary' => [
                'hits' => $this->metrics['hits'],
                'misses' => $this->metrics['misses'],
                'writes' => $this->metrics['writes'],
                'deletes' => $this->metrics['deletes'],
                'hit_rate' => $hitRate,
                'miss_rate' => $this->getMissRate(),
                'total_operations' => $this->metrics['hits'] + $this->metrics['misses'],
            ],
            'performance' => [
                'top_missed_keys' => $this->getTopMissedKeys(),
                'top_hit_keys' => $this->getTopHitKeys(),
                'key_patterns' => $this->analyzeKeyPatterns(),
            ],
            'recommendations' => $recommendations,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function analyzeModuleCacheUsage(string $moduleId): array
    {
        $moduleKeys = array_filter(
            array_keys($this->metrics['hit_keys']),
            fn($key) => str_starts_with($key, "module:{$moduleId}:")
        );

        $hits = array_sum(array_intersect_key($this->metrics['hit_keys'], array_flip($moduleKeys)));
        $misses = array_sum(array_intersect_key($this->metrics['missed_keys'], array_flip($moduleKeys)));

        return [
            'module_id' => $moduleId,
            'cache_keys' => count($moduleKeys),
            'hits' => $hits,
            'misses' => $misses,
            'hit_rate' => $hits + $misses > 0 ? ($hits / ($hits + $misses)) * 100 : 0,
            'keys' => $moduleKeys,
        ];
    }

    public function monitorQueryCache(): array
    {
        $queryKeys = array_filter(
            array_keys($this->metrics['hit_keys']),
            fn($key) => str_contains($key, 'query:') || str_contains($key, 'sql:')
        );

        $hits = array_sum(array_intersect_key($this->metrics['hit_keys'], array_flip($queryKeys)));
        $misses = array_sum(array_intersect_key($this->metrics['missed_keys'], array_flip($queryKeys)));

        return [
            'query_cache_keys' => count($queryKeys),
            'query_hits' => $hits,
            'query_misses' => $misses,
            'query_hit_rate' => $hits + $misses > 0 ? ($hits / ($hits + $misses)) * 100 : 0,
            'most_cached_queries' => array_slice($queryKeys, 0, 10),
        ];
    }

    public function optimizeCacheKeys(): array
    {
        $suggestions = [];
        $missedKeys = $this->getTopMissedKeys(20);

        foreach ($missedKeys as $key => $count) {
            if ($count > 5) { // Frequently missed keys
                $suggestions[] = [
                    'key' => $key,
                    'misses' => $count,
                    'suggestion' => 'Consider pre-warming this cache key or increasing TTL',
                    'priority' => $count > 20 ? 'high' : 'medium',
                ];
            }
        }

        // Analyze key patterns for optimization
        $patterns = $this->analyzeKeyPatterns();
        foreach ($patterns as $pattern => $data) {
            if ($data['miss_rate'] > 50) {
                $suggestions[] = [
                    'pattern' => $pattern,
                    'miss_rate' => $data['miss_rate'],
                    'suggestion' => 'High miss rate pattern - review caching strategy',
                    'priority' => 'high',
                ];
            }
        }

        return $suggestions;
    }

    public function trackCacheEfficiency(): array
    {
        $efficiency = [
            'memory_efficiency' => $this->calculateMemoryEfficiency(),
            'time_efficiency' => $this->calculateTimeEfficiency(),
            'storage_efficiency' => $this->calculateStorageEfficiency(),
        ];

        return $efficiency;
    }

    private function registerEventListeners(): void
    {
        if (!$this->enabled) {
            return;
        }

        // Listen for cache events
        app('events')->listen(CacheHit::class, function (CacheHit $event) {
            $this->handleCacheHit($event);
        });

        app('events')->listen(CacheMissed::class, function (CacheMissed $event) {
            $this->handleCacheMiss($event);
        });

        app('events')->listen(KeyWritten::class, function (KeyWritten $event) {
            $this->handleKeyWritten($event);
        });

        app('events')->listen(KeyDeleted::class, function (KeyDeleted $event) {
            $this->handleKeyDeleted($event);
        });
    }

    private function handleCacheHit(CacheHit $event): void
    {
        if (!$this->shouldTrackKey($event->key)) {
            return;
        }

        $this->metrics['hits']++;
        $this->metrics['hit_keys'][$event->key] = ($this->metrics['hit_keys'][$event->key] ?? 0) + 1;
    }

    private function handleCacheMiss(CacheMissed $event): void
    {
        if (!$this->shouldTrackKey($event->key)) {
            return;
        }

        $this->metrics['misses']++;
        $this->metrics['missed_keys'][$event->key] = ($this->metrics['missed_keys'][$event->key] ?? 0) + 1;
    }

    private function handleKeyWritten(KeyWritten $event): void
    {
        if (!$this->shouldTrackKey($event->key)) {
            return;
        }

        $this->metrics['writes']++;
        $this->metrics['written_keys'][$event->key] = now()->toISOString();
    }

    private function handleKeyDeleted(KeyDeleted $event): void
    {
        if (!$this->shouldTrackKey($event->key)) {
            return;
        }

        $this->metrics['deletes']++;
        $this->metrics['deleted_keys'][$event->key] = now()->toISOString();
    }

    private function shouldTrackKey(string $key): bool
    {
        if (empty($this->patterns)) {
            return true;
        }

        foreach ($this->patterns as $pattern) {
            if (fnmatch($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    private function resetMetrics(): void
    {
        $this->metrics = [
            'hits' => 0,
            'misses' => 0,
            'writes' => 0,
            'deletes' => 0,
            'hit_keys' => [],
            'missed_keys' => [],
            'written_keys' => [],
            'deleted_keys' => [],
        ];
    }

    private function analyzeKeyPatterns(): array
    {
        $patterns = [];

        // Analyze common key prefixes
        $allKeys = array_merge(
            array_keys($this->metrics['hit_keys']),
            array_keys($this->metrics['missed_keys'])
        );

        foreach ($allKeys as $key) {
            $prefix = $this->extractKeyPrefix($key);

            if (!isset($patterns[$prefix])) {
                $patterns[$prefix] = [
                    'hits' => 0,
                    'misses' => 0,
                    'keys' => [],
                ];
            }

            $patterns[$prefix]['keys'][] = $key;
            $patterns[$prefix]['hits'] += $this->metrics['hit_keys'][$key] ?? 0;
            $patterns[$prefix]['misses'] += $this->metrics['missed_keys'][$key] ?? 0;
        }

        // Calculate metrics for each pattern
        foreach ($patterns as $prefix => &$data) {
            $total = $data['hits'] + $data['misses'];
            $data['total_operations'] = $total;
            $data['hit_rate'] = $total > 0 ? ($data['hits'] / $total) * 100 : 0;
            $data['miss_rate'] = $total > 0 ? ($data['misses'] / $total) * 100 : 0;
            $data['key_count'] = count(array_unique($data['keys']));
        }

        return $patterns;
    }

    private function extractKeyPrefix(string $key): string
    {
        // Extract meaningful prefix from cache key
        $parts = explode(':', $key);
        return $parts[0] ?? $key;
    }

    private function generateRecommendations(): array
    {
        $recommendations = [];
        $hitRate = $this->getHitRate();

        if ($hitRate < 70) {
            $recommendations[] = [
                'type' => 'low_hit_rate',
                'severity' => 'high',
                'message' => "Cache hit rate is {$hitRate}%. Consider reviewing cache strategy.",
                'action' => 'Increase TTL for frequently accessed data or implement cache warming.',
            ];
        }

        if ($this->metrics['misses'] > $this->metrics['hits']) {
            $recommendations[] = [
                'type' => 'high_miss_rate',
                'severity' => 'high',
                'message' => 'Cache misses exceed hits.',
                'action' => 'Review cache keys and implement better caching patterns.',
            ];
        }

        $topMissed = $this->getTopMissedKeys(5);
        if (!empty($topMissed)) {
            $recommendations[] = [
                'type' => 'frequent_misses',
                'severity' => 'medium',
                'message' => 'Some keys are frequently missed.',
                'action' => 'Consider pre-warming these keys: ' . implode(', ', array_keys($topMissed)),
            ];
        }

        return $recommendations;
    }

    private function calculateMemoryEfficiency(): float
    {
        // Placeholder for memory efficiency calculation
        return 85.0; // Would implement actual memory usage analysis
    }

    private function calculateTimeEfficiency(): float
    {
        // Placeholder for time efficiency calculation
        return 92.0; // Would implement actual time savings analysis
    }

    private function calculateStorageEfficiency(): float
    {
        // Placeholder for storage efficiency calculation
        return 78.0; // Would implement actual storage usage analysis
    }
}