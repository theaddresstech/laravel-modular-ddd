<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Monitoring;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Cache\CacheManager;
use Illuminate\Queue\QueueManager;
use Psr\Log\LoggerInterface;

class MetricsCollector
{
    public function __construct(
        private ModuleManagerInterface $moduleManager,
        private ConnectionInterface $db,
        private CacheManager $cache,
        private QueueManager $queue,
        private LoggerInterface $logger
    ) {}

    public function collectSystemMetrics(): array
    {
        return [
            'timestamp' => now(),
            'modules' => $this->collectModuleMetrics(),
            'database' => $this->collectDatabaseMetrics(),
            'cache' => $this->collectCacheMetrics(),
            'queue' => $this->collectQueueMetrics(),
            'memory' => $this->collectMemoryMetrics(),
            'performance' => $this->collectPerformanceMetrics(),
        ];
    }

    public function collectModuleMetrics(): array
    {
        $modules = $this->moduleManager->list();

        $metrics = [
            'total_modules' => $modules->count(),
            'enabled_modules' => $modules->filter->isEnabled()->count(),
            'disabled_modules' => $modules->reject->isEnabled()->count(),
            'modules' => [],
        ];

        foreach ($modules as $module) {
            $moduleData = [
                'name' => $module->name,
                'version' => $module->version,
                'status' => $module->status->value,
                'is_enabled' => $module->isEnabled(),
                'path' => $module->path,
                'dependencies' => $module->dependencies,
                'size' => $this->calculateModuleSize($module->path),
                'files_count' => $this->countModuleFiles($module->path),
                'last_modified' => $this->getLastModified($module->path),
            ];

            // Add database metrics if module has tables
            $moduleData['database'] = $this->collectModuleDatabaseMetrics($module->name);

            $metrics['modules'][] = $moduleData;
        }

        return $metrics;
    }

    public function collectDatabaseMetrics(): array
    {
        try {
            $connectionName = $this->db->getName();
            $databaseName = $this->db->getDatabaseName();

            $metrics = [
                'connection' => $connectionName,
                'database' => $databaseName,
                'tables_count' => $this->getTablesCount(),
                'total_rows' => $this->getTotalRows(),
                'database_size' => $this->getDatabaseSize(),
                'index_usage' => $this->getIndexUsage(),
                'slow_queries' => $this->getSlowQueriesCount(),
            ];

            // Add per-module table metrics
            $modules = $this->moduleManager->list();
            foreach ($modules as $module) {
                $moduleMetrics = $this->collectModuleDatabaseMetrics($module->name);
                if (!empty($moduleMetrics['tables'])) {
                    $metrics['modules'][$module->name] = $moduleMetrics;
                }
            }

            return $metrics;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to collect database metrics', [
                'error' => $e->getMessage()
            ]);

            return [
                'error' => 'Unable to collect database metrics',
                'reason' => $e->getMessage(),
            ];
        }
    }

    public function collectCacheMetrics(): array
    {
        try {
            $store = $this->cache->getDefaultDriver();
            $cacheStore = $this->cache->store($store);

            $metrics = [
                'default_driver' => $store,
                'stores' => [],
            ];

            // Collect Redis metrics if available
            if ($store === 'redis') {
                $metrics['redis'] = $this->collectRedisMetrics();
            }

            // Collect module-specific cache metrics
            $modules = $this->moduleManager->list();
            foreach ($modules as $module) {
                $moduleCache = $this->collectModuleCacheMetrics($module->name);
                if (!empty($moduleCache)) {
                    $metrics['modules'][$module->name] = $moduleCache;
                }
            }

            return $metrics;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to collect cache metrics', [
                'error' => $e->getMessage()
            ]);

            return [
                'error' => 'Unable to collect cache metrics',
                'reason' => $e->getMessage(),
            ];
        }
    }

    public function collectQueueMetrics(): array
    {
        try {
            $defaultConnection = $this->queue->getDefaultDriver();

            $metrics = [
                'default_connection' => $defaultConnection,
                'connections' => [],
                'jobs' => [
                    'pending' => $this->getPendingJobsCount(),
                    'failed' => $this->getFailedJobsCount(),
                    'processed_today' => $this->getProcessedJobsTodayCount(),
                ],
            ];

            return $metrics;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to collect queue metrics', [
                'error' => $e->getMessage()
            ]);

            return [
                'error' => 'Unable to collect queue metrics',
                'reason' => $e->getMessage(),
            ];
        }
    }

    public function collectMemoryMetrics(): array
    {
        return [
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'limit' => $this->parseMemoryLimit(ini_get('memory_limit')),
            'usage_percentage' => $this->calculateMemoryUsagePercentage(),
        ];
    }

    public function collectPerformanceMetrics(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_load' => $this->getServerLoad(),
            'uptime' => $this->getSystemUptime(),
            'disk_usage' => $this->getDiskUsage(),
            'opcache' => $this->getOpcacheStatus(),
        ];
    }

    private function collectModuleDatabaseMetrics(string $moduleName): array
    {
        $moduleSnake = strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst($moduleName)));
        $tablePrefix = $moduleSnake . '_';

        try {
            $tables = $this->db->select("
                SELECT
                    table_name,
                    table_rows,
                    data_length,
                    index_length,
                    (data_length + index_length) as total_size
                FROM information_schema.tables
                WHERE table_schema = ?
                AND table_name LIKE ?
            ", [$this->db->getDatabaseName(), $tablePrefix . '%']);

            $metrics = [
                'tables' => [],
                'total_size' => 0,
                'total_rows' => 0,
            ];

            foreach ($tables as $table) {
                $tableData = [
                    'name' => $table->table_name,
                    'rows' => $table->table_rows ?? 0,
                    'data_size' => $table->data_length ?? 0,
                    'index_size' => $table->index_length ?? 0,
                    'total_size' => $table->total_size ?? 0,
                ];

                $metrics['tables'][] = $tableData;
                $metrics['total_size'] += $tableData['total_size'];
                $metrics['total_rows'] += $tableData['rows'];
            }

            return $metrics;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function collectModuleCacheMetrics(string $moduleName): array
    {
        $cachePrefix = strtolower($moduleName) . ':*';
        $metrics = [];

        try {
            if ($this->cache->getDefaultDriver() === 'redis') {
                $redis = $this->cache->store('redis')->getRedis();
                $keys = $redis->keys($cachePrefix);

                $metrics = [
                    'keys_count' => count($keys),
                    'memory_usage' => 0,
                ];

                // Calculate approximate memory usage
                foreach ($keys as $key) {
                    $metrics['memory_usage'] += strlen($redis->get($key));
                }
            }
        } catch (\Exception $e) {
            // Ignore cache collection errors
        }

        return $metrics;
    }

    private function collectRedisMetrics(): array
    {
        try {
            $redis = $this->cache->store('redis')->getRedis();
            $info = $redis->info();

            return [
                'version' => $info['redis_version'] ?? 'unknown',
                'uptime_days' => $info['uptime_in_days'] ?? 0,
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory' => $info['used_memory'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? '0B',
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
            ];
        } catch (\Exception $e) {
            return ['error' => 'Unable to collect Redis metrics'];
        }
    }

    private function calculateModuleSize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function countModuleFiles(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        return iterator_count($iterator);
    }

    private function getLastModified(string $path): ?string
    {
        if (!is_dir($path)) {
            return null;
        }

        $latest = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $mtime = $file->getMTime();
            if ($mtime > $latest) {
                $latest = $mtime;
            }
        }

        return $latest > 0 ? date('Y-m-d H:i:s', $latest) : null;
    }

    private function getTablesCount(): int
    {
        try {
            $result = $this->db->selectOne("
                SELECT COUNT(*) as count
                FROM information_schema.tables
                WHERE table_schema = ?
            ", [$this->db->getDatabaseName()]);

            return $result->count ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getTotalRows(): int
    {
        try {
            $result = $this->db->selectOne("
                SELECT SUM(table_rows) as total
                FROM information_schema.tables
                WHERE table_schema = ?
            ", [$this->db->getDatabaseName()]);

            return $result->total ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getDatabaseSize(): int
    {
        try {
            $result = $this->db->selectOne("
                SELECT SUM(data_length + index_length) as size
                FROM information_schema.tables
                WHERE table_schema = ?
            ", [$this->db->getDatabaseName()]);

            return $result->size ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getIndexUsage(): array
    {
        try {
            $results = $this->db->select("
                SELECT
                    table_name,
                    index_name,
                    cardinality
                FROM information_schema.statistics
                WHERE table_schema = ?
                ORDER BY cardinality DESC
                LIMIT 10
            ", [$this->db->getDatabaseName()]);

            return array_map(function ($row) {
                return [
                    'table' => $row->table_name,
                    'index' => $row->index_name,
                    'cardinality' => $row->cardinality,
                ];
            }, $results);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getSlowQueriesCount(): int
    {
        try {
            $result = $this->db->selectOne("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
            return (int)($result->Value ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getPendingJobsCount(): int
    {
        try {
            $result = $this->db->selectOne("SELECT COUNT(*) as count FROM jobs");
            return $result->count ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getFailedJobsCount(): int
    {
        try {
            $result = $this->db->selectOne("SELECT COUNT(*) as count FROM failed_jobs");
            return $result->count ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getProcessedJobsTodayCount(): int
    {
        try {
            $result = $this->db->selectOne("
                SELECT COUNT(*) as count
                FROM jobs
                WHERE created_at >= ?
                AND completed_at IS NOT NULL
            ", [now()->startOfDay()]);

            return $result->count ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return -1; // Unlimited
        }

        $unit = strtoupper(substr($limit, -1));
        $value = (int)substr($limit, 0, -1);

        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => (int)$limit,
        };
    }

    private function calculateMemoryUsagePercentage(): float
    {
        $current = memory_get_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));

        if ($limit === -1) {
            return 0; // Unlimited
        }

        return ($current / $limit) * 100;
    }

    private function getServerLoad(): ?array
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => $load[0] ?? null,
                '5min' => $load[1] ?? null,
                '15min' => $load[2] ?? null,
            ];
        }

        return null;
    }

    private function getSystemUptime(): ?string
    {
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $seconds = (float)explode(' ', $uptime)[0];
            return $this->formatUptime($seconds);
        }

        return null;
    }

    private function formatUptime(float $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return sprintf('%d days, %d hours, %d minutes', $days, $hours, $minutes);
    }

    private function getDiskUsage(): array
    {
        $path = base_path();

        return [
            'total' => disk_total_space($path),
            'free' => disk_free_space($path),
            'used' => disk_total_space($path) - disk_free_space($path),
        ];
    }

    private function getOpcacheStatus(): array
    {
        if (!function_exists('opcache_get_status')) {
            return ['enabled' => false];
        }

        $status = opcache_get_status(false);

        if (!$status) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'memory_usage' => $status['memory_usage'] ?? [],
            'opcache_statistics' => $status['opcache_statistics'] ?? [],
        ];
    }
}