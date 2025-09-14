<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Health\ValueObjects;

enum HealthStatus: string
{
    case Healthy = 'healthy';
    case Warning = 'warning';
    case Critical = 'critical';

    public function getColor(): string
    {
        return match ($this) {
            self::Healthy => 'green',
            self::Warning => 'yellow',
            self::Critical => 'red',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Healthy => '✅',
            self::Warning => '⚠️',
            self::Critical => '❌',
        };
    }

    public function isHealthy(): bool
    {
        return $this === self::Healthy;
    }

    public function isCritical(): bool
    {
        return $this === self::Critical;
    }
}