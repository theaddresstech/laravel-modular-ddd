<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Unit;

use TaiCrm\LaravelModularDdd\Communication\EventBus;
use TaiCrm\LaravelModularDdd\Foundation\Contracts\DomainEventInterface;
use TaiCrm\LaravelModularDdd\Tests\TestCase;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Mockery;

class EventBusTest extends TestCase
{
    private EventBus $eventBus;
    private $mockDispatcher;
    private $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDispatcher = Mockery::mock(Dispatcher::class);
        $this->mockLogger = Mockery::mock(LoggerInterface::class);

        $this->eventBus = new EventBus($this->mockDispatcher, $this->mockLogger);
    }

    public function test_can_subscribe_to_event(): void
    {
        $handler = fn() => 'handled';

        $this->eventBus->subscribe('TestEvent', $handler);

        $this->assertTrue($this->eventBus->hasSubscribers('TestEvent'));
        $this->assertCount(1, $this->eventBus->getSubscribers('TestEvent'));
    }

    public function test_can_dispatch_event_to_subscribers(): void
    {
        $handlerCalled = false;
        $handler = function() use (&$handlerCalled) {
            $handlerCalled = true;
        };

        $this->eventBus->subscribe('TestEvent', $handler);

        $mockEvent = Mockery::mock(DomainEventInterface::class);
        $mockEvent->shouldReceive('getEventType')->andReturn('TestEvent');
        $mockEvent->shouldReceive('getEventId')->andReturn('test-id');
        $mockEvent->shouldReceive('getPayload')->andReturn([]);

        $this->mockLogger->shouldReceive('info')->once();
        $this->mockDispatcher->shouldReceive('dispatch')->once();

        $this->eventBus->dispatch($mockEvent);

        $this->assertTrue($handlerCalled);
    }

    public function test_can_unsubscribe_from_event(): void
    {
        $handler = fn() => 'handled';

        $this->eventBus->subscribe('TestEvent', $handler);
        $this->assertTrue($this->eventBus->hasSubscribers('TestEvent'));

        $this->eventBus->unsubscribe('TestEvent');
        $this->assertFalse($this->eventBus->hasSubscribers('TestEvent'));
    }

    public function test_can_dispatch_multiple_events(): void
    {
        $events = collect([
            $this->createMockEvent('Event1'),
            $this->createMockEvent('Event2'),
        ]);

        $this->mockLogger->shouldReceive('info')->twice();
        $this->mockDispatcher->shouldReceive('dispatch')->twice();

        $this->eventBus->dispatchMany($events);

        $this->assertTrue(true); // Test passes if no exceptions thrown
    }

    public function test_handles_subscriber_exceptions_gracefully(): void
    {
        $handler = function() {
            throw new \Exception('Handler error');
        };

        $this->eventBus->subscribe('TestEvent', $handler);

        $mockEvent = $this->createMockEvent('TestEvent');

        $this->mockLogger->shouldReceive('info')->once();
        $this->mockLogger->shouldReceive('error')->once();
        $this->mockDispatcher->shouldReceive('dispatch')->once();

        // Should not throw exception despite handler failing
        $this->eventBus->dispatch($mockEvent);

        $this->assertTrue(true);
    }

    public function test_can_clear_all_subscribers(): void
    {
        $this->eventBus->subscribe('Event1', fn() => null);
        $this->eventBus->subscribe('Event2', fn() => null);

        $this->assertTrue($this->eventBus->hasSubscribers('Event1'));
        $this->assertTrue($this->eventBus->hasSubscribers('Event2'));

        $this->eventBus->clearSubscribers();

        $this->assertFalse($this->eventBus->hasSubscribers('Event1'));
        $this->assertFalse($this->eventBus->hasSubscribers('Event2'));
    }

    private function createMockEvent(string $eventType): DomainEventInterface
    {
        $mockEvent = Mockery::mock(DomainEventInterface::class);
        $mockEvent->shouldReceive('getEventType')->andReturn($eventType);
        $mockEvent->shouldReceive('getEventId')->andReturn('test-id-' . uniqid());
        $mockEvent->shouldReceive('getPayload')->andReturn([]);

        return $mockEvent;
    }
}