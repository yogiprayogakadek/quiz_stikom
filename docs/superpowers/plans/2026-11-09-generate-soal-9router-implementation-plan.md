# Generate Soal Otomatis via 9router Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun fitur pembuatan soal kuis otomatis berbasis teks RPS dan topik melalui 9router (OpenAI-compatible gateway) dengan deteksi provider/model dinamis dan verifikasi pratinjau sebelum disimpan ke database.

**Architecture:** Native Laravel `Http` client terhubung ke 9router `/v1/models` (dikelompokkan & di-cache) dan `/v1/chat/completions` (JSON format enforcement). Alur interaksi melalui form generator -> controller generate -> halaman pratinjau (edit/kurasi) -> batch insert DB transaction ke tabel `questions` & `options`.

**Tech Stack:** Laravel 12, PHP 8.2+, Blade, 9router API (OpenAI-compatible).

**Spec:** `docs/superpowers/specs/2026-11-09-generate-soal-9router-design.md`

## Global Constraints
- Menggunakan native `Illuminate\Support\Facades\Http`, tanpa penambahan package external di `composer.json`.
- Seluruh penyimpanan batch soal wajib dibungkus dalam `DB::transaction()`.
- Model AI list di-cache dengan key `9router_models` berdurasi 3600 detik dan memiliki tombol refresh paksa.
- Parsing respons AI wajib defensif terhadap codeblock markdown (```json ... ```) dan struktur `data` maupun direct list.

## Review Focus
1. 9router endpoint `/models` timeout/gagal: Harus fallback anggun ke model default `.env` tanpa error 500.
2. Model mengembalikan teks pengantar sebelum JSON: Parser regex wajib mengekstrak blok JSON valid secara bersih.
3. Jumlah opsi pilihan ganda kurang dari 2 atau tidak memiliki jawaban benar: Validator pratinjau wajib menandai/menolak butir tidak valid.
4. Kuis terkunci (sesi sudah dimulai): Tombol dan route generate wajib mematuhi aturan penguncian kuis (`quiz->isLocked()`).
5. Bobot dan durasi bernilai nol atau negatif: Diberi batas nilai default yang aman (`bobot: 1`, `durasi_detik: 60`).

---

### Task 1: Konfigurasi Service & Environment

**Files:**
- Modify: `config/services.php`
- Modify: `.env.example`
- Test: `tests/Unit/AiConfigTest.php`

**Interfaces:**
- Consumes: Environment variables `AI_BASE_URL`, `AI_API_KEY`, `AI_DEFAULT_MODEL`, `AI_TIMEOUT`.
- Produces: Configuration array `config('services.ai')`.

- [ ] **Step 1: Tulis unit test untuk verifikasi konfigurasi AI**

```php
// tests/Unit/AiConfigTest.php
public function test_ai_config_has_required_keys(): void
{
    $config = config('services.ai');
    $this->assertIsArray($config);
    $this->assertArrayHasKey('base_url', $config);
    $this->assertArrayHasKey('api_key', $config);
    $this->assertArrayHasKey('default_model', $config);
}
```

- [ ] **Step 2: Jalankan test dan pastikan gagal**

Run: `php artisan test --filter=AiConfigTest`
Expected: FAIL

- [ ] **Step 3: Tambahkan konfigurasi `ai` pada `config/services.php` dan update `.env.example`**

- [ ] **Step 4: Jalankan test dan pastikan sukses**

Run: `php artisan test --filter=AiConfigTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add config/services.php .env.example tests/Unit/AiConfigTest.php
git commit -m "feat(ai): add 9router configuration and unit test"
```

---

### Task 2: Service `App\Services\AiQuestionService`

**Files:**
- Create: `app/Services/AiQuestionService.php`
- Test: `tests/Unit/AiQuestionServiceTest.php`

**Interfaces:**
- Consumes: `config('services.ai')`, `Http::fake()`.
- Produces: 
  - `AiQuestionService::getAvailableModels(bool $forceRefresh = false): array`
  - `AiQuestionService::generateQuestions(array $params): array`

- [ ] **Step 1: Tulis unit test untuk model discovery & question generation**

```php
// tests/Unit/AiQuestionServiceTest.php
public function test_get_available_models_groups_by_provider(): void;
public function test_get_available_models_fallback_on_failure(): void;
public function test_generate_questions_parses_json_cleanly(): void;
```

- [ ] **Step 2: Jalankan test dan pastikan gagal**

Run: `php artisan test --filter=AiQuestionServiceTest`
Expected: FAIL

- [ ] **Step 3: Implementasikan `AiQuestionService.php`**
Implementasikan pemanggilan HTTP ke 9router, caching 1 jam, grouping provider berdasarkan prefix ID atau `owned_by`, sanitasi regex markdown codeblock, serta validasi struktur output soal.

- [ ] **Step 4: Jalankan test dan pastikan sukses**

Run: `php artisan test --filter=AiQuestionServiceTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/AiQuestionService.php tests/Unit/AiQuestionServiceTest.php
git commit -m "feat(ai): implement AiQuestionService with model discovery and question generator"
```

---

### Task 3: Routes & Controller Actions

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/QuestionController.php`
- Test: `tests/Feature/Admin/AiQuestionGenerationTest.php`

**Interfaces:**
- Consumes: `AiQuestionService`, `Quiz`, `Question`, `Option`.
- Produces:
  - `GET admin/quizzes/{quiz}/questions/ai-generate` (`QuestionController@aiForm`)
  - `POST admin/quizzes/{quiz}/questions/ai-generate` (`QuestionController@aiGenerate`)
  - `POST admin/quizzes/{quiz}/questions/ai-store` (`QuestionController@aiStore`)

- [ ] **Step 1: Tulis feature test untuk otorisasi dan endpoint controller AI**

```php
// tests/Feature/Admin/AiQuestionGenerationTest.php
public function test_admin_can_view_ai_form(): void;
public function test_ai_generate_returns_preview_view(): void;
public function test_ai_store_saves_selected_questions_and_options(): void;
public function test_cannot_generate_for_locked_quiz(): void;
```

- [ ] **Step 2: Jalankan test dan pastikan gagal**

Run: `php artisan test --filter=AiQuestionGenerationTest`
Expected: FAIL

- [ ] **Step 3: Daftarkan rute di `routes/web.php` dan tambahkan method `aiForm`, `aiGenerate`, `aiStore` di `QuestionController`**

- [ ] **Step 4: Jalankan test dan pastikan sukses**

Run: `php artisan test --filter=AiQuestionGenerationTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add routes/web.php app/Http/Controllers/Admin/QuestionController.php tests/Feature/Admin/AiQuestionGenerationTest.php
git commit -m "feat(ai): add routes and controller methods for ai question generator"
```

---

### Task 4: Antarmuka Blade (Form Generator & Preview)

**Files:**
- Create: `resources/views/admin/questions/ai-form.blade.php`
- Create: `resources/views/admin/questions/ai-preview.blade.php`
- Modify: `resources/views/admin/questions/index.blade.php`

**Interfaces:**
- Consumes: Model options grouped by provider, array of generated question drafts.
- Produces: HTML UI form dengan prinsip Antislop (clean typography, clear hierarchy, zero visual slop).

- [ ] **Step 1: Tambahkan tombol "Generate AI" di `resources/views/admin/questions/index.blade.php` berdampingan dengan tombol "Import CSV"**
- [ ] **Step 2: Buat tampilan `ai-form.blade.php` dengan dropdown combo box model terkelompok, textarea RPS, input topik, dan parameter soal**
- [ ] **Step 3: Buat tampilan `ai-preview.blade.php` dengan form review inline, radio button pemilihan kunci jawaban benar, checkbox pilih/abaikan soal, dan tombol simpan batch**
- [ ] **Step 4: Verifikasi rendering form via browser / feature test**
- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/questions/ai-form.blade.php resources/views/admin/questions/ai-preview.blade.php resources/views/admin/questions/index.blade.php
git commit -m "feat(ai): create blade views for ai question form and preview"
```

---

### Task 5: Verifikasi Menyeluruh & Regresi

**Files:**
- All tests

- [ ] **Step 1: Jalankan seluruh test suite kuis**

Run: `php artisan test`
Expected: Semua test kuis yang ada dan test baru lolos (100% PASS).

- [ ] **Step 2: Commit akhir & dokumentasi**

```bash
git commit --allow-empty -m "chore: complete 9router question generation feature"
```
