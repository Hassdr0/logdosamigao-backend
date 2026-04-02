#!/bin/bash
set -e

php artisan config:clear
php artisan migrate --force

apache2-foreground
