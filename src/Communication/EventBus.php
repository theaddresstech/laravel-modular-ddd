<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Communication;

use TaiCrm\LaravelModularDdd\Foundation\Contracts\DomainEventInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

class EventBus
{
    private Collection $handlers;

    public function __construct(
        private Dispatcher $dispatcher,
        private LoggerInterface $logger
    ) {
        $this->handlers = collect();
    }

    public function dispatch(DomainEventInterface $event): void
    {
        $this->logger->info("Dispatching event: " . $event->getEventType(), [
            'event_id' => $event->getEventId(),
            'event_type' => $event->getEventType(),
            'payload' => $event->getPayload(),
        ]);

        try {
            // Dispatch through Laravel's event system
            $this->dispatcher->dispatch($event->getEventType(), $event);

            // Also dispatch to registered handlers
            $this->dispatchToHandlers($event);

        } catch (\Exception $e) {
            $this->logger->error("Error dispatching event: " . $e->getMessage(), [
                'event_id' => $event->getEventId(),
                'event_type' => $event->getEventType(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    public function dispatchMany(Collection $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }

    public function subscribe(string $eventType, callable $handler): void
    {
        if (!$this->handlers->has($eventType)) {
            $this->handlers->put($eventType, collect());
        }

        $this->handlers->get($eventType)->push($handler);
    }

    public function unsubscribe(string $eventType, ?callable $handler = null): void
    {
        if (!$this->handlers->has($eventType)) {
            return;
        }

        if ($handler === null) {
            $this->handlers->forget($eventType);
            return;
        }

        $handlers = $this->handlers->get($eventType);
        $this->handlers->put($eventType, $handlers->reject(fn($h) => $h === $handler));
    }

    public function getSubscribers(string $eventType): Collection
    {
        return $this->handlers->get($eventType, collect());
    }

    public function hasSubscribers(string $eventType): bool
    {
        return $this->handlers->has($eventType) && $this->handlers->get($eventType)->isNotEmpty();
    }

    public function clearSubscribers(): void
    {
        $this->handlers = collect();
    }

    private function dispatchToHandlers(DomainEventInterface $event): void
    {
        $eventType = $event->getEventType();
        $handlers = $this->getSubscribers($eventType);

        foreach ($handlers as $handler) {
            try {
                $handler($event);
            } catch (\Exception $e) {
                $this->logger->error("Error in event handler: " . $e->getMessage(), [
                    'event_id' => $event->getEventId(),
                    'event_type' => $eventType,
                    'handler' => is_object($handler) ? get_class($handler) : 'closure',
                    'exception' => $e,
                ]);

                // Continue processing other handlers
            }
        }
    }
}