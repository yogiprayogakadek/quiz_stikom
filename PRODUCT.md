# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- Master platform mengelola organisasi dan kebijakan layanan.
- Owner dan admin organisasi mengelola peserta, kelas, kuis, sesi, serta penilaian milik organisasi mereka.
- Peserta mengikuti kelas dan kuis dari organisasi tempat mereka terdaftar.

## Product Purpose

Platform kuis multi-organisasi untuk membuat, menjalankan, memantau, dan menilai kuis kelas. Keberhasilan berarti setiap organisasi dapat bekerja mandiri tanpa melihat data organisasi lain.

## Positioning

Satu instalasi melayani banyak organisasi dengan branding, peserta, provider AI, kelas, dan kuis yang terpisah. Kuis realtime dan pengawasan sesi tetap menjadi alur utama.

## Operating Context

Master mendaftarkan organisasi pertama dan owner. Owner mengundang admin, mendaftarkan peserta, membuat kelas, lalu menjalankan sesi kuis. Peserta dapat menjadi anggota lebih dari satu organisasi, tetapi bekerja dalam satu organisasi aktif pada satu waktu.

## Capabilities and Constraints

- Laravel 12, Blade, Alpine, Tailwind, Reverb, dan queue Laravel adalah stack yang sudah ada.
- Organisasi menjadi batas kepemilikan data. `created_by` hanya mencatat pembuat.
- Login master platform dipisahkan dari login admin organisasi dan peserta.
- Identitas organisasi dipakai setelah konteks organisasi diketahui.
- Provider AI harus kompatibel dengan endpoint OpenAI yang telah dipakai aplikasi.
- Penilaian essay mendukung manual, rekomendasi AI yang dikonfirmasi manusia, dan otomatis untuk kuis berisiko rendah.
- Pendaftaran publik tetap ditutup sampai isolasi organisasi, verifikasi email, pemulihan password, kuota, dan perlindungan penyalahgunaan selesai.

## Brand Commitments

Nama, logo, warna, tagline, dan layout login dapat dikustom. Nama STIKOM Quiz adalah nilai awal yang dapat diganti, bukan identitas permanen platform.

## Evidence on Hand

Repository berisi alur kelas, kuis, sesi realtime, peserta, laporan, backup, provider AI soal, cover kelas, dan pengaturan publik. Belum ada materi legal, paket harga, testimoni, atau bukti penggunaan publik yang boleh ditampilkan sebagai klaim.

## Product Principles

- Organisasi adalah tenant, bukan akun admin perorangan.
- Batas data ditegakkan pada query dan authorization, bukan hanya disembunyikan di navigasi.
- AI membantu penilaian, tetapi manusia menetapkan nilai final untuk ujian penting.
- Alur admin harus tetap dapat dipakai tanpa provider AI.
- Fitur publik dibuka bertahap setelah kontrol keamanan dasarnya tersedia.

## Accessibility & Inclusion

Seluruh kontrol harus dapat digunakan dengan keyboard, memiliki label yang jelas, dan tetap terbaca pada layar sempit serta pembesaran teks.
