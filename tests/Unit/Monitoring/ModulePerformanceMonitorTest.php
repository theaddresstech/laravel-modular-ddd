<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Unit\Monitoring;

use TaiCrm\LaravelModularDdd\Monitoring\ModulePerformanceMonitor;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleState;
use PHPUnit\Framework\TestCase;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Psr\Log\LoggerInterface;
use Illuminate\Support\Collection;

class ModulePerformanceMonitorTest extends TestCase
{
    private ModulePerformanceMonitor $monitor;
    private ModuleManagerInterface $moduleManager;
    private CacheManager $cache;
    private Repository $cacheStore;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moduleManager = $this->createMock(ModuleManagerInterface::class);
        $this->cache = $this->createMock(CacheManager::class);
        $this->cacheStore = $this->createMock(Repository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Setup cache store methods
        $this->cacheStore->method('get')->willReturn([]);
        $this->cacheStore->method('put')->willReturn(true);
        $this->cacheStore->method('forget')->willReturn(true);

        // Make cache manager return our mock store
        $this->cache->method('store')->willReturn($this->cacheStore);

        $this->monitor = new ModulePerformanceMonitor(
            $this->moduleManager,
            $this->cache,
            $this->logger
        );
    }

    public function test_can_start_and_end_timer(): void
    {
        // Act
        $timerId = $this->monitor->startTimer('test.operation', ['key' => 'value']);

        // Wait a small amount to ensure duration > 0
        usleep(1000); // 1ms

        $metrics = $this->monitor->endTimer($timerId);

        // Assert
        $this->assertIsString($timerId);
        $this->assertStringContainsString('test.operation_', $timerId);

        $this->assertArrayHasKey('operation', $metrics);
        $this->assertArrayHasKey('duration', $metrics);
        $this->assertArrayHasKey('memory_used', $metrics);
        $this->assertArrayHasKey('peak_memory', $metrics);
        $this->assertArrayHasKey('context', $metrics);
        $this->assertArrayHasKey('timestamp', $metrics);

        $this->assertEquals('test.operation', $metrics['operation']);
        $this->assertGreaterThan(0, $metrics['duration']);
        $this->assertEquals(['key' => 'value'], $metrics['context']);
    }

    public function test_throws_exception_for_invalid_timer_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timer invalid_id not found');

        $this->monitor->endTimer('invalid_id');
    }

    public function test_can_record_metric(): void
    {
        $metric = [
            'operation' => 'test.operation',
            'duration' => 0.5,
            'memory_used' => 1024,
            'peak_memory' => 2048,
            'context' => ['key' => 'value'],
            'timestamp' => now(),
        ];

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn([]);

        $this->cache->expects($this->once())
            ->method('put')
            ->with(
                $this->stringContains('module_performance_metrics:test.operation'),
                $this->isType('array'),
                $this->anything()
            );

        $this->monitor->recordMetric($metric);
    }

    public function test_logs_slow_operations(): void
    {
        $slowMetric = [
            'operation' => 'slow.operation',
            'duration' => 2.0, // Above default threshold
            'memory_used' => 1024,
            'peak_memory' => 2048,
            'context' => [],
            'timestamp' => now(),
        ];

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Slow module operation detected', $this->arrayHasKey('operation'));

        $this->monitor->recordMetric($slowMetric);
    }

    public function test_can_get_metrics_for_operation(): void
    {
        $cachedMetrics = [
            [
                'operation' => 'test.operation',
                'duration' => 0.1,
                'timestamp' => now(),
            ],
            [
                'operation' => 'test.operation',
                'duration' => 0.2,
                'timestamp' => now(),
            ],
        ];

        $this->cache->expects($this->once())
            ->method('get')
            ->with($this->stringContains('module_performance_metrics:test.operation'))
            ->willReturn($cachedMetrics);

        $metrics = $this->monitor->getMetrics('test.operation');

        $this->assertInstanceOf(Collection::class, $metrics);
        $this->assertCount(2, $metrics);
    }

    public function test_can_get_aggregated_metrics(): void
    {
        $cachedMetrics = [
            [
                'operation' => 'test.operation',
                'duration' => 0.1,
                'memory_used' => 1024,
                'timestamp' => now()->subMinutes(30),
            ],
            [
                'operation' => 'test.operation',
                'duration' => 0.2,
                'memory_used' => 2048,
                'timestamp' => now()->subMinutes(15),
            ],
        ];

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn($cachedMetrics);

        $aggregated = $this->monitor->getAggregatedMetrics('test.operation', '1hour');

        $this->assertArrayHasKey('operation', $aggregated);
        $this->assertArrayHasKey('period', $aggregated);
        $this->assertArrayHasKey('total_operations', $aggregated);
        $this->assertArrayHasKey('average_duration', $aggregated);
        $this->assertArrayHasKey('min_duration', $aggregated);
        $this->assertArrayHasKey('max_duration', $aggregated);
        $this->assertArrayHasKey('average_memory', $aggregated);
        $this->assertArrayHasKey('operations_per_second', $aggregated);
        $this->assertArrayHasKey('percentiles', $aggregated);

        $this->assertEquals('test.operation', $aggregated['operation']);
        $this->assertEquals('1hour', $aggregated['period']);
        $this->assertEquals(2, $aggregated['total_operations']);
        $this->assertEquals(0.15, $aggregated['average_duration']); // (0.1 + 0.2) / 2
        $this->assertEquals(0.1, $aggregated['min_duration']);
        $this->assertEquals(0.2, $aggregated['max_duration']);
    }

    public function test_can_get_module_health(): void
    {
        $modules = collect([
            new ModuleInfo(
                name: 'TestModule',
                displayName: 'Test Module',
                description: 'A test module',
                version: '1.0.0',
                author: 'Test Author',
                dependencies: [],
                optionalDependencies: [],
                conflicts: [],
                provides: [],
                path: '/path/to/module',
                state: ModuleState::Enabled
            ),
        ]);

        $this->moduleManager->expects($this->once())
            ->method('list')
            ->willReturn($modules);

        // Mock cache calls for module metrics
        $this->cache->method('get')->willReturn([]);

        $health = $this->monitor->getModuleHealth();

        $this->assertInstanceOf(Collection::class, $health);
        $this->assertCount(1, $health);

        $moduleHealth = $health->first();
        $this->assertArrayHasKey('module', $moduleHealth);
        $this->assertArrayHasKey('status', $moduleHealth);
        $this->assertArrayHasKey('is_enabled', $moduleHealth);
        $this->assertArrayHasKey('performance', $moduleHealth);
        $this->assertArrayHasKey('health_score', $moduleHealth);

        $this->assertEquals('TestModule', $moduleHealth['module']);
        $this->assertTrue($moduleHealth['is_enabled']);
    }

    public function test_can_export_metrics_as_json(): void
    {
        $this->cache->method('get')->willReturn([]);

        $exported = $this->monitor->exportMetrics('json');

        $this->assertJson($exported);

        $decoded = json_decode($exported, true);
        $this->assertArrayHasKey('timestamp', $decoded);
        $this->assertArrayHasKey('total_modules', $decoded);
    }

    public function test_can_export_metrics_as_csv(): void
    {
        $this->cache->method('get')->willReturn([]);

        $exported = $this->monitor->exportMetrics('csv');

        $this->assertIsString($exported);
        $this->assertStringContainsString('Operation,Duration,Memory,Timestamp', $exported);
    }

    public function test_throws_exception_for_unsupported_export_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported format: invalid');

        $this->monitor->exportMetrics('invalid');
    }

    public function test_can_clear_metrics(): void
    {
        $this->cache->expects($this->once())
            ->method('forget')
            ->with($this->stringContains('module_performance_metrics:test.operation'));

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Performance metrics cleared', ['operation' => 'test.operation']);

        $this->monitor->clearMetrics('test.operation');
    }

    public function test_can_clear_all_metrics(): void
    {
        // Mock Redis keys method for getting all operations
        $this->cache->method('getStore')->willReturnSelf();
        $this->cache->method('getRedis')->willReturnSelf();
        $this->cache->method('keys')->willReturn([
            'module_performance_metrics:operation1',
            'module_performance_metrics:operation2',
        ]);

        $this->cache->expects($this->exactly(2))
            ->method('forget');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Performance metrics cleared', ['operation' => 'all']);

        $this->monitor->clearMetrics();
    }
}