<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Monitoring\CachePerformanceMonitor;
use TaiCrm\LaravelModularDdd\Monitoring\ModuleResourceMonitor;
use TaiCrm\LaravelModularDdd\Monitoring\QueryPerformanceAnalyzer;

class ModulePerformanceAnalyzeCommand extends Command
{
    protected $signature = 'module:performance:analyze
                            {--module= : Analyze specific module}
                            {--type=all : Analysis type (queries|cache|resources|all)}
                            {--export= : Export results to file}
                            {--watch : Continuous monitoring mode}
                            {--duration=60 : Watch duration in seconds}';
    protected $description = 'Analyze module performance metrics and generate optimization recommendations';

    public function __construct(
        private QueryPerformanceAnalyzer $queryAnalyzer,
        private CachePerformanceMonitor $cacheMonitor,
        private ModuleResourceMonitor $resourceMonitor,
        private ModuleManagerInterface $moduleManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->option('module');
        $type = $this->option('type');
        $export = $this->option('export');
        $watch = $this->option('watch');
        $duration = (int) $this->option('duration');

        $this->info('🔍 Module Performance Analysis');
        $this->line('');

        if ($watch) {
            return $this->runWatchMode($module, $type, $duration);
        }

        $results = $this->runAnalysis($module, $type);

        $this->displayResults($results);

        if ($export) {
            $this->exportResults($results, $export);
        }

        return 0;
    }

    private function runWatchMode(?string $module = null, string $type = 'all', int $duration = 60): int
    {
        $this->info("📊 Starting continuous monitoring for {$duration} seconds...");
        $this->newLine();

        // Start monitoring
        $this->queryAnalyzer->startMonitoring();
        $this->cacheMonitor->startMonitoring();

        if ($module) {
            $this->resourceMonitor->startMonitoring($module);
        }

        $startTime = time();
        $endTime = $startTime + $duration;

        $this->output->progressStart($duration);

        while (time() < $endTime) {
            sleep(1);
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
        $this->newLine();

        // Stop monitoring and get results
        $queryResults = $this->queryAnalyzer->stopMonitoring();
        $cacheResults = $this->cacheMonitor->stopMonitoring();

        $results = [
            'watch_duration' => $duration,
            'query_analysis' => $queryResults,
            'cache_analysis' => $cacheResults,
        ];

        if ($module) {
            $results['resource_analysis'] = $this->resourceMonitor->stopMonitoring($module);
        }

        $this->displayWatchResults($results);

        return 0;
    }

    private function runAnalysis(?string $module = null, string $type = 'all'): array
    {
        $results = [];

        if ($type === 'all' || $type === 'queries') {
            $this->info('🔍 Analyzing query performance...');
            $results['query_analysis'] = $this->analyzeQueries($module);
        }

        if ($type === 'all' || $type === 'cache') {
            $this->info('🔍 Analyzing cache performance...');
            $results['cache_analysis'] = $this->analyzeCache($module);
        }

        if ($type === 'all' || $type === 'resources') {
            $this->info('🔍 Analyzing resource usage...');
            $results['resource_analysis'] = $this->analyzeResources($module);
        }

        return $results;
    }

    private function analyzeQueries(?string $module = null): array
    {
        // For static analysis, we'd examine cached query data
        $this->queryAnalyzer->startMonitoring();

        // Simulate some queries if in analysis mode
        if ($module) {
            $moduleData = $this->moduleManager->get($module);
            if ($moduleData) {
                // Trigger module loading to capture queries
                $this->moduleManager->enable($module);
            }
        }

        // Get results after brief monitoring
        sleep(1);

        return $this->queryAnalyzer->stopMonitoring();
    }

    private function analyzeCache(?string $module = null): array
    {
        $this->cacheMonitor->startMonitoring();

        if ($module) {
            // Analyze module-specific cache patterns
            $moduleCache = $this->cacheMonitor->analyzeModuleCacheUsage($module);

            return ['module_cache' => $moduleCache];
        }

        sleep(1);

        return $this->cacheMonitor->stopMonitoring();
    }

    private function analyzeResources(?string $module = null): array
    {
        if ($module) {
            return $this->resourceMonitor->getModuleResourceUsage($module);
        }

        return $this->resourceMonitor->generateResourceReport();
    }

    private function displayResults(array $results): void
    {
        foreach ($results as $type => $data) {
            $this->displayAnalysisSection($type, $data);
        }

        $this->displayRecommendations($results);
    }

    private function displayWatchResults(array $results): void
    {
        $this->info("📊 Monitoring Results ({$results['watch_duration']}s)");
        $this->newLine();

        if (isset($results['query_analysis'])) {
            $this->displayQueryMetrics($results['query_analysis']);
        }

        if (isset($results['cache_analysis'])) {
            $this->displayCacheMetrics($results['cache_analysis']);
        }

        $this->displayRecommendations($results);
    }

    private function displayAnalysisSection(string $type, array $data): void
    {
        switch ($type) {
            case 'query_analysis':
                $this->displayQueryMetrics($data);

                break;
            case 'cache_analysis':
                $this->displayCacheMetrics($data);

                break;
            case 'resource_analysis':
                $this->displayResourceMetrics($data);

                break;
        }
    }

    private function displayQueryMetrics(array $data): void
    {
        $this->info('📊 Query Performance Metrics');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if (isset($data['summary'])) {
            $summary = $data['summary'];
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Queries', $summary['total_queries'] ?? 0],
                    ['Average Time', round($summary['average_time'] ?? 0, 2) . 'ms'],
                    ['Total Time', round($summary['total_time'] ?? 0, 2) . 'ms'],
                    ['Slow Queries', $summary['slow_queries'] ?? 0],
                    ['Slow Query Threshold', $summary['slow_query_threshold'] ?? 0 . 'ms'],
                ],
            );
        }

        if (isset($data['slow_queries']) && !empty($data['slow_queries'])) {
            $this->warn('⚠️  Slow Queries Detected:');
            foreach (array_slice($data['slow_queries'], 0, 5) as $query) {
                $this->line("  • {$query['time']}ms: " . substr($query['query'], 0, 80) . '...');
            }
        }

        if (isset($data['n_plus_one_queries']) && !empty($data['n_plus_one_queries'])) {
            $this->error('🚨 N+1 Query Patterns Detected:');
            foreach ($data['n_plus_one_queries'] as $pattern) {
                $this->line("  • {$pattern['count']} repetitions ({$pattern['total_time']}ms total)");
            }
        }

        $this->newLine();
    }

    private function displayCacheMetrics(array $data): void
    {
        $this->info('🗄️  Cache Performance Metrics');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if (isset($data['summary'])) {
            $summary = $data['summary'];
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Cache Hits', $summary['hits'] ?? 0],
                    ['Cache Misses', $summary['misses'] ?? 0],
                    ['Hit Rate', round($summary['hit_rate'] ?? 0, 2) . '%'],
                    ['Miss Rate', round($summary['miss_rate'] ?? 0, 2) . '%'],
                    ['Total Operations', $summary['total_operations'] ?? 0],
                ],
            );
        }

        if (isset($data['performance']['top_missed_keys'])) {
            $this->warn('⚠️  Most Missed Cache Keys:');
            foreach (array_slice($data['performance']['top_missed_keys'], 0, 5, true) as $key => $count) {
                $this->line("  • {$key}: {$count} misses");
            }
        }

        $this->newLine();
    }

    private function displayResourceMetrics(array $data): void
    {
        $this->info('💾 Resource Usage Metrics');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if (isset($data['summary'])) {
            $summary = $data['summary'];
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Modules', $summary['total_modules'] ?? 0],
                    ['Total Disk Usage', $this->formatBytes($summary['total_disk_usage'] ?? 0)],
                    ['Total Files', $summary['total_file_count'] ?? 0],
                    ['Total Classes', $summary['total_class_count'] ?? 0],
                    ['Total Routes', $summary['total_route_count'] ?? 0],
                ],
            );
        } elseif (isset($data['module_id'])) {
            // Single module metrics
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Module ID', $data['module_id']],
                    ['Disk Usage', $this->formatBytes($data['disk_usage'] ?? 0)],
                    ['File Count', $data['file_count'] ?? 0],
                    ['Class Count', $data['class_count'] ?? 0],
                    ['Route Count', $data['route_count'] ?? 0],
                    ['Dependencies', $data['dependency_count'] ?? 0],
                ],
            );
        }

        $this->newLine();
    }

    private function displayRecommendations(array $results): void
    {
        $this->info('💡 Performance Recommendations');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $recommendations = [];

        foreach ($results as $type => $data) {
            if (isset($data['recommendations'])) {
                $recommendations = array_merge($recommendations, $data['recommendations']);
            }
        }

        if (empty($recommendations)) {
            $this->info('✅ No performance issues detected!');

            return;
        }

        foreach ($recommendations as $rec) {
            $icon = match ($rec['severity'] ?? 'low') {
                'critical' => '🔴',
                'high' => '🟠',
                'medium' => '🟡',
                default => '🟢'
            };

            $this->line("{$icon} [{$rec['severity']}] {$rec['message']}");
            if (isset($rec['action'])) {
                $this->line("   Action: {$rec['action']}");
            }
            $this->newLine();
        }
    }

    private function exportResults(array $results, string $filename): void
    {
        $exportData = [
            'analysis_timestamp' => now()->toISOString(),
            'analysis_type' => $this->option('type'),
            'target_module' => $this->option('module'),
            'results' => $results,
        ];

        $json = json_encode($exportData, JSON_PRETTY_PRINT);
        file_put_contents($filename, $json);

        $this->info("📄 Results exported to: {$filename}");
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
