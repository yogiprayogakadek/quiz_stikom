# Auto-grade Essay per Kuis Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Kuis yang mengaktifkan `auto_grade_essay` menilai essay otomatis lewat queue setelah attempt Mode Acak selesai.

**Architecture:** Flag disimpan di `quizzes`. Saat attempt selesai, `QuizEngine` mengantre `AutoGradeEssayJob`. Job memakai `EssayGrader` untuk meminta saran AI dan menetapkan nilai final, dengan kuota, audit, dan konteks organisasi yang sama seperti jalur manual.

**Tech Stack:** Laravel, PHPUnit, queue database/sync, Blade, Vite.

**Spec:** `docs/superpowers/specs/2026-11-10-auto-grade-essay-design.md`

## Global Constraints

- `auto_grade_essay` default `false`; hanya kuis yang mengaktifkannya yang memakai job.
- Mode Serempak tidak berubah dan tetap menolak soal essay.
- Nilai final otomatis memakai `dinilai_oleh = null`, sehingga riwayat menampilkan **Sistem**.
- Kuota AI dihitung satu request per essay yang benar-benar dipanggil ke AI.
- Kegagalan AI atau kuota habis tidak boleh menggagalkan attempt; essay dibiarkan untuk penilaian manual.
- Tidak ada env atau config baru.
- Nilai essay selalu dibatasi `0..bobot` soal.

## Review Focus

- Admin menyimpan nilai saat job berjalan: job tidak boleh menimpa nilai manual.
- Kuota habis atau AI error: attempt tetap `selesai` dan essay tetap belum dinilai.
- Worker queue: provider AI harus milik organisasi attempt, bukan platform.
- Job terkirim dua kali atau kuis tidak mengaktifkan auto-grade: tidak ada nilai/audit final ganda.
- Attempt tanpa essay atau jawaban essay kosong: job tidak memanggil AI dan tidak mengubah skor.

---

### Task 1: Flag auto-grade per kuis

**Files:**
- Create: `database/migrations/2026_11_10_000010_add_auto_grade_essay_to_quizzes_table.php`
- Modify: `app/Models/Quiz.php`
- Modify: `app/Http/Requests/StoreQuizRequest.php`
- Modify: `app/Http/Controllers/Admin/QuizController.php`
- Modify: `resources/views/admin/quizzes/_form.blade.php`
- Modify: `resources/views/admin/quizzes/index.blade.php`
- Test: `tests/Feature/EssayTest.php`

**Interfaces:**
- Produces: `quizzes.auto_grade_essay` boolean default false; `Quiz::$auto_grade_essay` cast boolean.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/EssayTest.php` and import `App\Models\Kelas`:

```php
public function test_kuis_menyimpan_flag_auto_grade_essay(): void
{
    $admin = User::create([
        'nama' => 'Admin',
        'username' => 'admin-auto-flag',
        'password' => Hash::make('rahasia'),
        'role' => 'admin',
        'current_session_token' => 'token-auto-flag',
    ]);

    $kelas = Kelas::create([
        'nama' => 'Kelas Auto',
        'pin' => 'AUTO01',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->withSession(['auth_token' => 'token-auto-flag'])
        ->post(route('admin.quizzes.store'), [
            'kelas_id' => $kelas->id,
            'judul' => 'Kuis Auto',
        ])
        ->assertRedirect();

    $quiz = Quiz::where('judul', 'Kuis Auto')->firstOrFail();
    $this->assertFalse($quiz->auto_grade_essay);

    $this->actingAs($admin)
        ->withSession(['auth_token' => 'token-auto-flag'])
        ->put(route('admin.quizzes.update', $quiz), [
            'kelas_id' => $kelas->id,
            'judul' => 'Kuis Auto',
            'auto_grade_essay' => '1',
        ])
        ->assertRedirect();

    $this->assertTrue($quiz->fresh()->auto_grade_essay);

    $this->actingAs($admin)
        ->withSession(['auth_token' => 'token-auto-flag'])
        ->get(route('admin.quizzes.edit', $quiz))
        ->assertSee('Nilai essay otomatis dengan AI');
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=test_kuis_menyimpan_flag_auto_grade_essay`
Expected: FAIL because `quizzes.auto_grade_essay` does not exist.

- [ ] **Step 3: Implement the flag**

Migration:

```php
Schema::table('quizzes', function (Blueprint $table) {
    $table->boolean('auto_grade_essay')->default(false)->after('acak_opsi');
});
```

`Quiz`: add `auto_grade_essay` to `$fillable` and `'auto_grade_essay' => 'boolean'` to casts.

`StoreQuizRequest`: add `'auto_grade_essay' => ['nullable', 'boolean']`.

`QuizController::store` and `update`: add `'auto_grade_essay' => $request->boolean('auto_grade_essay')`.

Form: add a checkbox after the `acak_opsi` block with label **Nilai essay otomatis dengan AI**, helper text that it only applies to Mode Acak and low-risk quizzes, and `@checked((bool) old('auto_grade_essay', $quiz?->auto_grade_essay ?? false))`.

Index: add `@if ($kuis->auto_grade_essay) <span class="pill pill-sun text-xs">Nilai AI otomatis</span> @endif`.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=test_kuis_menyimpan_flag_auto_grade_essay`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_11_10_000010_add_auto_grade_essay_to_quizzes_table.php app/Models/Quiz.php app/Http/Requests/StoreQuizRequest.php app/Http/Controllers/Admin/QuizController.php resources/views/admin/quizzes/_form.blade.php resources/views/admin/quizzes/index.blade.php tests/Feature/EssayTest.php
git commit -m "feat: add per-quiz auto essay grading flag"
```

---

### Task 2: Shared essay grader and forced organization context

**Files:**
- Create: `app/Services/EssayGrader.php`
- Modify: `app/Support/OrganizationContext.php`
- Modify: `app/Http/Controllers/Admin/ReportController.php`
- Test: `tests/Feature/EssayTest.php`

**Interfaces:**
- Produces:
  - `OrganizationContext::force(?Organization $organization): void`
  - `EssayGrader::suggest(Answer $answer): array{score:int,confidence:int,feedback:string,model:string,provider:string}`
  - `EssayGrader::applyFinal(Answer $answer, int $score, ?int $userId = null, bool $auto = false, bool $onlyIfUnscored = false): bool`
- Consumes: `AiQuestionService::evaluateEssay()`, `OrganizationUsage::assertAiQuota()` / `recordAi()`, `AuditLog::record()`.

- [ ] **Step 1: Write the failing organization-context test**

Add to `tests/Feature/EssayTest.php`; import `App\Models\Organization`, `App\Models\OrganizationSetting`, `App\Services\AiQuestionService`, `App\Support\OrganizationContext`.

```php
public function test_force_organisasi_memakai_pengaturan_ai_organisasi(): void
{
    $organization = Organization::query()->firstOrFail();

    OrganizationSetting::create([
        'organization_id' => $organization->id,
        'ai_base_url' => 'https://org.example.test/v1',
        'ai_api_key' => 'org-key',
        'ai_default_model' => 'org-model',
    ]);

    config([
        'services.ai.base_url' => 'https://platform.example.test/v1',
        'services.ai.api_key' => 'platform-key',
        'services.ai.default_model' => 'platform-model',
    ]);

    Http::fake([
        'https://org.example.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'score' => 7,
                        'confidence' => 80,
                        'feedback' => 'Cukup.',
                    ]),
                ],
            ]],
        ]),
    ]);

    $context = app(OrganizationContext::class);
    $context->force($organization);

    try {
        $result = app(AiQuestionService::class)
            ->evaluateEssay('Pertanyaan', null, [], 'Jawaban', 10);
    } finally {
        $context->force(null);
    }

    $this->assertSame('org-model', $result['model']);
    Http::assertSent(fn ($request) => $request->url() === 'https://org.example.test/v1/chat/completions');
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=test_force_organisasi_memakai_pengaturan_ai_organisasi`
Expected: FAIL because `force()` does not exist.

- [ ] **Step 3: Implement `OrganizationContext::force`**

Add `protected ?Organization $forced = null;`.

`force(?Organization $organization)` sets `$this->forced` and calls `app()->forgetInstance('app.settings')`.

`current()` returns `$this->forced` first when non-null, before the auth/session lookup.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=test_force_organisasi_memakai_pengaturan_ai_organisasi`
Expected: PASS.

- [ ] **Step 5: Write the failing shared-grader test**

Add to `tests/Feature/EssayTest.php`:

```php
public function test_essay_grader_menyimpan_saran_dan_nilai_final(): void
{
    Http::fake([
        'https://ai.example.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'score' => 8,
                        'confidence' => 90,
                        'feedback' => 'Jawaban tepat.',
                    ]),
                ],
            ]],
        ]),
    ]);

    config([
        'services.ai.base_url' => 'https://ai.example.test/v1',
        'services.ai.api_key' => 'test-key',
        'services.ai.default_model' => 'test-model',
    ]);

    [$admin, , , $attempt, , $essay] = $this->buatAcakBerjalan();
    app(QuizEngine::class)->selesaikanAcak($attempt);

    $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();
    $grader = app(\App\Services\EssayGrader::class);

    $suggestion = $grader->suggest($answer);
    $this->assertTrue($grader->applyFinal($answer->fresh(), $suggestion['score'], $admin->id));

    $answer->refresh();
    $this->assertSame(8, $answer->skor);
    $this->assertNotNull($answer->dinilai_at);
    $this->assertSame($admin->id, $answer->dinilai_oleh);
}
```

- [ ] **Step 6: Run the test to verify it fails**

Run: `php artisan test --compact --filter=test_essay_grader_menyimpan_saran_dan_nilai_final`
Expected: FAIL because `EssayGrader` does not exist.

- [ ] **Step 7: Implement `EssayGrader` and refactor the controller**

`EssayGrader` constructor takes `AiQuestionService $ai, OrganizationUsage $usage`.

`suggest()` loads `question` and `attempt`, rejects non-essay/blank answers, calls `assertAiQuota()` when organization is present, calls `evaluateEssay()`, calls `recordAi($organizationId, 'essay')`, updates the AI suggestion fields, writes `essay_ai_suggested`, and returns the suggestion.

`applyFinal()` loads `question`, clamps the score to `0..bobot`, captures `from` only when `dinilai_at` was set, performs an `Answer::whereKey()->when($onlyIfUnscored, fn ($q) => $q->whereNull('dinilai_at'))->update([...])`, returns false when zero rows changed, and writes `essay_score_changed` with `from`, `to`, `ai_suggested_score`, `ai_model`, and `auto`.

`ReportController::saranNilaiEssay` changes signature to `(Answer $answer, EssayGrader $grader)` and calls `$grader->suggest($answer)` inside the existing try/catch.

`ReportController::nilaiEssay` adds `EssayGrader $grader` and replaces the manual answer update + audit block with `$grader->applyFinal($answer, (int) $nilai, $request->user()->id);`.

Remove now-unused `AiQuestionService` / `OrganizationUsage` imports from `ReportController`.

- [ ] **Step 8: Run the affected tests**

Run: `php artisan test --compact --filter=EssayTest`
Expected: PASS, including the existing manual suggestion and manual grading tests.

- [ ] **Step 9: Commit**

```bash
git add app/Services/EssayGrader.php app/Support/OrganizationContext.php app/Http/Controllers/Admin/ReportController.php tests/Feature/EssayTest.php
git commit -m "refactor: centralize essay grading and force organization context"
```

---

### Task 3: Auto-grade queue job and dispatch

**Files:**
- Create: `app/Jobs/AutoGradeEssayJob.php`
- Modify: `app/Services/QuizEngine.php`
- Test: `tests/Feature/EssayTest.php`

**Interfaces:**
- Consumes: `EssayGrader::suggest()`, `EssayGrader::applyFinal()`, `QuizEngine::hitungSkorAcak()`, `OrganizationContext::force()`.
- Produces: `AutoGradeEssayJob::__construct(int $attemptId)`, `AutoGradeEssayJob::handle(QuizEngine $engine): void`.

- [ ] **Step 1: Write the failing dispatch tests**

Add to `tests/Feature/EssayTest.php`; import `App\Jobs\AutoGradeEssayJob`, `Illuminate\Support\Facades\Queue`.

```php
public function test_kuis_auto_grade_mengantre_job_saat_attempt_selesai(): void
{
    Queue::fake();
    [, , , $attempt] = $this->buatAcakBerjalan(true, true);

    app(QuizEngine::class)->selesaikanAcak($attempt);

    Queue::assertPushed(AutoGradeEssayJob::class, fn ($job) => $job->attemptId === $attempt->id);
}

public function test_kuis_manual_tidak_mengantre_job_auto_grade(): void
{
    Queue::fake();
    [, , , $attempt] = $this->buatAcakBerjalan(true, false);

    app(QuizEngine::class)->selesaikanAcak($attempt);

    Queue::assertNotPushed(AutoGradeEssayJob::class);
}

public function test_kuis_auto_grade_mengantre_job_saat_sesi_ditutup(): void
{
    Queue::fake();
    [, , $session, $attempt] = $this->buatAcakBerjalan(true, true);

    app(QuizEngine::class)->tutupSesi($session);

    Queue::assertPushed(AutoGradeEssayJob::class, fn ($job) => $job->attemptId === $attempt->id);
}
```

- [ ] **Step 2: Extend the helper and run the tests to verify they fail**

Change `buatAcakBerjalan` signature to `private function buatAcakBerjalan(bool $denganJawaban = true, bool $autoGrade = false): array` and set `'auto_grade_essay' => $autoGrade` on the `Quiz::create`.

Run: `php artisan test --compact --filter=test_kuis_auto_grade_mengantre_job_saat_attempt_selesai`
Expected: FAIL because `AutoGradeEssayJob` does not exist.

- [ ] **Step 3: Implement the job**

`AutoGradeEssayJob` implements `ShouldQueue`, uses `Queueable`, `public int $tries = 1`, `public int $timeout = 300`.

`handle(QuizEngine $engine)`:
1. Load `Attempt::withoutGlobalScopes()->with('quizSession.quiz')->find($this->attemptId)`.
2. Return unless the attempt exists, `status === 'selesai'`, session mode is `acak`, and `quizSession.quiz.auto_grade_essay` is true.
3. Load ungraded essay answers with text: `answers()->whereHas('question', fn ($q) => $q->where('tipe', 'essay'))->with('question')->get()->filter(fn ($a) => filled($a->jawaban_teks) && $a->dinilai_at === null)`.
4. Return when the collection is empty.
5. `app(OrganizationContext::class)->force($attempt->organization)`.
6. Resolve `EssayGrader` after forcing; in `finally`, `force(null)`.
7. For each answer, reuse an existing AI suggestion when `ai_reviewed_at` and `ai_suggested_score` are set; otherwise call `suggest()`.
8. Call `applyFinal($answer, $suggestion['score'], null, true, true)`; skip event when it returns false.
9. Dispatch `EssayDinilai::safeDispatch(...)` for applied scores.
10. Catch `RuntimeException` containing `Kuota AI` and break; log and continue on other failures.
11. After the loop, call `$engine->hitungSkorAcak($attempt)` and `SessionMonitorUpdated::safeDispatch(... 'essay_graded')`.

- [ ] **Step 4: Dispatch from `QuizEngine`**

Add private `antrekanAutoGrade(Attempt $attempt): void` that loads `quizSession.quiz`, returns unless mode is `acak` and the quiz flag is true, then calls `AutoGradeEssayJob::dispatch($attempt->id)->afterCommit()`.

Call it from `selesaikanAcak` after `bukaPembahasanOtomatis($hasil)`.

Call it from `hitungSelesaiAcakTerkunci` after the attempt update. The `afterCommit()` keeps the job from running before `tutupSesi` commits.

- [ ] **Step 5: Run the dispatch tests to verify they pass**

Run: `php artisan test --compact --filter=test_kuis_auto_grade_mengantre_job_saat_attempt_selesai`
Run: `php artisan test --compact --filter=test_kuis_manual_tidak_mengantre_job_auto_grade`
Expected: PASS.

- [ ] **Step 6: Write the failing job behavior tests**

Add to `tests/Feature/EssayTest.php`:

```php
public function test_job_auto_grade_menyimpan_nilai_final_dan_audit(): void
{
    config([
        'services.ai.base_url' => 'https://ai.example.test/v1',
        'services.ai.api_key' => 'test-key',
        'services.ai.default_model' => 'test-model',
    ]);
    Http::fake([
        'https://ai.example.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'score' => 8,
                        'confidence' => 90,
                        'feedback' => 'Jawaban tepat.',
                    ]),
                ],
            ]],
        ]),
    ]);

    [, , , $attempt, , $essay] = $this->buatAcakBerjalan(true, true);
    app(QuizEngine::class)->selesaikanAcak($attempt);

    $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();
    $answer->refresh();

    $this->assertSame(8, $answer->skor);
    $this->assertNotNull($answer->dinilai_at);
    $this->assertNull($answer->dinilai_oleh);
    $this->assertSame(8, $answer->ai_suggested_score);

    $final = AuditLog::where('action', 'essay_score_changed')
        ->where('subject_id', $answer->id)
        ->firstOrFail();
    $this->assertNull($final->actor_id);
    $this->assertTrue($final->metadata['auto']);
    $this->assertSame(8, $final->metadata['to']);

    $this->assertDatabaseHas('ai_usage_events', [
        'organization_id' => $attempt->organization_id,
        'kind' => 'essay',
        'requests' => 1,
    ]);
}
```

Add the remaining behavior tests:

- `test_job_auto_grade_tidak_menimpa_nilai_admin`: `Queue::fake()`; finish auto attempt; manually POST `admin.attempts.nilai` with `9`; run `new AutoGradeEssayJob($attempt->id)` via `handle(app(QuizEngine::class))`; assert score stays `9` and only one final audit exists.
- `test_job_auto_grade_melewati_jawaban_saat_kuota_habis`: `config(['platform.ai_monthly_requests' => 0])`; `Http::fake()`; finish auto attempt; assert `dinilai_at` is null and `Http::assertNothingSent()`.
- `test_job_auto_grade_tetap_selesai_saat_ai_error`: fake the AI endpoint with a `500` response; finish auto attempt; assert attempt `status` stays `selesai` and essay `dinilai_at` is null.
- `test_job_auto_grade_tanpa_jawaban_essay_tidak_memanggil_ai`: use `buatAcakBerjalan(false, true)`; finish attempt; assert `Http::assertNothingSent()`.
- `test_job_auto_grade_tidak_menimpa_saat_dijalankan_dua_kali`: finish auto attempt with fake AI; run `handle()` twice; assert one `essay_score_changed` audit and score unchanged.
- `test_job_auto_grade_memakai_provider_organisasi`: set org `OrganizationSetting` AI fields to `https://org.example.test/v1`, platform config to another host, fake org URL with `Http::preventStrayRequests()`, finish auto attempt, assert `Http::assertSent` to org URL and `ai_usage_events` recorded.

- [ ] **Step 7: Run the behavior tests to verify they pass**

Run: `php artisan test --compact --filter=EssayTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Jobs/AutoGradeEssayJob.php app/Services/QuizEngine.php tests/Feature/EssayTest.php
git commit -m "feat: auto grade essay for opted-in quizzes"
```

---

### Task 4: Checklist and full verification

**Files:**
- Modify: `PUBLIC_PLATFORM_CHECKLIST.md`

- [ ] **Step 1: Run the full verification**

Run: `php artisan test --compact`
Expected: all tests pass.

Run: `npm run build`
Expected: Vite build succeeds.

Run: `php artisan view:cache`
Expected: Blade cache succeeds.

Run: `git diff --check`
Expected: no output.

Run: `php artisan migrate`
Expected: migration `2026_11_10_000010_add_auto_grade_essay_to_quizzes_table` applies cleanly.

- [ ] **Step 2: Update the checklist**

Mark `[x] Mode otomatis hanya untuk kuis yang mengaktifkannya.`

Add a checkpoint with:
- status: flag per kuis, job per attempt, nilai final AI dengan audit, kuota dan kegagalan jatuh ke manual.
- commit fitur: the Task 3 commit hash.
- verification counts from Step 1.
- risks: auto-grade tetap memakai satu request AI per essay; hasil AI bisa keliru dan tetap perlu pemantauan admin; job tidak mengulang otomatis jika AI gagal.
- next item: **Riwayat perubahan nilai, model, provider, dan pemberi nilai**.

- [ ] **Step 3: Commit the checklist**

```bash
git add PUBLIC_PLATFORM_CHECKLIST.md
git commit -m "docs: record auto essay grading checkpoint"
```

- [ ] **Step 4: Push**

```bash
git push
```
