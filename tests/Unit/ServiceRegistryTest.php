<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Unit;

use TaiCrm\LaravelModularDdd\Communication\ServiceRegistry;
use TaiCrm\LaravelModularDdd\Tests\TestCase;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;
use Mockery;

class ServiceRegistryTest extends TestCase
{
    private ServiceRegistry $serviceRegistry;
    private $mockContainer;
    private $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockContainer = Mockery::mock(Container::class);
        $this->mockLogger = Mockery::mock(LoggerInterface::class);

        $this->serviceRegistry = new ServiceRegistry($this->mockContainer, $this->mockLogger);
    }

    public function test_can_register_service(): void
    {
        $this->mockLogger->shouldReceive('info')->once();
        $this->mockContainer->shouldReceive('bound')->andReturn(false);
        $this->mockContainer->shouldReceive('bind')->once();

        $this->serviceRegistry->register('TestService', 'TestImplementation', 'TestModule');

        $this->assertTrue($this->serviceRegistry->exists('TestService'));
        $this->assertEquals('TestImplementation', $this->serviceRegistry->getImplementation('TestService'));
        $this->assertEquals('TestModule', $this->serviceRegistry->getModule('TestService'));
    }

    public function test_can_resolve_registered_service(): void
    {
        $mockService = new \stdClass();

        $this->mockLogger->shouldReceive('info')->once();
        $this->mockContainer->shouldReceive('bound')->andReturn(false);
        $this->mockContainer->shouldReceive('bind')->once();
        $this->mockContainer->shouldReceive('make')->with('TestService')->andReturn($mockService);

        $this->serviceRegistry->register('TestService', 'TestImplementation', 'TestModule');

        $resolved = $this->serviceRegistry->resolve('TestService');

        $this->assertSame($mockService, $resolved);
    }

    public function test_returns_null_for_unregistered_service(): void
    {
        $resolved = $this->serviceRegistry->resolve('NonexistentService');

        $this->assertNull($resolved);
    }

    public function test_can_unregister_service(): void
    {
        $this->mockLogger->shouldReceive('info')->twice();
        $this->mockContainer->shouldReceive('bound')->andReturn(false);
        $this->mockContainer->shouldReceive('bind')->once();

        $this->serviceRegistry->register('TestService', 'TestImplementation', 'TestModule');
        $this->assertTrue($this->serviceRegistry->exists('TestService'));

        $this->serviceRegistry->unregister('TestService', 'TestModule');
        $this->assertFalse($this->serviceRegistry->exists('TestService'));
    }

    public function test_cannot_unregister_service_from_different_module(): void
    {
        $this->mockLogger->shouldReceive('info')->once();
        $this->mockLogger->shouldReceive('warning')->once();
        $this->mockContainer->shouldReceive('bound')->andReturn(false);
        $this->mockContainer->shouldReceive('bind')->once();

        $this->serviceRegistry->register('TestService', 'TestImplementation', 'ModuleA');

        $this->serviceRegistry->unregister('TestService', 'ModuleB');

        // Service should still exist since it belongs to ModuleA
        $this->assertTrue($this->serviceRegistry->exists('TestService'));
    }

    public function test_can_get_services_by_module(): void
    {
        $this->mockLogger->shouldReceive('info')->times(3);
        $this->mockContainer->shouldReceive('bound')->andReturn(false);
        $this->mockContainer->shouldReceive('bind')->times(3);

        $this->serviceRegistry->register('Service1', 'Implementation1', 'ModuleA');
        $this->serviceRegistry->register('Service2', 'Implementation2', 'ModuleA');
        $this->serviceRegistry->register('Service3', 'Implementation3', 'ModuleB');

        $moduleAServices = $this->serviceRegistry->getServicesByModule('ModuleA');
        $moduleBServices = $this->serviceRegistry->getServicesByModule('ModuleB');

        $this->assertCount(2, $moduleAServices);
        $this->assertCount(1, $moduleBServices);
    }

    public function test_can_clear_module_services(): void
    {
        $this->mockLogger->shouldReceive('info')->times(4); // 2 registrations + 2 for clearing
        $this->mockContainer->shouldReceive('bound')->andReturn(false);
        $this->mockContainer->shouldReceive('bind')->times(2);

        $this->serviceRegistry->register('Service1', 'Implementation1', 'ModuleA');
        $this->serviceRegistry->register('Service2', 'Implementation2', 'ModuleA');

        $this->assertTrue($this->serviceRegistry->exists('Service1'));
        $this->assertTrue($this->serviceRegistry->exists('Service2'));

        $this->serviceRegistry->clearModule('ModuleA');

        $this->assertFalse($this->serviceRegistry->exists('Service1'));
        $this->assertFalse($this->serviceRegistry->exists('Service2'));
    }

    public function test_can_clear_all_services(): void
    {
        $this->mockLogger->shouldReceive('info')->times(3);
        $this->mockContainer->shouldReceive('bound')->andReturn(false);
        $this->mockContainer->shouldReceive('bind')->times(2);

        $this->serviceRegistry->register('Service1', 'Implementation1', 'ModuleA');
        $this->serviceRegistry->register('Service2', 'Implementation2', 'ModuleB');

        $this->assertTrue($this->serviceRegistry->exists('Service1'));
        $this->assertTrue($this->serviceRegistry->exists('Service2'));

        $this->serviceRegistry->clear();

        $this->assertFalse($this->serviceRegistry->exists('Service1'));
        $this->assertFalse($this->serviceRegistry->exists('Service2'));
    }

    public function test_handles_resolution_errors_gracefully(): void
    {
        $this->mockLogger->shouldReceive('info')->once();
        $this->mockLogger->shouldReceive('error')->once();
        $this->mockContainer->shouldReceive('bound')->andReturn(false);
        $this->mockContainer->shouldReceive('bind')->once();
        $this->mockContainer->shouldReceive('make')->andThrow(new \Exception('Resolution failed'));

        $this->serviceRegistry->register('TestService', 'TestImplementation', 'TestModule');

        $resolved = $this->serviceRegistry->resolve('TestService');

        $this->assertNull($resolved);
    }
}