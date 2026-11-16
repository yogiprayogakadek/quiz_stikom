# Rencana Implementasi: Ruang Penilaian Fokus dan Filter Jenis Sesi

Status: **belum diimplementasikan**  
Target branch: `main`  
Tujuan dokumen: menjadi instruksi lengkap untuk agen/provider lain tanpa perlu mengambil keputusan produk tambahan.

## Aturan Wajib untuk Agen Pelaksana

1. Baca seluruh dokumen ini sebelum mengubah kode.
2. Jangan membuka UI, browser, atau menjalankan otomasi visual. Pemeriksaan visual dilakukan pengguna.
3. Jangan membuat migration, tabel, kolom, endpoint, dependency, komponen global, atau sistem autosave baru.
4. Gunakan status `answers.dinilai_at`, endpoint penilaian essay yang sudah ada, Alpine yang sudah terpasang, dan progress overlay global yang sudah tersedia.
5. Jangan mengubah perilaku penilaian pilihan ganda, leaderboard, remidi, simulasi, audit log, isolasi workspace, atau otorisasi.
6. Jangan menyentuh, menghapus, memindahkan, memasukkan ke commit, atau mengubah berkas pengguna yang tidak terlacak, termasuk:
   - `UTS.pdf`
   - `landing_page.jpg`
   - seluruh berkas `.jfif` yang tidak terlacak
7. Jangan menjalankan formatter pada seluruh repository. Jalankan Pint hanya pada berkas PHP yang benar-benar diubah.
8. Jangan melakukan refactor di luar kebutuhan rencana ini. Jika implementasi ternyata membutuhkan berkas di luar allowlist, hentikan pekerjaan dan laporkan alasannya kepada pengguna.
9. Jangan melakukan commit atau push sebelum seluruh pemeriksaan yang tercantum di dokumen ini hijau.
10. Jika ada perubahan milik pengguna pada salah satu berkas allowlist, pertahankan perubahan tersebut dan jangan menimpanya.

## Allowlist Berkas

Agen hanya boleh mengubah berkas berikut:

- `app/Http/Controllers/Admin/ReportController.php`
- `app/Http/Controllers/Admin/SessionController.php`
- `resources/views/admin/reports/attempt.blade.php`
- `resources/views/admin/sessions/index.blade.php`
- `resources/js/app.js` hanya jika helper Alpine kecil memang diperlukan
- `tests/Feature/EssayTest.php`
- `tests/Feature/AdminListFilterTest.php`

Dokumen ini sendiri boleh diperbarui hanya untuk memberi tanda checklist selesai. Berkas di luar daftar tersebut tidak boleh disentuh tanpa persetujuan baru dari pengguna.

## Ringkasan Hasil yang Diinginkan

Ubah halaman detail hasil peserta menjadi ruang penilaian satu soal essay pada satu waktu. Admin dapat berpindah melalui navigator nomor, melihat status setiap soal, menyimpan nilai aktif, lalu menuju soal berikutnya tanpa menggulir seluruh jawaban.

Pada daftar sesi, tambahkan filter terpisah **Semua**, **Ujian nyata**, dan **Simulasi** yang dapat digunakan bersama filter status dan kelas.

## Implementasi Ruang Penilaian

### Data yang Disiapkan Controller

- Dari rincian attempt yang sudah ada, bentuk koleksi khusus soal essay sesuai urutan `attempt_questions`.
- Tetapkan status setiap soal:
  - `graded`: jawaban memiliki `dinilai_at`.
  - `pending`: jawaban terisi dan belum memiliki `dinilai_at`.
  - `unanswered`: jawaban kosong atau tidak tersedia.
- Hitung jumlah graded, pending, dan unanswered untuk ringkasan progress.
- Pilih soal aktif dengan prioritas:
  1. Query `soal={questionId}` yang valid dan merupakan soal essay pada attempt tersebut.
  2. Soal essay berstatus pending pertama.
  3. Soal essay pertama.
- Cari satu attempt lain dalam sesi dan workspace yang sama yang masih memiliki jawaban essay terisi tetapi belum dinilai. Data ini dipakai untuk tombol **Peserta berikutnya** setelah peserta aktif selesai dinilai.
- Jangan menambahkan query yang dapat melewati scope organisasi atau otorisasi yang sudah ada.

### Tata Letak dan Navigasi

- Tampilkan ringkasan progress: sudah dinilai, belum dinilai, tidak dijawab, dan progress keseluruhan.
- Tampilkan hanya satu card soal essay aktif pada satu waktu.
- Card aktif memuat:
  - Nomor dan bobot soal.
  - Pertanyaan dan seluruh blok konten terstruktur.
  - Sub-soal dan jawaban terstruktur peserta.
  - Kunci jawaban dan rubrik.
  - Saran AI beserta tombol membuat/memperbarui saran.
  - Riwayat penilaian.
  - Input nilai final.
- Nilai soal yang belum pernah dinilai harus berupa input kosong, bukan angka `0`.
- Nilai nol yang sudah benar-benar disimpan tetap ditampilkan sebagai nilai sah.
- Navigator hanya berisi soal essay dan membedakan:
  - Soal aktif.
  - Sudah dinilai.
  - Belum dinilai.
  - Tidak dijawab.
- Status tidak boleh dibedakan hanya dengan warna. Gunakan teks, simbol, atau label aksesibel.
- Setiap tombol nomor memiliki target sentuh minimal 44×44 px, `aria-label`, dan `aria-current` pada soal aktif.
- Desktop menggunakan navigator samping yang sticky. Mobile menggunakan navigator horizontal yang dapat digulir tanpa menyebabkan overflow halaman.
- Sediakan tombol **Sebelumnya**, **Simpan nilai**, dan **Simpan & berikutnya**.
- Rincian seluruh jawaban, termasuk pilihan ganda, tetap tersedia dalam elemen native `<details>` di bawah ruang penilaian. Saat ruang penilaian essay tersedia, rincian ini tertutup secara default.

### Penyimpanan Nilai

- Tetap gunakan route `admin.attempts.nilai` dan payload `nilai[question_id]`.
- Setiap submit hanya boleh mengirim nilai soal aktif. Jangan mengirim ulang nilai semua soal karena akan menghasilkan audit log yang tidak perlu.
- Tambahkan field request terbatas:
  - `after_save`: `stay` atau `next`.
  - `focus_question_id`: ID soal essay aktif.
- **Simpan nilai** kembali ke soal aktif.
- **Simpan & berikutnya** menuju soal essay berikutnya berdasarkan urutan attempt.
- Jika soal aktif adalah soal terakhir:
  - Arahkan ke soal pending pertama jika masih ada.
  - Tetap di soal terakhir dan tampilkan pesan selesai jika seluruh jawaban terisi sudah dinilai.
- Redirect menggunakan query `soal={questionId}` dan anchor ruang penilaian agar posisi kerja admin dipertahankan.
- Validasi lama mengenai nilai bilangan bulat, minimum nol, dan maksimum bobot tetap berlaku.
- Gunakan progress overlay global dengan pesan **Menyimpan nilai essay...** dan perlindungan submit ganda yang sudah tersedia.
- Perubahan nilai yang belum disimpan harus memunculkan peringatan native ketika admin meninggalkan halaman. Navigasi antarsoal tidak boleh diam-diam membuang input.

### Saran AI dan Peserta Berikutnya

- Permintaan saran AI harus kembali ke soal aktif yang sama.
- Saran AI tidak otomatis menjadi nilai final.
- Setelah seluruh jawaban essay terisi milik peserta selesai dinilai, tampilkan tombol menuju peserta berikutnya yang masih memiliki essay pending.
- Jangan memasukkan soal tidak dijawab ke jumlah pending dan jangan memaksa admin memberi nilai pada jawaban kosong.

## Filter Jenis Sesi

- Tambahkan query `jenis` pada daftar sesi dengan nilai:
  - Tidak ada nilai: semua sesi.
  - `real`: hanya sesi dengan `quiz.is_percobaan = false`.
  - `simulasi`: hanya sesi dengan `quiz.is_percobaan = true`.
- Tolak atau abaikan nilai query selain `real` dan `simulasi`.
- Filter jenis harus dapat digunakan bersama `status` dan `kelas`.
- Semua link filter harus mempertahankan filter lain yang sedang aktif.
- Gunakan label antarmuka **Semua**, **Ujian nyata**, dan **Simulasi**.
- Card simulasi tetap menampilkan badge **Simulasi**. Ujian nyata tidak perlu badge tambahan.
- Data pencarian card harus menyertakan kata `simulasi` atau `ujian nyata` sesuai jenisnya.
- Empty state harus menyebut jenis sesi aktif agar pengguna memahami mengapa daftar kosong.
- Implementasi menggunakan relasi dan kolom yang sudah ada; tidak boleh menambah schema baru.

## Pengujian Wajib

Tambahkan atau perluas pengujian untuk memastikan:

- Navigator hanya memuat soal essay sesuai urutan attempt.
- Status graded, pending, unanswered, dan aktif dirender dengan benar.
- Soal aktif default adalah pending pertama dan query `soal` yang valid dapat memilih soal lain.
- Query `soal` asing, milik attempt lain, atau soal pilihan ganda diabaikan.
- Input belum dinilai kosong, sedangkan nilai nol yang sudah disimpan tetap tampil sebagai `0`.
- Submit satu soal tidak mengubah nilai, `dinilai_at`, atau audit log soal lain.
- Aksi `stay` dan `next` mengarahkan ke soal yang benar.
- Batas awal/akhir navigator dan kondisi seluruh soal selesai bekerja.
- Jawaban kosong tidak dihitung pending dan tidak membutuhkan nilai.
- Peserta berikutnya hanya berasal dari sesi dan workspace yang sama.
- Filter `real` dan `simulasi` menghasilkan daftar yang tepat.
- Filter jenis tetap bekerja ketika digabungkan dengan status dan kelas.
- Nilai query filter yang tidak dikenal kembali ke keadaan semua sesi.
- Isolasi organisasi dan pembatasan role admin tetap berlaku.

Jalankan pemeriksaan berikut:

```powershell
php artisan test --compact tests/Feature/EssayTest.php tests/Feature/AdminListFilterTest.php
php artisan test --compact
npm run build
php artisan view:clear
php artisan view:cache
php artisan route:list --name=admin.attempts.nilai
php vendor/bin/pint app/Http/Controllers/Admin/ReportController.php app/Http/Controllers/Admin/SessionController.php tests/Feature/EssayTest.php tests/Feature/AdminListFilterTest.php
git diff --check
```

Setelah formatter dijalankan, ulangi targeted test dan `git diff --check`.

## Checklist Pelaksanaan

- [x] Status repository diperiksa dan perubahan pengguna dicatat.
- [x] Tidak ada berkas di luar allowlist yang berubah.
- [x] Data status penilaian dan soal aktif disiapkan controller.
- [x] Ruang penilaian satu-card dan navigator essay selesai.
- [x] Nilai aktif dapat disimpan tanpa menyentuh soal lain.
- [x] Alur stay, next, AI, dan peserta berikutnya selesai.
- [x] Rincian seluruh jawaban dipindahkan ke `<details>`.
- [x] Filter Semua/Ujian nyata/Simulasi selesai.
- [x] Targeted tests hijau.
- [x] Seluruh test suite hijau.
- [x] Vite build dan Blade cache hijau.
- [x] Pint terbatas dan `git diff --check` hijau.
- [x] Tidak ada PDF/JPG/JFIF pengguna yang disentuh atau di-stage.
- [x] Commit dan push dilakukan hanya jika pengguna memintanya.

## Cara Menggunakan Dokumen Ini

Berikan instruksi berikut kepada agen/provider yang akan mengerjakan:

> Baca `docs/plans/penilaian-fokus-dan-filter-sesi.md` sampai selesai. Implementasikan seluruh checklist sesuai allowlist. Jangan menyentuh berkas di luar allowlist, jangan membuka UI/browser, dan jangan mengubah atau memasukkan berkas pengguna yang tidak terlacak ke commit. Jika membutuhkan berkas lain, berhenti dan laporkan alasannya. Jalankan seluruh verifikasi yang tertulis, lalu tandai checklist yang benar-benar selesai.

Jika kuota provider habis di tengah pekerjaan, agen berikutnya harus:

1. Membaca dokumen ini dan `git status --short`.
2. Memeriksa diff yang sudah ada sebelum melanjutkan.
3. Tidak mengulang pekerjaan yang sudah selesai.
4. Memperbarui checklist hanya berdasarkan bukti test atau pemeriksaan nyata.
5. Tidak menandai selesai jika masih ada test gagal atau perubahan di luar allowlist.
