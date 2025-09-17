<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Monitoring;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnhancedPerformanceMiddleware
{
    private array $metrics = [];

    public function __construct(
        private QueryPerformanceAnalyzer $queryAnalyzer,
        private CachePerformanceMonitor $cacheMonitor,
        private ModuleResourceMonitor $resourceMonitor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Start monitoring
        $this->queryAnalyzer->startMonitoring();
        $this->cacheMonitor->startMonitoring(['*']);

        // Track request details
        $requestId = uniqid('req_', true);
        $this->metrics[$requestId] = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'start_time' => $startTime,
            'start_memory' => $startMemory,
        ];

        try {
            $response = $next($request);

            // Calculate metrics
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);

            $this->metrics[$requestId]['end_time'] = $endTime;
            $this->metrics[$requestId]['execution_time'] = ($endTime - $startTime) * 1000;
            $this->metrics[$requestId]['memory_usage'] = $endMemory - $startMemory;
            $this->metrics[$requestId]['response_size'] = strlen($response->getContent());
            $this->metrics[$requestId]['status_code'] = $response->getStatusCode();

            // Stop monitoring and get results
            $queryMetrics = $this->queryAnalyzer->stopMonitoring();
            $cacheMetrics = $this->cacheMonitor->stopMonitoring();

            $this->metrics[$requestId]['query_metrics'] = $queryMetrics;
            $this->metrics[$requestId]['cache_metrics'] = $cacheMetrics;

            // Add performance headers
            $this->addPerformanceHeaders($response, $this->metrics[$requestId]);

            // Log performance metrics
            $this->logPerformanceMetrics($requestId, $this->metrics[$requestId]);

            // Check for performance issues
            $this->checkPerformanceThresholds($requestId, $this->metrics[$requestId]);

            return $response;
        } catch (Exception $e) {
            // Log error with performance context
            $this->logErrorWithMetrics($requestId, $e);

            throw $e;
        } finally {
            // Cleanup
            unset($this->metrics[$requestId]);
        }
    }

    public function terminate(Request $request, Response $response): void
    {
        // Additional cleanup or async processing can happen here
    }

    private function addPerformanceHeaders(Response $response, array $metrics): void
    {
        $response->headers->set('X-Response-Time', round($metrics['execution_time'], 2) . 'ms');
        $response->headers->set('X-Memory-Usage', $this->formatBytes($metrics['memory_usage']));
        $response->headers->set('X-Query-Count', $metrics['query_metrics']['summary']['total_queries'] ?? 0);
        $response->headers->set('X-Cache-Hit-Rate', round($metrics['cache_metrics']['summary']['hit_rate'] ?? 0, 2) . '%');

        if (isset($metrics['query_metrics']['summary']['slow_queries']) && $metrics['query_metrics']['summary']['slow_queries'] > 0) {
            $response->headers->set('X-Slow-Queries', $metrics['query_metrics']['summary']['slow_queries']);
        }
    }

    private function logPerformanceMetrics(string $requestId, array $metrics): void
    {
        $logData = [
            'request_id' => $requestId,
            'url' => $metrics['url'],
            'method' => $metrics['method'],
            'status_code' => $metrics['status_code'],
            'execution_time' => $metrics['execution_time'],
            'memory_usage' => $metrics['memory_usage'],
            'query_count' => $metrics['query_metrics']['summary']['total_queries'] ?? 0,
            'slow_queries' => $metrics['query_metrics']['summary']['slow_queries'] ?? 0,
            'cache_hit_rate' => $metrics['cache_metrics']['summary']['hit_rate'] ?? 0,
        ];

        // Log at different levels based on performance
        if ($metrics['execution_time'] > 5000) { // 5 seconds
            Log::error('Very slow request detected', $logData);
        } elseif ($metrics['execution_time'] > 2000) { // 2 seconds
            Log::warning('Slow request detected', $logData);
        } else {
            Log::info('Request completed', $logData);
        }
    }

    private function checkPerformanceThresholds(string $requestId, array $metrics): void
    {
        $issues = [];

        // Check execution time
        if ($metrics['execution_time'] > 1000) {
            $issues[] = "Slow execution time: {$metrics['execution_time']}ms";
        }

        // Check memory usage
        if ($metrics['memory_usage'] > 50 * 1024 * 1024) { // 50MB
            $issues[] = 'High memory usage: ' . $this->formatBytes($metrics['memory_usage']);
        }

        // Check query performance
        $queryMetrics = $metrics['query_metrics'];
        if (isset($queryMetrics['summary']['slow_queries']) && $queryMetrics['summary']['slow_queries'] > 0) {
            $issues[] = "Slow queries detected: {$queryMetrics['summary']['slow_queries']}";
        }

        if (isset($queryMetrics['summary']['total_queries']) && $queryMetrics['summary']['total_queries'] > 50) {
            $issues[] = "High query count: {$queryMetrics['summary']['total_queries']}";
        }

        // Check N+1 queries
        if (isset($queryMetrics['n_plus_one_queries']) && count($queryMetrics['n_plus_one_queries']) > 0) {
            $issues[] = 'N+1 queries detected: ' . count($queryMetrics['n_plus_one_queries']);
        }

        // Check cache performance
        $cacheMetrics = $metrics['cache_metrics'];
        if (isset($cacheMetrics['summary']['hit_rate']) && $cacheMetrics['summary']['hit_rate'] < 70) {
            $issues[] = "Low cache hit rate: {$cacheMetrics['summary']['hit_rate']}%";
        }

        if (!empty($issues)) {
            Log::warning('Performance issues detected', [
                'request_id' => $requestId,
                'url' => $metrics['url'],
                'issues' => $issues,
                'metrics' => $metrics,
            ]);

            // Optionally trigger alerts or notifications
            $this->triggerPerformanceAlert($requestId, $issues, $metrics);
        }
    }

    private function logErrorWithMetrics(string $requestId, Exception $e): void
    {
        $metrics = $this->metrics[$requestId] ?? [];

        Log::error('Request failed with performance context', [
            'request_id' => $requestId,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'execution_time' => isset($metrics['start_time']) ? (microtime(true) - $metrics['start_time']) * 1000 : null,
            'memory_usage' => isset($metrics['start_memory']) ? memory_get_usage(true) - $metrics['start_memory'] : null,
            'url' => $metrics['url'] ?? null,
            'method' => $metrics['method'] ?? null,
        ]);
    }

    private function triggerPerformanceAlert(string $requestId, array $issues, array $metrics): void
    {
        // Implement alerting logic here
        // This could send notifications, trigger webhooks, etc.

        $alertData = [
            'type' => 'performance_alert',
            'request_id' => $requestId,
            'severity' => $this->calculateSeverity($issues, $metrics),
            'issues' => $issues,
            'url' => $metrics['url'],
            'execution_time' => $metrics['execution_time'],
            'timestamp' => now()->toISOString(),
        ];

        // Example: Log alert (in real implementation, this might send to monitoring service)
        Log::alert('Performance alert triggered', $alertData);
    }

    private function calculateSeverity(array $issues, array $metrics): string
    {
        $executionTime = $metrics['execution_time'] ?? 0;
        $memoryUsage = $metrics['memory_usage'] ?? 0;

        if ($executionTime > 10000 || $memoryUsage > 200 * 1024 * 1024) {
            return 'critical';
        }

        if ($executionTime > 5000 || $memoryUsage > 100 * 1024 * 1024) {
            return 'high';
        }

        if ($executionTime > 2000 || count($issues) > 2) {
            return 'medium';
        }

        return 'low';
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
