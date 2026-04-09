#!/bin/sh
set -e

echo "Running migrations..."
php artisan migrate --force

echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan icons:cache
php artisan filament:cache-components

echo "Creating storage link..."
php artisan storage:link || true

echo "Starting services..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
