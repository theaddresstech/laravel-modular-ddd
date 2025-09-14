<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Health\ValueObjects;

use TaiCrm\LaravelModularDdd\Foundation\ValueObject;
use Carbon\Carbon;

readonly class HealthReport extends ValueObject
{
    public function __construct(
        public string $moduleName,
        public HealthStatus $status,
        public array $checks,
        public Carbon $timestamp
    ) {}

    public static function failed(string $moduleName, string $message): self
    {
        return new self(
            moduleName: $moduleName,
            status: HealthStatus::Critical,
            checks: [
                [
                    'name' => 'ModuleValidation',
                    'status' => HealthStatus::Critical,
                    'message' => $message,
                    'details' => [],
                ]
            ],
            timestamp: now()
        );
    }

    public function equals(object $other): bool
    {
        return $other instanceof self &&
               $this->moduleName === $other->moduleName &&
               $this->status === $other->status &&
               $this->timestamp->equalTo($other->timestamp);
    }

    public function isHealthy(): bool
    {
        return $this->status->isHealthy();
    }

    public function isCritical(): bool
    {
        return $this->status->isCritical();
    }

    public function hasWarnings(): bool
    {
        return $this->status === HealthStatus::Warning ||
               collect($this->checks)->contains('status', HealthStatus::Warning);
    }

    public function getHealthyChecks(): array
    {
        return array_filter($this->checks, fn($check) => $check['status'] === HealthStatus::Healthy);
    }

    public function getWarningChecks(): array
    {
        return array_filter($this->checks, fn($check) => $check['status'] === HealthStatus::Warning);
    }

    public function getCriticalChecks(): array
    {
        return array_filter($this->checks, fn($check) => $check['status'] === HealthStatus::Critical);
    }

    public function toArray(): array
    {
        return [
            'module_name' => $this->moduleName,
            'status' => $this->status->value,
            'checks' => $this->checks,
            'timestamp' => $this->timestamp->toISOString(),
            'summary' => [
                'total_checks' => count($this->checks),
                'healthy' => count($this->getHealthyChecks()),
                'warnings' => count($this->getWarningChecks()),
                'critical' => count($this->getCriticalChecks()),
            ],
        ];
    }
}