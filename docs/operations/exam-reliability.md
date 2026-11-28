# Ketahanan server saat ujian

Panduan ini menyiapkan satu server Nginx, PHP-FPM, MySQL, Redis, Reverb, dan Supervisor untuk maksimal 100 peserta serentak. Lakukan perpindahan session ke Redis ketika tidak ada ujian aktif karena login lama akan berakhir.

## Redis

Pasang Redis dan ekstensi PHP sesuai versi PHP server:

```bash
sudo apt update
sudo apt install redis-server php-redis
```

Atur `/etc/redis/redis.conf`:

```ini
bind 127.0.0.1 ::1
protected-mode yes
appendonly yes
maxmemory 256mb
maxmemory-policy noeviction
```

Restart Redis, lalu pastikan `redis-cli ping` menghasilkan `PONG`. Jangan membuka port 6379 ke jaringan.

Gunakan konfigurasi produksi berikut pada `.env`:

```dotenv
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE_RETRY_AFTER=240
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_CONNECTION=session

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_SESSION_DB=2
```

Setelah mengubah `.env`, jalankan `php artisan config:cache`. Pastikan halaman **Master → Sistem** menunjukkan Redis siap.

## Worker, Reverb, dan Scheduler

Gunakan empat program Supervisor agar antrean, WebSocket, serta penjadwal (`schedule:work` untuk penutupan attempt, pemulihan sesi Serempak, dan cadangan database harian) dikelola di satu tempat. Sesuaikan path proyek dan versi PHP bila diperlukan.

```ini
[program:quiz-realtime]
command=php /var/www/skylearn-kuis/artisan queue:work redis --queue=realtime --sleep=1 --tries=1 --timeout=30 --max-time=3600
directory=/var/www/skylearn-kuis
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/quiz-realtime.log
stopwaitsecs=40

[program:quiz-default]
command=php /var/www/skylearn-kuis/artisan queue:work redis --queue=default --sleep=2 --tries=1 --timeout=180 --max-time=3600
directory=/var/www/skylearn-kuis
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/quiz-default.log
stopwaitsecs=190

[program:quiz-reverb]
command=php /var/www/skylearn-kuis/artisan reverb:start --host=127.0.0.1 --port=8080
directory=/var/www/skylearn-kuis
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/quiz-reverb.log
stopwaitsecs=10

[program:quiz-scheduler]
command=php /var/www/skylearn-kuis/artisan schedule:work
directory=/var/www/skylearn-kuis
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/quiz-scheduler.log
stopwaitsecs=10
```

Muat konfigurasi dengan `sudo supervisorctl reread && sudo supervisorctl update`, lalu periksa `sudo supervisorctl status`.

## Nginx dan Reverb

Backend menghubungi Reverb melalui localhost. Browser memakai host yang sama dengan aplikasi:

```dotenv
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_HOST=192.168.1.10
VITE_REVERB_PORT=80
VITE_REVERB_SCHEME=http
```

Tambahkan proxy berikut ke blok `server` Nginx. Untuk HTTPS, gunakan port 443 dan `VITE_REVERB_SCHEME=https`.

```nginx
location ~ ^/(app|apps) {
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_read_timeout 60s;
    proxy_send_timeout 60s;
    proxy_pass http://127.0.0.1:8080;
}
```

Tambahkan waktu upstream ke format access log agar request lambat dapat dibedakan dari jaringan klien:

```nginx
log_format timed '$remote_addr $request status=$status request_time=$request_time upstream_time=$upstream_response_time';
access_log /var/log/nginx/quiz-access.log timed;
```

Uji dengan `sudo nginx -t`, lalu gunakan `sudo systemctl reload nginx`, bukan restart.

## PHP-FPM

Mulai dengan konfigurasi pool berikut pada server 2–4 GB:

```ini
pm = dynamic
pm.max_children = 10
pm.start_servers = 3
pm.min_spare_servers = 3
pm.max_spare_servers = 6
pm.max_requests = 500
request_terminate_timeout = 120s
```

Aktifkan OPcache produksi (`opcache.enable=1`, `opcache.memory_consumption=128`, `opcache.max_accelerated_files=20000`). Setelah uji beban, hitung `pm.max_children` dari RAM yang tersisa dibagi p95 RSS worker PHP dan batasi hasil antara 8–24.

## Saat ujian aktif

1. Jangan restart MySQL.
2. Jika Reverb mati, jalankan `sudo supervisorctl restart quiz-reverb`. Polling tetap melayani peserta.
3. Restart worker dan scheduler secara graceful dengan `php artisan queue:restart` serta `php artisan schedule:interrupt`; Supervisor akan menyalakannya kembali dengan konfigurasi terbaru.
4. Perubahan Nginx memakai reload.
5. Restart PHP-FPM hanya jika log menunjukkan `pm.max_children` tercapai atau worker macet. Minta peserta tetap di halaman agar antrean localStorage dapat dikirim ulang.

Periksa `uptime`, `free -h`, `df -h`, `sudo supervisorctl status`, status **Scheduler** di halaman **Master -> Sistem**, dan log Nginx/PHP-FPM sebelum mengambil tindakan.

## Uji sebelum ujian nyata

Buat akun pengujian dari PowerShell di laptop:

```powershell
.\tests\load\generate-participants.ps1 -Count 100 -Prefix loadtest -Password 'ganti-password-uji' -OutputPath .\storage\app\private\loadtest-participants.csv
```

Pilih workspace pengujian dan impor CSV peserta tersebut. Buat kuis `Simulasi Beban Jaringan Komputer`, lalu impor `tests/load/questions-network-simulation.csv` melalui menu impor soal. Buat sesi sumber dengan Mode Serempak, acak opsi dan leaderboard realtime aktif, maksimal 100 peserta, kebijakan clipboard `monitor`, serta durasi berdasarkan soal. Buat simulasi dari sesi tersebut, pilih seluruh akun `loadtest`, dan buka lobby sebelum menjalankan k6. Skrip akan menyelesaikan kewajiban ganti password pertama, login, dan bergabung ke simulasi secara otomatis.

Jalankan tes k6 selama 10 menit:

```powershell
k6 run -e BASE_URL=https://quiz.ruangproject.my.id -e WORKSPACE_SLUG=simulasi-kubernetes -e SESSION_ID=<ID_SIMULASI> -e USERNAME_PREFIX=loadtest -e PASSWORD=ganti-password-uji -e VUS=100 -e DURATION_SECONDS=600 .\tests\load\k6-peserta.js
```

Tunggu monitor admin menampilkan seluruh peserta sudah bergabung, kemudian mulai sesi. Jalankan skrip hanya pada simulasi karena jawaban benar-benar disimpan. Kriteria lulus: error workflow di bawah 1%, p95 status di bawah 500 ms, dan p95 penyimpanan jawaban di bawah 750 ms.

Ulangi pengujian dengan Reverb dihentikan, lalu dengan worker `realtime` dan PHP-FPM di-restart satu per satu. Jawaban harus tetap tersimpan atau dikirim ulang dari localStorage tanpa duplikasi, polling tetap memperbarui status, dan queue `realtime` harus kembali kosong setelah proses pulih.
