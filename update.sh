#!/usr/bin/env bash
# update.sh – helper script for updating the Pyrodactyl panel
# Usage: ./update.sh (run as root or a user with sudo privileges)

set -euo pipefail

# Change to project directory
cd /var/www/pyrodactyl-oss || exit 1

# Put application into maintenance mode
php artisan down

# Pull latest code from GitHub
git pull

# Ensure cache and bootstrap directories have correct permissions before Composer installs
chmod -R 755 storage/* bootstrap/cache

# Install PHP dependencies (without dev packages, optimized autoloader)
composer install --no-dev --optimize-autoloader

# Install Node dependencies and build frontend assets
pnpm i
pnpm build

# Clear compiled views and cached config
php artisan view:clear
php artisan config:clear

# Run migrations with seed data, forcing overwrite of existing data
php artisan migrate --seed --force

# Re‑apply ownership for web server user (www-data)
chown -R www-data:www-data /var/www/pyrodactyl-oss/*

# Restart queue workers so they pick up any new code or config
php artisan queue:restart

# Bring application back online
php artisan up

echo "Update completed successfully at $(date)"