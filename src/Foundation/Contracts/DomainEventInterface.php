<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation\Contracts;

use DateTimeImmutable;

interface DomainEventInterface
{
    public function getEventId(): string;

    public function getOccurredOn(): DateTimeImmutable;

    public function getEventVersion(): int;

    public function getEventType(): string;

    public function getPayload(): array;
}
