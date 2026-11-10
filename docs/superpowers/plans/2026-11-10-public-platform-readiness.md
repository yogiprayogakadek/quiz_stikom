# Rencana Implementasi: Kesiapan Platform Publik

Tanggal: 10 November 2026
Status: siap dieksekusi
Spec SMTP: `docs/superpowers/specs/2026-11-10-smtp-platform-settings-design.md`

## Hasil yang dituju

Semua item yang belum dicentang di `PUBLIC_PLATFORM_CHECKLIST.md` selesai, ditambah SMTP platform dari panel master:

1. Pengaturan SMTP platform dan email uji.
2. Riwayat perubahan nilai, model, provider, dan pemberi nilai.
3. Pendaftaran organisasi mandiri dengan verifikasi email, rate limit, dan honeypot.
4. Halaman Syarat Penggunaan dan Kebijakan Privasi.
5. Kuota per organisasi untuk admin, peserta, kelas, AI, dan penyimpanan.
6. Konteks organisasi untuk seluruh job AI.
7. Export dan penghapusan data organisasi dari panel master.
8. Monitoring error, backup, dan prosedur pemulihan.

## Asumsi yang dipakai

- Pendaftaran organisasi langsung aktif setelah email owner diverifikasi. Tidak ada antrean persetujuan master.
- Rate limit pendaftaran 5 percobaan per menit per IP. Honeypot bernama `website`. Email wajib diverifikasi.
- Kuota kosong berarti memakai nilai platform dari `config/platform.php`. Nilai `0` berarti fitur diblokir.
- Export organisasi berupa satu file JSON. Berkas biner (logo dan cover) tidak ikut dibundel, hanya path-nya. Ini batas versi pertama.
- Penghapusan organisasi menghapus data tenant dan membership. Akun pengguna tidak dihapus karena akun bisa menjadi anggota organisasi lain.
- Halaman legal memakai teks kerangka yang diberi label "Draf". Teks final harus diganti setelah ditinjau. Tidak ada klaim hukum yang dibuat-buat.
- Terjemahan penuh antarmuka tidak dikerjakan pada pass ini. Catatan terbuka tetap dicatat di checklist.
- Langkah server untuk produksi dicatat di dokumen operasi. Yang bisa dikerjakan dari kode dikerjakan di sini.

## Phase 0: Baseline dan keputusan

### Task 0.1: Catat baseline

- Jalankan `php artisan test --compact`.
- Jalankan `npm run build`.
- Catat hasil di ringkasan akhir. Jangan mengubah kode dulu.

### Task 0.2: Sahkan spec SMTP

- Ubah baris `Status: menunggu persetujuan` di `docs/superpowers/specs/2026-11-10-smtp-platform-settings-design.md` menjadi `Status: disetujui`.
- Commit bersama plan ini.

## Phase 1: SMTP platform

### Task 1.1: Migrasi kolom SMTP

Buat `database/migrations/2026_11_10_000010_add_mail_settings_to_app_settings.php`.

Kolom di `app_settings`:

- `mail_host` string 255 nullable
- `mail_port` unsignedSmallInteger nullable
- `mail_encryption` string 3 nullable
- `mail_username` string 255 nullable
- `mail_password` text nullable
- `mail_from_address` string 255 nullable
- `mail_from_name` string 120 nullable

`down()` menghapus kolom dalam urutan terbalik.

### Task 1.2: Model AppSetting

Di `app/Models/AppSetting.php`:

- Tambahkan tujuh kolom ke `$fillable`.
- Tambahkan cast `mail_password => encrypted` dan `mail_port => integer`.
- Jangan tambahkan kolom SMTP ke `fields()`. `fields()` adalah daftar warisan organisasi, dan SMTP bukan milik tenant.

### Task 1.3: Service PlatformMail

Buat `app/Services/PlatformMail.php`.

Perilaku:

- `apply(): void` membaca `AppSetting::current()`.
- Jika `mail_host` kosong, keluar tanpa mengubah config. Ini mempertahankan fallback env.
- Set `mail.default = smtp`.
- Set host, port, username, password.
- Scheme `smtps` jika `mail_encryption === 'ssl'`, selain itu `smtp`.
- Port default 465 untuk SSL, 587 untuk TLS, hanya jika kolom port kosong.
- Set `mail.from.address` dan `mail.from.name`; jika kosong, pertahankan config yang ada.
- Jangan pernah menulis password ke log atau audit.

Di `app/Providers/AppServiceProvider.php`, panggil `PlatformMail::apply()` pada request web:

```php
if (! $this->app->runningInConsole()) {
    PlatformMail::apply();
}
```

Email uji memanggil `PlatformMail::apply()` sendiri agar test dan request CLI tetap memakai setting tersimpan.

### Task 1.4: Mailable email uji

Buat `app/Mail/SmtpTestMail.php` dengan:

- Constructor menerima `public string $siteName`.
- `envelope()` subject `Uji SMTP {siteName}`.
- `content()` memakai view `mail.smtp-test`.

Buat `resources/views/mail/smtp-test.blade.php`. Isi: nama platform, satu paragraf bahwa email ini membuktikan konfigurasi SMTP berfungsi, dan penutup tanpa tautan palsu.

### Task 1.5: Controller dan route

Buat `app/Http/Controllers/Master/MailSettingController.php`:

- `edit(): View` mengirim `AppSetting::current()` ke `master.mail.edit`.
- `update(Request): RedirectResponse`:
  - Validasi host, port 1..65535, encryption `tls|ssl`, username, password, from address, from name.
  - `mail_host`, `mail_port`, `mail_encryption`, `mail_from_address` wajib jika host diisi.
  - Password kosong tidak menimpa password lama.
  - Simpan ke baris `id = 1`.
  - `AuditLog::record('platform_mail_updated', 'platform', 1, ['host' => ..., 'port' => ..., 'from' => ...])` tanpa password.
- `sendTest(Request): RedirectResponse`:
  - Validasi `test_email` wajib email.
  - Panggil `PlatformMail::apply()`.
  - Kirim `new SmtpTestMail(app('app.settings')->site_name)`.
  - Tangkap `Throwable`, `report($e)`, kembalikan error `test_email` yang tidak membocorkan kredensial.

Route di grup master:

```php
Route::get('email', [MailSettingController::class, 'edit'])->name('mail.edit');
Route::put('email', [MailSettingController::class, 'update'])->name('mail.update');
Route::post('email/test', [MailSettingController::class, 'sendTest'])
    ->middleware('throttle:3,1')
    ->name('mail.test');
```

### Task 1.6: Halaman master

Buat `resources/views/master/mail/edit.blade.php` dengan `x-layouts.admin`:

- Ringkasan status: "SMTP aktif" jika host terisi, "Memakai env" jika kosong.
- Form host, port, enkripsi, username, password, from address, from name.
- Password memakai `autocomplete="new-password"` dan helper "Kosongkan untuk mempertahankan password lama."
- Tombol simpan dan tombol kirim email uji.
- Email uji memakai form terpisah dan input `test_email`.
- Tampilkan error validasi di bawah field terkait.
- Semua field punya label dan `aria-invalid` saat error.

Tambahkan ikon `mail` dan `server` di `resources/views/components/icon.blade.php`.

Di `resources/views/components/layouts/admin.blade.php`, tambahkan tiga tautan master di drawer mobile dan sidebar desktop:

- Organisasi, aktif hanya untuk `master.organizations.*`.
- Email, aktif untuk `master.mail.*`.
- Sistem, aktif untuk `master.system.*`.

Jangan pakai `resources/views/components/nav-admin.blade.php`; komponen itu tidak dirender di layout mana pun.

### Task 1.7: Tes SMTP

Buat `tests/Feature/PlatformMailSettingsTest.php`:

- `test_platform_admin_dapat_membuka_pengaturan_email`
- `test_admin_organisasi_ditolak_dari_pengaturan_email`
- `test_pengaturan_smtp_disimpan_dan_password_terenkripsi`
- `test_password_kosong_mempertahankan_password_lama`
- `test_pengaturan_smtp_divalidasi`
- `test_platform_mail_menerapkan_tls_ssl_dan_fallback`
- `test_email_uji_terkirim`
- `test_email_uji_gagal_menampilkan_error`
- `test_navigasi_master_menampilkan_tautan_email`

Gunakan `Mail::fake()` untuk email uji. Jangan memakai `MailFake::raw` karena tidak mencatat pesan.

### Task 1.8: Checklist

Centang item SMTP di `PUBLIC_PLATFORM_CHECKLIST.md` dan tambahkan catatan bahwa password tidak pernah ditampilkan kembali.

Verifikasi phase: `php artisan test --compact --filter=PlatformMailSettingsTest`.

## Phase 2: Riwayat penilaian

### Task 2.1: Simpan provider pada perubahan nilai

Di `app/Services/EssayGrader.php`:

- Sebelum menulis audit `essay_score_changed`, ambil audit `essay_ai_suggested` terbaru untuk answer yang sama.
- Tambahkan `ai_provider` ke metadata. Jika tidak ada saran AI, nilainya null.
- Pertahankan `ai_model`, `ai_suggested_score`, `from`, `to`, dan `auto`.

Jangan menambah kolom database. Provider diambil dari audit saran yang sudah ada.

### Task 2.2: Tampilkan riwayat lengkap

Di `resources/views/admin/reports/attempt.blade.php`, bagian riwayat:

- Untuk `essay_ai_suggested`, tampilkan skor saran, keyakinan, provider, dan model.
- Untuk `essay_score_changed`, tampilkan nilai sebelumnya, nilai final, pemberi nilai, dan label "Sistem" jika actor null.
- Tampilkan saran AI dan model/provider jika metadata memilikinya, atau fallback ke saran AI terbaru di koleksi riwayat.
- Tampilkan "Ditetapkan otomatis oleh sistem" jika `auto` true.
- Jangan menampilkan data kosong sebagai teks "null".

### Task 2.3: Tes riwayat

Tambahkan ke `tests/Feature/EssayTest.php`:

- `test_riwayat_nilai_final_menampilkan_provider_model_dan_pemberi_nilai`
- `test_riwayat_nilai_manual_tanpa_ai_tetap_menampilkan_pemberi_nilai`

Verifikasi phase: `php artisan test --compact --filter=EssayTest`.

## Phase 3: Pendaftaran organisasi mandiri

### Task 3.1: Controller pendaftaran

Buat `app/Http/Controllers/Auth/OrganizationRegistrationController.php`.

- `create(): View` mengembalikan `auth.register-organization`.
- `store(Request): RedirectResponse`:
  - Cek honeypot `website`. Jika terisi, kembalikan redirect dengan pesan sukses generik tanpa menulis database.
  - Validasi nama organisasi, slug unik alpha_dash, tipe, timezone, nama owner, username unik, email unik, password confirmed minimal 8, dan `terms` accepted.
  - Normalisasi slug ke huruf kecil.
  - Buat organisasi lebih dulu, lalu owner, lalu membership owner. Urutan ini mencegah hook `User::booted` menambahkan owner ke organisasi lain saat hanya ada satu organisasi.
  - Set `must_change_password = false`, `role = admin`, `is_platform_admin = false`.
  - Audit `organization_self_registered` dengan organization_id.
  - Setelah transaksi, login owner, buat `current_session_token`, isi session `auth_token`, dan regenerate session.
  - Kirim verifikasi email di luar transaksi. Jika gagal, `report($e)` dan tetap arahkan ke halaman verifikasi.
  - Redirect ke `verification.notice` dengan pesan sukses.

### Task 3.2: Route dan form

Di `routes/web.php`, grup `guest`:

```php
Route::get('/daftar', [OrganizationRegistrationController::class, 'create'])->name('register.organization');
Route::post('/daftar', [OrganizationRegistrationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('register.organization.store');
```

Buat `resources/views/auth/register-organization.blade.php`:

- `x-layouts.guest`, satu kartu, lebar maksimal nyaman, reflow satu kolom di mobile.
- Field: nama organisasi, slug, jenis, zona waktu, nama owner, username, email, password, konfirmasi.
- Honeypot `website` dengan `class="hidden"`, `tabindex="-1"`, `autocomplete="off"`.
- Checkbox `terms` dengan tautan ke Syarat dan Privasi.
- Semua field punya label, error text, dan autocomplete yang sesuai.

Tambahkan tautan "Daftarkan organisasi" di `resources/views/auth/login.blade.php`, hanya untuk login pengguna biasa, bukan login master.

### Task 3.3: Tes pendaftaran

Buat `tests/Feature/OrganizationRegistrationTest.php`:

- `test_halaman_pendaftaran_dapat_dibuka`
- `test_pendaftaran_membuat_organisasi_owner_dan_mengirim_verifikasi`
- `test_pendaftaran_menolak_slug_username_dan_email_duplikat`
- `test_pendaftaran_menolak_tanpa_persetujuan_syarat`
- `test_honeypot_tidak_membuat_organisasi`
- `test_rate_limit_pendaftaran`
- `test_owner_baru_tidak_masuk_ke_organisasi_lain`
- `test_owner_belum_verifikasi_diarahkan_ke_halaman_verifikasi`

Gunakan `Notification::fake()` untuk verifikasi email.

Verifikasi phase: `php artisan test --compact --filter=OrganizationRegistrationTest`.

## Phase 4: Halaman legal

### Task 4.1: Controller dan route

Buat `app/Http/Controllers/LegalController.php` dengan `terms()` dan `privacy()`.

Route publik di luar grup guest:

```php
Route::get('/syarat', [LegalController::class, 'terms'])->name('terms.show');
Route::get('/privasi', [LegalController::class, 'privacy'])->name('privacy.show');
```

### Task 4.2: View legal

Buat `resources/views/legal/terms.blade.php` dan `resources/views/legal/privacy.blade.php` memakai `x-layouts.guest`.

Aturan isi:

- Banner "Draf" yang jelas: dokumen belum ditinjau secara hukum dan harus diganti sebelum produksi.
- Syarat: ruang lingkup layanan, akun dan keamanan, penggunaan yang dilarang, integritas kuis, bantuan AI, penangguhan, perubahan, kontak.
- Privasi: data yang dikumpulkan, tujuan, penyimpanan, akses, penyedia AI pihak ketiga, retensi, hak pengguna, kontak.
- Kontak memakai `AppSetting::current()->mail_from_address` atau `config('mail.from.address')`.
- Jangan mengarang nomor, alamat, atau klaim kepatuhan.
- Navigasi kembali ke login dan tautan silang Syarat/Privasi.

### Task 4.3: Tautan legal

- Tautan di form pendaftaran.
- Tautan di footer login pengguna biasa.
- Tes `tests/Feature/LegalPagesTest.php`: kedua halaman 200, memuat judul, label Draf, dan tautan silang.

Verifikasi phase: `php artisan test --compact --filter=LegalPagesTest`.

## Phase 5: Kuota per organisasi dan konteks job AI

### Task 5.1: Migrasi kuota

Buat `database/migrations/2026_11_10_000011_add_quotas_to_organizations.php`:

- `ai_monthly_requests` unsignedInteger nullable
- `storage_mb` unsignedInteger nullable
- `admin_limit` unsignedSmallInteger nullable
- `participant_limit` unsignedInteger nullable
- `class_limit` unsignedSmallInteger nullable

Tambahkan kolom ke `$fillable` `Organization`.

### Task 5.2: OrganizationUsage

Di `app/Services/OrganizationUsage.php`:

- `aiLimit(int $organizationId): int` membaca kolom organisasi, fallback `config('platform.ai_monthly_requests')`.
- `storageLimitBytes(int $organizationId): int` membaca kolom organisasi, fallback `config('platform.storage_mb')`.
- Tambahkan `adminLimit`, `participantLimit`, `classLimit`.
- Tambahkan `canAddAdmin`, `canAddParticipant`, `canAddClass`, dan `participantRemaining`.
- Hitung admin dari membership `owner` dan `admin` yang aktif.
- Hitung peserta dari membership `participant` yang aktif.
- Hitung kelas dengan `Kelas::withoutGlobalScopes()->where('organization_id', ...)`.
- Semua nilai limit memakai `max(0, ...)`.
- `summary()` memakai limit organisasi, bukan global.

### Task 5.3: Tegakkan kuota

- `Admin\TeamController::store`: tolak dengan `ValidationException` jika `canAddAdmin` false.
- `Admin\ParticipantController::store`: tolak jika `canAddParticipant` false.
- `Admin\ParticipantController::import`: hitung sisa kuota. Baris setelah sisa habis ditandai gagal dengan alasan "Kuota peserta workspace penuh." Jangan membuat user atau membership.
- `Admin\KelasController::store`: tolak jika `canAddClass` false.
- Pesan error menyebut batas dan cara menghubungi master.

### Task 5.4: Halaman kuota master

Tambahkan ke `Master\OrganizationController`:

- `editLimits(Organization $organization): View`
- `updateLimits(Request, Organization $organization): RedirectResponse`

Route:

```php
Route::get('organizations/{organization}/kuota', [OrganizationController::class, 'editLimits'])->name('organizations.limits.edit');
Route::put('organizations/{organization}/kuota', [OrganizationController::class, 'updateLimits'])->name('organizations.limits.update');
```

View `resources/views/master/organizations/limits.blade.php`:

- Tampilkan pemakaian saat ini: admin, peserta, kelas, AI bulan ini, penyimpanan.
- Form lima limit. Kosong berarti default platform. Tampilkan nilai default di helper.
- Tautan kembali ke daftar organisasi.
- Tambahkan tautan "Kuota" di tabel organisasi.

Audit `organization_limits_updated` dengan nilai lama dan baru.

### Task 5.5: Konteks organisasi seluruh job AI

- `GenerateAiQuestionsJob`:
  - Load `AiGeneration::withoutGlobalScopes()`.
  - Set `OrganizationContext::force($generation->organization)` sebelum resolve `AiQuestionService`.
  - Resolve service lewat `app(AiQuestionService::class)` setelah context di-set.
  - Reset context di `finally`.
- `GenerateClassCoverJob`:
  - Load `Kelas::withoutGlobalScopes()`.
  - Set context dari `$kelas->organization` sebelum membaca `app('app.settings')`.
  - Reset context di `finally`.
- `AutoGradeEssayJob` sudah benar. Tambahkan tes regresi agar tetap memakai provider organisasi.

### Task 5.6: Tes kuota dan job

Tambahkan ke `tests/Feature/OrganizationUsageTest.php`:

- `test_limit_ai_dan_penyimpanan_memakai_nilai_organisasi`
- `test_limit_null_memakai_default_platform`
- `test_admin_tidak_bisa_menambah_admin_saat_kuota_penuh`
- `test_admin_tidak_bisa_menambah_peserta_saat_kuota_penuh`
- `test_import_peserta_berhenti_saat_kuota_penuh`
- `test_admin_tidak_bisa_menambah_kelas_saat_kuota_penuh`
- `test_master_dapat_mengubah_kuota_organisasi`
- `test_job_ai_questions_memakai_provider_organisasi`
- `test_job_cover_memakai_provider_organisasi`

Verifikasi phase: `php artisan test --compact --filter=OrganizationUsageTest`.

## Phase 6: Export dan hapus organisasi

### Task 6.1: Export JSON

Tambahkan `export(Organization $organization)` ke `Master\OrganizationController`.

Route:

```php
Route::get('organizations/{organization}/export', [OrganizationController::class, 'export'])->name('organizations.export');
```

Isi export:

- `exported_at`, `organization`
- `settings` tanpa `ai_api_key` dan `image_api_key`
- `memberships`, `users` tanpa password
- `classes`, `quizzes`, `questions` beserta options
- `sessions`, `attempts`, `answers`, `events`
- `ai_usage`, `participant_login_activities`
- `audit_logs` organisasi

Semua query memakai `withoutGlobalScopes()` dan filter `organization_id`. Nama file `organisasi-{slug}-{Ymd-His}.json`. Audit `organization_exported`.

### Task 6.2: Konfirmasi dan hapus

Tambahkan:

- `showDelete(Organization $organization): View`
- `destroy(Request, Organization $organization): RedirectResponse`

Route:

```php
Route::get('organizations/{organization}/delete', [OrganizationController::class, 'showDelete'])->name('organizations.delete');
Route::delete('organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');
```

Halaman konfirmasi:

- Jelaskan data yang dihapus dan yang tetap ada.
- Input `konfirmasi` harus sama dengan slug. Jika tidak, tolak dengan error.
- Tampilkan jumlah kelas, kuis, sesi, attempt, dan anggota.

Urutan hapus dalam transaksi:

1. Attempts, sessions, questions, quizzes, classes.
2. AiGeneration, AiUsageEvent, ParticipantLoginActivity.
3. OrganizationSetting, audit log organisasi.
4. Membership.
5. Organization.
6. Audit `organization_deleted` dengan organization_id null.

Kumpulkan path logo dan cover sebelum hapus, lalu hapus file dari disk `public` setelah transaksi berhasil. Jangan hapus akun user.

### Task 6.3: Tes export dan hapus

Buat `tests/Feature/OrganizationDataTest.php`:

- `test_master_dapat_export_data_organisasi`
- `test_export_tidak_memuat_kunci_ai`
- `test_admin_organisasi_tidak_dapat_export`
- `test_hapus_organisasi_menolak_konfirmasi_salah`
- `test_hapus_organisasi_menghapus_data_tenant_dan_file`
- `test_hapus_organisasi_tidak_menghapus_akun_user`
- `test_admin_organisasi_tidak_dapat_menghapus`

Verifikasi phase: `php artisan test --compact --filter=OrganizationDataTest`.

## Phase 7: Monitoring, backup, dan pemulihan

### Task 7.1: Service kesehatan sistem

Buat `app/Services/SystemHealth.php`:

- `snapshot(): array` mengembalikan:
  - `failed_jobs` count dan daftar 10 terbaru
  - `pending_jobs` count
  - `last_backup` dari `DatabaseBackupService::daftar()`
  - `debug` dari `config('app.debug')`
  - `queue` dari `config('queue.default')`
  - `mail_configured` dari `AppSetting::current()->mail_host`
  - `disk_free` dan `disk_total` dari `disk_free_space(base_path())` jika tersedia
- Jangan membaca isi log penuh. Tampilkan path `storage/logs/laravel.log` dan waktu modifikasi terakhir.

### Task 7.2: Halaman master sistem

Buat `app/Http/Controllers/Master/SystemHealthController.php`:

- `index(SystemHealth $health): View`
- `backupStore(DatabaseBackupService $backup): RedirectResponse`
- `backupDownload(string $nama): StreamedResponse`
- `backupDestroy(string $nama): RedirectResponse`
- `retryFailedJob(string $id): RedirectResponse`
- `forgetFailedJob(string $id): RedirectResponse`

Route di grup master:

```php
Route::get('system', [SystemHealthController::class, 'index'])->name('system.index');
Route::post('system/backups', [SystemHealthController::class, 'backupStore'])->name('system.backups.store');
Route::get('system/backups/{nama}', [SystemHealthController::class, 'backupDownload'])->where('nama', '[A-Za-z0-9._-]+')->name('system.backups.download');
Route::delete('system/backups/{nama}', [SystemHealthController::class, 'backupDestroy'])->where('nama', '[A-Za-z0-9._-]+')->name('system.backups.destroy');
Route::post('system/failed-jobs/{id}/retry', [SystemHealthController::class, 'retryFailedJob'])->name('system.failed-jobs.retry');
Route::delete('system/failed-jobs/{id}', [SystemHealthController::class, 'forgetFailedJob'])->name('system.failed-jobs.forget');
```

View `resources/views/master/system/index.blade.php`:

- Fokus halaman: menemukan kegagalan dan memulihkan. Bukan deretan kartu statistik hiasan.
- Bagian "Perlu perhatian": debug aktif, failed jobs, SMTP belum diatur, backup lama.
- Tabel failed jobs: waktu, queue, potongan pesan error satu baris, aksi retry dan hapus.
- Empty state: "Tidak ada job gagal. Antrean bersih."
- Bagian backup: daftar file, buat backup, unduh, hapus.
- Bagian prosedur pemulihan singkat dengan tautan ke `docs/operations/recovery.md`.
- Tautan ke halaman email jika SMTP belum diatur.

### Task 7.3: Dokumen operasi

Buat `docs/operations/recovery.md`:

- Aktifkan maintenance dari panel master.
- Unduh backup terbaru.
- Pulihkan dengan `mysql` ke database kosong.
- Jalankan `php artisan migrate --force`.
- Bersihkan cache config, route, dan view.
- Verifikasi login master, login organisasi, dan satu attempt.
- Nonaktifkan maintenance.
- Catat batas: backup memakai `mysqldump`, bukan snapshot storage. File logo dan cover harus dicadangkan terpisah.

Buat `docs/operations/production-checklist.md`:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` publik.
- SMTP diatur dari panel master.
- `php artisan migrate --force`.
- Queue worker berjalan dan di-restart saat deploy.
- `DB_QUEUE_RETRY_AFTER` lebih besar dari timeout job terpanjang.
- Backup terjadwal dan uji restore.
- Log dan failed jobs dipantau.

### Task 7.4: Tes monitoring

Buat `tests/Feature/SystemHealthTest.php`:

- `test_master_dapat_melihat_kesehatan_sistem`
- `test_admin_organisasi_tidak_dapat_melihat_kesehatan_sistem`
- `test_master_dapat_membuat_dan_mengunduh_backup`
- `test_master_dapat_retry_dan_menghapus_failed_job`
- `test_halaman_menampilkan_empty_state_antrean_bersih`

Verifikasi phase: `php artisan test --compact --filter=SystemHealthTest`.

## Phase 8: Checklist dan verifikasi akhir

### Task 8.1: Perbarui checklist

Di `PUBLIC_PLATFORM_CHECKLIST.md`, centang:

- Riwayat nilai, model, provider, pemberi nilai.
- Pendaftaran organisasi mandiri dan verifikasi email.
- Rate limit dan bot protection pendaftaran.
- Kuota organisasi, admin, peserta, kelas, AI.
- Export dan hapus organisasi.
- Syarat dan Privasi.
- Monitoring error, backup, prosedur pemulihan.
- SMTP platform.

Tambahkan catatan terbuka:

- Terjemahan penuh antarmuka belum dikerjakan.
- Teks legal masih draf dan perlu tinjauan.
- Export JSON belum membundel file biner.
- Langkah produksi server ada di `docs/operations/production-checklist.md`.

### Task 8.2: Verifikasi wajib

Jalankan berurutan:

```text
php artisan test --compact
npm run build
php artisan view:cache
git diff --check
```

Jika `npm` wrapper gagal, pakai `npm.cmd`. Jika `git` wrapper gagal, pakai `git.exe`.

### Task 8.3: Commit

Commit per phase dengan pesan:

- `feat: add platform smtp settings`
- `feat: show complete essay grading history`
- `feat: add organization self-registration`
- `feat: add legal pages`
- `feat: add per-organization quotas`
- `feat: add organization export and deletion`
- `feat: add system health and recovery`
- `docs: update public platform checklist`

Minta izin sebelum push.

## Risiko dan batas

- Teks legal adalah draf. Jangan mengklaim kepatuhan hukum.
- Export JSON tidak membawa file biner. Jika user butuh portabilitas penuh, upgrade ke ZIP.
- Penghapusan organisasi tidak menghapus akun user. Akun tanpa organisasi tidak bisa masuk ke dashboard sampai ditambahkan ke organisasi lain.
- Honeypot dan throttle mengurangi bot, bukan menggantikan CAPTCHA. Jika spam tetap terjadi, tambahkan CAPTCHA.
- SMTP dari database tidak berlaku untuk queue worker yang sudah berjalan. Saat ini tidak ada email queued. Jika nanti ada, worker harus memanggil `PlatformMail::apply()` atau di-restart.
- `APP_URL`, queue worker, dan restore server tetap tugas operasi. Kode hanya menyediakan panel dan dokumen.
