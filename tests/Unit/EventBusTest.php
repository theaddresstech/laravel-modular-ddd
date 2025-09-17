<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Unit;

use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Mockery;
use Psr\Log\LoggerInterface;
use TaiCrm\LaravelModularDdd\Foundation\EventBus;
use TaiCrm\LaravelModularDdd\Foundation\Contracts\DomainEventInterface;
use TaiCrm\LaravelModularDdd\Tests\TestCase;

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

    public function testCanSubscribeToEvent(): void
    {
        $handler = static fn () => 'handled';

        $this->eventBus->subscribe('TestEvent', $handler);

        $this->assertTrue($this->eventBus->hasSubscribers('TestEvent'));
        $this->assertCount(1, $this->eventBus->getSubscribers('TestEvent'));
    }

    public function testCanDispatchEventToSubscribers(): void
    {
        $handlerCalled = false;
        $handler = static function () use (&$handlerCalled): void {
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

    public function testCanUnsubscribeFromEvent(): void
    {
        $handler = static fn () => 'handled';

        $this->eventBus->subscribe('TestEvent', $handler);
        $this->assertTrue($this->eventBus->hasSubscribers('TestEvent'));

        $this->eventBus->unsubscribe('TestEvent');
        $this->assertFalse($this->eventBus->hasSubscribers('TestEvent'));
    }

    public function testCanDispatchMultipleEvents(): void
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

    public function testHandlesSubscriberExceptionsGracefully(): void
    {
        $handler = static function (): void {
            throw new Exception('Handler error');
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

    public function testCanClearAllSubscribers(): void
    {
        $this->eventBus->subscribe('Event1', static fn () => null);
        $this->eventBus->subscribe('Event2', static fn () => null);

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
