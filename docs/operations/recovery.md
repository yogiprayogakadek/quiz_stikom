# Pemulihan database

Panduan ini untuk memulihkan platform dari backup SQL. Backup dibuat dari panel **Master → Sistem → Backup database** dan memakai `mysqldump`.

## Sebelum mulai

- Pastikan akses SSH dan kredensial database produksi tersedia.
- Catat waktu insiden dan perubahan terakhir.
- Backup hanya berisi database. File logo, cover kelas, dan file publik lain harus dicadangkan terpisah dari `storage/app/public`.
- Jangan menimpa database produksi tanpa backup darurat terbaru.

## Langkah

1. Masuk sebagai master, buka **Master → Organisasi**, lalu aktifkan maintenance. Peserta dan admin organisasi akan melihat halaman maintenance; master tetap bisa masuk.
2. Unduh backup terbaru dari **Master → Sistem → Backup database**.
3. Siapkan database kosong dengan nama dan user yang sama seperti konfigurasi `.env`. Jangan mengarahkan restore ke database yang masih dipakai.
4. Pulihkan backup:

   ```bash
   mysql --host=127.0.0.1 --user=DB_USER -p DB_NAME < backup-YYYYMMDD-HHMMSS-xxxx.sql
   ```

5. Jalankan migrasi yang tertunda:

   ```bash
   php artisan migrate --force
   ```

6. Bersihkan cache agar konfigurasi dan view lama tidak dipakai:

   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

7. Verifikasi:
   - Login master berhasil.
   - Login salah satu organisasi berhasil.
   - Buka satu attempt dan pastikan jawaban serta nilai tampil.
   - Buka **Master → Sistem** dan pastikan tidak ada job gagal baru.
8. Nonaktifkan maintenance dari halaman master.
9. Catat hasil, waktu, dan backup yang dipakai di tiket insiden.

## Batasan

- Restore tidak memulihkan file storage. Jika file logo atau cover hilang, pulihkan dari cadangan storage terpisah.
- Backup memakai `mysqldump`; database harus MySQL/MariaDB dan binary harus tersedia di server.
- Job yang sedang berjalan saat restore bisa hilang. Setelah restore, periksa failed jobs dan jalankan ulang bila perlu.
- Backup rutin minimal mingguan dan uji restore di lingkungan staging sebelum insiden.
