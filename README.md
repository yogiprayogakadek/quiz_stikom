# STIKOM Quiz

Aplikasi kuis kelas untuk jaringan lokal. Admin membuat bank soal, menyusun kuis, dan menjalankan sesi. Peserta masuk dengan akun yang dibuat admin, mengerjakan soal di perangkat masing-masing, dan layar proyektor menampilkan progres serta hasil.

## Isi

- Mode Acak: setiap peserta mendapat urutan soal dan opsi yang diacak sekali, lalu mengerjakan sendiri sampai selesai.
- Mode Serempak: semua peserta mengerjakan soal yang sama dalam hitungan waktu yang dipandu admin.
- Realtime lewat Laravel Reverb, dengan polling sebagai cadangan.
- Impor peserta dari CSV, laporan sesi, ekspor CSV, dan catatan aktivitas mencurigakan.
- Satu login aktif per akun. Login baru mengakhiri sesi lama.

## Kebutuhan

- PHP 8.2 atau lebih baru dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`.
- Composer 2.
- MySQL 8 atau MariaDB 10.6 atau lebih baru.
- Node.js 20 atau lebih baru dan npm.
- Nginx dan PHP-FPM untuk pemakaian kelas. `php artisan serve` cukup untuk uji lokal.
- Supervisor atau systemd untuk menjaga queue worker dan Reverb tetap hidup.

## Pasang di komputer pengembang

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Isi `.env`:

```dotenv
APP_NAME="STIKOM Quiz"
APP_URL=http://127.0.0.1:8000
APP_LOCALE=id
APP_FALLBACK_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_quiz
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=isi-angka
REVERB_APP_KEY=isi-kunci
REVERB_APP_SECRET=isi-rahasia
REVERB_HOST=127.0.0.1
REVERB_SERVER_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

ADMIN_NAMA="Admin Kuis"
ADMIN_USERNAME=admin
ADMIN_PASSWORD=ganti-password-ini
```

Buat database, lalu jalankan migrasi dan seeder:

```sql
CREATE DATABASE db_quiz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate --seed
npm install
npm run build
```

Seeder membuat satu akun admin dari `ADMIN_USERNAME` dan `ADMIN_PASSWORD`. Ganti password itu setelah login pertama. Seeder berhenti dengan pesan jelas bila `ADMIN_PASSWORD` kosong.

Jalankan empat proses di terminal terpisah saat pengembangan:

```bash
php artisan serve
php artisan queue:work --tries=1
php artisan reverb:start --host=0.0.0.0 --port=8080
npm run dev
```

Buka `http://127.0.0.1:8000`. Reverb memakai port 8080, jadi browser harus bisa menjangkau port itu juga.

## Pasang untuk kelas di LAN

Contoh alamat server: `192.168.1.10`. Ganti angka itu dengan alamat server yang sebenarnya.

### 1. Siapkan aplikasi

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

`.env` produksi:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://192.168.1.10

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_quiz
DB_USERNAME=kuis
DB_PASSWORD=password-database

QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=isi-angka
REVERB_APP_KEY=isi-kunci
REVERB_APP_SECRET=isi-rahasia
REVERB_HOST=192.168.1.10
REVERB_SERVER_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=192.168.1.10
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

`VITE_REVERB_HOST` ditanam saat build, jadi jalankan `npm run build` setelah nilai itu diubah. Bila `VITE_REVERB_HOST` masih `localhost` atau `127.0.0.1`, aplikasi memakai hostname halaman sebagai gantinya, tetapi menulis alamat LAN langsung lebih pasti.

```bash
php artisan migrate --seed --force
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Untuk pembaruan berikutnya, jalankan `./deploy.sh` dari root proyek. Skrip itu menarik kode, memasang dependensi, menjalankan migrasi, membangun aset, dan menyegarkan cache. Reverb dan queue worker tetap dijalankan oleh supervisor/systemd.

### 2. Nginx dan PHP-FPM

Contoh `/etc/nginx/sites-available/skylearn-kuis`:

```nginx
server {
    listen 80;
    server_name 192.168.1.10;
    root /var/www/skylearn-kuis/public;
    index index.php;

    client_max_body_size 10M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Sesuaikan versi PHP pada `fastcgi_pass` dengan versi yang terpasang. Aktifkan situs, lalu muat ulang Nginx dan PHP-FPM:

```bash
sudo ln -s /etc/nginx/sites-available/skylearn-kuis /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl restart php8.3-fpm
```

Pastikan `storage` dan `bootstrap/cache` dapat ditulis oleh user PHP-FPM:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
```

### 3. Reverb

Supervisor, `/etc/supervisor/conf.d/skylearn-reverb.conf`:

```ini
[program:skylearn-reverb]
command=php /var/www/skylearn-kuis/artisan reverb:start --host=0.0.0.0 --port=8080
directory=/var/www/skylearn-kuis
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/skylearn-reverb.log
stopwaitsecs=10
```

Reverb hanya menangani WebSocket. Nginx tidak perlu menjadi reverse proxy untuk port 8080 bila browser terhubung langsung ke server. Bila sekolah memakai HTTPS atau satu port saja, tambahkan reverse proxy WebSocket ke `127.0.0.1:8080` dan ubah `REVERB_SCHEME=https`.

### 4. Queue worker

Broadcast dan penutupan soal berjalan lewat queue. Tanpa worker, realtime tertunda dan sesi bisa terasa macet.

`/etc/supervisor/conf.d/skylearn-queue.conf`:

```ini
[program:skylearn-queue]
command=php /var/www/skylearn-kuis/artisan queue:work --tries=1 --timeout=90
directory=/var/www/skylearn-kuis
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/skylearn-queue.log
stopwaitsecs=60
```

Muat konfigurasi:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start skylearn-reverb skylearn-queue
```

### 5. Penutup attempt kedaluwarsa

Mode Acak menutup attempt yang melewati waktu lewat scheduler. Tambahkan cron:

```cron
* * * * * cd /var/www/skylearn-kuis && php artisan schedule:run >> /dev/null 2>&1
```

Perintah yang dijalankan: `kuis:tutup-attempt-kedaluwarsa`, setiap menit.

### 6. Firewall

Buka port 80 untuk aplikasi dan port 8080 untuk Reverb:

```bash
sudo ufw allow 80/tcp
sudo ufw allow 8080/tcp
```

Bila Reverb dimatikan dengan sengaja, set `BROADCAST_CONNECTION=log` di `.env`, lalu jalankan `php artisan config:cache`. Aplikasi tetap berjalan lewat polling setiap tiga detik. Jangan biarkan `BROADCAST_CONNECTION=reverb` saat Reverb tidak hidup: setiap broadcast menunggu koneksi gagal dan permainan terasa lambat.

## Alamat penting

| Alamat | Keterangan |
| --- | --- |
| `/login` | Masuk admin dan peserta |
| `/admin` | Dashboard admin |
| `/dashboard` | Dashboard peserta |
| `/admin/sessions/{id}` | Ruang kendali sesi |
| `/display/{token}` | Layar proyektor, tanpa login |
| `/admin/participants/import` | Impor peserta dari CSV |
| `/admin/sessions/{id}/laporan` | Laporan dan ekspor sesi |

Token layar dibuat saat sesi dibuat dan dipakai lagi saat lobby dibuka. Siapa pun yang memiliki tautan layar dapat melihat progres kelas, jadi tampilkan hanya di proyektor kelas.

## Format CSV peserta

Unduh contoh dari `/admin/participants/template`. Kolom yang dibaca: `nama` dan `username`. Password boleh dikosongkan, lalu sistem membuatkannya dan menampilkan daftar password satu kali setelah impor. Unduh daftar itu sebelum meninggalkan halaman.

## Uji beban

Skrip k6 ada di `tests/load/k6-peserta.js`. Skrip ini membuat 100 peserta virtual yang login, membuka dashboard, dan memuat state sesi. Jalankan setelah aplikasi dan database siap:

```bash
k6 run -e BASE_URL=http://192.168.1.10 -e SESSION_ID=1 tests/load/k6-peserta.js
```

Buat sesi uji dan akun peserta terlebih dahulu. Skrip tidak membuat akun dan tidak mengirim jawaban, jadi aman dijalankan di sesi latihan.

## Perintah berguna

```bash
php artisan migrate:fresh --seed    # reset database lokal
php artisan kuis:tutup-attempt-kedaluwarsa
php artisan queue:work --tries=1
php artisan reverb:start --host=0.0.0.0 --port=8080
npm run build
```

## Catatan keamanan

- `APP_DEBUG=false` dan `APP_ENV=production` di kelas. Halaman error debug menampilkan isi environment.
- Jangan commit `.env`. File itu memuat kunci aplikasi, kredensial database, dan kunci Reverb.
- Ganti `ADMIN_PASSWORD` setelah login pertama.
- Token layar adalah rahasia sesi. Jangan tempel di grup chat umum.
- Aplikasi ini untuk satu kelas di jaringan lokal. Bila diakses dari internet, tambahkan HTTPS, batasi akses, dan tinjau ulang konfigurasi Reverb.
