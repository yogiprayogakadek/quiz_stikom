# Essay Plagiarism & AI Detection (Smart Hybrid) Design

## 1. Intent & Goal
Menambahkan sistem audit integritas akademik pada jawaban essay peserta kuis melalui dua mekanisme yang terpisah:
1. **Pengecekan kemiripan teks antar-peserta (Cross-Participant Similarity)**: Dijalankan otomatis pasca-sesi kuis atau on-demand oleh admin untuk mendeteksi potensi saling contek antar-peserta dalam satu sesi/soal secara lokal di server tanpa kuota AI.
2. **Deteksi teks buatan AI/LLM (AI-Generated Text Detection)**: Dijalankan on-demand oleh admin per jawaban essay untuk menganalisis apakah jawaban dibuat oleh LLM/ChatGPT menggunakan provider AI yang aktif.

## 2. Non-Goals & Constraints
- **Non-Goals**:
  - Tidak memindai internet publik/Google search (fokus pada saling contek antar-peserta dan contekkan LLM).
  - Tidak otomatis memotong nilai peserta; keputusan penalti/diskualifikasi tetap menjadi hak prerogatif pengajar/admin.
- **Constraints**:
  - Pengecekan kemiripan antar-peserta wajib 100% lokal di PHP tanpa dependensi library eksternal berbayar atau konsumsi token AI.
  - Kompatibel dengan SQLite (testing) dan MySQL (production).

## 3. Approaches Considered
1. **Opsi 1: All-in-one AI Prompt (Evaluasi + Plagiarisme)**
   - *Kelemahan*: Boros token, peserta yang selesai lebih awal belum punya pembanding, hasil tidak deterministik.
2. **Opsi 2: Full Automatic Plagiarisme + Deteksi AI**
   - *Kelemahan*: Menghabiskan kuota token AI jika semua jawaban essay otomatis dipindai AI.
3. **Opsi 3: Smart Hybrid (Terpilih)**
   - *Keunggulan*: Kemiripan antar-peserta dihitung otomatis di background menggunakan algoritma N-gram/Jaccard lokal (gratis & cepat). Deteksi AI dijalankan on-demand sesuai kebutuhan pengajar.

## 4. Architecture & Data Model
### A. Database
Kolom baru pada tabel `answers`:
- `similarity_score` (`unsignedTinyInteger`, nullable)
- `similarity_data` (`json`, nullable)
- `similarity_checked_at` (`timestamp`, nullable)
- `ai_generated_score` (`unsignedTinyInteger`, nullable)
- `ai_generated_reason` (`text`, nullable)
- `ai_generated_at` (`timestamp`, nullable)

### B. Core Services & Jobs
- `App\Services\TextSimilarityService`:
  - `calculate(string $textA, string $textB): float` (0.0 - 1.0)
  - `compareAnswers(Collection|array $answers): array`
  - Algoritma: N-gram tokenization (shingling n=3 kata atau n=4 karakter) + Jaccard similarity index, dengan normalisasi teks (lowercase, strip markdown/tags, strip punctuation, strip whitespace).
- `App\Jobs\ScanSessionPlagiarismJob`:
  - Memindai seluruh jawaban essay per soal pada sesi tertentu, menyimpan `similarity_score` dan `similarity_data` pada masing-masing `Answer`.
- `App\Services\AiQuestionService::detectAiGenerated(string $question, string $answer): array`:
  - Mengirim prompt analisis linguistik (perplexitas, variasi kalimat, struktur generik LLM) ke LLM yang aktif.
  - Mengembalikan `{"score": int, "reason": string}`.

### C. Admin Web UI & Endpoints
- `POST /admin/sessions/{session}/scan-plagiarism`: Memicu scan plagiarisme sesi di background / langsung.
- `POST /admin/answers/{answer}/detect-ai`: Memicu audit deteksi AI on-demand untuk 1 jawaban essay.
- Tampilan `admin.reports.attempt`:
  - Badge kemiripan `[⚠️ 85% mirip Budi]` dengan modal perbandingan side-by-side teks yang bersangkutan.
  - Tombol / badge audit AI `[🤖 Deteksi AI: 90%]` dengan alasan singkat.
- Tampilan `admin.reports.session`:
  - Ringkasan indikasi plagiat pada sesi kuis dan tombol "Pindai Ulang Plagiarisme".
