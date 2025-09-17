<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Unit;

use Exception;
use Illuminate\Contracts\Container\Container;
use Mockery;
use Psr\Log\LoggerInterface;
use stdClass;
use TaiCrm\LaravelModularDdd\Communication\ServiceRegistry;
use TaiCrm\LaravelModularDdd\Tests\TestCase;

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

    public function testCanRegisterService(): void
    {
        $this->mockLogger->shouldReceive('info')->once();
        $this->mockContainer->shouldReceive('bound')->andReturn(false);
        $this->mockContainer->shouldReceive('bind')->once();

        $this->serviceRegistry->register('TestService', 'TestImplementation', 'TestModule');

        $this->assertTrue($this->serviceRegistry->exists('TestService'));
        $this->assertSame('TestImplementation', $this->serviceRegistry->getImplementation('TestService'));
        $this->assertSame('TestModule', $this->serviceRegistry->getModule('TestService'));
    }

    public function testCanResolveRegisteredService(): void
    {
        $mockService = new stdClass();

        $this->mockLogger->shouldReceive('info')->once();
        $this->mockContainer->shouldReceive('bound')->andReturn(false);
        $this->mockContainer->shouldReceive('bind')->once();
        $this->mockContainer->shouldReceive('make')->with('TestService')->andReturn($mockService);

        $this->serviceRegistry->register('TestService', 'TestImplementation', 'TestModule');

        $resolved = $this->serviceRegistry->resolve('TestService');

        $this->assertSame($mockService, $resolved);
    }

    public function testReturnsNullForUnregisteredService(): void
    {
        $resolved = $this->serviceRegistry->resolve('NonexistentService');

        $this->assertNull($resolved);
    }

    public function testCanUnregisterService(): void
    {
        $this->mockLogger->shouldReceive('info')->twice();
        $this->mockContainer->shouldReceive('bound')->andReturn(false);
        $this->mockContainer->shouldReceive('bind')->once();

        $this->serviceRegistry->register('TestService', 'TestImplementation', 'TestModule');
        $this->assertTrue($this->serviceRegistry->exists('TestService'));

        $this->serviceRegistry->unregister('TestService', 'TestModule');
        $this->assertFalse($this->serviceRegistry->exists('TestService'));
    }

    public function testCannotUnregisterServiceFromDifferentModule(): void
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

    public function testCanGetServicesByModule(): void
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

    public function testCanClearModuleServices(): void
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

    public function testCanClearAllServices(): void
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

    public function testHandlesResolutionErrorsGracefully(): void
    {
        $this->mockLogger->shouldReceive('info')->once();
        $this->mockLogger->shouldReceive('error')->once();
        $this->mockContainer->shouldReceive('bound')->andReturn(false);
        $this->mockContainer->shouldReceive('bind')->once();
        $this->mockContainer->shouldReceive('make')->andThrow(new Exception('Resolution failed'));

        $this->serviceRegistry->register('TestService', 'TestImplementation', 'TestModule');

        $resolved = $this->serviceRegistry->resolve('TestService');

        $this->assertNull($resolved);
    }
}
