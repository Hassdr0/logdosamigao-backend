#!/bin/bash
set -e

# Fix MPM conflict
a2dismod mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

php artisan config:clear
php artisan migrate --force

exec apache2-foreground
