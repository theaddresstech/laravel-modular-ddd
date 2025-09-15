# Installation Guide

This guide walks you through installing and setting up Laravel Modular DDD in your Laravel application.

## System Requirements

- **PHP**: 8.1, 8.2, or 8.3
- **Laravel**: 10.x, 11.x, or 12.x
- **Composer**: 2.0+
- **Database**: MySQL 8.0+, PostgreSQL 13+, or SQLite 3.35+
- **Cache**: Redis (recommended) or Memcached
- **Queue**: Redis, database, or SQS (for event processing)

## Installation Steps

### 1. Install the Package

```bash
composer require mghrby/laravel-modular-ddd
```

### 2. Publish Configuration

```bash
# Publish the main configuration file
php artisan vendor:publish --provider="TaiCrm\LaravelModularDdd\ModularDddServiceProvider" --tag="config"

# Publish stub templates (optional - for customization)
php artisan vendor:publish --provider="TaiCrm\LaravelModularDdd\ModularDddServiceProvider" --tag="stubs"

# Publish migrations (for authorization system)
php artisan vendor:publish --provider="TaiCrm\LaravelModularDdd\ModularDddServiceProvider" --tag="migrations"
```

### 3. Run Migrations

```bash
# Create authorization tables
php artisan migrate
```

### 4. Configure Your Environment

Add these environment variables to your `.env` file:

```env
# Module Configuration
MODULAR_DDD_MODULES_PATH=modules
MODULAR_DDD_AUTO_DISCOVERY=true
MODULAR_DDD_CACHE_ENABLED=true

# API Versioning
MODULAR_DDD_API_PREFIX=api
MODULAR_DDD_API_SUPPORTED_VERSIONS=v1,v2
MODULAR_DDD_API_DEFAULT_VERSION=v2
MODULAR_DDD_API_LATEST_VERSION=v2

# Performance Monitoring
MODULAR_DDD_MONITORING_ENABLED=true
MODULAR_DDD_PERFORMANCE_THRESHOLD=1000

# Security
MODULAR_DDD_SECURITY_ENABLED=true
MODULAR_DDD_SIGNATURE_VERIFICATION=false
```

### 5. Clear and Rebuild Cache

```bash
# Clear application cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Build module registry
php artisan module:cache rebuild
```

## Initial Configuration

### Configure Your User Model

Update your User model to support module permissions:

```php
// app/Models/User.php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use TaiCrm\LaravelModularDdd\Authorization\Traits\HasModulePermissions;

class User extends Authenticatable
{
    use HasModulePermissions;

    // Your existing code...
}
```

### Register Middleware (Optional)

If you want to automatically monitor performance, add the middleware to your HTTP kernel:

```php
// app/Http/Kernel.php
protected $middleware = [
    // ... other middleware
    \TaiCrm\LaravelModularDdd\Monitoring\EnhancedPerformanceMiddleware::class,
];

// For API versioning (already registered automatically)
protected $middlewareAliases = [
    // ... other aliases
    'api.version' => \TaiCrm\LaravelModularDdd\Http\Middleware\ApiVersionMiddleware::class,
];
```

## Verification

### 1. Check Installation

```bash
# Verify the package is installed
php artisan module:list

# Check system health
php artisan module:health --all
```

### 2. Test Basic Functionality

```bash
# Create a test module
php artisan module:make TestModule

# Install and enable it
php artisan module:install TestModule
php artisan module:enable TestModule

# Verify it's working
php artisan module:status TestModule
```

### 3. Test API Versioning

```bash
# Check API version discovery
curl -H "Accept: application/json" http://your-app.local/api/versions
```

Expected response:
```json
{
  "api": {
    "name": "Your Application",
    "description": "Modular Domain-Driven Design API"
  },
  "versions": {
    "current": "v2",
    "latest": "v2",
    "supported": [...]
  }
}
```

## Next Steps

1. **[Create Your First Module](creating-first-module.md)** - Learn how to create and structure modules
2. **[API Development](../guides/api-development.md)** - Build RESTful APIs with versioning
3. **[Performance Monitoring](../guides/performance-monitoring.md)** - Set up performance monitoring
4. **[Authorization Setup](../guides/authorization.md)** - Configure permissions and roles

## Troubleshooting

### Common Issues

#### Module Directory Not Found
```bash
# Ensure the modules directory exists
mkdir -p modules

# Update the path in config if needed
php artisan config:clear
```

#### Permission Denied
```bash
# Set proper permissions for modules directory
chmod -R 755 modules
chown -R www-data:www-data modules  # On Linux
```

#### Cache Issues
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild module cache
php artisan module:cache clear
php artisan module:cache rebuild
```

#### Database Connection Issues
```bash
# Test database connection
php artisan migrate:status

# Run migrations if needed
php artisan migrate
```

### Getting Help

- **Documentation**: Check the [complete documentation](../README.md)
- **GitHub Issues**: Report bugs at [GitHub Repository](https://github.com/theaddresstech/laravel-modular-ddd/issues)
- **Discussions**: Join discussions at [GitHub Discussions](https://github.com/theaddresstech/laravel-modular-ddd/discussions)

## Production Considerations

### Performance Optimizations

```bash
# Enable OPcache in production
# Add to php.ini:
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000

# Cache configurations
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize module cache
php artisan module:cache rebuild
```

### Security Hardening

```env
# Production environment variables
MODULAR_DDD_SECURITY_ENABLED=true
MODULAR_DDD_SIGNATURE_VERIFICATION=true
MODULAR_DDD_QUARANTINE_ENABLED=true
```

### Monitoring Setup

```bash
# Set up performance monitoring
php artisan module:performance:analyze --export=baseline.json

# Configure alerts
php artisan module:metrics --setup-alerts
```

---

You're now ready to start building modular applications with Laravel Modular DDD!