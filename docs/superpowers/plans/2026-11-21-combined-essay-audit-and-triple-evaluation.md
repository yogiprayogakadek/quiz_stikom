# Combined Essay Integrity Audit & Triple-Mode AI Suggestions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Provide an all-in-one AI evaluation for student essay answers that executes an academic integrity audit (AI detection) and generates 3 rubric-scoring modes (Ketat, Seimbang, Longgar) in a single API call per question, with bulk execution and interactive 1-click UI grading.

**Architecture:** A single combined LLM prompt parses both AI detection probability and 3-mode rubric scores in one request to maximize speed and token efficiency. Results are persisted to `answers.ai_suggestions_json` along with baseline compatibility fields, exposed via a dedicated admin endpoint, and presented in the attempt report UI as interactive clickable mode badges.

**Tech Stack:** Laravel 11 / PHP 8.5, SQLite / MySQL, Alpine.js, Tailwind CSS, Blade templates, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-21-combined-essay-audit-and-triple-evaluation-design.md`

## Global Constraints
- Database changes must be backward-compatible with existing schema and use safe nullable columns.
- All tests must pass on SQLite (`php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit`).
- Strict tenant isolation must be enforced across organization boundaries.
- Adhere to the established styling (`pill-*`, `btn-*`, Tailwind CSS) in `attempt.blade.php`.

## Review Focus
1. Malformed or partial JSON responses from LLM: gracefully retry or fail with helpful error without breaking the bulk loop.
2. Empty or whitespace-only essay answers: bypass LLM call and return validation error.
3. AI quota exhaustion mid-batch: stop cleanly, report transient quota error, and preserve already-evaluated answers.
4. User clicking a mode button while manual unsaved edits exist: update the form inputs cleanly and flag `kotor = true`.
5. Re-evaluating an already scored or audited answer: overwrite suggestions and detection timestamps appropriately without data corruption.

---

### Task 1: Migration and Model Casts for `ai_suggestions_json`

**Files:**
- Create: `database/migrations/2026_11_21_000003_add_ai_suggestions_json_to_answers_table.php`
- Modify: `app/Models/Answer.php`
- Test: `tests/Unit/AnswerAiSuggestionsAttributeTest.php`

**Interfaces:**
- Produces: `$answer->ai_suggestions_json` cast as array.

- [ ] **Step 1: Write the failing test**

```php
namespace Tests\Unit;

use App\Models\Answer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnswerAiSuggestionsAttributeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_suggestions_json_is_cast_to_array(): void
    {
        $answer = new Answer();
        $answer->ai_suggestions_json = [
            'ketat' => ['score' => 12, 'feedback' => 'Kurang detail.'],
            'seimbang' => ['score' => 15, 'feedback' => 'Cukup.'],
            'longgar' => ['score' => 18, 'feedback' => 'Bagus.'],
        ];

        $this->assertIsArray($answer->ai_suggestions_json);
        $this->assertSame(15, $answer->ai_suggestions_json['seimbang']['score']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="AnswerAiSuggestionsAttributeTest"`
Expected: FAIL (column or cast not registered)

- [ ] **Step 3: Create migration and update model**

Create migration adding `ai_suggestions_json` nullable JSON column to `answers` after `ai_feedback`. Add `'ai_suggestions_json' => 'array'` to `casts()` in `Answer.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="AnswerAiSuggestionsAttributeTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/*add_ai_suggestions_json* app/Models/Answer.php tests/Unit/AnswerAiSuggestionsAttributeTest.php
git commit -m "feat: add ai_suggestions_json column and cast to answers table"
```

---

### Task 2: Service Method `AiQuestionService::auditAndEvaluateEssay`

**Files:**
- Modify: `app/Services/AiQuestionService.php`
- Test: `tests/Unit/AiAuditAndEvaluateServiceTest.php`

**Interfaces:**
- Produces: `AiQuestionService::auditAndEvaluateEssay(string $question, ?string $referenceAnswer, array $criteria, string $answer, int $maxScore): array`

- [ ] **Step 1: Write the failing test**

```php
namespace Tests\Unit;

use App\Services\AiQuestionService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAuditAndEvaluateServiceTest extends TestCase
{
    public function test_audit_and_evaluate_essay_parses_detection_and_3_modes(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'ai_detection' => [
                                'score' => 78,
                                'reason' => 'Pola kalimat sangat terstruktur khas generator AI.',
                            ],
                            'suggestions' => [
                                'ketat' => ['score' => 14, 'feedback' => 'Implementasi kurang mendalam.'],
                                'seimbang' => ['score' => 17, 'confidence' => 85, 'feedback' => 'Konsep utama benar.'],
                                'longgar' => ['score' => 20, 'feedback' => 'Ide dasar dapat diterima.'],
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $service = app(AiQuestionService::class);
        $result = $service->auditAndEvaluateEssay('Apa itu OOP?', 'OOP adalah...', [], 'OOP adalah konsep...', 20);

        $this->assertSame(78, $result['ai_detection']['score']);
        $this->assertSame(14, $result['suggestions']['ketat']['score']);
        $this->assertSame(17, $result['suggestions']['seimbang']['score']);
        $this->assertSame(20, $result['suggestions']['longgar']['score']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="AiAuditAndEvaluateServiceTest"`
Expected: FAIL (`Call to undefined method auditAndEvaluateEssay`)

- [ ] **Step 3: Implement `auditAndEvaluateEssay` in `AiQuestionService`**

Construct combined prompt instructing LLM to output valid JSON with both `ai_detection` and `suggestions` (ketat, seimbang, longgar). Include retry loop and JSON extractor.

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="AiAuditAndEvaluateServiceTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/AiQuestionService.php tests/Unit/AiAuditAndEvaluateServiceTest.php
git commit -m "feat: implement auditAndEvaluateEssay in AiQuestionService"
```

---

### Task 3: Grader Method `EssayGrader::auditAndEvaluate`

**Files:**
- Modify: `app/Services/EssayGrader.php`
- Test: `tests/Unit/EssayGraderAuditAndEvaluateTest.php`

**Interfaces:**
- Consumes: `AiQuestionService::auditAndEvaluateEssay`
- Produces: `EssayGrader::auditAndEvaluate(Answer $answer): array`

- [ ] **Step 1: Write the failing test**

```php
namespace Tests\Unit;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\EssayGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EssayGraderAuditAndEvaluateTest extends TestCase
{
    use RefreshDatabase;

    public function test_grader_audit_and_evaluate_persists_data_and_logs(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'ai_detection' => ['score' => 82, 'reason' => 'Khas teks AI.'],
                            'suggestions' => [
                                'ketat' => ['score' => 12, 'feedback' => 'Ketat.'],
                                'seimbang' => ['score' => 16, 'confidence' => 90, 'feedback' => 'Seimbang.'],
                                'longgar' => ['score' => 19, 'feedback' => 'Longgar.'],
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $admin = User::create(['nama' => 'Admin', 'username' => 'adm_gr', 'password' => 'secret', 'role' => 'admin']);
        $quiz = Quiz::create(['judul' => 'Kuis Grader', 'created_by' => $admin->id]);
        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Grader',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'tok-gr',
            'created_by' => $admin->id,
        ]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'tipe' => 'essay',
            'teks' => 'Jelaskan TCP/IP',
            'bobot' => 20,
            'urutan' => 1,
        ]);
        $user = User::create(['nama' => 'Siswa', 'username' => 'siswa_gr', 'password' => 'secret']);
        $attempt = Attempt::create([
            'quiz_session_id' => $session->id,
            'user_id' => $user->id,
            'status' => 'selesai',
            'started_at' => now(),
        ]);
        $answer = Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answered_at' => now(),
            'jawaban_teks' => 'TCP/IP adalah protokol komunikasi jaringan.',
        ]);

        $grader = app(EssayGrader::class);
        $result = $grader->auditAndEvaluate($answer);

        $this->assertSame(82, $result['ai_detection']['score']);
        $this->assertSame(16, $result['suggestions']['seimbang']['score']);

        $answer->refresh();
        $this->assertSame(82, $answer->ai_generated_score);
        $this->assertSame(16, $answer->ai_suggested_score);
        $this->assertSame('Seimbang.', $answer->ai_feedback);
        $this->assertNotNull($answer->ai_suggestions_json);
        $this->assertSame(12, $answer->ai_suggestions_json['ketat']['score']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="EssayGraderAuditAndEvaluateTest"`
Expected: FAIL (`Call to undefined method auditAndEvaluate`)

- [ ] **Step 3: Implement `auditAndEvaluate` in `EssayGrader`**

Verify question is essay, check quota with `assertAiQuota`, call `AiQuestionService::auditAndEvaluateEssay`, record quota with `recordAi`, update `Answer` model with both `ai_suggestions_json`, `ai_generated_*`, and baseline `ai_suggested_*` (`seimbang`), and record `AuditLog`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="EssayGraderAuditAndEvaluateTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/EssayGrader.php tests/Unit/EssayGraderAuditAndEvaluateTest.php
git commit -m "feat: implement auditAndEvaluate in EssayGrader"
```

---

### Task 4: Admin Controller Endpoint & Route

**Files:**
- Modify: `app/Http/Controllers/Admin/ReportController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AdminAuditAndEvaluateEndpointTest.php`

**Interfaces:**
- Produces: `POST /admin/answers/{answer}/audit-and-evaluate` -> JSON / Redirect response.

- [ ] **Step 1: Write the failing test**

```php
namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAuditAndEvaluateEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_call_audit_and_evaluate_endpoint(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'ai_detection' => ['score' => 90, 'reason' => 'Pola LLM.'],
                            'suggestions' => [
                                'ketat' => ['score' => 15, 'feedback' => 'Ketat.'],
                                'seimbang' => ['score' => 18, 'confidence' => 95, 'feedback' => 'Seimbang.'],
                                'longgar' => ['score' => 20, 'feedback' => 'Longgar.'],
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $org = Organization::create(['name' => 'Org', 'slug' => 'org-ae', 'type' => 'education', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $admin = User::create(['nama' => 'Admin', 'username' => 'admin_ae', 'password' => Hash::make('secret'), 'role' => 'admin', 'current_session_token' => 'tok-adm']);
        $org->users()->attach($admin->id, ['role' => 'owner', 'status' => 'active']);

        $quiz = Quiz::create(['organization_id' => $org->id, 'judul' => 'Kuis Endpoint', 'created_by' => $admin->id]);
        $session = QuizSession::create(['organization_id' => $org->id, 'quiz_id' => $quiz->id, 'nama' => 'Sesi', 'mode' => 'acak', 'status' => 'selesai', 'display_token' => 'tok-s', 'created_by' => $admin->id]);
        $question = Question::create(['organization_id' => $org->id, 'quiz_id' => $quiz->id, 'tipe' => 'essay', 'teks' => 'Soal 1', 'bobot' => 20, 'urutan' => 1]);
        $student = User::create(['nama' => 'Student', 'username' => 'stu_ae', 'password' => Hash::make('secret'), 'role' => 'peserta']);
        $attempt = Attempt::create(['organization_id' => $org->id, 'quiz_session_id' => $session->id, 'user_id' => $student->id, 'status' => 'selesai', 'started_at' => now()]);
        $answer = Answer::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'answered_at' => now(), 'jawaban_teks' => 'Jawaban peserta']);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'current_organization_id' => $org->id])
            ->postJson(route('admin.answers.audit-and-evaluate', $answer->id));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ai_detection.score', 90)
            ->assertJsonPath('suggestions.seimbang.score', 18);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="AdminAuditAndEvaluateEndpointTest"`
Expected: FAIL (route `admin.answers.audit-and-evaluate` not defined)

- [ ] **Step 3: Define route and implement controller action**

Add `Route::post('answers/{answer}/audit-and-evaluate', [ReportController::class, 'auditAndEvaluateEssay'])->name('answers.audit-and-evaluate');` in `routes/web.php`.
Implement `auditAndEvaluateEssay(Request $request, Answer $answer, EssayGrader $grader)` in `ReportController.php`. Return JSON or redirect back with flash message.

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="AdminAuditAndEvaluateEndpointTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add routes/web.php app/Http/Controllers/Admin/ReportController.php tests/Feature/AdminAuditAndEvaluateEndpointTest.php
git commit -m "feat: add audit-and-evaluate route and controller endpoint"
```

---

### Task 5: UI Integration in Attempt Report (`attempt.blade.php`)

**Files:**
- Modify: `app/Http/Controllers/Admin/ReportController.php` (pass `ai_suggestions_json` to view data)
- Modify: `resources/views/admin/reports/attempt.blade.php`
- Test: `tests/Feature/CombinedAuditUiTest.php`

**Interfaces:**
- Produces: Interactive *"Audit & Saran Semua Essay"* button, progress dialog, and 3 clickable mode pills (`[Ketat: X] [Seimbang: Y] [Longgar: Z]`) per question.

- [ ] **Step 1: Write the failing test**

```php
namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CombinedAuditUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_attempt_report_renders_combined_audit_button_and_mode_pills(): void
    {
        $org = Organization::create(['name' => 'Org UI', 'slug' => 'org-ui-test', 'type' => 'education', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $admin = User::create(['nama' => 'Admin UI', 'username' => 'adm_ui_test', 'password' => Hash::make('secret'), 'role' => 'admin', 'current_session_token' => 'tok-ui']);
        $org->users()->attach($admin->id, ['role' => 'owner', 'status' => 'active']);

        $quiz = Quiz::create(['organization_id' => $org->id, 'judul' => 'Kuis UI', 'created_by' => $admin->id]);
        $session = QuizSession::create(['organization_id' => $org->id, 'quiz_id' => $quiz->id, 'nama' => 'Sesi UI', 'mode' => 'acak', 'status' => 'selesai', 'display_token' => 'tok-sui', 'created_by' => $admin->id]);
        $question = Question::create(['organization_id' => $org->id, 'quiz_id' => $quiz->id, 'tipe' => 'essay', 'teks' => 'Soal UI', 'bobot' => 20, 'urutan' => 1]);
        $student = User::create(['nama' => 'Student UI', 'username' => 'stu_ui_test', 'password' => Hash::make('secret'), 'role' => 'peserta']);
        $attempt = Attempt::create(['organization_id' => $org->id, 'quiz_session_id' => $session->id, 'user_id' => $student->id, 'status' => 'selesai', 'started_at' => now()]);
        AttemptQuestion::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'urutan' => 1, 'urutan_opsi_json' => []]);

        $answer = Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answered_at' => now(),
            'jawaban_teks' => 'Jawaban UI',
            'ai_generated_score' => 88,
            'ai_suggestions_json' => [
                'ketat' => ['score' => 12, 'feedback' => 'Saran ketat.'],
                'seimbang' => ['score' => 16, 'feedback' => 'Saran seimbang.'],
                'longgar' => ['score' => 20, 'feedback' => 'Saran longgar.'],
            ],
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'current_organization_id' => $org->id])
            ->get(route('admin.reports.attempt', $attempt->id));

        $response->assertOk()
            ->assertSee('Audit & Saran Semua Essay')
            ->assertSee('Ketat: 12')
            ->assertSee('Seimbang: 16')
            ->assertSee('Longgar: 20');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="CombinedAuditUiTest"`
Expected: FAIL (view lacks the elements)

- [ ] **Step 3: Update `ReportController::attempt` and `attempt.blade.php`**

1. Include `'ai_suggestions_json' => $answer?->ai_suggestions_json ?? []` in mapping for `$rincian`.
2. In `attempt.blade.php`, render the bulk button *"Audit & Saran Semua Essay"* which triggers `mintaSemuaSaran()` targetting the new `audit-and-evaluate` endpoint.
3. Display 3-mode clickable pills inside the essay card:
   - Clicking a pill executes Alpine helper:
     ```js
     terapkanMode(mode, score, feedback) {
         this.kini = score;
         if (this.$refs.nilai) this.$refs.nilai.value = score;
         if (this.$refs.catatan) this.$refs.catatan.value = feedback;
         this.catatanKini = feedback;
     }
     ```
4. Render per-question *"Audit Ulang & Saran"* form/button.

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter="CombinedAuditUiTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/ReportController.php resources/views/admin/reports/attempt.blade.php tests/Feature/CombinedAuditUiTest.php
git commit -m "feat: add bulk combined audit and 3-mode clickable suggestion pills in UI"
```

---

### Task 6: Full Regression Verification

**Files:**
- Entire repository

- [ ] **Step 1: Run all test suites**

Run: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit`
Expected: 0 errors, 0 failures across all tests.

- [ ] **Step 2: Verify git status is clean**

Run: `git status`
Expected: Clean working tree on active branch.
