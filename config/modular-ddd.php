<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Modules Path
    |--------------------------------------------------------------------------
    |
    | This defines the path where your modules are stored. By default, it's
    | set to 'modules' directory in your project root.
    |
    */
    'modules_path' => env('MODULAR_DDD_MODULES_PATH', base_path('modules')),

    /*
    |--------------------------------------------------------------------------
    | Module Registry Storage
    |--------------------------------------------------------------------------
    |
    | Define where to store the module registry information.
    |
    */
    'registry_storage' => env('MODULAR_DDD_REGISTRY_STORAGE', storage_path('framework/modular-ddd')),

    /*
    |--------------------------------------------------------------------------
    | Auto Discovery
    |--------------------------------------------------------------------------
    |
    | Whether to automatically discover modules on boot.
    |
    */
    'auto_discovery' => env('MODULAR_DDD_AUTO_DISCOVERY', true),

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configure module caching behavior.
    |
    */
    'cache' => [
        'enabled' => env('MODULAR_DDD_CACHE_ENABLED', true),
        'ttl' => env('MODULAR_DDD_CACHE_TTL', 3600),
        'key_prefix' => env('MODULAR_DDD_CACHE_PREFIX', 'modular_ddd'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Configure performance monitoring and metrics collection.
    |
    */
    'monitoring' => [
        'enabled' => env('MODULAR_DDD_MONITORING_ENABLED', true),
        'metrics_storage' => env('MODULAR_DDD_METRICS_STORAGE', 'redis'),
        'performance_threshold' => env('MODULAR_DDD_PERFORMANCE_THRESHOLD', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configure security scanning and validation.
    |
    */
    'security' => [
        'enabled' => env('MODULAR_DDD_SECURITY_ENABLED', true),
        'quarantine_enabled' => env('MODULAR_DDD_QUARANTINE_ENABLED', true),
        'signature_verification' => env('MODULAR_DDD_SIGNATURE_VERIFICATION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Bus Configuration
    |--------------------------------------------------------------------------
    |
    | Configure inter-module event communication.
    |
    */
    'event_bus' => [
        'driver' => env('MODULAR_DDD_EVENT_DRIVER', 'sync'),
        'async_queue' => env('MODULAR_DDD_EVENT_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for development environment.
    |
    */
    'development' => [
        'hot_reload' => env('MODULAR_DDD_HOT_RELOAD', false),
        'file_watching' => env('MODULAR_DDD_FILE_WATCHING', false),
    ],
];