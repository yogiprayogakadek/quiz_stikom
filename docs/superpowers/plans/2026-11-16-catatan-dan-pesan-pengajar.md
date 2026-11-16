# Catatan Pengajar per Jawaban + Pesan Pengajar Saat Ujian

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pengajar bisa menulis catatan pada setiap jawaban essay saat menilai, peserta melihat catatan itu setelah semua essay dinilai, dan pengajar bisa mengirim pesan yang muncul sebagai toast kanan atas ke peserta yang sedang mengerjakan (blast atau pilihan).

**Architecture:** Catatan disimpan di kolom baru `answers.catatan_pengajar`, ditulis dari form ruang penilaian yang sudah ada dan dirender di kartu terpisah pada halaman hasil. Pesan disimpan di tabel `session_messages` (satu baris per penerima), disiarkan lewat kanal publik `session-user.{session}.{user}` yang sudah dipakai event pengawasan, dengan cadangan polling di endpoint status/state yang sudah ada.

**Tech Stack:** Laravel 12, Blade + Alpine.js, Laravel Reverb (broadcast `ShouldBroadcastNow`), Tailwind CSS v4, PHPUnit (`php artisan test`).

**Spec:** Keputusan desain ada di bagian "Keputusan" di bawah (hasil brainstorming dengan pemilik produk, 16 Nov 2026).

## Keputusan

- Catatan hanya untuk soal essay, karena penilaian hanya tersedia untuk essay mode acak (`ReportController::nilaiEssay` menolak selain itu).
- Catatan baru terlihat peserta setelah **semua** essay yang dijawab selesai dinilai. Tidak bergantung pada pengaturan pembahasan.
- Pesan pengajar hanya bisa dikirim saat sesi `berjalan`, dan hanya ke attempt berstatus `berjalan`.
- Toast pesan menetap sampai peserta menutupnya, disimpan di DB, dan dikirim ulang lewat polling bila websocket putus.

**Non-goals:** catatan untuk soal pilihan ganda; thread/riwayat banyak catatan per jawaban; toast di dashboard peserta atau layar lobby; pengiriman pesan ke peserta yang belum bergabung.

## Global Constraints

- Teks UI berbahasa Indonesia, tanpa em dash (R-02). Gunakan koma, titik, atau tanda kurung.
- Semua kontrol: target sentuh minimal 44px (`min-h-11`), punya label/`aria-label`, dan fokus keyboard terlihat (R-03, R-32, R-39).
- Kontras teks memakai token yang sudah ada (`text-ink`, `text-ink-muted`, `border-outline`, `bg-page`, `border-brand`). Jangan bikin palet baru.
- Tanpa dependency baru. Tanpa library toast.
- Toast pengumuman memakai `role="status"` + `aria-live="polite"`, bukan `alert`, karena tidak memblokir ujian.
- Semua query DB lewat Eloquent dan ikut `organization_id` yang sudah ada di model terkait.
- Test wajib lulus: `php artisan test`.

## Review Focus

- Essay yang tidak dijawab (`jawaban_teks` kosong) tidak boleh menghalangi tampilnya catatan, dan tidak boleh menerima catatan. Aturan ini memakai `jawaban_teks` persis seperti ruang penilaian, termasuk untuk essay berstruktur, supaya kedua layar tidak pernah berbeda status.
- Menyimpan catatan pada jawaban yang sudah dinilai tidak boleh mengubah `dinilai_at`/`skor` atau menggandakan log nilai.
- `AutoGradeEssayJob` dan `EssayGrader::applyFinal` tanpa argumen catatan tidak boleh menghapus catatan guru.
- Blast pesan saat ada peserta yang baru selesai di antara pemilihan dan insert: attempt yang sudah tidak `berjalan` dilewati, jumlah terkirim dilaporkan apa adanya.
- Menutup pesan milik peserta lain harus 403, dan menutup dua kali tetap 200 (idempoten).
- Websocket mati: pesan tetap muncul lewat polling dalam 5 detik (mode acak) atau 3 detik (mode serempak).

## Struktur File

**Create**
- `database/migrations/2026_11_16_000001_add_catatan_pengajar_to_answers_table.php`
- `database/migrations/2026_11_16_000002_create_session_messages_table.php`
- `app/Models/SessionMessage.php`
- `app/Events/PesanPengajar.php`
- `app/Http/Controllers/Admin/SessionMessageController.php`
- `app/Http/Controllers/Peserta/SessionMessageController.php`
- `resources/views/admin/sessions/_pesan.blade.php`
- `tests/Feature/CatatanPengajarTest.php`
- `tests/Feature/PesanPengajarTest.php`

**Modify**
- `app/Models/Answer.php`
- `app/Services/EssayGrader.php`
- `app/Http/Controllers/Admin/ReportController.php`
- `resources/views/admin/reports/attempt.blade.php`
- `app/Http/Controllers/Peserta/AttemptController.php`
- `app/Http/Controllers/Peserta/SessionController.php`
- `resources/views/peserta/hasil.blade.php`
- `resources/views/peserta/attempt/show.blade.php`
- `resources/views/peserta/session/play.blade.php`
- `resources/views/admin/sessions/_peserta.blade.php`
- `routes/web.php`

---

### Task 1: Kolom `catatan_pengajar` pada jawaban

**Files:**
- Create: `database/migrations/2026_11_16_000001_add_catatan_pengajar_to_answers_table.php`
- Modify: `app/Models/Answer.php`
- Test: `tests/Feature/CatatanPengajarTest.php`

**Interfaces:**
- Produces: `Answer::catatan_pengajar` (string|null, mass-assignable).

- [ ] **Step 1: Tulis test gagal**

```php
public function test_catatan_pengajar_tersimpan_dan_terbaca(): void
{
    $answer = $this->jawabanEssay(); // helper di file test ini, lihat Task 2 Step 1

    $answer->update(['catatan_pengajar' => 'Struktur argumen sudah tepat.']);

    $this->assertSame('Struktur argumen sudah tepat.', $answer->fresh()->catatan_pengajar);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=test_catatan_pengajar_tersimpan_dan_terbaca`
Expected: FAIL, kolom `catatan_pengajar` tidak dikenal.

- [ ] **Step 3: Tambah migration dan fillable**

Migration: `Schema::table('answers', fn (Blueprint $t) => $t->text('catatan_pengajar')->nullable());` dan `down()` memanggil `dropColumn`.
Tambahkan `'catatan_pengajar'` ke `$fillable` di `app/Models/Answer.php`.

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=test_catatan_pengajar_tersimpan_dan_terbaca`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Models/Answer.php tests/Feature/CatatanPengajarTest.php
git commit -m "feat: tambah kolom catatan pengajar pada answers"
```

---

### Task 2: Simpan catatan bersama nilai

**Files:**
- Modify: `app/Services/EssayGrader.php:80-131` (`applyFinal`)
- Modify: `app/Http/Controllers/Admin/ReportController.php:224-320` (`nilaiEssay`) dan `:108-215` (`attempt`)
- Test: `tests/Feature/CatatanPengajarTest.php`

**Interfaces:**
- Consumes: `answers.catatan_pengajar` dari Task 1.
- Produces:
  - `EssayGrader::applyFinal(Answer $answer, int $score, ?int $userId = null, bool $auto = false, bool $onlyIfUnscored = false, ?string $catatan = null): bool`
  - Rincian `ReportController::attempt` memuat key `catatan_pengajar` (string|null).

- [ ] **Step 1: Tulis test gagal**

Tambahkan helper `jawabanEssay(array $sessionOverrides = [])` (Organization, admin, quiz, sesi `acak` status `selesai`, participant, attempt, satu soal essay, satu `Answer` berisi `jawaban_teks`) mengikuti pola setup `tests/Feature/ExamControlTest.php:506-560`. Helper mengembalikan `[admin, attempt, answer, organization]` dan menerima override seperti `pembahasan_mode`.

```php
public function test_admin_menyimpan_nilai_dan_catatan_sekaligus(): void
{
    [$admin, $attempt, $answer, $organization] = $this->jawabanEssay();

    $this->actingAs($admin)
        ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
        ->post(route('admin.attempts.nilai', $attempt), [
            'nilai' => [$answer->question_id => 7],
            'catatan' => [$answer->question_id => 'Kesimpulan kurang tajam.'],
        ])
        ->assertRedirect();

    $answer->refresh();
    $this->assertSame(7, $answer->skor);
    $this->assertSame('Kesimpulan kurang tajam.', $answer->catatan_pengajar);
}

public function test_auto_grade_tidak_menghapus_catatan_guru(): void
{
    [, , $answer] = $this->jawabanEssay();
    $answer->update(['catatan_pengajar' => 'Catatan guru.']);

    app(\App\Services\EssayGrader::class)->applyFinal($answer, 5, null, true);

    $this->assertSame('Catatan guru.', $answer->fresh()->catatan_pengajar);
}

public function test_catatan_lebih_dari_2000_karakter_ditolak(): void
{
    [$admin, $attempt, $answer, $organization] = $this->jawabanEssay();

    $this->actingAs($admin)
        ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
        ->post(route('admin.attempts.nilai', $attempt), [
            'nilai' => [$answer->question_id => 5],
            'catatan' => [$answer->question_id => str_repeat('a', 2001)],
        ])
        ->assertSessionHasErrors('catatan.'.$answer->question_id);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=CatatanPengajarTest`
Expected: FAIL pada ketiga test (kolom belum ditulis, validasi belum ada).

- [ ] **Step 3: Implementasi**

Di `EssayGrader::applyFinal`, tambah parameter `?string $catatan = null` dan `$lengkap = ['skor' => $score, 'dinilai_at' => now(), 'dinilai_oleh' => $userId];`. Jika `$catatan !== null`, tambahkan `$lengkap['catatan_pengajar'] = trim($catatan) === '' ? null : trim($catatan);` lalu pakai `$lengkap` di `Answer::query()->...->update($lengkap)`. Jangan sentuh log `essay_score_changed`.

Di `ReportController::nilaiEssay`, tambah validasi:
```php
'catatan' => ['nullable', 'array'],
'catatan.*' => ['nullable', 'string', 'max:2000'],
```
dan panggil `$grader->applyFinal($answer, (int) $nilai, $request->user()->id, catatan: $data['catatan'][$questionId] ?? null);`.

Di `ReportController::attempt`, tambahkan `'catatan_pengajar' => $answer?->catatan_pengajar,` pada array rincian.

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=CatatanPengajarTest`
Expected: PASS semua.

- [ ] **Step 5: Commit**

```bash
git add app/Services/EssayGrader.php app/Http/Controllers/Admin/ReportController.php tests/Feature/CatatanPengajarTest.php
git commit -m "feat: simpan catatan pengajar bersama nilai essay"
```

---

### Task 3: Isian catatan di ruang penilaian

**Files:**
- Modify: `resources/views/admin/reports/attempt.blade.php:341-380`
- Test: `tests/Feature/CatatanPengajarTest.php`

**Interfaces:**
- Consumes: `$aktifEssay['catatan_pengajar']` dari Task 2.
- Produces: field POST `catatan[{question_id}]` pada form `admin.attempts.nilai`.

- [ ] **Step 1: Tulis test gagal**

```php
public function test_ruang_penilaian_menampilkan_isian_catatan(): void
{
    [$admin, $attempt, $answer] = $this->jawabanEssay();

    $this->actingAs($admin)
        ->get(route('admin.reports.attempt', $attempt))
        ->assertOk()
        ->assertSee('name="catatan['.$answer->question_id.']"', false)
        ->assertSee('Catatan untuk peserta');
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=test_ruang_penilaian_menampilkan_isian_catatan`
Expected: FAIL, teks tidak ditemukan.

- [ ] **Step 3: Implementasi**

Di blok `@php` yang menyiapkan `$nilaiAwal`, tambahkan `$catatanAwal = old('catatan.'.$aktifEssay['question_id'], $aktifEssay['catatan_pengajar']);`.
Di dalam form `admin.attempts.nilai`, setelah field nilai, tambahkan:
```blade
<label for="catatan-{{ $aktifEssay['question_id'] }}" class="label">Catatan untuk peserta</label>
<textarea id="catatan-{{ $aktifEssay['question_id'] }}"
    name="catatan[{{ $aktifEssay['question_id'] }}]"
    rows="3" maxlength="2000" class="input"
    aria-describedby="bantuan-catatan-{{ $aktifEssay['question_id'] }}"
    @error('catatan.'.$aktifEssay['question_id']) aria-invalid="true" @enderror>{{ $catatanAwal }}</textarea>
<p id="bantuan-catatan-{{ $aktifEssay['question_id'] }}" class="help mt-1">Tampil ke peserta setelah semua essay dinilai. Kosongkan untuk menghapus.</p>
@error('catatan.'.$aktifEssay['question_id']) <p class="field-error">{{ $message }}</p> @enderror
```
Field catatan tidak boleh `required` dan tidak mengubah `min`/`max` nilai.

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=CatatanPengajarTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/reports/attempt.blade.php tests/Feature/CatatanPengajarTest.php
git commit -m "feat: isian catatan pengajar di ruang penilaian essay"
```

---

### Task 4: Kartu catatan pengajar di halaman hasil peserta

**Files:**
- Modify: `app/Http/Controllers/Peserta/AttemptController.php:461-518`
- Modify: `app/Http/Controllers/Peserta/SessionController.php:280-309`
- Modify: `resources/views/peserta/hasil.blade.php:128`
- Test: `tests/Feature/CatatanPengajarTest.php`

**Interfaces:**
- Produces: view `peserta.hasil` menerima `catatanPengajar` (Collection of `['urutan' => int, 'skor' => int, 'bobot' => int, 'catatan' => string]`) dan `catatanTampil` (bool).

- [ ] **Step 1: Tulis test gagal**

Tambahkan helper `tambahEssayKedua(Attempt $attempt)`: buat `Question` essay baru (`bobot` 10), tautkan ke attempt lewat `AttemptQuestion`, lalu buat `Answer` dengan `jawaban_teks` terisi dan `dinilai_at` null.

```php
public function test_catatan_tersembunyi_selama_masih_ada_essay_belum_dinilai(): void
{
    [$admin, $attempt, $answer] = $this->jawabanEssay();
    $this->tambahEssayKedua($attempt); // jawaban terisi, dinilai_at null
    app(EssayGrader::class)->applyFinal($answer, 8, $admin->id, catatan: 'Baik.');

    $this->actingAs($attempt->user)
        ->get(route('peserta.attempt.result', $attempt))
        ->assertOk()
        ->assertDontSee('Catatan pengajar');
}

public function test_catatan_tampil_setelah_semua_essay_dinilai_walau_pembahasan_tertutup(): void
{
    [$admin, $attempt, $answer] = $this->jawabanEssay(['pembahasan_mode' => 'tertutup']);
    app(EssayGrader::class)->applyFinal($answer, 8, $admin->id, catatan: 'Argumen kuat, contoh kurang.');

    $this->actingAs($attempt->user)
        ->get(route('peserta.attempt.result', $attempt))
        ->assertOk()
        ->assertSee('Catatan pengajar')
        ->assertSee('Argumen kuat, contoh kurang.');
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=CatatanPengajarTest`
Expected: FAIL, kartu belum ada.

- [ ] **Step 3: Implementasi**

Di `AttemptController::result`, setelah `$rincian` dibuat:
```php
$catatanPengajar = $rincian
    ->filter(fn (array $baris) => $baris['tipe'] === 'essay' && filled($baris['catatan_pengajar']))
    ->map(fn (array $baris) => [
        'urutan' => $baris['urutan'],
        'skor' => $baris['skor'],
        'bobot' => $baris['bobot'],
        'catatan' => $baris['catatan_pengajar'],
    ])->values();

$essayBelumDinilai = $rincian->filter(fn (array $baris) => $baris['tipe'] === 'essay'
    && filled($baris['jawaban_saya'])
    && $baris['dinilai_at'] === null)->count();
```
lalu kirim `'catatanPengajar' => $catatanPengajar` dan `'catatanTampil' => $catatanPengajar->isNotEmpty() && $essayBelumDinilai === 0` dari `view(...)`.
Di `SessionController::result`, tambahkan `'catatanPengajar' => collect()` dan `'catatanTampil' => false` pada array view.

Di `resources/views/peserta/hasil.blade.php`, tepat sebelum `@if ($tampilkan)` blok rincian, tambahkan section:
```blade
@if ($catatanTampil)
    <section class="mt-6 card" aria-labelledby="catatan-pengajar">
        <h2 id="catatan-pengajar" class="text-2xl">Catatan pengajar</h2>
        <p class="mt-1 text-base text-ink-muted">Ditulis per soal setelah seluruh jawaban essay dinilai.</p>
        <ul class="mt-4 space-y-4">
            @foreach ($catatanPengajar as $baris)
                <li class="rounded-xl border border-outline bg-page p-4">
                    <p class="text-sm font-bold text-ink">Soal {{ $baris['urutan'] }} · Nilai {{ $baris['skor'] }} / {{ $baris['bobot'] }}</p>
                    <p class="mt-1 whitespace-pre-line text-base text-ink">{{ $baris['catatan'] }}</p>
                </li>
            @endforeach
        </ul>
    </section>
@endif
```
Jangan tambahkan kolom baru pada tabel rincian.

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=CatatanPengajarTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Peserta/AttemptController.php app/Http/Controllers/Peserta/SessionController.php resources/views/peserta/hasil.blade.php tests/Feature/CatatanPengajarTest.php
git commit -m "feat: tampilkan catatan pengajar di hasil peserta setelah semua essay dinilai"
```

---

### Task 5: Tabel dan model `session_messages`

**Files:**
- Create: `database/migrations/2026_11_16_000002_create_session_messages_table.php`
- Create: `app/Models/SessionMessage.php`
- Test: `tests/Feature/PesanPengajarTest.php`

**Interfaces:**
- Produces:
  - `SessionMessage` fillable: `quiz_session_id`, `attempt_id`, `user_id`, `pengirim_id`, `teks`, `tipe`, `ditutup_at`.
  - `SessionMessage::aktifUntukAttempt(int $attemptId): Collection<int, SessionMessage>`
  - `SessionMessage::untukPeserta(): array{id:int, teks:string, tipe:string, waktu:string}` (waktu format `H:i`)

- [ ] **Step 1: Tulis test gagal**

```php
public function test_pesan_aktif_hanya_yang_belum_ditutup(): void
{
    [, , $attempts] = $this->sesiBerjalan(1);
    $attempt = $attempts[0];
    $aktif = SessionMessage::create([
        'quiz_session_id' => $attempt->quiz_session_id,
        'attempt_id' => $attempt->id,
        'user_id' => $attempt->user_id,
        'teks' => 'Sisa waktu 10 menit.',
        'tipe' => 'info',
    ]);
    SessionMessage::create([
        'quiz_session_id' => $attempt->quiz_session_id,
        'attempt_id' => $attempt->id,
        'user_id' => $attempt->user_id,
        'teks' => 'Sudah ditutup.',
        'tipe' => 'info',
        'ditutup_at' => now(),
    ]);

    $hasil = SessionMessage::aktifUntukAttempt($attempt->id);

    $this->assertCount(1, $hasil);
    $this->assertSame($aktif->id, $hasil->first()->id);
    $this->assertSame(['id', 'teks', 'tipe', 'waktu'], array_keys($hasil->first()->untukPeserta()));
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=test_pesan_aktif_hanya_yang_belum_ditutup`
Expected: FAIL, class tidak ada.

- [ ] **Step 3: Implementasi**

Migration:
```php
Schema::create('session_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
    $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('pengirim_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('teks', 500);
    $table->string('tipe', 20)->default('info');
    $table->timestamp('ditutup_at')->nullable();
    $table->timestamps();
    $table->index(['attempt_id', 'ditutup_at']);
});
```
Model: relasi `attempt()` dan `pengirim()`, cast `ditutup_at` ke `datetime`, `aktifUntukAttempt` mengurutkan `orderBy('id')` dan `whereNull('ditutup_at')`, `untukPeserta()` mengembalikan `['id', 'teks', 'tipe', 'waktu' => $this->created_at->timezone(config('app.timezone'))->format('H:i')]`.

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=test_pesan_aktif_hanya_yang_belum_ditutup`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Models/SessionMessage.php tests/Feature/PesanPengajarTest.php
git commit -m "feat: tambah tabel dan model session_messages"
```

---

### Task 6: Event `PesanPengajar`

**Files:**
- Create: `app/Events/PesanPengajar.php`
- Test: `tests/Feature/PesanPengajarTest.php`

**Interfaces:**
- Produces: `PesanPengajar::safeDispatch(int $sessionId, int $userId, array $payload): void`, siaran `PesanPengajar` pada kanal `session-user.{sessionId}.{userId}`.

- [ ] **Step 1: Tulis test gagal**

```php
public function test_event_pesan_disiarakan_ke_kanal_peserta(): void
{
    $event = new PesanPengajar(9, 42, ['id' => 1, 'teks' => 'Halo', 'tipe' => 'info', 'waktu' => '10:15']);

    $this->assertSame('session-user.9.42', $event->broadcastOn()[0]->name);
    $this->assertSame('PesanPengajar', $event->broadcastAs());
    $this->assertSame('Halo', $event->broadcastWith()['teks']);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=test_event_pesan_disiarakan_ke_kanal_peserta`
Expected: FAIL, class tidak ada.

- [ ] **Step 3: Implementasi**

Bentuk class mengikuti `app/Events/PesertaPeringatan.php`: `ShouldBroadcastNow`, konstruktor `(int $sessionId, int $userId, array $payload)`, `safeDispatch` menangkap `Throwable` dan menulis `Log::warning('PesanPengajar broadcast error: '.$e->getMessage())`, `broadcastOn()` mengembalikan `[new Channel('session-user.'.$this->sessionId.'.'.$this->userId)]`, `broadcastAs()` `'PesanPengajar'`, `broadcastWith()` mengembalikan `$this->payload`.

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=test_event_pesan_disiarakan_ke_kanal_peserta`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Events/PesanPengajar.php tests/Feature/PesanPengajarTest.php
git commit -m "feat: event PesanPengajar untuk toast peserta"
```

---

### Task 7: Kirim pesan dari panel admin

**Files:**
- Create: `app/Http/Controllers/Admin/SessionMessageController.php`
- Modify: `routes/web.php:216` (setelah route `sessions.attempts.kick`)
- Test: `tests/Feature/PesanPengajarTest.php`

**Interfaces:**
- Produces: route `POST admin/sessions/{session}/pesan` nama `admin.sessions.pesan`, field `teks` (max 500), `target` (`semua|terpilih`), `attempt_ids[]`.

- [ ] **Step 1: Tulis test gagal**

Helper `sesiBerjalan(int $jumlahPeserta = 2)` mengikuti pola setup `tests/Feature/ExamControlTest.php:506-560` dan mengembalikan `[admin, session, attempts, organization]` dengan organisasi, admin, kelas, kuis, sesi `acak` status `berjalan`, dan N attempt status `berjalan` (`$attempts` adalah Collection, urut id).

```php
public function test_blast_mengirim_ke_semua_peserta_yang_sedang_mengerjakan(): void
{
    Event::fake([PesanPengajar::class]);
    [$admin, $session, $attempts, $organization] = $this->sesiBerjalan(2);

    $this->actingAs($admin)
        ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
        ->post(route('admin.sessions.pesan', $session), ['teks' => 'Sisa waktu 10 menit.', 'target' => 'semua'])
        ->assertRedirect();

    $this->assertSame(2, SessionMessage::where('quiz_session_id', $session->id)->count());
    Event::assertDispatchedTimes(PesanPengajar::class, 2);
}

public function test_pilih_peserta_hanya_mengirim_ke_attempt_terpilih(): void
{
    Event::fake([PesanPengajar::class]);
    [$admin, $session, $attempts, $organization] = $this->sesiBerjalan(2);

    $this->actingAs($admin)
        ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
        ->post(route('admin.sessions.pesan', $session), [
            'teks' => 'Periksa soal nomor 3.',
            'target' => 'terpilih',
            'attempt_ids' => [$attempts[0]->id],
        ])
        ->assertRedirect();

    $this->assertSame(1, SessionMessage::count());
    $this->assertSame($attempts[0]->id, SessionMessage::first()->attempt_id);
}

public function test_pesan_kosong_dan_sesi_selesai_ditolak(): void
{
    [$admin, $session, , $organization] = $this->sesiBerjalan(1);

    $this->actingAs($admin)
        ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
        ->post(route('admin.sessions.pesan', $session), ['teks' => '  ', 'target' => 'semua'])
        ->assertSessionHasErrors('teks');

    $session->update(['status' => 'selesai']);

    $this->actingAs($admin)
        ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
        ->post(route('admin.sessions.pesan', $session), ['teks' => 'Halo', 'target' => 'semua'])
        ->assertSessionHasErrors('teks');

    $this->assertSame(0, SessionMessage::count());
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=PesanPengajarTest`
Expected: FAIL, route tidak ada.

- [ ] **Step 3: Implementasi**

Controller `store(Request $request, QuizSession $session)`:
1. Jika `$session->status !== 'berjalan'`, lempar `ValidationException::withMessages(['teks' => 'Pesan hanya bisa dikirim saat sesi berjalan.'])`.
2. Validasi:
```php
$data = $request->validate([
    'teks' => ['required', 'string', 'max:500'],
    'target' => ['required', 'in:semua,terpilih'],
    'attempt_ids' => ['required_if:target,terpilih', 'array', 'min:1'],
    'attempt_ids.*' => ['integer'],
]);
```
3. Ambil penerima: `$session->attempts()->where('status', 'berjalan')->when($data['target'] === 'terpilih', fn ($q) => $q->whereIn('id', array_map('intval', $data['attempt_ids'])))->get()`. Jika kosong, `ValidationException` pada `attempt_ids` dengan pesan `'Tidak ada peserta yang sedang mengerjakan.'`.
4. Dalam `DB::transaction`, buat satu `SessionMessage` per attempt (teks `trim($data['teks'])`, `pengirim_id` = `$request->user()->id`, `tipe` `'info'`).
5. Setelah commit, untuk tiap pesan panggil `PesanPengajar::safeDispatch($session->id, $pesan->user_id, $pesan->untukPeserta())`.
6. `return back()->with('sukses', "Pesan terkirim ke {$jumlah} peserta.");`

Route di grup admin: `Route::post('sessions/{session}/pesan', [SessionMessageController::class, 'store'])->name('sessions.pesan');` dan tambahkan `use` di atas `routes/web.php`.

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=PesanPengajarTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/SessionMessageController.php routes/web.php tests/Feature/PesanPengajarTest.php
git commit -m "feat: endpoint kirim pesan pengajar ke peserta ujian"
```

---

### Task 8: Peserta menutup pesan

**Files:**
- Create: `app/Http/Controllers/Peserta/SessionMessageController.php`
- Modify: `routes/web.php:250` (dalam grup peserta, dekat route `peserta.attempt.feedback`)
- Test: `tests/Feature/PesanPengajarTest.php`

**Interfaces:**
- Produces: `POST /pesan/{message}/tutup` nama `peserta.pesan.tutup`, respons JSON `{"ok": true}`.

- [ ] **Step 1: Tulis test gagal**

```php
public function test_peserta_menutup_pesannya_sendiri_dan_idempoten(): void
{
    [, , $attempts] = $this->sesiBerjalan(1);
    $attempt = $attempts[0];
    $pesan = SessionMessage::create([
        'quiz_session_id' => $attempt->quiz_session_id,
        'attempt_id' => $attempt->id,
        'user_id' => $attempt->user_id,
        'teks' => 'Halo',
        'tipe' => 'info',
    ]);

    $this->actingAs($attempt->user)
        ->withSession(['auth_token' => $attempt->user->current_session_token])
        ->postJson(route('peserta.pesan.tutup', $pesan))
        ->assertOk()
        ->assertJson(['ok' => true]);

    $this->assertNotNull($pesan->fresh()->ditutup_at);

    $this->actingAs($attempt->user)
        ->withSession(['auth_token' => $attempt->user->current_session_token])
        ->postJson(route('peserta.pesan.tutup', $pesan))
        ->assertOk();
}

public function test_peserta_lain_tidak_bisa_menutup_pesan_orang(): void
{
    [, , $attempts] = $this->sesiBerjalan(2);
    $attempt = $attempts[0];
    $pesan = SessionMessage::create([
        'quiz_session_id' => $attempt->quiz_session_id,
        'attempt_id' => $attempt->id,
        'user_id' => $attempt->user_id,
        'teks' => 'Halo',
        'tipe' => 'info',
    ]);
    $lain = \App\Models\User::whereKeyNot($attempt->user_id)->where('role', 'peserta')->firstOrFail();

    $this->actingAs($lain)
        ->withSession(['auth_token' => $lain->current_session_token])
        ->postJson(route('peserta.pesan.tutup', $pesan))
        ->assertForbidden();
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=PesanPengajarTest`
Expected: FAIL, route tidak ada.

- [ ] **Step 3: Implementasi**

`tutup(Request $request, SessionMessage $message): JsonResponse`:
```php
abort_unless($message->user_id === $request->user()->id, 403);
if ($message->ditutup_at === null) {
    $message->forceFill(['ditutup_at' => now()])->save();
}
return response()->json(['ok' => true]);
```
Route di grup peserta: `Route::post('/pesan/{message}/tutup', [PesertaSessionMessageController::class, 'tutup'])->name('pesan.tutup');` dengan alias import `use App\Http\Controllers\Peserta\SessionMessageController as PesertaSessionMessageController;`.

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=PesanPengajarTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Peserta/SessionMessageController.php routes/web.php tests/Feature/PesanPengajarTest.php
git commit -m "feat: endpoint tutup pesan pengajar untuk peserta"
```

---

### Task 9: Composer pesan di panel admin

**Files:**
- Create: `resources/views/admin/sessions/_pesan.blade.php`
- Modify: `resources/views/admin/sessions/_peserta.blade.php:32` (setelah form tambah waktu, sebelum tabel)
- Test: `tests/Feature/PesanPengajarTest.php`

**Interfaces:**
- Consumes: route `admin.sessions.pesan` (Task 7), variabel `$session` dan `$attempts` yang sudah ada di view.
- Produces: dialog `#dialog-pesan` dengan radio `target`, checklist `attempt_ids[]`, textarea `teks`.

- [ ] **Step 1: Tulis test gagal**

```php
public function test_panel_admin_menampilkan_composer_pesan_saat_sesi_berjalan(): void
{
    [$admin, $session, , $organization] = $this->sesiBerjalan(1);

    $this->actingAs($admin)
        ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
        ->get(route('admin.sessions.show', $session))
        ->assertOk()
        ->assertSee('Kirim pesan')
        ->assertSee('name="teks"', false);

    $session->update(['status' => 'selesai']);
    // attempt tetap berjalan agar variabel tampilan tidak berubah
    $this->actingAs($admin)
        ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
        ->get(route('admin.sessions.show', $session))
        ->assertOk()
        ->assertDontSee('Kirim pesan');
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=test_panel_admin_menampilkan_composer_pesan_saat_sesi_berjalan`
Expected: FAIL.

- [ ] **Step 3: Implementasi**

Di `_peserta.blade.php`, hitung dulu `$pesertaAktif = $attempts->where('status', 'berjalan')`, lalu `@if ($session->status === 'berjalan' && $pesertaAktif->isNotEmpty()) @include('admin.sessions._pesan', ['pesertaAktif' => $pesertaAktif]) @endif`.

`_pesan.blade.php` berisi satu tombol `Kirim pesan` (`btn btn-secondary`) yang membuka `<dialog id="dialog-pesan">`, dan form `POST route('admin.sessions.pesan', $session)`:
- `x-data="{ target: 'semua', terpilih: [], cari: '' }"`.
- Field `teks`: `<textarea name="teks" maxlength="500" rows="3" required class="input" x-model="teks">` plus penghitung `x-text="teks.length + ' / 500'"`.
- Radio `target` dengan label "Semua peserta yang sedang mengerjakan" dan "Peserta terpilih".
- Saat `target === 'terpilih'`, tampilkan kotak cari (`x-model="cari"`) dan daftar checkbox `name="attempt_ids[]"` dari `$pesertaAktif` (nama + username), mengikuti markup daftar di dialog simulasi `resources/views/admin/sessions/show.blade.php:136-152`.
- Tombol submit `x-bind:disabled="target === 'terpilih' && terpilih.length === 0"`.
- Tombol tutup memanggil `$refs.dialog.close()`; Escape ditangani native `<dialog>`.
- Tampilkan `@error('teks')` dan `@error('attempt_ids')` di dalam dialog.
- Beri `aria-labelledby="judul-dialog-pesan"` pada dialog dan `autofocus` pada textarea.

Semua label berbahasa Indonesia, tombol minimal `min-h-11`.

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=PesanPengajarTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/sessions/_pesan.blade.php resources/views/admin/sessions/_peserta.blade.php tests/Feature/PesanPengajarTest.php
git commit -m "feat: composer pesan pengajar di panel sesi"
```

---

### Task 10: Toast pesan di halaman ujian mode acak

**Files:**
- Modify: `app/Http/Controllers/Peserta/AttemptController.php:47-160` (`show`) dan `:420-459` (`status`)
- Modify: `resources/views/peserta/attempt/show.blade.php:20-60` (state), `:329-355` (Echo), `:954-1000` (`cekStatus`), `:1647-1660` (toast)
- Test: `tests/Feature/PesanPengajarTest.php`

**Interfaces:**
- Consumes: `SessionMessage::aktifUntukAttempt`, `SessionMessage::untukPeserta` (Task 5), event `PesanPengajar` (Task 6), route `peserta.pesan.tutup` (Task 8).
- Produces: state Alpine `pesanMasuk` (array payload), method `tutupPesan(id)`, key `pesan` pada respons `GET peserta/attempt/{attempt}/status`.

- [ ] **Step 1: Tulis test gagal**

```php
public function test_halaman_ujian_dan_polling_membawa_pesan_aktif(): void
{
    [$admin, , $attempts] = $this->sesiBerjalan(1);
    $attempt = $attempts[0];
    $pesan = SessionMessage::create([
        'quiz_session_id' => $attempt->quiz_session_id,
        'attempt_id' => $attempt->id,
        'user_id' => $attempt->user_id,
        'teks' => 'Tetap fokus, sisa 10 menit.',
        'tipe' => 'info',
    ]);

    $this->actingAs($attempt->user)
        ->withSession(['auth_token' => $attempt->user->current_session_token])
        ->get(route('peserta.attempt.show', $attempt))
        ->assertOk()
        ->assertSee('Tetap fokus, sisa 10 menit.');

    $this->actingAs($attempt->user)
        ->withSession(['auth_token' => $attempt->user->current_session_token])
        ->getJson(route('peserta.attempt.status', $attempt))
        ->assertOk()
        ->assertJsonPath('pesan.0.id', $pesan->id)
        ->assertJsonPath('pesan.0.teks', 'Tetap fokus, sisa 10 menit.');

    $pesan->update(['ditutup_at' => now()]);

    $this->actingAs($attempt->user)
        ->withSession(['auth_token' => $attempt->user->current_session_token])
        ->getJson(route('peserta.attempt.status', $attempt))
        ->assertOk()
        ->assertJsonPath('pesan', []);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=test_halaman_ujian_dan_polling_membawa_pesan_aktif`
Expected: FAIL.

- [ ] **Step 3: Implementasi**

Controller:
- Di `AttemptController::show`, tambahkan `'pesanAktif' => SessionMessage::aktifUntukAttempt($attempt->id)->map->untukPeserta()->values()` ke array view.
- Di `AttemptController::status`, tambahkan `'pesan' => SessionMessage::aktifUntukAttempt($attempt->id)->map->untukPeserta()->values(),` pada `response()->json([...])`.

View `attempt/show.blade.php`:
- Tambah state `pesanMasuk: @js($pesanAktif),` dan `pesanTeks: '',` di objek Alpine (dekat `clipboardToast`).
- Method:
```js
gabungPesan(daftar) {
    if (!Array.isArray(daftar)) return;
    for (const pesan of daftar) {
        if (!this.pesanMasuk.some((item) => item.id === pesan.id)) this.pesanMasuk.push(pesan);
    }
},
async tutupPesan(id) {
    this.pesanMasuk = this.pesanMasuk.filter((item) => item.id !== id);
    try {
        await window.kuisFetch(this.tutupUrlPesan.replace('__ID__', id), { method: 'POST', progress: false });
    } catch (e) {}
},
```
dengan `tutupUrlPesan: '{{ route('peserta.pesan.tutup', ['message' => '__ID__']) }}',` ditambahkan ke objek `config` di `:1273-1275`.
- Di `pasangEcho()`, pada rantai `echo.channel(userChannel)` tambahkan `.listen('.PesanPengajar', (data) => { this.gabungPesan([data]); this.bunyikanAlert(640, 0.15); })`.
- Di `cekStatus()`, setelah respons OK, panggil `this.gabungPesan(hasil.data?.pesan ?? [])`.
- Ganti blok toast clipboard (`:1647-1659`) menjadi satu kontainer tumpuk:
```blade
<div class="fixed right-4 top-4 z-[85] flex w-[min(calc(100vw-2rem),24rem)] flex-col gap-3">
    <template x-for="pesan in pesanMasuk" :key="pesan.id">
        <div class="rounded-xl border-2 border-brand bg-page p-4 shadow-xl" role="status" aria-live="polite" x-cloak>
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-bold text-ink-muted">Pesan pengajar</p>
                    <p class="mt-1 text-sm font-bold leading-relaxed text-ink" x-text="pesan.teks"></p>
                </div>
                <button type="button" class="btn btn-ghost min-h-11 px-3" x-on:click="tutupPesan(pesan.id)" x-bind:aria-label="'Tutup pesan: ' + pesan.teks">Tutup</button>
            </div>
        </div>
    </template>
    <div x-show="clipboardToast" x-cloak class="rounded-xl border bg-page p-4 shadow-xl"
        x-bind:class="clipboardToastBlocked ? 'border-coral text-coral-deep' : 'border-brand text-ink'"
        role="status" aria-live="polite">
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-bold leading-relaxed" x-text="clipboardToast"></p>
            <button type="button" class="btn btn-ghost min-h-10 px-2" x-on:click="clipboardToast = ''" aria-label="Tutup notifikasi copy dan paste">Tutup</button>
        </div>
    </div>
</div>
```

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=PesanPengajarTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Peserta/AttemptController.php resources/views/peserta/attempt/show.blade.php tests/Feature/PesanPengajarTest.php
git commit -m "feat: toast pesan pengajar di halaman ujian mode acak"
```

---

### Task 11: Toast pesan di halaman ujian mode serempak

**Files:**
- Modify: `app/Http/Controllers/Peserta/SessionController.php:37-66` (`play`) dan `:68-200` (`state`)
- Modify: `resources/views/peserta/session/play.blade.php:3-50` (state), `:394-435` (Echo), `:89-140` (`muat`), `:715-735` (toast)
- Test: `tests/Feature/PesanPengajarTest.php`

**Interfaces:**
- Consumes: sama dengan Task 10.
- Produces: `config.pesanAwal` array, state `pesanMasuk`, key `pesan` pada respons `GET peserta/sesi/{session}/state`.

- [ ] **Step 1: Tulis test gagal**

```php
public function test_state_sesi_serempak_membawa_pesan_aktif(): void
{
    [$admin, $session, $attempt] = $this->sesiSerempakBerjalan();
    $pesan = SessionMessage::create([
        'quiz_session_id' => $session->id,
        'attempt_id' => $attempt->id,
        'user_id' => $attempt->user_id,
        'teks' => 'Jangan lupa cek kembali jawaban.',
        'tipe' => 'info',
    ]);

    $this->actingAs($attempt->user)
        ->withSession(['auth_token' => $attempt->user->current_session_token])
        ->getJson(route('peserta.session.state', $session))
        ->assertOk()
        ->assertJsonPath('pesan.0.id', $pesan->id);
}
```
Helper `sesiSerempakBerjalan()` sama seperti `sesiBerjalan(1)` tetapi `mode` `'serempak'` dan `fase` `'soal_terbuka'`, dan mengembalikan `[admin, session, attempt]` (attempt tunggal hasil `$attempts[0]`).

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=test_state_sesi_serempak_membawa_pesan_aktif`
Expected: FAIL.

- [ ] **Step 3: Implementasi**

- `SessionController::play`: tambahkan `'pesanAwal' => SessionMessage::aktifUntukAttempt($attempt->id)->map->untukPeserta()->values()` ke array view.
- `SessionController::state`: pada `response()->json([...])`, tambahkan `'pesan' => SessionMessage::aktifUntukAttempt($attempt->id)->map->untukPeserta()->values()`.
- `play.blade.php`: tambahkan `pesanMasuk: config.pesanAwal ?? []` di objek state, method `gabungPesan` dan `tutupPesan` yang sama seperti Task 10 (termasuk `tutupUrlPesan` di objek config, lihat `:525-535`), `.listen('.PesanPengajar', (data) => { this.gabungPesan([data]); this.bunyikanAlert(640, 0.15); })` pada kanal peserta, dan `this.gabungPesan(hasil.data?.pesan ?? [])` di dalam `muat()` setelah `this.state = hasil.data;`.
- Ganti blok toast clipboard menjadi kontainer tumpuk yang sama persis dengan Task 10 (template pesan pengajar di atas, toast clipboard di bawah).

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=PesanPengajarTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Peserta/SessionController.php resources/views/peserta/session/play.blade.php tests/Feature/PesanPengajarTest.php
git commit -m "feat: toast pesan pengajar di halaman ujian mode serempak"
```

---

### Task 12: Verifikasi menyeluruh

**Files:** tidak ada perubahan kode.

- [ ] **Step 1: Jalankan seluruh test**

Run: `php artisan test`
Expected: semua PASS, termasuk `CatatanPengajarTest`, `PesanPengajarTest`, `ExamControlTest`, `EssayTest`, `EssayTimingTest`.

- [ ] **Step 2: Build aset**

Run: `npm run build`
Expected: build sukses tanpa error. Diperlukan karena kelas Tailwind baru (`z-[85]`, `min-h-11`, `gap-3`) dipindai dari blade.

- [ ] **Step 3: Click-through manual**

Jalankan `php artisan serve` + `php artisan reverb:start` + `npm run dev`, lalu catat hasil tiap baris:
1. Admin buka ruang penilaian attempt acak, isi nilai + catatan, simpan, muat ulang: catatan masih ada, nilai tidak berubah.
2. Peserta dengan satu essay lain belum dinilai membuka hasil: kartu Catatan pengajar belum muncul.
3. Admin menilai essay terakhir: halaman peserta ter-refresh sendiri, kartu Catatan pengajar muncul, teks catatan terbaca, tanpa error konsol.
4. Sesi pembahasan `tertutup`: kartu Catatan pengajar tetap muncul, kunci jawaban tetap tidak muncul.
5. Admin klik Kirim pesan, pilih Semua peserta, kirim: toast muncul di kanan atas semua tab peserta yang sedang mengerjakan, tombol Tutup menghilangkannya, dan hilang juga setelah muat ulang halaman.
6. Admin pilih Peserta terpilih: hanya satu peserta menerima toast.
7. Matikan Reverb: kirim pesan lagi, toast tetap muncul dalam 5 detik di halaman acak dan 3 detik di halaman serempak.
8. Tab melalui tombol Kirim pesan, radio target, checkbox peserta, textarea, tombol Tutup toast: semua fokus terlihat dan Escape menutup dialog.
9. Cek lebar 360px: toast tidak melebar keluar layar, tabel peserta tetap bisa digulir horizontal.

- [ ] **Step 4: Laporkan Delivery Gate**

Tulis laporan PASS/FAIL antislop: R-02 (tanpa em dash), R-03 (mobile), R-25 (kontras token), R-26 (semua tombol berfungsi), R-27 (empty state composer saat tidak ada peserta berjalan), R-32 (keyboard), R-35 (click-through di atas), R-39 (aria-live, aria-label), R-40 (tidak ada media baru).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: verifikasi catatan pengajar dan pesan ujian"
```
