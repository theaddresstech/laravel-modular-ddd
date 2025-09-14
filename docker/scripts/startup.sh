#!/bin/sh

set -e

echo "Starting Laravel Modular DDD application..."

# Wait for database to be ready
echo "Waiting for database connection..."
until nc -z database 3306; do
  echo "Database is unavailable - sleeping"
  sleep 2
done
echo "Database is up - continuing"

# Wait for Redis to be ready
echo "Waiting for Redis connection..."
until nc -z redis 6379; do
  echo "Redis is unavailable - sleeping"
  sleep 1
done
echo "Redis is up - continuing"

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

# Install and enable all modules
echo "Installing and enabling modules..."
php artisan module:install --all --force
php artisan module:enable --all --force
php artisan module:migrate --all --force

# Clear and optimize caches
echo "Optimizing application..."
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan module:cache rebuild

# Set proper permissions
chown -R www-data:www-data /var/www/storage
chown -R www-data:www-data /var/www/bootstrap/cache
chown -R www-data:www-data /var/www/modules

# Health check
echo "Performing health check..."
php artisan module:health --all

echo "Application startup completed successfully"

# Start supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf