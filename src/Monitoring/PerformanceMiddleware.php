<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Monitoring;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class PerformanceMiddleware
{
    public function __construct(
        private ModulePerformanceMonitor $monitor,
    ) {}

    public function handle(Request $request, Closure $next): BaseResponse
    {
        // Start timing
        $timerId = $this->monitor->startTimer(
            $this->getOperationName($request),
            [
                'method' => $request->method(),
                'url' => $request->url(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        );

        $response = $next($request);

        // End timing and record metrics
        $metrics = $this->monitor->endTimer($timerId);

        // Add error context if response indicates an error
        if ($response->getStatusCode() >= 400) {
            $metrics['context']['error'] = true;
            $metrics['context']['status_code'] = $response->getStatusCode();
        }

        // Add response size if available
        if ($response instanceof Response) {
            $content = $response->getContent();
            if ($content !== false) {
                $metrics['context']['response_size'] = strlen($content);
            }
        }

        // Re-record with additional context
        $this->monitor->recordMetric($metrics);

        // Add performance headers to response
        $response->headers->set('X-Performance-Duration', number_format($metrics['duration'] * 1000, 2) . 'ms');
        $response->headers->set('X-Performance-Memory', $this->formatBytes($metrics['memory_used']));

        return $response;
    }

    private function getOperationName(Request $request): string
    {
        // Try to identify module from route
        $route = $request->route();
        if ($route) {
            $action = $route->getAction();
            if (isset($action['controller'])) {
                $controller = $action['controller'];
                if (preg_match('/Modules\\\\(\w+)\\\\/', $controller, $matches)) {
                    return "module.{$matches[1]}.{$request->method()}." . str_replace('/', '.', trim($request->getPathInfo(), '/'));
                }
            }
        }

        // Fallback to generic operation name
        return "http.{$request->method()}." . str_replace('/', '.', trim($request->getPathInfo(), '/'));
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
