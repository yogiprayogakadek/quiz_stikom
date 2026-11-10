# SMTP Platform dari Panel Master

Tanggal: 10 November 2026
Status: disetujui

## Intent

Master platform dapat mengatur satu server SMTP dari panel master. Semua email verifikasi, reset password, dan email uji memakai konfigurasi itu. Saat ini aplikasi hanya membaca `MAIL_*` dari env, sehingga perubahan mengharuskan deploy.

## Goals

- Form master untuk host, port, TLS/SSL, username, password, alamat pengirim, dan nama pengirim.
- Password disimpan terenkripsi dan tidak pernah ditampilkan kembali.
- Konfigurasi berlaku tanpa deploy.
- Tombol kirim email uji memakai pengaturan yang sudah tersimpan.
- Hanya platform admin yang dapat membuka dan mengubah pengaturan.
- Checklist platform diperbarui.

## Non-goals

- SMTP per organisasi atau alamat pengirim per tenant.
- Editor template email.
- Antrean email baru.
- CAPTCHA atau penyedia transactional email.
- Migrasi otomatis kredensial dari env ke database.

## Constraints

- Laravel 12, tabel `app_settings`, `AppSetting::current()`, dan `config/mail.php` yang sudah ada.
- Route master memakai middleware `platform_admin`.
- Panel mengikuti bahasa visual admin yang sudah ada: kartu, border, input, dan aksen biru.
- Jika `mail_host` kosong, aplikasi tetap memakai konfigurasi env yang sekarang.

## Approach

**A. Platform-wide di `app_settings` (dipilih).** Satu konfigurasi SMTP untuk seluruh platform. Cocok untuk email verifikasi dan reset password karena keduanya infrastruktur platform, bukan milik tenant.

**B. Per-organisasi.** Setiap tenant menyimpan kredensial sendiri. Ditolak untuk sekarang karena menambah pilihan mailer per konteks, penyimpanan banyak secret, dan risiko deliverability per tenant.

## Data

Migration baru menambah kolom nullable ke `app_settings`:

- `mail_host` string 255
- `mail_port` unsigned small integer
- `mail_username` string 255
- `mail_password` text
- `mail_encryption` string 10, nilai `tls` atau `ssl`
- `mail_from_address` string 255
- `mail_from_name` string 120

`AppSetting` menambahkan kolom itu ke `$fillable` dan cast `mail_password` sebagai `encrypted`. `AppSetting::fields()` tidak diubah karena daftar itu dipakai untuk pewarisan pengaturan ke organisasi, dan SMTP tidak diwariskan.

## Runtime

Service baru `App\Services\PlatformMail` menyediakan `apply()`:

- Membaca `AppSetting::current()`.
- Jika `mail_host` kosong, tidak mengubah konfigurasi env.
- Jika terisi, mengatur `mail.default` ke `smtp`, host, port, username, password, scheme, dan `mail.from`.
- `tls` memakai scheme `smtp`; `ssl` memakai scheme `smtps`.
- Port kosong memakai 587 untuk TLS dan 465 untuk SSL.

`apply()` dipanggil pada request web sebelum controller berjalan, dan dipanggil ulang pada aksi kirim email uji. Email yang dikirim dari job antrean harus memanggil `apply()` saat fitur itu ditambahkan. Perubahan pengaturan tidak otomatis masuk ke worker yang sudah berjalan lama.

## UI

Halaman master baru: `GET /master/email`, `PUT /master/email`, `POST /master/email/test`.

Isi halaman:

- Kartu status: "SMTP aktif" atau "SMTP belum diatur", host, port, dan alamat pengirim. Password tidak ditampilkan.
- Form server: host, port, enkripsi TLS/SSL, username, password, alamat pengirim, nama pengirim.
- Form terpisah untuk email uji: alamat tujuan dan tombol kirim.
- Navigasi master mendapat item "Email" dengan ikon surat.
- Empty state menjelaskan bahwa email memakai env sampai host diisi.
- Error validasi dan error pengiriman tampil di dekat form.
- Tombol memakai indikator progres global yang sudah ada.

Password memakai input `type="password"` dan placeholder "Kosongkan untuk mempertahankan password saat ini". Tidak ada tombol untuk melihat password.

## Email Uji

Mailable baru `App\Mail\SmtpTestMail` dengan view teks sederhana. Aksi:

- Validasi alamat tujuan.
- Terapkan pengaturan SMTP terbaru.
- Kirim email.
- Tangkap kegagalan, catat pesan umum tanpa password, lalu kembalikan error ke form.
- Route diberi throttle 3 kirim per menit.

## Validasi

- `mail_host`: nullable, string, maksimal 255.
- `mail_port`: nullable, integer, 1 sampai 65535.
- `mail_encryption`: wajib jika host terisi, nilai `tls` atau `ssl`.
- `mail_username`: nullable, string, maksimal 255.
- `mail_password`: nullable, string, maksimal 500.
- `mail_from_address`: wajib jika host terisi, email, maksimal 255.
- `mail_from_name`: nullable, string, maksimal 120.
- Password kosong mempertahankan nilai lama.

## Keamanan dan Audit

- Hanya platform admin.
- Password terenkripsi di database, tidak muncul di HTML, log, atau pesan error.
- Perubahan pengaturan dicatat ke `AuditLog` sebagai `platform_mail_updated` tanpa nilai secret.
- Route email uji dibatasi rate limit.

## Tests

- Platform admin dapat membuka halaman.
- Admin organisasi dan tamu tidak dapat membuka halaman.
- Pengaturan tersimpan dan password terenkripsi.
- Password kosong mempertahankan password lama.
- Validasi host, port, enkripsi, dan alamat pengirim.
- `PlatformMail::apply()` mengatur TLS, SSL, dan fallback env.
- Email uji terkirim ke alamat yang diminta.
- Kegagalan SMTP menampilkan error tanpa membocorkan password.
- Halaman memuat label dan input yang diperlukan.

## Checklist

Tambahkan ke `PUBLIC_PLATFORM_CHECKLIST.md`:

- Item SMTP platform dari panel master.
- Catatan terbuka: terjemahan penuh, konteks organisasi untuk seluruh job AI, dan checklist kesiapan produksi.

## Risks

- Deliverability tetap bergantung pada SPF, DKIM, dan reputasi domain di luar aplikasi.
- Password tidak dapat dibaca kembali, hanya diganti.
- Perubahan SMTP tidak otomatis masuk ke worker antrean yang sudah berjalan.
- SMTP platform-wide berarti organisasi tidak dapat memakai domain pengirim sendiri.

## Setelah Desain Ini

Item checklist lain dikerjakan setelah SMTP selesai, dengan urutan yang sudah disepakati: riwayat nilai, pendaftaran organisasi dan proteksi bot, kuota per organisasi, export dan penghapusan data, syarat dan privasi, lalu monitoring dan pemulihan.
