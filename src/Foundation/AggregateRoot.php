<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation;

use Illuminate\Support\Collection;
use TaiCrm\LaravelModularDdd\Foundation\Contracts\DomainEventInterface;

abstract class AggregateRoot
{
    private Collection $domainEvents;

    public function __construct()
    {
        $this->domainEvents = collect();
    }

    public function releaseEvents(): Collection
    {
        $events = $this->domainEvents;
        $this->domainEvents = collect();

        return $events;
    }

    public function getUncommittedEvents(): Collection
    {
        return $this->domainEvents;
    }

    public function hasUncommittedEvents(): bool
    {
        return $this->domainEvents->isNotEmpty();
    }

    public function clearEvents(): void
    {
        $this->domainEvents = collect();
    }

    protected function recordEvent(DomainEventInterface $event): void
    {
        $this->domainEvents->push($event);
    }

    protected function apply(DomainEventInterface $event): void
    {
        $this->recordEvent($event);
        $this->handleEvent($event);
    }

    protected function handleEvent(DomainEventInterface $event): void
    {
        $method = $this->getApplyMethod($event);

        if (method_exists($this, $method)) {
            $this->{$method}($event);
        }
    }

    private function getApplyMethod(DomainEventInterface $event): string
    {
        $classParts = explode('\\', $event::class);
        $eventName = end($classParts);

        return 'apply' . $eventName;
    }
}
