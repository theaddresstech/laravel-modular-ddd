<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use TaiCrm\LaravelModularDdd\Monitoring\ModulePerformanceMonitor;

class ModuleMetricsCommand extends Command
{
    protected $signature = 'module:metrics
                            {operation? : Specific operation to show metrics for}
                            {--period=1hour : Time period (1hour, 1day, 1week, 1month)}
                            {--format=table : Output format (table, json, csv, xml)}
                            {--export= : Export to file}
                            {--system : Show system-wide metrics}
                            {--health : Show module health overview}
                            {--clear : Clear metrics for operation or all if no operation specified}';
    protected $description = 'Display and manage module performance metrics';

    public function __construct(
        private ModulePerformanceMonitor $monitor,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('clear')) {
            return $this->handleClear();
        }

        if ($this->option('health')) {
            return $this->showHealthOverview();
        }

        if ($this->option('system')) {
            return $this->showSystemMetrics();
        }

        $operation = $this->argument('operation');

        if ($operation) {
            return $this->showOperationMetrics($operation);
        }

        return $this->showAllMetrics();
    }

    private function handleClear(): int
    {
        $operation = $this->argument('operation');

        if ($operation) {
            if ($this->confirm("Clear metrics for operation '{$operation}'?")) {
                $this->monitor->clearMetrics($operation);
                $this->info("Metrics cleared for operation: {$operation}");
            }
        } else {
            if ($this->confirm('Clear ALL metrics? This cannot be undone.')) {
                $this->monitor->clearMetrics();
                $this->info('All metrics cleared successfully.');
            }
        }

        return 0;
    }

    private function showHealthOverview(): int
    {
        $this->info('Module Health Overview');
        $this->line('');

        $health = $this->monitor->getModuleHealth();

        if ($health->isEmpty()) {
            $this->warn('No modules found or no metrics available.');

            return 0;
        }

        $rows = $health->map(function ($module) {
            $status = $module['is_enabled'] ? '<info>Enabled</info>' : '<comment>Disabled</comment>';
            $healthScore = $this->getHealthScoreColor($module['health_score']);

            return [
                $module['module'],
                $status,
                $healthScore,
                number_format($module['performance']['average_response_time'] * 1000, 2) . 'ms',
                number_format($module['performance']['total_operations']),
                number_format($module['performance']['error_rate'] * 100, 2) . '%',
                $this->formatBytes($module['performance']['memory_usage']),
            ];
        })->toArray();

        $this->table([
            'Module',
            'Status',
            'Health Score',
            'Avg Response',
            'Operations',
            'Error Rate',
            'Avg Memory',
        ], $rows);

        return 0;
    }

    private function showSystemMetrics(): int
    {
        $metrics = $this->monitor->getSystemMetrics();

        $this->info('System-wide Performance Metrics');
        $this->line('');

        $this->displayKeyValue([
            'Timestamp' => $metrics['timestamp']->format('Y-m-d H:i:s'),
            'Total Modules' => $metrics['total_modules'],
            'Enabled Modules' => $metrics['enabled_modules'],
            'Total Operations' => number_format($metrics['total_operations']),
            'Average Response Time' => number_format($metrics['average_response_time'] * 1000, 2) . 'ms',
        ]);

        $this->line('');
        $this->info('Memory Usage:');
        $memory = $metrics['memory_usage'];
        $this->displayKeyValue([
            'Current' => $this->formatBytes($memory['current']),
            'Peak' => $this->formatBytes($memory['peak']),
            'Limit' => $memory['limit'],
        ]);

        if ($this->option('export')) {
            $this->exportData($metrics, $this->option('export'));
        }

        return 0;
    }

    private function showOperationMetrics(string $operation): int
    {
        $period = $this->option('period');
        $format = $this->option('format');

        $aggregated = $this->monitor->getAggregatedMetrics($operation, $period);

        if (empty($aggregated)) {
            $this->warn("No metrics found for operation: {$operation}");

            return 1;
        }

        $this->info("Metrics for operation: {$operation} (Period: {$period})");
        $this->line('');

        if ($format === 'table') {
            $this->displayOperationTable($aggregated);
        } else {
            $exported = $this->monitor->exportMetrics($format, $operation);
            $this->line($exported);
        }

        if ($this->option('export')) {
            $this->exportOperationData($operation, $aggregated);
        }

        return 0;
    }

    private function showAllMetrics(): int
    {
        $this->info('All Module Operations');
        $this->line('');

        $metrics = $this->monitor->getSystemMetrics();

        if (empty($metrics['operations_summary'])) {
            $this->warn('No metrics available.');

            return 0;
        }

        $rows = [];
        foreach ($metrics['operations_summary'] as $operation => $data) {
            $rows[] = [
                $operation,
                number_format($data['total_operations']),
                number_format($data['average_duration'] * 1000, 2) . 'ms',
                number_format($data['min_duration'] * 1000, 2) . 'ms',
                number_format($data['max_duration'] * 1000, 2) . 'ms',
                $this->formatBytes($data['average_memory']),
                number_format($data['operations_per_second'], 2) . '/s',
            ];
        }

        $this->table([
            'Operation',
            'Count',
            'Avg Time',
            'Min Time',
            'Max Time',
            'Avg Memory',
            'Ops/Sec',
        ], $rows);

        return 0;
    }

    private function displayOperationTable(array $metrics): void
    {
        $this->displayKeyValue([
            'Total Operations' => number_format($metrics['total_operations']),
            'Average Duration' => number_format($metrics['average_duration'] * 1000, 2) . 'ms',
            'Min Duration' => number_format($metrics['min_duration'] * 1000, 2) . 'ms',
            'Max Duration' => number_format($metrics['max_duration'] * 1000, 2) . 'ms',
            'Average Memory' => $this->formatBytes($metrics['average_memory']),
            'Total Memory' => $this->formatBytes($metrics['total_memory']),
            'Operations/Second' => number_format($metrics['operations_per_second'], 2),
        ]);

        if (!empty($metrics['percentiles'])) {
            $this->line('');
            $this->info('Response Time Percentiles:');
            $percentiles = $metrics['percentiles'];
            $this->displayKeyValue([
                '50th percentile' => number_format($percentiles['p50'] * 1000, 2) . 'ms',
                '90th percentile' => number_format($percentiles['p90'] * 1000, 2) . 'ms',
                '95th percentile' => number_format($percentiles['p95'] * 1000, 2) . 'ms',
                '99th percentile' => number_format($percentiles['p99'] * 1000, 2) . 'ms',
            ]);
        }
    }

    private function displayKeyValue(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->line(sprintf('  <comment>%s:</comment> %s', $key, $value));
        }
    }

    private function getHealthScoreColor(int $score): string
    {
        return match (true) {
            $score >= 90 => "<info>{$score}</info>",
            $score >= 70 => "<comment>{$score}</comment>",
            default => "<error>{$score}</error>",
        };
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function exportData(array $data, string $filename): void
    {
        $format = pathinfo($filename, PATHINFO_EXTENSION) ?: 'json';
        $exported = $this->monitor->exportMetrics($format);

        file_put_contents($filename, $exported);
        $this->info("Metrics exported to: {$filename}");
    }

    private function exportOperationData(string $operation, array $data): void
    {
        $filename = $this->option('export');
        $format = pathinfo($filename, PATHINFO_EXTENSION) ?: 'json';

        $content = match ($format) {
            'json' => json_encode($data, JSON_PRETTY_PRINT),
            'csv' => $this->arrayToCSV($data),
            default => json_encode($data, JSON_PRETTY_PRINT)
        };

        file_put_contents($filename, $content);
        $this->info("Operation metrics exported to: {$filename}");
    }

    private function arrayToCSV(array $data): string
    {
        $csv = "Metric,Value\n";
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                $csv .= "{$key},{$value}\n";
            }
        }

        return $csv;
    }
}
