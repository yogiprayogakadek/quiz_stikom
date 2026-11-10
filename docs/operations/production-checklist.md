# Checklist produksi

Gunakan sebelum deploy pertama dan setiap perubahan besar.

## Aplikasi

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` memakai alamat publik HTTPS
- `APP_KEY` sudah dibuat dan disimpan aman
- `SESSION_SECURE_COOKIE=true`
- `TRUSTED_PROXIES` sesuai load balancer
- Batas upload dan `post_max_size` PHP cukup untuk file CSV dan logo

## Database dan antrean

- `php artisan migrate --force` dijalankan saat deploy
- Queue worker berjalan dengan Supervisor/systemd dan di-restart setiap deploy
- `DB_QUEUE_RETRY_AFTER` lebih besar dari timeout job terpanjang
- Scheduler berjalan untuk tugas terjadwal
- Failed jobs dipantau dari **Master → Sistem**

## Email

- SMTP diatur dari **Master → Email platform**
- Kirim email uji dan pastikan diterima
- `MAIL_FROM_ADDRESS` dan `MAIL_FROM_NAME` valid
- Domain pengirim memiliki SPF/DKIM bila tersedia

## Keamanan dan operasi

- Backup database terjadwal dan pernah diuji restore
- File `storage/app/public` dicadangkan terpisah
- Log aplikasi dipantau; path terlihat di **Master → Sistem**
- Akses master dibatasi dan password kuat
- Maintenance diaktifkan saat perubahan berisiko
- Prosedur pemulihan: `docs/operations/recovery.md`

## Setelah deploy

- Login master dan satu organisasi
- Buka satu kuis dan satu attempt
- Cek halaman **Master → Sistem** tanpa peringatan baru
- Cek email verifikasi/reset password
- Cek upload logo dan cover kelas
