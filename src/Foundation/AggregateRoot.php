<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation;

use TaiCrm\LaravelModularDdd\Foundation\Contracts\DomainEventInterface;
use Illuminate\Support\Collection;

abstract class AggregateRoot
{
    private Collection $domainEvents;

    public function __construct()
    {
        $this->domainEvents = collect();
    }

    protected function recordEvent(DomainEventInterface $event): void
    {
        $this->domainEvents->push($event);
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
        $classParts = explode('\\', get_class($event));
        $eventName = end($classParts);
        return 'apply' . $eventName;
    }
}