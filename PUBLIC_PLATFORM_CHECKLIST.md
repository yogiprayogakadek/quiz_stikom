# Checklist Platform Publik

Dokumen ini adalah titik handoff. Provider berikutnya harus mulai dari item pertama yang belum dicentang, membaca bagian keputusan tetap, lalu menjalankan verifikasi sebelum melanjutkan.

## Keputusan tetap

- Organisasi adalah tenant dan pemilik data.
- Akun dapat menjadi anggota beberapa organisasi melalui `organization_memberships`.
- `users.role` tetap menjadi pembeda kasar `admin` dan `peserta`; hak organisasi disimpan pada membership.
- Master adalah admin dengan `is_platform_admin = true` agar migrasi enum lama tidak berisiko.
- Pengaturan organisasi mewarisi nilai platform ketika belum diisi.
- Mode essay default adalah manual. AI memberi rekomendasi sampai owner memilih otomatis untuk kuis berisiko rendah.
- `users.email` nullable dan unik. Akun lama tanpa email tetap dapat login dan mengganti password; akun baru dari form admin wajib memakai email unik.
- Verifikasi email hanya diberlakukan ketika email terisi. Mengubah email mereset `email_verified_at`.
- Reset password memakai password broker native Laravel. Akun tanpa email tetap direset oleh admin melalui halaman peserta.

## Fase 1: Fondasi organisasi

- [x] Tambahkan model dan tabel organisasi serta membership.
- [x] Migrasikan pengguna lama ke organisasi default.
- [x] Tambahkan `organization_id` pada kelas, kuis, sesi, aktivitas login, dan pekerjaan AI.
- [x] Terapkan scope organisasi pada model tenant.
- [x] Batasi daftar peserta dan relasi kelas ke organisasi aktif.
- [x] Tambahkan test kebocoran akses antarorganisasi.
- [x] Buat akun master pertama dari admin lama atau seeder.

## Fase 2: Master dan admin organisasi

- [x] Dashboard master untuk daftar organisasi.
- [x] Master dapat membuat organisasi dan owner pertama.
- [x] Owner dapat menambahkan admin organisasi dengan password sementara.
- [x] Suspend organisasi dan pencabutan akses.
- [x] Audit perubahan role dan membership.

## Fase 3: Konteks peserta

- [x] Peserta hanya melihat kelas organisasi aktif.
- [x] Kelas privat hanya dapat dimasuki melalui membership atau PIN.
- [x] Pemilih organisasi untuk akun dengan beberapa membership.
- [x] Branding mengikuti organisasi aktif.
- [x] Login organisasi melalui slug tanpa membuka data organisasi lain.

## Fase 4: Keamanan akun

- [x] Semua role dapat mengganti password.
- [x] Perubahan sukarela mewajibkan password lama.
- [x] Password sementara mewajibkan pergantian saat login pertama.
- [x] Minimum password menjadi 8 karakter.
- [x] Perubahan password mencabut sesi lama.
- [x] Email, verifikasi email, dan lupa password.
  - Email unik dan nullable; akun lama tanpa email tetap kompatibel.
  - Form peserta, admin organisasi, dan owner baru mewajibkan email.
  - Verifikasi email hanya aktif bila email terisi; ganti email mereset verifikasi.
  - Reset password memakai token native Laravel dan mencabut sesi lama.

## Fase 5: Pengaturan organisasi

- [x] Pisahkan pengaturan platform dan organisasi.
- [ ] Branding, slug, zona waktu, bahasa, dan kebijakan peserta per organisasi.
- [x] Provider AI platform atau API key organisasi yang terenkripsi.
- [ ] Preview branding sebelum disimpan.
- [ ] Kuota pemakaian AI dan penyimpanan.

## Fase 6: Penilaian essay

- [x] Rubrik, jawaban acuan, kriteria, dan poin maksimal per soal essay.
- [x] Antrean penilaian manual.
- [x] Rekomendasi nilai AI dengan confidence dan umpan balik.
- [x] Admin mengonfirmasi atau mengubah rekomendasi.
- [ ] Mode otomatis hanya untuk kuis yang mengaktifkannya.
- [ ] Riwayat perubahan nilai, model, provider, dan pemberi nilai.

## Fase 7: Kesiapan publik

- [x] Pisahkan login master dari login pengguna organisasi.
- [x] Master dapat mengaktifkan maintenance dan pengguna menerima halaman status 503.
- [ ] Pendaftaran organisasi mandiri dan verifikasi email.
- [ ] Rate limit serta proteksi bot pada pendaftaran.
- [ ] Kuota organisasi, admin, peserta, kelas, dan AI.
- [ ] Export dan penghapusan data organisasi.
- [ ] Syarat penggunaan dan kebijakan privasi.
- [ ] Monitoring error, backup, dan prosedur pemulihan.

## Verifikasi wajib setiap checkpoint

```powershell
php artisan test --compact
npm run build
php artisan view:cache
git diff --check
```

Catat commit terakhir, test yang lulus, risiko tersisa, dan item checklist berikutnya sebelum handoff.

## Checkpoint 10 November 2026

- Status: fondasi tenant, login master terpisah, maintenance global, suspend organisasi, pengaturan per organisasi, keamanan password, audit role/membership, rubrik essay terstruktur, serta email/verifikasi/reset password sudah berfungsi.
- Verifikasi: `100 passed (522 assertions)`, build Vite berhasil, Blade cache berhasil, dan `git diff --check` bersih.
- Risiko tersisa: `MAIL_MAILER=log` masih default sehingga email verifikasi dan reset belum terkirim di produksi sampai SMTP dikonfigurasi. Akun hasil impor CSV dan akun lama tanpa email tetap kompatibel, tetapi belum bisa reset password mandiri; email dapat ditambahkan lewat edit peserta. Pendaftaran publik, kuota, dan rate limit pendaftaran belum ada.
- Item berikutnya: **Branding, slug, zona waktu, bahasa, dan kebijakan peserta per organisasi** (item pertama yang belum dicentang).
- Aturan essay: AI hanya menyimpan rekomendasi; nilai final tetap ditetapkan admin sampai mode otomatis beserta auditnya selesai.
