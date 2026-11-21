# Essay Plagiarism & AI Detection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan sistem deteksi plagiarisme cerdas (smart hybrid) pada jawaban essay peserta: kalkulasi kemiripan teks antar-peserta secara lokal di server dan deteksi teks buatan AI secara on-demand.

**Architecture:** Layanan kemiripan teks lokal (`TextSimilarityService`) menggunakan tokenisasi N-Gram + Jaccard index untuk membandingkan jawaban essay per soal dalam satu sesi kuis tanpa biaya API; `ScanSessionPlagiarismJob` mengeksekusi pemindaian saat sesi berakhir atau on-demand; `AiQuestionService` menyediakan metode `detectAiGenerated()` untuk menganalisis probabilitas teks buatan AI per jawaban secara on-demand.

**Tech Stack:** Laravel 11, PHP 8.2+, SQLite (test) / MySQL (prod), Alpine.js, Tailwind CSS.

**Spec:** `docs/superpowers/specs/2026-11-21-essay-plagiarism-and-ai-detection-design.md`

## Global Constraints

- Pengecekan kemiripan teks antar-peserta wajib 100% lokal di PHP, deterministik, dan tanpa ketergantungan API eksternal.
- Nilai kemiripan dan probabilitas AI berada pada rentang skala persentase bulat `0` - `100`.
- Ambang batas badge: `< 50%` aman (tanpa badge peringatan), `50% - 79%` perhatian (kuning), `≥ 80%` peringatan tinggi (merah).
- Seluruh pengujian wajib dapat dijalankan pada koneksi SQLite via `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit`.

## Review Focus

1. Jawaban essay kosong atau sangat pendek: tidak boleh memicu pembagian dengan nol (division by zero) atau ditandai kemiripan palsu 100%.
2. Jawaban dari peserta yang sama (misal submit ulang/attempt ganda): tidak boleh dibandingkan dengan jawaban miliknya sendiri.
3. Sesi kuis dengan hanya 1 peserta essay: pemindaian harus selesai tanpa error dan menandai `similarity_score = null`.
4. Kuota AI habis saat audit AI on-demand: harus mengembalikan pesan error yang jelas tanpa memecah halaman laporan admin.
5. Format teks dengan markdown/tag HTML: wajib dinormalisasi terlebih dahulu sebelum tokenisasi N-Gram.

---

### Task 1: Database Migration & Model Attributes

**Files:**
- Create: `database/migrations/2026_11_21_000002_add_plagiarism_fields_to_answers_table.php`
- Modify: `app/Models/Answer.php`
- Test: `tests/Unit/AnswerPlagiarismAttributesTest.php`

**Interfaces:**
- Produces: Kolom pada `Answer`: `similarity_score` (int|null), `similarity_data` (array|null), `similarity_checked_at` (Carbon|null), `ai_generated_score` (int|null), `ai_generated_reason` (string|null), `ai_generated_at` (Carbon|null).

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/AnswerPlagiarismAttributesTest.php
namespace Tests\Unit;

use App\Models\Answer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnswerPlagiarismAttributesTest extends TestCase
{
    use RefreshDatabase;

    public function test_answer_casts_plagiarism_attributes_properly(): void
    {
        $answer = Answer::create([
            'attempt_id' => 1,
            'question_id' => 1,
            'jawaban_teks' => 'Jawaban pengujian',
            'similarity_score' => 85,
            'similarity_data' => [['answer_id' => 2, 'student_name' => 'Budi', 'score' => 85]],
            'similarity_checked_at' => now(),
            'ai_generated_score' => 90,
            'ai_generated_reason' => 'Struktur kalimat khas LLM',
            'ai_generated_at' => now(),
        ]);

        $fresh = Answer::find($answer->id);
        $this->assertSame(85, $fresh->similarity_score);
        $this->assertIsArray($fresh->similarity_data);
        $this->assertSame('Budi', $fresh->similarity_data[0]['student_name']);
        $this->assertSame(90, $fresh->ai_generated_score);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="AnswerPlagiarismAttributesTest"`
Expected: FAIL (tabel belum memiliki kolom-kolom baru)

- [ ] **Step 3: Implement migration and Answer model fillable & casts**

Buat migrasi penambahan kolom `similarity_score`, `similarity_data`, `similarity_checked_at`, `ai_generated_score`, `ai_generated_reason`, `ai_generated_at` pada tabel `answers`, serta perbarui `$fillable` dan `casts()` di `app/Models/Answer.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="AnswerPlagiarismAttributesTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/*add_plagiarism_fields* app/Models/Answer.php tests/Unit/AnswerPlagiarismAttributesTest.php
git commit -m "feat: add plagiarism and ai-generated attributes to answers table"
```

---

### Task 2: Local Text Similarity Engine (`TextSimilarityService`)

**Files:**
- Create: `app/Services/TextSimilarityService.php`
- Test: `tests/Unit/TextSimilarityServiceTest.php`

**Interfaces:**
- Produces: `TextSimilarityService::similarity(string $a, string $b): float` (nilai 0.0 sampai 1.0)
- Produces: `TextSimilarityService::comparePair(string $a, string $b): int` (persentase bulat 0 sampai 100)
- Produces: `TextSimilarityService::findMatches(Collection|array $answers): array` (peta per `answer_id` berisi `highest_score` dan daftar pembanding)

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/TextSimilarityServiceTest.php
namespace Tests\Unit;

use App\Services\TextSimilarityService;
use Tests\TestCase;

class TextSimilarityServiceTest extends TestCase
{
    public function test_similarity_returns_100_for_identical_normalized_texts(): void
    {
        $service = new TextSimilarityService();
        $this->assertSame(100, $service->comparePair('Halo dunia!', 'halo   dunia'));
    }

    public function test_similarity_returns_0_for_empty_or_completely_different_texts(): void
    {
        $service = new TextSimilarityService();
        $this->assertSame(0, $service->comparePair('', ''));
        $this->assertSame(0, $service->comparePair('abc', ''));
    }

    public function test_find_matches_detects_highest_similarity_between_answers(): void
    {
        $service = new TextSimilarityService();
        $items = [
            ['id' => 1, 'text' => 'Algoritma Dijkstra mencari jalur terpendek dari satu titik ke titik lain dalam graf berbobot positif.'],
            ['id' => 2, 'text' => 'Algoritma Dijkstra menemukan rute terpendek dari satu simpul ke simpul lainnya pada graf berbobot positif.'],
            ['id' => 3, 'text' => 'Fotosintesis adalah proses pembentukan karbohidrat oleh tumbuhan berklorofil menggunakan cahaya matahari.'],
        ];

        $results = $service->findMatches($items);

        $this->assertGreaterThan(60, $results[1]['highest_score']);
        $this->assertSame(2, $results[1]['matches'][0]['id']);
        $this->assertLessThan(20, $results[3]['highest_score']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="TextSimilarityServiceTest"`
Expected: FAIL (`TextSimilarityService` not found)

- [ ] **Step 3: Implement `TextSimilarityService`**

Implementasikan tokenisasi n-gram (word shingling n=3 dengan fallback char n-gram n=4 untuk jawaban pendek), normalisasi (strip tag, lowercase, hapus tanda baca, collapse space), dan rumus Jaccard `count(intersection) / count(union)`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="TextSimilarityServiceTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/TextSimilarityService.php tests/Unit/TextSimilarityServiceTest.php
git commit -m "feat: implement local TextSimilarityService using n-gram jaccard index"
```

---

### Task 3: Session Plagiarism Scanner Job (`ScanSessionPlagiarismJob`)

**Files:**
- Create: `app/Jobs/ScanSessionPlagiarismJob.php`
- Modify: `app/Services/QuizEngine.php` (trigger job saat sesi selesai jika ada soal essay)
- Test: `tests/Feature/ScanSessionPlagiarismJobTest.php`

**Interfaces:**
- Consumes: `TextSimilarityService::findMatches()`
- Produces: `ScanSessionPlagiarismJob::dispatch($sessionId)` menyimpan hasil ke `Answer` yang terkait

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ScanSessionPlagiarismJobTest.php
namespace Tests\Feature;

use App\Jobs\ScanSessionPlagiarismJob;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanSessionPlagiarismJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_scans_and_updates_essay_similarity_in_session(): void
    {
        $quiz = Quiz::factory()->create();
        $session = QuizSession::factory()->create(['quiz_id' => $quiz->id]);
        $question = Question::factory()->create(['quiz_id' => $quiz->id, 'tipe' => 'essay']);

        $u1 = User::factory()->create(['nama' => 'Peserta A']);
        $u2 = User::factory()->create(['nama' => 'Peserta B']);

        $att1 = Attempt::factory()->create(['quiz_session_id' => $session->id, 'user_id' => $u1->id]);
        $att2 = Attempt::factory()->create(['quiz_session_id' => $session->id, 'user_id' => $u2->id]);

        $ans1 = Answer::create([
            'attempt_id' => $att1->id,
            'question_id' => $question->id,
            'jawaban_teks' => 'Jaringan komputer terbagi menjadi LAN MAN dan WAN berdasarkan cakupan geografis.',
        ]);
        $ans2 = Answer::create([
            'attempt_id' => $att2->id,
            'question_id' => $question->id,
            'jawaban_teks' => 'Jaringan komputer terbagi menjadi LAN MAN dan WAN berdasarkan cakupan geografis.',
        ]);

        (new ScanSessionPlagiarismJob($session->id))->handle(app(\App\Services\TextSimilarityService::class));

        $ans1->refresh();
        $ans2->refresh();

        $this->assertSame(100, $ans1->similarity_score);
        $this->assertSame('Peserta B', $ans1->similarity_data[0]['student_name']);
        $this->assertSame(100, $ans2->similarity_score);
        $this->assertSame('Peserta A', $ans2->similarity_data[0]['student_name']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="ScanSessionPlagiarismJobTest"`
Expected: FAIL (`ScanSessionPlagiarismJob` not found)

- [ ] **Step 3: Implement `ScanSessionPlagiarismJob` and hook to session complete**

Kelompokkan jawaban essay per `question_id`, panggil `TextSimilarityService`, lalu simpan `similarity_score`, `similarity_data`, dan `similarity_checked_at` pada masing-masing record `Answer`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="ScanSessionPlagiarismJobTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/ScanSessionPlagiarismJob.php app/Services/QuizEngine.php tests/Feature/ScanSessionPlagiarismJobTest.php
git commit -m "feat: add ScanSessionPlagiarismJob to calculate similarity across session answers"
```

---

### Task 4: AI-Generated Content Detection in `AiQuestionService`

**Files:**
- Modify: `app/Services/AiQuestionService.php`
- Modify: `app/Services/EssayGrader.php`
- Test: `tests/Unit/AiGeneratedDetectionTest.php`

**Interfaces:**
- Produces: `AiQuestionService::detectAiGenerated(string $question, string $answer): array{score: int, reason: string}`
- Produces: `EssayGrader::detectAi(Answer $answer): array{score: int, reason: string}`

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/AiGeneratedDetectionTest.php
namespace Tests\Unit;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Question;
use App\Services\AiQuestionService;
use App\Services\EssayGrader;
use App\Services\OrganizationUsage;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiGeneratedDetectionTest extends TestCase
{
    public function test_detect_ai_returns_score_and_reason_and_updates_answer(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => '{"score":85,"reason":"Gaya bahasa sangat baku dengan pola transisi khas model AI."}'],
                ]],
            ]),
        ]);

        $service = app(AiQuestionService::class);
        $result = $service->detectAiGenerated('Jelaskan pengertian TCP', 'TCP adalah protokol transport layer...');

        $this->assertSame(85, $result['score']);
        $this->assertStringContainsString('khas model AI', $result['reason']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="AiGeneratedDetectionTest"`
Expected: FAIL (`Call to undefined method detectAiGenerated`)

- [ ] **Step 3: Implement `detectAiGenerated` and `EssayGrader::detectAi`**

Buat prompt evaluasi linguistik terstruktur ke client AI dengan instruksi mengembalikan JSON `{"score": int, "reason": string}` (0 = murni manusia, 100 = murni AI). Update record `Answer` dengan `ai_generated_score`, `ai_generated_reason`, `ai_generated_at`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="AiGeneratedDetectionTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/AiQuestionService.php app/Services/EssayGrader.php tests/Unit/AiGeneratedDetectionTest.php
git commit -m "feat: add detectAiGenerated to AiQuestionService and EssayGrader"
```

---

### Task 5: Admin Controllers & Endpoints

**Files:**
- Modify: `app/Http/Controllers/Admin/ReportController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AdminPlagiarismEndpointsTest.php`

**Interfaces:**
- Produces: `POST /admin/sessions/{session}/scan-plagiarism` -> JSON/Redirect status scan
- Produces: `POST /admin/answers/{answer}/detect-ai` -> JSON status & detail deteksi AI

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/AdminPlagiarismEndpointsTest.php
namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPlagiarismEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_trigger_session_plagiarism_scan(): void
    {
        [$admin, $org, $session] = $this->setupQuizSession();

        $response = $this->actingAs($admin)
            ->withSession(['current_organization_id' => $org->id])
            ->post(route('admin.reports.session.scan-plagiarism', $session->id));

        $response->assertRedirect();
        $response->assertSessionHas('status');
    }

    public function test_admin_can_trigger_ai_detection_for_single_answer(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"score":90,"reason":"Khas AI."}']]]])]);

        [$admin, $org, $session, $answer] = $this->setupQuizAnswer();

        $response = $this->actingAs($admin)
            ->withSession(['current_organization_id' => $org->id])
            ->postJson(route('admin.answers.detect-ai', $answer->id));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('score', 90);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="AdminPlagiarismEndpointsTest"`
Expected: FAIL (route belum terdefinisi)

- [ ] **Step 3: Implement controller actions in `ReportController` & register routes**

Tambahkan method `scanPlagiarism(QuizSession $session)` dan `detectAi(Answer $answer, EssayGrader $grader)` dengan validasi kepemilikan workspace dan otorisasi admin.

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="AdminPlagiarismEndpointsTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/ReportController.php routes/web.php tests/Feature/AdminPlagiarismEndpointsTest.php
git commit -m "feat: add scan-plagiarism and detect-ai endpoints in ReportController"
```

---

### Task 6: UI Plagiarism Badges, Side-by-Side Modal, & Session Report Summary

**Files:**
- Modify: `resources/views/admin/reports/attempt.blade.php`
- Modify: `resources/views/admin/reports/session.blade.php`
- Test: `tests/Feature/PlagiarismUiTest.php`

**Interfaces:**
- Produces: Badge kemiripan `[⚠️ X% mirip Nama]` dan tombol modal perbandingan teks di attempt report.
- Produces: Tombol & badge audit AI dengan alasan `[🤖 X% AI]` di attempt report.
- Produces: Kartu statistik contekkan dan tombol "Pindai Ulang Plagiarisme" di session report.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/PlagiarismUiTest.php
namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlagiarismUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_attempt_report_displays_plagiarism_and_ai_badges(): void
    {
        [$admin, $org, $attempt, $answer] = $this->setupAttemptWithPlagiarism();

        $response = $this->actingAs($admin)
            ->withSession(['current_organization_id' => $org->id])
            ->get(route('admin.reports.attempt', $attempt->id));

        $response->assertOk()
            ->assertSee('85% mirip')
            ->assertSee('Audit AI');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="PlagiarismUiTest"`
Expected: FAIL (view belum menampilkan teks tersebut)

- [ ] **Step 3: Update `attempt.blade.php` and `session.blade.php`**

Tambahkan badge indikator kemiripan dengan warna dinamis (kuning/merah), modal perbandingan jawaban side-by-side menggunakan Alpine.js, tombol audit AI on-demand, dan tombol trigger scan sesi di halaman laporan sesi.

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="PlagiarismUiTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/reports/attempt.blade.php resources/views/admin/reports/session.blade.php tests/Feature/PlagiarismUiTest.php
git commit -m "feat: display plagiarism badge, side-by-side modal, and ai audit in reports"
```
