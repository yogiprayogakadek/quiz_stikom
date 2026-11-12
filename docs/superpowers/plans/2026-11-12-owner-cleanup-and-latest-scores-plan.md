# Owner Cleanup and Latest Scores Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Master dapat menghapus akun owner yatim dengan guard server, dan widget Nilai Terbaru mengurutkan lima attempt terbaru dari skor tertinggi.

**Architecture:** Tambah satu aksi destroy pada `OwnerController` dengan guard eksplisit dan soft-delete. Daftar owner memakai hitungan keanggotaan untuk menampilkan aksi hanya pada akun yang layak. Dashboard tetap memakai query lima attempt terbaru, lalu mengurutkan koleksinya berdasarkan `skor_total`.

**Tech Stack:** Laravel 12, Blade, PHPUnit, SQLite in-memory.

**Spec:** `docs/superpowers/specs/2026-11-12-owner-cleanup-and-latest-scores-design.md`

## Global Constraints

- Jangan menambah dependency.
- Pertahankan isolasi organisasi dan perilaku soft-delete yang sudah ada.
- Guard server harus sama ketatnya dengan eligibility UI.
- Widget Nilai Terbaru tetap berarti lima attempt terbaru, bukan top five sepanjang masa.
- Gunakan `skor_total` untuk urutan karena itu nilai yang ditampilkan.

## Review Focus

- Admin yang masih memiliki workspace harus ditolak, bukan dihapus.
- Admin yang masih menjadi anggota workspace lain harus ditolak meskipun tidak memiliki workspace.
- Admin dengan permintaan workspace pending harus ditolak.
- Platform admin dan akun non-admin tidak boleh dapat dihapus lewat route ini.
- Attempt paling lama dengan skor tertinggi harus tetap keluar dari jendela lima terbaru.

---

### Task 1: Owner deletion endpoint and guards

**Files:**
- Modify: `routes/web.php:92`
- Modify: `app/Http/Controllers/Master/OwnerController.php`
- Test: `tests/Feature/WorkspaceOwnershipTest.php`

**Interfaces:**
- Consumes: `User::ownedOrganizations()`, `User::organizations()`, `WorkspaceRequest::STATUS_PENDING`, `AuditLog::record()`.
- Produces: `OwnerController::destroy(User $owner): RedirectResponse`; route `master.owners.destroy`.

- [ ] **Step 1: Write the failing deletion tests**

Add tests covering:
- master deletes an orphan admin, asserts soft delete and `owner_deleted` audit log;
- owner with an owned workspace gets `422` and remains active;
- admin with active membership but no owned workspace gets `422` and remains active;
- admin with pending workspace request gets `422` and remains active;
- platform admin gets `404`.

- [ ] **Step 2: Run the new tests to verify they fail**

Run: `php artisan test --filter=WorkspaceOwnershipTest`
Expected: FAIL because `master.owners.destroy` does not exist.

- [ ] **Step 3: Add the guarded destroy action**

In `OwnerController`, add `destroy(User $owner): RedirectResponse`. Abort `404` unless the user is an admin and not a platform admin. Abort `422` when any owned workspace, any membership, or a pending workspace request exists. In a transaction, clear `current_session_token`, soft-delete the user, and record `owner_deleted` with name, username, and email metadata. Redirect to `master.owners.index` with a success message.

Register `Route::delete('owners/{owner}', [OwnerController::class, 'destroy'])->name('owners.destroy');` inside the master group.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=WorkspaceOwnershipTest`
Expected: PASS.

### Task 2: Owner list delete action

**Files:**
- Modify: `app/Http/Controllers/Master/OwnerController.php:19-25`
- Modify: `resources/views/master/owners/index.blade.php:63-102`
- Test: `tests/Feature/WorkspaceOwnershipTest.php`

**Interfaces:**
- Consumes: `owned_organizations_count`, `organizations_count`, `$pendingRequests`.
- Produces: action column that renders a delete form only for eligible owners.

- [ ] **Step 1: Write the failing view test**

Add a test that loads `master.owners.index` with an orphan admin, an admin who is a member of a workspace, and an admin with a pending request. Assert the orphan's `master.owners.destroy` URL is present, the other URLs are absent, and the reason texts appear.

- [ ] **Step 2: Run the view test to verify it fails**

Run: `php artisan test --filter=WorkspaceOwnershipTest`
Expected: FAIL because the action column and delete form do not exist.

- [ ] **Step 3: Add membership counts and the action column**

Add `->withCount('organizations')` to the owner list query. Add an **Aksi** header and cell. Compute eligibility from zero owned workspaces, zero memberships, and no pending request. Render a POST form with `@csrf`, `@method('DELETE')`, and a confirmation. Render **Masih anggota workspace** or **Permintaan pending** for the two ineligible orphan cases.

- [ ] **Step 4: Run the view test to verify it passes**

Run: `php artisan test --filter=WorkspaceOwnershipTest`
Expected: PASS.

### Task 3: Latest scores ordering

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php:43-49`
- Test: `tests/Feature/DashboardLatestScoresTest.php` (create)

**Interfaces:**
- Consumes: `Attempt` query already ordered by `updated_at` and limited to five.
- Produces: `$leaderboardTerbaru` sorted by `skor_total` descending.

- [ ] **Step 1: Write the failing dashboard test**

Create six completed attempts on a real quiz session. Give the oldest attempt the highest score, and give the five newer attempts mixed scores with `nilai` values that would produce a different order. Assert `leaderboardTerbaru` contains only the five newest scores in descending `skor_total` order.

- [ ] **Step 2: Run the dashboard test to verify it fails**

Run: `php artisan test --filter=DashboardLatestScoresTest`
Expected: FAIL because the collection is still ordered by `updated_at`.

- [ ] **Step 3: Sort the limited collection**

Append `->sortByDesc('skor_total')->values()` to the existing `leaderboardTerbaru` query. Do not remove the `orderByDesc('updated_at')->limit(5)` window.

- [ ] **Step 4: Run the dashboard test to verify it passes**

Run: `php artisan test --filter=DashboardLatestScoresTest`
Expected: PASS.

### Task 4: Verification and commit

- [ ] **Step 1: Run the focused tests**

Run: `php artisan test --filter=WorkspaceOwnershipTest` and `php artisan test --filter=DashboardLatestScoresTest`
Expected: PASS.

- [ ] **Step 2: Run the full suite**

Run: `php artisan test`
Expected: all tests pass.

- [ ] **Step 3: Compile Blade views**

Run: `php artisan view:cache`
Expected: exit code 0.

- [ ] **Step 4: Run the Impeccable detector on changed views**

Run the project's Impeccable detector against `resources/views/master/owners/index.blade.php` and `resources/views/admin/dashboard.blade.php`. Fix only findings introduced by this change.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Master/OwnerController.php app/Http/Controllers/DashboardController.php routes/web.php resources/views/master/owners/index.blade.php tests/Feature/WorkspaceOwnershipTest.php tests/Feature/DashboardLatestScoresTest.php docs/superpowers/specs/2026-11-12-owner-cleanup-and-latest-scores-design.md docs/superpowers/plans/2026-11-12-owner-cleanup-and-latest-scores-plan.md
git commit -m "feat: delete orphan owners and sort latest scores"
```
