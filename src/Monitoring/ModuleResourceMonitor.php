<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Monitoring;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ModuleResourceMonitor
{
    private array $metrics = [];
    private array $thresholds;

    public function __construct(
        private ModuleManagerInterface $moduleManager
    ) {
        $this->thresholds = [
            'memory_usage' => 128 * 1024 * 1024, // 128MB
            'execution_time' => 5000, // 5 seconds in milliseconds
            'file_count' => 1000,
            'class_count' => 100,
        ];
    }

    public function startMonitoring(string $moduleId): void
    {
        $this->metrics[$moduleId] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_peak_memory' => memory_get_peak_usage(true),
            'initial_included_files' => count(get_included_files()),
        ];
    }

    public function stopMonitoring(string $moduleId): array
    {
        if (!isset($this->metrics[$moduleId])) {
            return [];
        }

        $startMetrics = $this->metrics[$moduleId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);
        $endIncludedFiles = count(get_included_files());

        $metrics = [
            'module_id' => $moduleId,
            'execution_time' => ($endTime - $startMetrics['start_time']) * 1000, // milliseconds
            'memory_usage' => $endMemory - $startMetrics['start_memory'],
            'peak_memory_usage' => $endPeakMemory - $startMetrics['start_peak_memory'],
            'files_loaded' => $endIncludedFiles - $startMetrics['initial_included_files'],
            'timestamp' => now()->toISOString(),
        ];

        $this->analyzeMetrics($metrics);
        $this->storeMetrics($moduleId, $metrics);

        unset($this->metrics[$moduleId]);

        return $metrics;
    }

    public function getModuleResourceUsage(string $moduleId): array
    {
        $module = $this->moduleManager->get($moduleId);

        if (!$module) {
            return [];
        }

        $modulePath = $module->path;
        $resourceUsage = [
            'module_id' => $moduleId,
            'module_path' => $modulePath,
            'disk_usage' => $this->calculateDiskUsage($modulePath),
            'file_count' => $this->countFiles($modulePath),
            'class_count' => $this->countClasses($modulePath),
            'dependency_count' => count($module->dependencies ?? []),
            'route_count' => $this->countRoutes($modulePath),
            'last_modified' => $this->getLastModified($modulePath),
        ];

        return $resourceUsage;
    }

    public function getAllModulesResourceUsage(): array
    {
        $modules = $this->moduleManager->list();
        $usage = [];

        foreach ($modules as $module) {
            $usage[$module->name] = $this->getModuleResourceUsage($module->name);
        }

        return $usage;
    }

    public function generateResourceReport(): array
    {
        $allUsage = $this->getAllModulesResourceUsage();
        $totalUsage = $this->calculateTotalUsage($allUsage);
        $recommendations = $this->generateResourceRecommendations($allUsage);

        return [
            'summary' => $totalUsage,
            'modules' => $allUsage,
            'recommendations' => $recommendations,
            'thresholds' => $this->thresholds,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function monitorModuleLoad(string $moduleId): array
    {
        $this->startMonitoring($moduleId);

        try {
            // Simulate module loading process
            $module = $this->moduleManager->get($moduleId);

            if (!$module) {
                throw new \Exception("Module {$moduleId} not found");
            }

            // Monitor actual module loading
            $loadStartTime = microtime(true);
            $this->moduleManager->enable($moduleId);
            $loadTime = (microtime(true) - $loadStartTime) * 1000;

            $metrics = $this->stopMonitoring($moduleId);
            $metrics['load_time'] = $loadTime;
            $metrics['status'] = 'success';

            return $metrics;
        } catch (\Exception $e) {
            $metrics = $this->stopMonitoring($moduleId);
            $metrics['status'] = 'error';
            $metrics['error'] = $e->getMessage();

            Log::error("Module load monitoring failed", [
                'module_id' => $moduleId,
                'error' => $e->getMessage(),
                'metrics' => $metrics,
            ]);

            return $metrics;
        }
    }

    public function setThreshold(string $metric, $value): void
    {
        $this->thresholds[$metric] = $value;
    }

    public function getThresholds(): array
    {
        return $this->thresholds;
    }

    private function analyzeMetrics(array $metrics): void
    {
        $warnings = [];

        if ($metrics['execution_time'] > $this->thresholds['execution_time']) {
            $warnings[] = "High execution time: {$metrics['execution_time']}ms";
        }

        if ($metrics['memory_usage'] > $this->thresholds['memory_usage']) {
            $warnings[] = "High memory usage: " . $this->formatBytes($metrics['memory_usage']);
        }

        if (!empty($warnings)) {
            Log::warning("Module performance warning", [
                'module_id' => $metrics['module_id'],
                'warnings' => $warnings,
                'metrics' => $metrics,
            ]);
        }
    }

    private function storeMetrics(string $moduleId, array $metrics): void
    {
        $key = "module_metrics:{$moduleId}:" . date('Y-m-d-H');
        $existingMetrics = Cache::get($key, []);
        $existingMetrics[] = $metrics;

        // Keep only last 100 entries per hour
        if (count($existingMetrics) > 100) {
            $existingMetrics = array_slice($existingMetrics, -100);
        }

        Cache::put($key, $existingMetrics, 86400); // Store for 24 hours
    }

    private function calculateDiskUsage(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    private function countFiles(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $count++;
            }
        }

        return $count;
    }

    private function countClasses(string $path): int
    {
        $count = 0;
        $phpFiles = glob($path . '/**/*.php', GLOB_BRACE);

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            $count += preg_match_all('/^\s*(class|interface|trait)\s+\w+/m', $content);
        }

        return $count;
    }

    private function countRoutes(string $path): int
    {
        $routeFiles = [
            $path . '/Routes/api.php',
            $path . '/Routes/web.php',
        ];

        $count = 0;
        foreach ($routeFiles as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $count += preg_match_all('/Route::(get|post|put|patch|delete|options|any|apiResource|resource)/', $content);
            }
        }

        return $count;
    }

    private function getLastModified(string $path): string
    {
        if (!is_dir($path)) {
            return '';
        }

        $lastModified = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $lastModified = max($lastModified, $file->getMTime());
            }
        }

        return $lastModified > 0 ? date('Y-m-d H:i:s', $lastModified) : '';
    }

    private function calculateTotalUsage(array $allUsage): array
    {
        $total = [
            'total_modules' => count($allUsage),
            'total_disk_usage' => 0,
            'total_file_count' => 0,
            'total_class_count' => 0,
            'total_route_count' => 0,
        ];

        foreach ($allUsage as $usage) {
            $total['total_disk_usage'] += $usage['disk_usage'] ?? 0;
            $total['total_file_count'] += $usage['file_count'] ?? 0;
            $total['total_class_count'] += $usage['class_count'] ?? 0;
            $total['total_route_count'] += $usage['route_count'] ?? 0;
        }

        return $total;
    }

    private function generateResourceRecommendations(array $allUsage): array
    {
        $recommendations = [];

        foreach ($allUsage as $moduleId => $usage) {
            if (($usage['file_count'] ?? 0) > $this->thresholds['file_count']) {
                $recommendations[] = [
                    'module' => $moduleId,
                    'type' => 'high_file_count',
                    'severity' => 'medium',
                    'message' => "Module has {$usage['file_count']} files, consider refactoring.",
                ];
            }

            if (($usage['class_count'] ?? 0) > $this->thresholds['class_count']) {
                $recommendations[] = [
                    'module' => $moduleId,
                    'type' => 'high_class_count',
                    'severity' => 'medium',
                    'message' => "Module has {$usage['class_count']} classes, consider splitting.",
                ];
            }

            if (($usage['disk_usage'] ?? 0) > 10 * 1024 * 1024) { // 10MB
                $recommendations[] = [
                    'module' => $moduleId,
                    'type' => 'high_disk_usage',
                    'severity' => 'low',
                    'message' => "Module uses " . $this->formatBytes($usage['disk_usage']) . " disk space.",
                ];
            }
        }

        return $recommendations;
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