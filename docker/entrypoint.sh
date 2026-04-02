#!/bin/bash
set -e

# Fix MPM conflict
a2dismod mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# Use Railway's PORT
PORT=${PORT:-80}
sed -i "s/\*:80/\*:${PORT}/g" /etc/apache2/sites-available/000-default.conf
sed -i "s/^Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf

php artisan config:clear
php artisan migrate --force

exec apache2-foreground
