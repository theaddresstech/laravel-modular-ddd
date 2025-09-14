# Production Deployment Guide

This comprehensive guide covers everything you need to know about deploying Laravel applications using the Modular DDD package to production environments.

## Table of Contents

1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Environment Configuration](#environment-configuration)
3. [Security Considerations](#security-considerations)
4. [Performance Optimization](#performance-optimization)
5. [Database Management](#database-management)
6. [Monitoring and Logging](#monitoring-and-logging)
7. [Backup and Recovery](#backup-and-recovery)
8. [CI/CD Pipeline](#cicd-pipeline)
9. [Troubleshooting](#troubleshooting)

## Pre-Deployment Checklist

### System Requirements

**Minimum System Requirements:**
- PHP 8.2 or higher
- Laravel 11.0 or higher
- MySQL 8.0+ / PostgreSQL 13+ / SQLite 3.35+
- Redis 6.0+ (recommended for caching and sessions)
- Nginx 1.20+ or Apache 2.4+
- Composer 2.4+
- Node.js 16+ (if using frontend assets)

**Recommended Server Specifications:**
- CPU: 2+ cores
- RAM: 4GB minimum, 8GB+ recommended
- Storage: SSD with at least 20GB free space
- Network: Stable internet connection

### Pre-Deployment Tasks

#### 1. Code Quality Assurance

```bash
# Run all tests
./vendor/bin/phpunit

# Static analysis
./vendor/bin/phpstan analyse

# Code style check
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Security scan
composer audit
```

#### 2. Module Health Check

```bash
# Check all modules
php artisan module:health --all

# Verify module dependencies
php artisan module:visualize --find-cycles

# Test module installation order
php artisan module:visualize --installation-order
```

#### 3. Performance Testing

```bash
# Clear and rebuild caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Module-specific caching
php artisan module:cache rebuild

# Performance metrics baseline
php artisan module:metrics --system --export=baseline-metrics.json
```

## Environment Configuration

### Production Environment Variables

Create a comprehensive `.env` file for production:

```env
# Application
APP_NAME="Your Application Name"
APP_ENV=production
APP_KEY=your-32-character-secret-key
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_secure_password

# Cache
CACHE_DRIVER=redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379
REDIS_DB=0

# Sessions
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_CONNECTION=session

# Queue
QUEUE_CONNECTION=redis
REDIS_QUEUE=default

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-mail-host
MAIL_PORT=587
MAIL_USERNAME=your-mail-username
MAIL_PASSWORD=your-mail-password
MAIL_ENCRYPTION=tls

# Modular DDD Configuration
MODULAR_DDD_MONITORING=true
MODULAR_DDD_SLOW_THRESHOLD=1.0
MODULAR_DDD_MEMORY_WARNING=67108864
MODULAR_DDD_METRICS_RETENTION=86400
MODULAR_DDD_MIDDLEWARE_MONITORING=true
MODULAR_DDD_ALERTS=true
MODULAR_DDD_VERIFY_SIGNATURES=true
MODULAR_DDD_ALLOWED_SOURCES="trusted-source.com,internal.company.com"
MODULAR_DDD_SANDBOX=false

# Logging
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

# Broadcasting
BROADCAST_DRIVER=redis
```

### Configuration Files

#### 1. Module-Specific Configuration

**config/modules/modular-ddd.php:**

```php
<?php

return [
    'modules_path' => base_path('modules'),
    'registry_storage' => storage_path('app/modules'),
    'auto_discovery' => true,

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'prefix' => env('APP_NAME', 'app') . '_modular_ddd',
    ],

    'validation' => [
        'strict' => true,
        'required_directories' => [
            'Domain',
            'Application',
            'Infrastructure',
            'Presentation',
        ],
        'required_files' => [
            'manifest.json',
        ],
    ],

    'monitoring' => [
        'enabled' => true,
        'performance' => [
            'enabled' => true,
            'slow_operation_threshold' => (float)env('MODULAR_DDD_SLOW_THRESHOLD', 1.0),
            'memory_limit_warning' => (int)env('MODULAR_DDD_MEMORY_WARNING', 50 * 1024 * 1024),
            'metrics_retention' => (int)env('MODULAR_DDD_METRICS_RETENTION', 86400),
        ],
        'alerts' => [
            'enabled' => env('MODULAR_DDD_ALERTS', false),
            'thresholds' => [
                'error_rate' => 0.05,
                'response_time' => 2.0,
                'memory_usage' => 0.8,
            ],
        ],
    ],

    'security' => [
        'signature_verification' => env('MODULAR_DDD_VERIFY_SIGNATURES', true),
        'allowed_sources' => array_filter(explode(',', env('MODULAR_DDD_ALLOWED_SOURCES', '*'))),
        'sandbox_mode' => env('MODULAR_DDD_SANDBOX', false),
    ],
];
```

## Security Considerations

### 1. Module Security

#### Signature Verification

```bash
# Enable module signature verification
MODULAR_DDD_VERIFY_SIGNATURES=true

# Restrict allowed sources
MODULAR_DDD_ALLOWED_SOURCES="trusted-repo.com,internal.company.com"
```

#### Module Sandboxing

```bash
# Enable sandbox mode for untrusted modules
MODULAR_DDD_SANDBOX=true
```

### 2. Application Security

#### File Permissions

```bash
# Set proper directory permissions
sudo chown -R www-data:www-data /var/www/your-app
sudo chmod -R 755 /var/www/your-app
sudo chmod -R 775 /var/www/your-app/storage
sudo chmod -R 775 /var/www/your-app/bootstrap/cache
sudo chmod -R 775 /var/www/your-app/modules
```

#### Environment Security

```bash
# Secure .env file
chmod 600 .env
chown www-data:www-data .env

# Remove sensitive files from public access
echo "deny from all" > storage/.htaccess
echo "deny from all" > modules/.htaccess
```

### 3. Database Security

```sql
-- Create dedicated database user with limited privileges
CREATE USER 'your_app_user'@'%' IDENTIFIED BY 'secure_random_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON your_database.* TO 'your_app_user'@'%';
GRANT CREATE, DROP, INDEX, ALTER ON your_database.* TO 'your_app_user'@'%';
FLUSH PRIVILEGES;
```

### 4. Web Server Configuration

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /var/www/your-app/public;

    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";

    index index.php;
    charset utf-8;

    # Block access to sensitive directories
    location ~ ^/(storage|bootstrap|config|database|modules/.*/Config|modules/.*/Database) {
        deny all;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Performance Optimization

### 1. Application Optimization

#### Caching Strategy

```bash
# Enable OPCache
echo "opcache.enable=1" >> /etc/php/8.2/fpm/conf.d/10-opcache.ini
echo "opcache.memory_consumption=256" >> /etc/php/8.2/fpm/conf.d/10-opcache.ini
echo "opcache.max_accelerated_files=20000" >> /etc/php/8.2/fpm/conf.d/10-opcache.ini
echo "opcache.revalidate_freq=0" >> /etc/php/8.2/fpm/conf.d/10-opcache.ini

# Laravel caching
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Module caching
php artisan module:cache rebuild
```

#### PHP-FPM Configuration

```ini
; /etc/php/8.2/fpm/pool.d/www.conf
[www]
user = www-data
group = www-data
listen = /run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 15
pm.max_requests = 1000

; Performance tuning
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 300
php_admin_value[upload_max_filesize] = 100M
php_admin_value[post_max_size] = 100M
```

### 2. Database Optimization

#### Connection Pooling

```php
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
    'options' => [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_TIMEOUT => 30,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'"
    ],
],
```

#### Database Indexing

```sql
-- Add indexes for commonly queried module tables
CREATE INDEX idx_module_status ON modules (status);
CREATE INDEX idx_module_dependencies ON module_dependencies (module_name, dependency_name);
CREATE INDEX idx_performance_metrics_operation ON performance_metrics (operation, timestamp);
CREATE INDEX idx_performance_metrics_timestamp ON performance_metrics (timestamp DESC);
```

### 3. Redis Configuration

```bash
# /etc/redis/redis.conf
maxmemory 1gb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

## Database Management

### 1. Migration Strategy

```bash
# Pre-deployment: Test migrations in staging
php artisan migrate:status
php artisan migrate --pretend

# Production deployment
php artisan migrate --force

# Post-deployment: Verify module tables
php artisan module:migrate --all --force
```

### 2. Database Backup Strategy

```bash
#!/bin/bash
# daily-backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="your_database"
BACKUP_DIR="/backups/database"
RETENTION_DAYS=7

# Create backup
mysqldump -u ${DB_USERNAME} -p${DB_PASSWORD} ${DB_NAME} > ${BACKUP_DIR}/backup_${DATE}.sql

# Compress backup
gzip ${BACKUP_DIR}/backup_${DATE}.sql

# Remove old backups
find ${BACKUP_DIR} -name "backup_*.sql.gz" -mtime +${RETENTION_DAYS} -delete

# Module-specific backup
php artisan module:backup --all --output=${BACKUP_DIR}/modules_${DATE}.zip
```

### 3. Database Health Monitoring

```bash
# Monitor database performance
php artisan module:metrics --system --format=json | jq '.database'

# Check table sizes
SELECT
    table_name,
    table_rows,
    data_length,
    index_length,
    (data_length + index_length) as total_size
FROM information_schema.tables
WHERE table_schema = 'your_database'
ORDER BY total_size DESC;
```

## Monitoring and Logging

### 1. Application Monitoring

#### Health Checks

```bash
# Create health check endpoint
php artisan make:controller HealthController

# Add to routes/web.php
Route::get('/health', [HealthController::class, 'check']);
```

```php
<?php
// app/Http/Controllers/HealthController.php

use TaiCrm\LaravelModularDdd\Monitoring\MetricsCollector;
use TaiCrm\LaravelModularDdd\Health\ModuleHealthChecker;

class HealthController extends Controller
{
    public function check(MetricsCollector $metrics, ModuleHealthChecker $health)
    {
        $systemMetrics = $metrics->collectSystemMetrics();
        $moduleHealth = $health->checkAll();

        $status = $this->determineHealthStatus($systemMetrics, $moduleHealth);

        return response()->json([
            'status' => $status,
            'timestamp' => now(),
            'modules' => $moduleHealth,
            'system' => $systemMetrics,
        ], $status === 'healthy' ? 200 : 503);
    }

    private function determineHealthStatus($systemMetrics, $moduleHealth): string
    {
        // Implement your health determination logic
        $failedModules = array_filter($moduleHealth, fn($h) => !$h['healthy']);

        if (!empty($failedModules)) {
            return 'unhealthy';
        }

        if ($systemMetrics['memory']['usage_percentage'] > 90) {
            return 'degraded';
        }

        return 'healthy';
    }
}
```

#### Performance Monitoring

```bash
# Enable performance monitoring
MODULAR_DDD_MONITORING=true
MODULAR_DDD_MIDDLEWARE_MONITORING=true

# Set up alerts
MODULAR_DDD_ALERTS=true
```

### 2. Logging Configuration

```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'modules'],
        'ignore_exceptions' => false,
    ],

    'modules' => [
        'driver' => 'daily',
        'path' => storage_path('logs/modules.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
        'replace_placeholders' => true,
    ],

    'performance' => [
        'driver' => 'daily',
        'path' => storage_path('logs/performance.log'),
        'level' => 'info',
        'days' => 30,
    ],
],
```

### 3. Log Rotation and Cleanup

```bash
# /etc/logrotate.d/laravel-modules
/var/www/your-app/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
    postrotate
        sudo systemctl reload php8.2-fpm
    endscript
}
```

## Backup and Recovery

### 1. Automated Backup Script

```bash
#!/bin/bash
# backup-application.sh

APP_PATH="/var/www/your-app"
BACKUP_PATH="/backups/application"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

echo "Starting application backup: $DATE"

# Create backup directory
mkdir -p $BACKUP_PATH/$DATE

# Database backup
echo "Backing up database..."
php $APP_PATH/artisan db:backup --output=$BACKUP_PATH/$DATE/database.sql.gz

# Module backup
echo "Backing up modules..."
php $APP_PATH/artisan module:backup --all --output=$BACKUP_PATH/$DATE/modules.zip

# Application files backup (excluding cache and logs)
echo "Backing up application files..."
tar -czf $BACKUP_PATH/$DATE/application.tar.gz \
    --exclude='node_modules' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='bootstrap/cache/*' \
    $APP_PATH

# Configuration backup
echo "Backing up configuration..."
cp $APP_PATH/.env $BACKUP_PATH/$DATE/env.backup

# Create backup manifest
cat > $BACKUP_PATH/$DATE/manifest.json << EOF
{
    "timestamp": "$DATE",
    "application_version": "$(cd $APP_PATH && git rev-parse HEAD)",
    "php_version": "$(php -v | head -1)",
    "laravel_version": "$(cd $APP_PATH && php artisan --version)",
    "modules": $(cd $APP_PATH && php artisan module:list --format=json)
}
EOF

# Cleanup old backups
find $BACKUP_PATH -type d -mtime +$RETENTION_DAYS -exec rm -rf {} +

echo "Backup completed: $BACKUP_PATH/$DATE"
```

### 2. Recovery Procedures

#### Application Recovery

```bash
#!/bin/bash
# restore-application.sh

BACKUP_DATE=$1
BACKUP_PATH="/backups/application/$BACKUP_DATE"
APP_PATH="/var/www/your-app"

if [ -z "$BACKUP_DATE" ]; then
    echo "Usage: $0 <backup_date>"
    echo "Available backups:"
    ls -la /backups/application/
    exit 1
fi

echo "Restoring application from backup: $BACKUP_DATE"

# Put application in maintenance mode
php $APP_PATH/artisan down

# Restore application files
echo "Restoring application files..."
cd /var/www
tar -xzf $BACKUP_PATH/application.tar.gz

# Restore configuration
echo "Restoring configuration..."
cp $BACKUP_PATH/env.backup $APP_PATH/.env

# Restore database
echo "Restoring database..."
php $APP_PATH/artisan db:restore $BACKUP_PATH/database.sql.gz

# Restore modules
echo "Restoring modules..."
php $APP_PATH/artisan module:restore $BACKUP_PATH/modules.zip

# Clear caches and optimize
php $APP_PATH/artisan cache:clear
php $APP_PATH/artisan config:cache
php $APP_PATH/artisan route:cache
php $APP_PATH/artisan view:cache

# Bring application back online
php $APP_PATH/artisan up

echo "Application restoration completed"
```

## CI/CD Pipeline

### 1. GitHub Actions Workflow

```yaml
# ._github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [ main ]
    tags: [ 'v*' ]

jobs:
  tests:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: test_db
        ports:
          - 3306:3306

      redis:
        image: redis:6.0
        ports:
          - 6379:6379

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, mysql, redis
          coverage: xdebug

      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Copy environment file
        run: cp .env.testing .env

      - name: Generate app key
        run: php artisan key:generate

      - name: Run migrations
        run: php artisan migrate

      - name: Install modules
        run: |
          php artisan module:install --all
          php artisan module:enable --all
          php artisan module:migrate --all

      - name: Run tests
        run: |
          php artisan test
          php artisan module:health --all

      - name: Static analysis
        run: |
          ./vendor/bin/phpstan analyse
          ./vendor/bin/php-cs-fixer fix --dry-run

      - name: Security audit
        run: composer audit

  deploy:
    needs: tests
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'

    steps:
      - uses: actions/checkout@v3

      - name: Deploy to production
        uses: appleboy/ssh-action@v0.1.5
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USERNAME }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          script: |
            cd /var/www/your-app

            # Put in maintenance mode
            php artisan down

            # Pull latest code
            git pull origin main

            # Install dependencies
            composer install --no-dev --optimize-autoloader

            # Clear old caches
            php artisan cache:clear
            php artisan config:clear
            php artisan route:clear
            php artisan view:clear

            # Run migrations
            php artisan migrate --force
            php artisan module:migrate --all --force

            # Rebuild caches
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan module:cache rebuild

            # Restart services
            sudo systemctl reload php8.2-fpm
            sudo systemctl reload nginx

            # Bring back online
            php artisan up

            # Verify deployment
            php artisan module:health --all
```

### 2. Zero-Downtime Deployment

```bash
#!/bin/bash
# zero-downtime-deploy.sh

APP_NAME="your-app"
RELEASES_PATH="/var/www/releases"
SHARED_PATH="/var/www/shared"
CURRENT_PATH="/var/www/current"
RELEASE_DATE=$(date +%Y%m%d_%H%M%S)
RELEASE_PATH="$RELEASES_PATH/$RELEASE_DATE"

echo "Starting zero-downtime deployment: $RELEASE_DATE"

# Create release directory
mkdir -p $RELEASE_PATH

# Clone application
git clone --depth=1 https://github.com/yourorg/yourapp.git $RELEASE_PATH
cd $RELEASE_PATH

# Install dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Link shared files
ln -sf $SHARED_PATH/.env $RELEASE_PATH/.env
ln -sf $SHARED_PATH/storage/app $RELEASE_PATH/storage/app
ln -sf $SHARED_PATH/storage/logs $RELEASE_PATH/storage/logs

# Build assets (if needed)
# npm ci
# npm run production

# Run database migrations
php artisan migrate --force
php artisan module:migrate --all --force

# Clear and rebuild caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan module:cache rebuild

# Health check
php artisan module:health --all

# Switch to new release atomically
ln -sfn $RELEASE_PATH $CURRENT_PATH

# Reload PHP-FPM
sudo systemctl reload php8.2-fpm

# Keep only last 5 releases
cd $RELEASES_PATH
ls -t | tail -n +6 | xargs rm -rf

echo "Deployment completed successfully: $RELEASE_DATE"
```

## Troubleshooting

### Common Issues and Solutions

#### 1. Module Loading Issues

```bash
# Check module status
php artisan module:list

# Verify module structure
php artisan module:health ModuleName --verbose

# Clear module cache
php artisan module:cache clear
php artisan module:cache rebuild

# Check dependencies
php artisan module:visualize --find-cycles
```

#### 2. Performance Issues

```bash
# Check system metrics
php artisan module:metrics --system

# Identify slow operations
php artisan module:metrics --format=table

# Monitor memory usage
php artisan module:metrics --system --export=system-metrics.json
```

#### 3. Database Connection Issues

```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check migration status
php artisan migrate:status
php artisan module:migrate --status --all
```

#### 4. Cache Issues

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan module:cache clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan module:cache rebuild
```

### Log Analysis

#### Application Logs

```bash
# Monitor application logs
tail -f storage/logs/laravel.log

# Monitor module logs
tail -f storage/logs/modules.log

# Monitor performance logs
tail -f storage/logs/performance.log

# Search for errors
grep -r "ERROR" storage/logs/
grep -r "CRITICAL" storage/logs/
```

#### System Logs

```bash
# PHP-FPM logs
sudo tail -f /var/log/php8.2-fpm.log

# Nginx logs
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/nginx/access.log

# System logs
sudo journalctl -u php8.2-fpm -f
sudo journalctl -u nginx -f
```

### Emergency Procedures

#### Rollback Deployment

```bash
#!/bin/bash
# rollback-deployment.sh

PREVIOUS_RELEASE=$(ls -t /var/www/releases | sed -n '2p')

if [ -z "$PREVIOUS_RELEASE" ]; then
    echo "No previous release found"
    exit 1
fi

echo "Rolling back to: $PREVIOUS_RELEASE"

# Put in maintenance mode
php /var/www/current/artisan down

# Switch to previous release
ln -sfn /var/www/releases/$PREVIOUS_RELEASE /var/www/current

# Rollback database if needed
# php /var/www/current/artisan migrate:rollback

# Clear caches
php /var/www/current/artisan cache:clear
php /var/www/current/artisan config:cache

# Reload services
sudo systemctl reload php8.2-fpm

# Bring back online
php /var/www/current/artisan up

echo "Rollback completed"
```

#### Emergency Maintenance Mode

```bash
# Enable maintenance mode with custom message
php artisan down --message="System maintenance in progress" --retry=60

# Allow specific IPs during maintenance
php artisan down --allow=192.168.1.100 --allow=10.0.0.0/8

# Disable all modules temporarily
php artisan module:disable --all

# Emergency health check
php artisan module:health --all --format=json | jq '.[] | select(.healthy == false)'
```

This production deployment guide provides a comprehensive foundation for deploying Laravel applications using the Modular DDD package. Always test deployment procedures in a staging environment before applying to production, and maintain regular backups and monitoring to ensure system reliability.