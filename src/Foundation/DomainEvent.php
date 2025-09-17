<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use TaiCrm\LaravelModularDdd\Foundation\Contracts\DomainEventInterface;

abstract readonly class DomainEvent implements DomainEventInterface
{
    private string $eventId;
    private DateTimeImmutable $occurredOn;
    private int $eventVersion;

    public function __construct()
    {
        $this->eventId = Uuid::uuid4()->toString();
        $this->occurredOn = new DateTimeImmutable();
        $this->eventVersion = 1;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function getEventVersion(): int
    {
        return $this->eventVersion;
    }

    public function getEventType(): string
    {
        $reflection = new ReflectionClass($this);

        return $reflection->getShortName();
    }

    abstract public function getPayload(): array;

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->getEventType(),
            'occurred_on' => $this->occurredOn->format('Y-m-d H:i:s.u'),
            'event_version' => $this->eventVersion,
            'payload' => $this->getPayload(),
        ];
    }
}
