# Auto-grade Essay per Kuis

## Intent

Kuis berisiko rendah boleh menilai essay otomatis dengan AI. Kuis lain tetap manual agar keputusan nilai tetap di tangan admin. Pengaturan ini melekat pada kuis, bukan pada sesi atau platform.

## Goals

- Flag `auto_grade_essay` per kuis, default mati.
- Saat attempt Mode Acak selesai, essay yang belum dinilai diproses otomatis lewat queue.
- Nilai AI langsung menjadi nilai final, tercatat di audit, dan tetap bisa diubah admin.
- Kuota AI per organisasi dihormati. Jika kuota habis atau AI gagal, essay tetap menunggu penilaian manual.
- Job memakai provider AI organisasi, bukan pengaturan platform.

## Non-goals

- Mode Serempak; mode ini memang menolak soal essay.
- Auto-grade attempt yang belum selesai.
- Penilaian ulang otomatis setelah admin mengubah nilai.
- Pengaturan auto-grade di level sesi atau platform.

## Approaches

1. **Job per attempt saat peserta selesai (dipilih).** Attempt yang selesai langsung mengantre satu job. Nilai cepat tersedia, kuota dihitung per essay, dan attempt yang direset tidak memakai kuota. Trade-off: satu job per attempt.
2. **Batch saat sesi ditutup.** Semua attempt dinilai sekaligus. Lebih sedikit job, tetapi peserta menunggu sampai sesi berakhir dan attempt yang direset tetap sempat dinilai.
3. **Saat laporan dibuka.** Nilai baru dibuat ketika admin membuka laporan. Paling hemat kuota, tetapi tidak terasa otomatis bagi peserta dan hasil bergantung pada kunjungan admin.

## Design

### Data dan UI

- Migration baru menambahkan `quizzes.auto_grade_essay` boolean, default `false`.
- `Quiz` menambahkan field ke `$fillable` dan cast boolean.
- `StoreQuizRequest` memvalidasi flag sebagai boolean nullable.
- `QuizController` menyimpan `$request->boolean('auto_grade_essay')` saat create/update.
- Form kuis menampilkan checkbox **Nilai essay otomatis dengan AI** dengan bantuan bahwa ini untuk kuis berisiko rendah dan nilai masih bisa diubah admin.
- Daftar kuis menampilkan badge kecil saat auto-grade aktif.

### Penilaian

- `EssayGrader` menjadi pemilik aturan penilaian essay:
  - `suggest(Answer $answer)`: cek kuota, panggil AI, simpan rekomendasi, catat pemakaian, dan tulis audit `essay_ai_suggested`.
  - `applyFinal(Answer $answer, int $score, ?int $userId)`: batasi nilai ke bobot soal, simpan `skor`, `dinilai_at`, `dinilai_oleh`, dan tulis audit `essay_score_changed`.
- `ReportController::saranNilaiEssay` memakai `suggest()`; `nilaiEssay` memakai `applyFinal()` agar jalur manual dan otomatis konsisten.

### Job otomatis

- `AutoGradeEssayJob` menerima ID attempt dan dijalankan setelah commit.
- Job hanya memproses attempt `selesai` pada sesi `acak` yang kuisnya mengaktifkan auto-grade.
- Untuk setiap jawaban essay yang punya teks dan belum `dinilai_at`:
  - Pakai rekomendasi AI yang sudah ada jika tersedia; jika tidak, panggil `EssayGrader::suggest()`.
  - Terapkan nilai final dengan `applyFinal(..., null)` sehingga riwayat menampilkan **Sistem**.
  - Siarkan `EssayDinilai` agar hasil peserta dan monitor admin ikut diperbarui.
- Setelah semua jawaban, hitung ulang skor attempt dengan `QuizEngine::hitungSkorAcak()` dan siarkan `SessionMonitorUpdated`.
- Jika kuota habis, hentikan sisa jawaban dan biarkan manual. Jika satu panggilan AI gagal, lanjut ke jawaban berikutnya.
- Job idempotent: jawaban yang sudah final dilewati, dan job yang terkirim dua kali tidak menimpa nilai admin.

### Pemicu

- `QuizEngine::selesaikanAcak` mengantre job setelah transaksi selesai.
- `hitungSelesaiAcakTerkunci` mengantre job dengan `afterCommit` saat sesi ditutup.
- `kumpulkanPaksa` otomatis ikut karena memakai `selesaikanAcak` untuk Mode Acak.

### Konteks organisasi

- `OrganizationContext::force(?Organization)` menetapkan organisasi untuk worker dan membuang cache `app.settings`.
- Job memanggil `force()` sebelum me-resolve `EssayGrader`, lalu `force(null)` di `finally`.
- Ini memastikan provider dan API key yang dipakai adalah milik organisasi attempt.

## Tests

- Flag default mati dan tersimpan lewat create/update.
- Kuis tanpa flag tidak mengantre job.
- Kuis dengan flag mengantre job saat attempt selesai.
- Job mengisi nilai final, audit, dan pemakaian AI.
- Job tidak menimpa nilai yang sudah dinilai admin.
- Kuota habis atau AI gagal meninggalkan essay untuk penilaian manual.
- Job yang dijalankan dua kali tidak menambah nilai atau audit final.
