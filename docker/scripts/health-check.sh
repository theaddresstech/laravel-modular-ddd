#!/bin/sh

# Health check script for Laravel Modular DDD

set -e

# Check if PHP-FPM is running
if ! pgrep -f php-fpm >/dev/null; then
    echo "ERROR: PHP-FPM is not running"
    exit 1
fi

# Check if Nginx is running
if ! pgrep -f nginx >/dev/null; then
    echo "ERROR: Nginx is not running"
    exit 1
fi

# Check database connectivity
if ! php -r "
try {
    new PDO('mysql:host=database;port=3306;dbname=laravel_app', 'laravel_user', getenv('DB_PASSWORD'));
    echo 'Database OK' . PHP_EOL;
} catch (Exception \$e) {
    echo 'Database ERROR: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"; then
    echo "ERROR: Database connection failed"
    exit 1
fi

# Check Redis connectivity
if ! php -r "
try {
    \$redis = new Redis();
    \$redis->connect('redis', 6379);
    \$redis->ping();
    echo 'Redis OK' . PHP_EOL;
} catch (Exception \$e) {
    echo 'Redis ERROR: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"; then
    echo "ERROR: Redis connection failed"
    exit 1
fi

# Check application health endpoint
if ! curl -f -s http://localhost/health >/dev/null; then
    echo "ERROR: Application health endpoint failed"
    exit 1
fi

# Check module health
if ! php artisan module:health --all --format=json | jq -e '.[] | select(.healthy == false) | empty' >/dev/null 2>&1; then
    echo "WARNING: Some modules are not healthy"
    php artisan module:health --all --format=json | jq '.[] | select(.healthy == false)'
fi

echo "Health check passed"
exit 0