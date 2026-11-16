# Rencana Implementasi: Riwayat Kuis Peserta

Status: **belum diimplementasikan**  
Target branch: `main`  
Tujuan dokumen: menjadi instruksi lengkap untuk agen/provider lain tanpa perlu mengambil keputusan produk tambahan.

## Aturan Wajib untuk Agen Pelaksana

1. Baca seluruh dokumen ini sebelum mengubah kode.
2. Kerjakan hanya item yang belum dicentang pada bagian checklist.
3. Jangan membuka UI, browser, atau menjalankan otomasi visual. Pemeriksaan visual dilakukan pengguna.
4. Jangan membuat migration, tabel, kolom, dependency, service, event, JavaScript, CSS, atau komponen global baru.
5. Gunakan tabel `attempts`, relasi, middleware peserta, route hasil, komponen, dan pola pagination yang sudah tersedia.
6. Jangan mengubah dashboard peserta, halaman hasil, mekanisme penilaian, leaderboard, remidi, simulasi, feedback, atau pengawasan ujian.
7. Jangan menyentuh berkas di luar allowlist. Jika implementasi membutuhkan berkas lain, hentikan pekerjaan dan laporkan alasannya kepada pengguna.
8. Jangan menjalankan formatter pada seluruh repository. Jalankan Pint hanya pada berkas PHP yang benar-benar diubah.
9. Jangan mengubah, menghapus, memindahkan, memasukkan ke commit, atau menambahkan ke `.gitignore` berkas pengguna yang tidak terlacak, termasuk:
   - `UTS.pdf`
   - `landing_page.jpg`
   - seluruh berkas `.jfif` yang tidak terlacak
   - `graphify-out/`
10. Jangan commit atau push sebelum seluruh pemeriksaan hijau dan pengguna memintanya.
11. Jika salah satu berkas allowlist sudah berisi perubahan pengguna, pertahankan perubahan tersebut dan jangan menimpanya.

## Allowlist Berkas

Agen hanya boleh membuat atau mengubah berkas berikut:

- `routes/web.php`
- `app/Http/Controllers/Peserta/HistoryController.php`
- `app/Http/Controllers/Admin/SessionController.php`
- `resources/views/peserta/history.blade.php`
- `resources/views/components/layouts/peserta.blade.php`
- `tests/Feature/ParticipantQuizHistoryTest.php`
- `tests/Feature/TrialSessionTest.php`
- `docs/plans/riwayat-kuis-peserta.md` hanya untuk memperbarui checklist

Berkas di luar daftar tersebut tidak boleh disentuh tanpa persetujuan baru dari pengguna.

## Ringkasan Hasil yang Diinginkan

Tambahkan halaman khusus **Riwayat Kuis** agar peserta dapat melihat seluruh attempt yang pernah selesai dikerjakan dalam workspace aktif.

Riwayat menampilkan setiap attempt secara terpisah dan diurutkan dari waktu selesai terbaru. Tampilan default hanya memuat ujian nyata agar simulasi tidak bercampur, tetapi peserta dapat membuka filter simulasi atau semua jenis ujian.

Fitur menggunakan data yang sudah tersedia. Tidak ada migration atau endpoint API baru.

## Route dan Otorisasi

- Tambahkan route `GET /riwayat-kuis` dengan nama `peserta.history.index`.
- Letakkan route dalam grup middleware peserta yang sudah memakai:
  - `auth`
  - `role:peserta`
  - `organization_member`
  - `participant_presence`
- Gunakan controller invokable `App\Http\Controllers\Peserta\HistoryController`.
- Jangan membuat route JSON atau endpoint pencarian terpisah.

## Query Riwayat

- Ambil hanya attempt yang:
  - Dimiliki pengguna yang sedang login.
  - Berada dalam workspace aktif.
  - Memenuhi scope `mengikuti()`.
  - Berstatus `selesai`.
- Urutkan berdasarkan `selesai_at` menurun, kemudian ID menurun untuk urutan yang stabil.
- Eager-load sesi, kuis, dan kelas agar tidak terjadi N+1 query.
- Hitung jawaban essay terisi yang belum memiliki `dinilai_at` menggunakan `withCount`.
- Jawaban essay kosong tidak dihitung sebagai penilaian tertunda.
- Gunakan pagination server sebanyak 12 attempt dan `withQueryString()`.
- Jangan memakai data dari workspace lain walaupun akun peserta terdaftar di beberapa workspace.

## Filter dan Pencarian

### Jenis ujian

- Tanpa query atau `jenis=real`: hanya `quiz.is_percobaan = false`.
- `jenis=simulasi`: hanya `quiz.is_percobaan = true`.
- `jenis=semua`: ujian nyata dan simulasi.
- Nilai lain dianggap `real`.

### Status penilaian

- Tanpa query atau `status=semua`: semua attempt selesai.
- `status=final`: tidak memiliki jawaban essay terisi yang masih menunggu penilaian.
- `status=pending`: memiliki minimal satu jawaban essay terisi dengan `dinilai_at = null`.
- Nilai lain dianggap `semua`.

### Kelas dan pencarian

- `kelas={id}` hanya diterima jika kelas tersebut muncul pada riwayat peserta dalam workspace aktif.
- Daftar pilihan kelas berasal dari riwayat peserta, bukan hanya keanggotaan kelas peserta saat ini, agar kelas lama tetap dapat difilter.
- `q` maksimal 100 karakter dan mencari:
  - Judul kuis.
  - Nama sesi.
  - Nama kelas.
- Semua filter harus dapat digabungkan dan dipertahankan ketika peserta berpindah halaman pagination.
- Tombol **Reset filter** mengembalikan halaman ke default ujian nyata.

## Tampilan Halaman

- Tambahkan tautan **Riwayat Kuis** pada sidebar desktop dan menu mobile peserta.
- Gunakan ikon `clock` yang sudah tersedia; jangan menambah ikon baru.
- Tandai menu aktif menggunakan pola `request()->routeIs(...)` yang sudah dipakai layout.
- Gunakan daftar card responsif, bukan tabel lebar.
- Setiap card menampilkan:
  - Judul kuis.
  - Nama sesi.
  - Nama kelas atau **Tanpa kelas**.
  - Mode Acak atau Mode Serempak.
  - Badge Ujian nyata, Simulasi, dan Remidi bila relevan.
  - Tanggal serta waktu selesai dalam zona waktu workspace.
  - Durasi pengerjaan jika `mulai_at` dan `selesai_at` tersedia.
  - Nilai attempt.
  - Tombol **Lihat hasil**.
- Link hasil menggunakan:
  - `peserta.attempt.result` untuk Mode Acak.
  - `peserta.session.result` untuk Mode Serempak.
- Jangan menduplikasi rincian soal atau jawaban pada halaman riwayat.

## Aturan Nilai Essay

- Jika terdapat jawaban essay terisi yang belum dinilai:
  - Tampilkan status **Menunggu penilaian essay**.
  - Tampilkan jumlah jawaban yang masih menunggu.
  - Jangan tampilkan angka nilai sementara, bintang, atau angka `0`.
- Jika seluruh essay terisi sudah dinilai, tampilkan `skor_total` sebagai nilai attempt.
- Nilai final nol tetap ditampilkan sebagai `0`.
- Jawaban essay kosong tidak membuat status pending.
- Halaman **Lihat hasil** tetap menjadi sumber rincian nilai dan pembahasan sesuai pengaturan sesi.

## Empty, Error, dan Mobile State

- Jika tidak ada riwayat sama sekali, jelaskan bahwa hasil akan muncul setelah peserta menyelesaikan ujian.
- Jika data kosong karena filter, jelaskan filter yang aktif dan tampilkan tombol reset.
- Card harus tersusun satu kolom pada layar kecil dan boleh menjadi dua kolom pada layar lebar.
- Tidak boleh ada horizontal overflow.
- Setiap kontrol interaktif memiliki target minimal 44×44 px, fokus keyboard terlihat, dan label aksesibel.
- Status tidak boleh dibedakan hanya dengan warna.

## Retensi Riwayat

Saat ini `attempts.quiz_session_id` memakai `cascadeOnDelete`, sehingga menghapus sesi juga menghapus seluruh riwayat peserta.

Tambahkan guard pada aksi hapus sesi admin:

- Sesi ujian nyata dengan attempt yang memenuhi `mengikuti()` tidak boleh dihapus.
- Tampilkan pesan bahwa sesi menjadi bagian dari riwayat peserta dan harus dipertahankan.
- Sesi ujian nyata tanpa attempt peserta tetap dapat dihapus.
- Simulasi tetap dapat dihapus beserta attempt-nya seperti perilaku sekarang.
- Penghapusan simulasi tidak boleh memengaruhi kuis atau sesi sumber.
- Jangan menambah soft delete atau tabel snapshot dalam implementasi ini.

## Rekomendasi Fitur Lanjutan

Fitur berikut tidak dikerjakan dalam rencana ini dan harus memiliki rencana terpisah:

1. **Notifikasi penilaian essay selesai**  
   Prioritas berikutnya yang direkomendasikan. Peserta menerima pemberitahuan ketika status berubah dari menunggu menjadi final.

2. **Grafik perkembangan nilai**  
   Tampilkan setelah peserta memiliki minimal tiga ujian nyata agar tren tidak menyesatkan.

3. **Analisis kompetensi atau topik**  
   Membutuhkan metadata topik pada soal dan tidak boleh disimpulkan hanya dari judul kuis.

4. **Unduh transkrip hasil pribadi**  
   Buat PDF hanya jika ada kebutuhan administrasi nyata.

5. **Arsip/soft delete sesi**  
   Diperlukan jika admin nanti perlu menyembunyikan sesi nyata tanpa menghapus riwayat.

Jangan mengimplementasikan kelima fitur tersebut dalam pekerjaan riwayat kuis ini.

## Pengujian Wajib

Tambahkan pengujian untuk memastikan:

- Peserta hanya melihat attempt miliknya dalam workspace aktif.
- Attempt menunggu, berjalan, atau hanya disiapkan admin tidak muncul.
- Data workspace lain tidak bocor.
- Default hanya menampilkan ujian nyata.
- Filter semua, simulasi, status final, status pending, kelas, dan pencarian bekerja.
- Kombinasi filter dan pagination mempertahankan query string.
- Setiap attempt tampil terpisah meskipun berasal dari kuis yang sama.
- Simulasi dan remidi memiliki badge yang benar.
- Essay tertunda menyembunyikan angka nilai.
- Essay kosong tidak dianggap pending.
- Nilai final nol tetap tampil sebagai `0`.
- Link hasil Mode Acak dan Serempak mengarah ke route yang benar.
- Admin dan pengguna tanpa membership peserta tidak dapat membuka halaman.
- Sesi nyata yang memiliki attempt peserta tidak dapat dihapus.
- Sesi nyata tanpa attempt tetap dapat dihapus.
- Simulasi dengan attempt tetap dapat dihapus dan sumbernya tetap ada.

Jalankan pemeriksaan berikut:

```powershell
php artisan test --compact tests/Feature/ParticipantQuizHistoryTest.php tests/Feature/TrialSessionTest.php
php artisan test --compact
npm run build
php artisan view:clear
php artisan view:cache
php artisan route:list --name=peserta.history.index
php vendor/bin/pint app/Http/Controllers/Peserta/HistoryController.php app/Http/Controllers/Admin/SessionController.php tests/Feature/ParticipantQuizHistoryTest.php tests/Feature/TrialSessionTest.php
git diff --check
```

Setelah Pint dijalankan, ulangi targeted tests dan `git diff --check`.

## Checklist Pelaksanaan

- [x] Status repository diperiksa dan perubahan pengguna dicatat.
- [x] Tidak ada berkas di luar allowlist yang berubah.
- [x] Route dan controller riwayat dibuat.
- [x] Query terisolasi per peserta dan workspace.
- [x] Filter serta pagination selesai.
- [x] Halaman riwayat dan navigasi peserta selesai.
- [x] Nilai pending dan final tampil sesuai aturan.
- [x] Retensi sesi nyata diterapkan.
- [x] Targeted tests hijau.
- [x] Seluruh test suite hijau.
- [x] Vite build, Blade cache, dan route check hijau.
- [x] Pint terbatas dan `git diff --check` hijau.
- [x] Tidak ada PDF, JPG, JFIF, atau `graphify-out/` yang disentuh atau di-stage.
- [x] Commit dan push dilakukan hanya jika pengguna memintanya.

## Cara Menggunakan Dokumen Ini

Berikan instruksi berikut kepada agen/provider yang akan mengerjakan:

> Baca `docs/plans/riwayat-kuis-peserta.md` sampai selesai. Implementasikan hanya item checklist yang belum selesai dan patuhi allowlist secara ketat. Jangan membuka UI/browser, jangan membuat migration atau dependency, dan jangan menyentuh berkas pengguna yang tidak terlacak. Jika membutuhkan berkas di luar allowlist, berhenti dan laporkan alasannya. Jalankan seluruh verifikasi yang tertulis dan tandai checklist hanya berdasarkan bukti pengujian nyata.

Jika pekerjaan dilanjutkan oleh provider lain:

1. Jalankan `git status --short`.
2. Baca diff yang sudah ada sebelum mengubah apa pun.
3. Pertahankan perubahan pengguna dan pekerjaan provider sebelumnya yang benar.
4. Jangan mengulang pekerjaan yang sudah selesai.
5. Perbarui checklist hanya berdasarkan hasil test atau pemeriksaan nyata.
6. Jangan menandai implementasi selesai jika masih ada test gagal atau perubahan di luar allowlist.
