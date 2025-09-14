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
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure API versioning and routing for modules.
    |
    */
    'api' => [
        // Legacy single version (deprecated, use versions.default instead)
        'version' => env('MODULAR_DDD_API_VERSION', 'v1'),
        'prefix' => env('MODULAR_DDD_API_PREFIX', 'api'),
        'middleware' => ['api'],

        // Multi-version support configuration
        'versions' => [
            // List of supported API versions
            'supported' => env('MODULAR_DDD_API_SUPPORTED_VERSIONS', 'v1,v2')
                ? explode(',', env('MODULAR_DDD_API_SUPPORTED_VERSIONS', 'v1,v2'))
                : ['v1', 'v2'],

            // Default version to use when none specified
            'default' => env('MODULAR_DDD_API_DEFAULT_VERSION', 'v1'),

            // Latest/recommended version
            'latest' => env('MODULAR_DDD_API_LATEST_VERSION', 'v1'),

            // Deprecated versions (still supported but with warnings)
            'deprecated' => env('MODULAR_DDD_API_DEPRECATED_VERSIONS', 'v1')
                ? explode(',', env('MODULAR_DDD_API_DEPRECATED_VERSIONS', 'v1'))
                : ['v1'],

            // Sunset dates for deprecated versions (YYYY-MM-DD format)
            'sunset_dates' => [
                'v1' => env('MODULAR_DDD_API_V1_SUNSET_DATE', '2025-12-31'),
            ],
        ],

        // Version negotiation configuration
        'negotiation' => [
            // Priority order: url, header, query, default
            'strategy' => env('MODULAR_DDD_API_NEGOTIATION_STRATEGY', 'url,header,query,default'),

            // URL patterns for version detection
            'url_patterns' => [
                // Pattern: /api/v1/endpoint, /api/v2.1/endpoint
                'versioned_prefix' => true,
                // Pattern: /api/endpoint?version=v1
                'query_parameter' => ['api_version', 'version', 'v'],
            ],

            // Header names to check for version
            'headers' => [
                'Accept-Version',
                'X-API-Version',
                'Api-Version',
            ],

            // Accept header version parsing (e.g., application/vnd.api+json;version=2)
            'accept_header_parsing' => true,
        ],

        // Backward compatibility settings
        'compatibility' => [
            // Enable automatic response transformation between versions
            'auto_transform' => env('MODULAR_DDD_API_AUTO_TRANSFORM', true),

            // Enable request transformation (upgrade old requests to new format)
            'request_transformation' => env('MODULAR_DDD_API_REQUEST_TRANSFORM', true),

            // Enable response transformation (downgrade new responses to old format)
            'response_transformation' => env('MODULAR_DDD_API_RESPONSE_TRANSFORM', true),

            // Transformation cache TTL (seconds)
            'transform_cache_ttl' => env('MODULAR_DDD_API_TRANSFORM_CACHE_TTL', 3600),
        ],

        // Documentation and discovery
        'documentation' => [
            // API documentation URL
            'url' => env('MODULAR_DDD_API_DOCS_URL', '/api/docs'),

            // Version discovery endpoint
            'discovery_endpoint' => env('MODULAR_DDD_API_DISCOVERY_ENDPOINT', '/api/versions'),

            // Include deprecation notices in responses
            'include_deprecation_notices' => env('MODULAR_DDD_API_INCLUDE_DEPRECATION', true),
        ],
    ],

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