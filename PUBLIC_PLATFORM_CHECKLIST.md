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
- [x] Branding, slug, zona waktu, bahasa, dan kebijakan peserta per organisasi.
  - Branding, logo, teks login, dan provider AI sudah per organisasi. Slug, zona waktu, locale, dan izin masuk kelas lewat PIN kini dapat diubah dari halaman **Pengaturan**.
  - Timestamp tetap disimpan UTC; zona waktu organisasi dipakai saat menampilkan tanggal/jam di laporan, riwayat akses, proctoring, hasil peserta, backup, dan CSV.
  - Bahasa `id`/`en` diterapkan sebagai locale Laravel dan Carbon. Teks antarmuka utama masih Bahasa Indonesia; terjemahan penuh belum dibuat.
  - Kebijakan peserta saat ini adalah izin masuk kelas sendiri lewat PIN. Jika dimatikan, admin harus menambahkan peserta ke kelas.
- [x] Provider AI platform atau API key organisasi yang terenkripsi.
- [x] Preview branding sebelum disimpan.
  - Halaman **Pengaturan** menampilkan pratinjau login live untuk nama aplikasi, tagline, logo, warna utama, teks login, dan layout.
  - Logo baru dari input file langsung dipratinjau sebelum disimpan. Nilai teks memakai `old()` saat validasi gagal; file yang gagal divalidasi tidak dapat dipulihkan oleh browser.
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
- Commit checkpoint: `4b74c3c` (`feat: add audit logs, essay rubric, and email recovery`).
- Verifikasi: `100 passed (522 assertions)`, build Vite berhasil, Blade cache berhasil, dan `git diff --check` bersih.
- Risiko tersisa: `MAIL_MAILER=log` masih default sehingga email verifikasi dan reset belum terkirim di produksi sampai SMTP dikonfigurasi. Akun hasil impor CSV dan akun lama tanpa email tetap kompatibel, tetapi belum bisa reset password mandiri; email dapat ditambahkan lewat edit peserta. Pendaftaran publik, kuota, dan rate limit pendaftaran belum ada.
- Item berikutnya: **Branding, slug, zona waktu, bahasa, dan kebijakan peserta per organisasi** (item pertama yang belum dicentang).
- Aturan essay: AI hanya menyimpan rekomendasi; nilai final tetap ditetapkan admin sampai mode otomatis beserta auditnya selesai.

## Checkpoint 10 November 2026 — pengaturan organisasi

- Status: branding per organisasi sudah ada; slug, zona waktu, locale, dan kebijakan masuk kelas lewat PIN sekarang dapat diubah dari **Pengaturan**. Timestamp tetap UTC dan tampilan tanggal mengikuti zona waktu organisasi.
- Commit checkpoint: `1a5babe` (`feat: add workspace locale and participant policies`).
- Verifikasi: `104 passed (537 assertions)`, build Vite berhasil, Blade cache berhasil, `git diff --check` bersih, dan migration `2026_11_10_000008_add_locale_and_participant_policy_to_settings` berhasil dijalankan lokal.
- Risiko tersisa: locale baru mengganti pesan sistem dan nama bulan; teks antarmuka utama masih Indonesia sampai terjemahan penuh ditambahkan. Mengubah slug membuat tautan login lama tidak berlaku. Jika PIN dinonaktifkan, peserta harus ditambahkan admin; belum ada undangan atau pendaftaran mandiri.
- Item berikutnya: **Preview branding sebelum disimpan**.

## Checkpoint 10 November 2026 — preview branding

- Status: pratinjau branding live sudah ada di halaman **Pengaturan**. Perubahan nama, tagline, logo, warna, teks login, dan layout langsung terlihat sebelum disimpan.
- Commit checkpoint: `afa353f` (`feat: preview branding before saving`).
- Verifikasi: `105 passed (544 assertions)`, build Vite berhasil, Blade cache berhasil, dan `git diff --check` bersih.
- Risiko tersisa: pratinjau bergantung pada Alpine dan belum diuji lewat browser otomatis atau screenshot; yang diuji adalah markup dan data awal. Preview menampilkan halaman login, belum menampilkan navigasi admin. Logo yang dipilih harus diunggah ulang jika validasi form gagal.
- Item berikutnya: **Kuota pemakaian AI dan penyimpanan**.
