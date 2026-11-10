# Checklist Platform Publik

Dokumen ini adalah titik handoff. Provider berikutnya harus mulai dari item pertama yang belum dicentang, membaca bagian keputusan tetap, lalu menjalankan verifikasi sebelum melanjutkan.

## Keputusan tetap

- Organisasi adalah tenant dan pemilik data.
- Akun dapat menjadi anggota beberapa organisasi melalui `organization_memberships`.
- `users.role` tetap menjadi pembeda kasar `admin` dan `peserta`; hak organisasi disimpan pada membership.
- Master adalah admin dengan `is_platform_admin = true` agar migrasi enum lama tidak berisiko.
- Pengaturan organisasi mewarisi nilai platform ketika belum diisi.
- Mode essay default adalah manual. AI memberi rekomendasi sampai owner memilih otomatis untuk kuis berisiko rendah.

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
- [ ] Audit perubahan role dan membership.

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
- [ ] Email, verifikasi email, dan lupa password.

## Fase 5: Pengaturan organisasi

- [x] Pisahkan pengaturan platform dan organisasi.
- [ ] Branding, slug, zona waktu, bahasa, dan kebijakan peserta per organisasi.
- [x] Provider AI platform atau API key organisasi yang terenkripsi.
- [ ] Preview branding sebelum disimpan.
- [ ] Kuota pemakaian AI dan penyimpanan.

## Fase 6: Penilaian essay

- [ ] Rubrik, jawaban acuan, kriteria, dan poin maksimal per soal essay.
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

- Status: fondasi tenant selesai; login master terpisah, maintenance global, suspend organisasi, pengaturan per organisasi, keamanan password, dan rekomendasi nilai essay AI sudah berfungsi.
- Verifikasi: `86 passed (453 assertions)`, build Vite berhasil, Blade cache berhasil, dan `git diff --check` bersih.
- Risiko tersisa: belum ada email pemulihan, pendaftaran publik, kuota, dan audit perubahan role/nilai.
- Item berikutnya: mulai dari **Audit perubahan role dan membership**, lalu lanjutkan email pemulihan dan rubrik essay terstruktur.
- Aturan essay: AI hanya menyimpan rekomendasi; nilai final tetap ditetapkan admin sampai mode otomatis beserta auditnya selesai.
