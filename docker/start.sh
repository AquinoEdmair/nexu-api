#!/bin/sh
set -e

echo "Fixing permissions..."
mkdir -p /var/www/html/storage/logs /var/www/html/storage/framework/cache /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views
touch /var/www/html/storage/logs/laravel.log
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

echo "Running migrations..."
php artisan migrate --force

echo "Seeding database..."
php artisan db:seed --force

echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan icons:cache
php artisan filament:cache-components

echo "Creating storage link..."
php artisan storage:link || true

# Toggle Horizon based on ENABLE_HORIZON env var (default: false).
# When disabled, strip the horizon block from supervisord.conf so it doesn't
# hit Redis. Horizon is the LAST block in supervisord.conf, so we delete
# from "[program:horizon]" to the end of file. Flip ENABLE_HORIZON=true
# in Railway to bring it back.
if [ "${ENABLE_HORIZON:-false}" != "true" ]; then
    echo "Horizon disabled (ENABLE_HORIZON != true) — removing from supervisord..."
    sed -i '/\[program:horizon\]/,$d' /etc/supervisord.conf
else
    echo "Horizon enabled."
fi

echo "Starting services..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
