# Distribusi Bobot Nilai Maksimal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan kalkulasi pembagian bobot otomatis berdasarkan nilai maksimal kuis (default 100) untuk soal pilihan ganda dan essay pada generator AI.

**Architecture:** Helper deterministik di `AiQuestionService` untuk membagi nilai maksimal menjadi array bobot per soal, parameter terintegrasi di controller dan form, serta kalkulator live di Blade UI.

**Tech Stack:** Laravel 12, PHP 8.2+, Blade, Tailwind CSS.

**Spec:** `docs/superpowers/specs/2026-11-09-distribusi-bobot-nilai-maksimal-design.md`

## Global Constraints
- Bobot tiap soal wajib berupa integer >= 1.
- Total akumulasi bobot yang digenerate harus tepat sama dengan `nilai_maksimal`.
- Default `nilai_maksimal` = 100, default `porsi_pg` = 60.

---

### Task 1: Kalkulasi Distribusi Bobot di `AiQuestionService`

**Files:**
- Modify: `app/Services/AiQuestionService.php`
- Modify: `tests/Unit/AiQuestionServiceTest.php`

- [ ] **Step 1: Tulis unit test untuk `calculateWeights`**
- [ ] **Step 2: Jalankan test dan pastikan gagal**
- [ ] **Step 3: Implementasikan `calculateWeights` dan integrasikan ke `generateQuestions`**
- [ ] **Step 4: Jalankan test dan pastikan lolos**
- [ ] **Step 5: Commit**

### Task 2: Validasi Controller & Parameter Form

**Files:**
- Modify: `app/Http/Controllers/Admin/QuestionController.php`
- Modify: `tests/Feature/Admin/AiQuestionGenerationTest.php`

- [ ] **Step 1: Tulis feature test untuk penerimaan `nilai_maksimal` & `porsi_pg`**
- [ ] **Step 2: Jalankan test dan pastikan gagal**
- [ ] **Step 3: Update validasi di `QuestionController@aiGenerate` dan teruskan ke view pratinjau**
- [ ] **Step 4: Jalankan test dan pastikan lolos**
- [ ] **Step 5: Commit**

### Task 3: Antarmuka Blade (`ai-form.blade.php` & `ai-preview.blade.php`)

**Files:**
- Modify: `resources/views/admin/questions/ai-form.blade.php`
- Modify: `resources/views/admin/questions/ai-preview.blade.php`

- [ ] **Step 1: Tambahkan input `nilai_maksimal` & `porsi_pg` dengan live calculation di `ai-form.blade.php`**
- [ ] **Step 2: Tambahkan indikator total bobot dan validasi target di `ai-preview.blade.php`**
- [ ] **Step 3: Jalankan seluruh test suite kuis**
- [ ] **Step 4: Commit**
