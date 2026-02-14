#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/pyrodactyl-oss"
PHP_USER="www-data"        # adjust if your php-fpm runs as a different user
PHP_GROUP="www-data"

echo "==> Updating app in $APP_DIR"
cd "$APP_DIR"

echo "==> Fetching & pulling latest changes (current branch)"
git fetch origin
git pull

echo "==> Installing/updating dependencies with pnpm"
pnpm install

echo "==> Building frontend (pnpm ship)"
pnpm ship

echo "==> Setting permissions on storage and bootstrap/cache for $PHP_USER:$PHP_GROUP"
chown -R "$PHP_USER":"$PHP_GROUP" storage bootstrap/cache

echo "==> Putting app into maintenance mode"
php artisan down || true

echo "==> Running database migrations"
php artisan migrate --force || true

echo "==> Clearing caches"
php artisan config:clear
php artisan route:clear    # do NOT route:cache right now due to duplicate route name
php artisan view:clear
php artisan cache:clear

echo "==> Rebuilding config and view caches"
php artisan config:cache
php artisan view:cache
# php artisan route:cache   # intentionally disabled until duplicate route name is fixed

echo "==> Bringing app back up"
php artisan up

echo "==> Restarting Nginx"
systemctl restart nginx

echo "==> Done."
