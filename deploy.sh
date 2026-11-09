#!/usr/bin/env sh
# Jalankan dari root proyek di server kelas.
set -eu

git pull --ff-only
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=TrialQuizSeeder --force
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
