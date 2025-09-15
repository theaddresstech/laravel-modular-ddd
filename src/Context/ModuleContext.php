<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Context;

/**
 * Enumeration of module loading contexts
 */
enum ModuleContext: string
{
    case API = 'api';
    case WEB = 'web';
    case CLI = 'cli';
    case ADMIN = 'admin';
    case TESTING = 'testing';
    case QUEUE = 'queue';
    case BROADCAST = 'broadcast';
    case SCHEDULE = 'schedule';
    case MAINTENANCE = 'maintenance';

    /**
     * Get all available contexts
     */
    public static function all(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Detect current context based on environment
     */
    public static function detect(): array
    {
        $contexts = [];

        // CLI context detection
        if (app()->runningInConsole()) {
            $contexts[] = self::CLI;

            // More specific CLI contexts
            if (app()->runningUnitTests()) {
                $contexts[] = self::TESTING;
            } elseif (self::isQueueWorker()) {
                $contexts[] = self::QUEUE;
            } elseif (self::isScheduledTask()) {
                $contexts[] = self::SCHEDULE;
            } elseif (self::isMaintenanceMode()) {
                $contexts[] = self::MAINTENANCE;
            }
        } else {
            // Web request contexts
            $request = app('request');

            if ($request && str_starts_with($request->path(), 'api/')) {
                $contexts[] = self::API;
            }

            if ($request && str_starts_with($request->path(), 'admin/')) {
                $contexts[] = self::ADMIN;
            }

            if ($request && !in_array(self::API, $contexts) && !in_array(self::ADMIN, $contexts)) {
                $contexts[] = self::WEB;
            }

            // Broadcasting context
            if (self::isBroadcastRequest()) {
                $contexts[] = self::BROADCAST;
            }
        }

        return array_map(fn($context) => $context->value, $contexts);
    }

    /**
     * Get context priority (lower number = higher priority)
     */
    public function getPriority(): int
    {
        return match ($this) {
            self::CLI, self::TESTING => 1,
            self::API => 2,
            self::WEB => 3,





            self::ADMIN => 4,
            self::QUEUE => 5,
            self::BROADCAST => 6,
            self::SCHEDULE => 7,
            self::MAINTENANCE => 8,
        };
    }

    /**
     * Check if this context requires eager loading
     */
    public function requiresEagerLoading(): bool
    {
        return match ($this) {
            self::CLI, self::TESTING, self::MAINTENANCE => true,
            default => false,
        };
    }

    /**
     * Check if this context supports lazy loading
     */
    public function supportsLazyLoading(): bool
    {
        return match ($this) {
            self::API, self::WEB, self::ADMIN => true,
            default => false,
        };
    }

    /**
     * Get memory constraints for this context
     */
    public function getMemoryConstraints(): array
    {
        return match ($this) {
            self::CLI, self::TESTING => [
                'max_memory' => '512M',
                'gc_threshold' => 1000,
            ],
            self::API => [
                'max_memory' => '128M',
                'gc_threshold' => 100,
            ],
            self::WEB => [
                'max_memory' => '256M',
                'gc_threshold' => 500,
            ],
            self::ADMIN => [
                'max_memory' => '256M',
                'gc_threshold' => 500,
            ],
            self::QUEUE => [
                'max_memory' => '1G',
                'gc_threshold' => 2000,
            ],
            default => [
                'max_memory' => '128M',
                'gc_threshold' => 100,
            ],
        };
    }

    private static function isQueueWorker(): bool
    {
        return isset($_SERVER['argv']) && in_array('queue:work', $_SERVER['argv'], true);
    }

    private static function isScheduledTask(): bool
    {
        return isset($_SERVER['argv']) && in_array('schedule:run', $_SERVER['argv'], true);
    }

    private static function isMaintenanceMode(): bool
    {
        return app()->isDownForMaintenance();
    }

    private static function isBroadcastRequest(): bool
    {
        $request = app('request');
        return $request && str_contains($request->path(), 'broadcasting/');
    }
}