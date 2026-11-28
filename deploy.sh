#!/usr/bin/env sh
# Jalankan dari root proyek di server kelas.
set -eu

git pull --ff-only
composer install --no-dev --optimize-autoloader
php artisan migrate --force
[ -L public/storage ] || php artisan storage:link
php artisan db:seed --class=TrialQuizSeeder --force
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan schedule:interrupt

if command -v supervisorctl >/dev/null 2>&1; then
    supervisorctl status 2>/dev/null || echo "Catatan: jalankan 'sudo supervisorctl status' untuk memeriksa worker, reverb, dan scheduler."
fi
