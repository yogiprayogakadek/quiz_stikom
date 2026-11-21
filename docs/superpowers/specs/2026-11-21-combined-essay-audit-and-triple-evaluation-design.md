# Design: Combined Essay Integrity Audit & Triple-Mode AI Suggestions

## 1. Intent & Context
Allow evaluators (instructors/admins) to run a comprehensive, one-click evaluation on student essay answers. In a single AI API call per essay answer, the system performs:
1. Academic integrity audit (AI-generated probability 0–100% and concise rationale).
2. Rubric evaluation across three scoring leniency modes:
   - **Ketat** (Strict)
   - **Seimbang** (Balanced)
   - **Longgar** (Lenient)

Instructors can trigger this evaluation in bulk across all essay questions in an attempt, view side-by-side score buttons (`[Ketat: X] [Seimbang: Y] [Longgar: Z]`), and click any option to quickly populate the final score and feedback fields.

## 2. Goals & Anti-Goals
- **Goals:**
  - 1 API call per essay answer to compute both AI detection and 3-mode evaluation.
  - Store multi-mode suggestions in database (`ai_suggestions_json` on `answers` table).
  - Provide interactive bulk evaluation with progress bar dialog in `attempt.blade.php`.
  - Provide individual re-run capability per essay question.
  - Enable one-click score adoption from any of the 3 modes directly in the grading UI.
- **Anti-Goals:**
  - Automatic unreviewed score submission (human-in-the-loop teacher grading is required).
  - Multiple separate HTTP calls per essay answer.

## 3. Database Schema
Table `answers`:
- Add `ai_suggestions_json` (`json`, nullable, after `ai_feedback`):
  Structure:
  ```json
  {
    "ketat": { "score": 14, "feedback": "Penjelasan implementasi kurang mendalam." },
    "seimbang": { "score": 17, "feedback": "Konsep inti terjawab dengan tepat." },
    "longgar": { "score": 20, "feedback": "Ide pokok benar dan dapat diterima." }
  }
  ```
- Retain backward compatibility:
  - `ai_generated_score`, `ai_generated_reason`, `ai_generated_at` populated from `ai_detection`.
  - `ai_suggested_score`, `ai_confidence`, `ai_feedback`, `ai_reviewed_at` populated using the `seimbang` mode as default baseline.

## 4. Service Architecture
- `AiQuestionService::auditAndEvaluateEssay(string $question, ?string $referenceAnswer, array $criteria, string $answer, int $maxScore): array`
  - Single system prompt acting as academic integrity auditor & rubric evaluator across 3 scoring modes.
  - Returns:
    ```php
    [
        'ai_detection' => ['score' => int, 'reason' => string],
        'suggestions' => [
            'ketat' => ['score' => int, 'feedback' => string],
            'seimbang' => ['score' => int, 'confidence' => int, 'feedback' => string],
            'longgar' => ['score' => int, 'feedback' => string],
        ],
        'model' => string,
        'provider' => string,
    ]
    ```
- `EssayGrader::auditAndEvaluate(Answer $answer): array`
  - Validates organization AI quota.
  - Calls `AiQuestionService::auditAndEvaluateEssay`.
  - Records usage (`recordAi`).
  - Updates `Answer` record with `ai_suggestions_json`, `ai_generated_*`, and `ai_suggested_*`.
  - Records `AuditLog`.

## 5. Endpoints & Routes
- Route: `POST /admin/answers/{answer}/audit-and-evaluate`
  - Name: `admin.answers.audit-and-evaluate`
  - Controller: `ReportController::auditAndEvaluateEssay`
  - Returns JSON for async frontend bulk loops and redirect for non-JSON requests.

## 6. UI Specifications
- File: `resources/views/admin/reports/attempt.blade.php`
- Bulk action button: *"Audit & Saran Semua Essay"*
  - Progress dialog tracking completion count and percentage with "Hentikan" button.
- Per-question scoring card:
  - AI Detection badge (`🤖 X% AI`).
  - 3-Mode quick select pill buttons:
    - Button `Ketat: {score}`
    - Button `Seimbang: {score}`
    - Button `Longgar: {score}`
  - Clicking any button sets form input `nilai` to that score and appends/replaces `catatan` with that mode's feedback.
  - Per-question re-run button *"Audit Ulang & Buat Saran"*.
