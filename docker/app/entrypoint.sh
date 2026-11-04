#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data bootstrap/cache storage/framework

exec docker-php-entrypoint "$@"
