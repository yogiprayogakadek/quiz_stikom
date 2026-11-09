# Desain Fitur: Generate Soal Otomatis Berbasis RPS & Topik via 9router

## 1. Ringkasan & Tujuan (Intent & Why)
Fitur ini memungkinkan Dosen/Admin membuat butir soal kuis (Pilihan Ganda & Essay) secara otomatis berbasis silabus/RPS (Rencana Pembelajaran Semester) dan topik kajian tertentu.

Integrasi menggunakan **9router** (OpenAI-compatible multi-model gateway) agar aplikasi fleksibel menggunakan beragam model LLM (Gemini, Claude, GPT-4o, DeepSeek, dsb.) tanpa keterikatan pada satu vendor dan tanpa merubah kode aplikasi. Fitur dilengkapi deteksi otomatis daftar model/provider dari 9router untuk ditampilkan pada antarmuka (combo box/dropdown).

---

## 2. Tujuan & Batasan (Goals, Non-Goals, Anti-Goals)

### Goals
- **Auto-Detect Models/Providers:** Mendeteksi model yang aktif di 9router secara dinamis via endpoint `/models` dan mengelompokkannya per provider di dropdown form.
- **Input Fleksibel & Cepat:** Input berupa teks salinan RPS/CPMK dan topik spesifik tanpa membebani sistem dengan parsing berkas dokumen berat.
- **Multi-Format Output:** Mendukung generate soal Pilihan Ganda (beserta opsi jawaban & kunci) dan Essay (beserta rubrik/kunci essay dan pembahasan).
- **Human-in-the-Loop (Preview & Review):** Hasil generate AI tidak langsung masuk database, melainkan ditampilkan di halaman review agar dosen dapat mengoreksi, mengedit opsi, atau memilih hanya soal yang berkualitas.
- **Konsistensi Skema Data:** Soal hasil generate langsung sesuai dengan skema database yang ada (`questions` dan `options`).

### Non-Goals
- Tidak mengunggah atau mem-parsing dokumen fisik PDF/DOCX secara otomatis (dosen cukup menyalin teks bagian materi/CPMK).
- Tidak membuat tabel database baru untuk draft (menggunakan form review session/payload sebelum commit ke tabel utama).

### Anti-Goals
- Tidak ada auto-save tanpa verifikasi dosen (mencegah halusinasi AI merusak kualitas kuis).
- Tidak menginstal SDK vendor pihak ketiga (menjaga dependensi tetap minimal menggunakan native HTTP client Laravel).

---

## 3. Pendekatan yang Dipertimbangkan (Approaches)

1. **Pendekatan 1: Native Laravel HTTP + 9router OpenAI-Compatible API + Dedicated Preview View (Dipilih)**
   - Menggunakan `Illuminate\Support\Facades\Http` bawaan Laravel.
   - Panggilan dinamis ke `GET {AI_BASE_URL}/models` dengan caching 1 jam untuk mengisi combo box model.
   - Panggilan ke `POST {AI_BASE_URL}/chat/completions` dengan enforcement format JSON terstruktur.
   - Halaman pratinjau khusus sebelum batch commit ke database.
   - *Alasan Dipilih:* Ringan, tanpa package tambahan, sesuai pola arsitektur kode kuis yang ada, dan multi-model secara instan.

2. **Pendekatan 2: SDK Vendor Pihak Ketiga (OpenAI PHP / Google GenAI SDK)**
   - Menginstal package composer resmi vendor.
   - *Kelemahan:* Keterikatan vendor spesifik, menambah dependensi `composer.json`, menyulitkan pergantian provider di 9router.

3. **Pendekatan 3: Background Worker Queue + Tabel Staging (`draft_questions`)**
   - Proses generate di latar belakang dengan antrean (Job).
   - *Kelemahan:* Model modern (Flash/Mini) merespons dalam 2-5 detik, queue menambah kompleksitas database dan dependensi worker tanpa nilai tambah yang signifikan.

---

## 4. Konfigurasi Lingkungan (`.env` & `config/services.php`)

```env
# Konfigurasi 9router
AI_BASE_URL=https://api.9router.com/v1
AI_API_KEY=your_9router_api_key_here
AI_DEFAULT_MODEL=ag/gemini-3.8-flash-medium
AI_TIMEOUT=60
```

`config/services.php`:
```php
'ai' => [
    'base_url' => env('AI_BASE_URL', 'https://api.9router.com/v1'),
    'api_key' => env('AI_API_KEY'),
    'default_model' => env('AI_DEFAULT_MODEL', 'ag/gemini-3.8-flash-medium'),
    'timeout' => (int) env('AI_TIMEOUT', 60),
],
```

---

## 5. Komponen Layanan (`App\Services\AiQuestionService`)

Layanan ini mengelola 2 tugas utama:

### A. Deteksi Provider & Model (`getAvailableModels()`)
- Memanggil `GET {AI_BASE_URL}/models`.
- Parsing defensif: mendukung response OpenAI standard (`$response->json('data')`) maupun root array (`$response->json()`).
- Response 9router di-cache menggunakan `Cache::remember('9router_models', 3600, ...)`.
- Parsing & grouping provider berdasarkan prefix `id` (contoh: `ag/`, `google/`, `openai/`, `anthropic/`, `deepseek/`, `meta-llama/`) atau atribut `owned_by`.
- Menyediakan fallback ke `AI_DEFAULT_MODEL` jika koneksi atau API key bermasalah.
- Method `refreshModelsCache()` untuk membersihkan cache saat dosen ingin memperbarui daftar model.

### B. Eksekusi Generate Soal (`generateQuestions()`)
- Parameter:
  - `model`: string (ID model dari combo box)
  - `rps_text`: string (potongan RPS/CPMK)
  - `topik`: string (topik materi kuis)
  - `jumlah_pg`: int
  - `jumlah_essay`: int
  - `level`: string (Taksonomi Bloom: C1-C2 Pemahaman, C3-C4 Penerapan/Analisis, C5-C6 Evaluasi/Kreasi)
- Mengirim pesan dengan system instruction ketat:
  - Memaksa format respons murni JSON array.
  - Skema respons terstandarisasi:
    ```json
    {
      "questions": [
        {
          "teks": "Soal pertanyaan...",
          "tipe": "pilihan_ganda",
          "bobot": 1,
          "durasi_detik": 60,
          "pembahasan": "Penjelasan mengapa jawaban benar...",
          "opsi": [
            { "teks": "Pilihan A", "is_correct": true },
            { "teks": "Pilihan B", "is_correct": false },
            { "teks": "Pilihan C", "is_correct": false },
            { "teks": "Pilihan D", "is_correct": false }
          ]
        },
        {
          "teks": "Pertanyaan essay...",
          "tipe": "essay",
          "bobot": 5,
          "durasi_detik": 180,
          "pembahasan": "Panduan penilaian...",
          "kunci_essay": "Kata kunci jawaban penting..."
        }
      ]
    }
    ```

---

## 6. Rute & Alur Kerja Antarmuka (UI & Routing)

### Alur URL Admin:
1. `GET /admin/quizzes/{quiz}/questions/ai-generate` (`QuestionController@aiForm`)
   - Menampilkan form:
     - Dropdown Model AI (dikelompokkan per Provider: Google, Anthropic, OpenAI, Meta, dll.).
     - Tombol reload model list (AJAX atau link refresh).
     - Textarea RPS / Capaian Pembelajaran.
     - Input Teks Topik.
     - Input Jumlah Soal PG & Essay.
     - Dropdown Tingkat Kesulitan (Bloom C1 - C6).
2. `POST /admin/quizzes/{quiz}/questions/ai-generate` (`QuestionController@aiGenerate`)
   - Memvalidasi input.
   - Memanggil `AiQuestionService@generateQuestions`.
   - Mengembalikan view pratinjau (`ai-preview.blade.php`) membawa data array soal hasil generate.
3. `POST /admin/quizzes/{quiz}/questions/ai-store` (`QuestionController@aiStore`)
   - Menerima payload soal yang sudah disetujui/diedit dosen.
   - `DB::transaction()`:
     - Membuat record `Question`.
     - Untuk tipe `pilihan_ganda`, membuat 4-5 record `Option`.
   - Redirect kembali ke `admin.quizzes.questions.index` dengan pesan sukses.

---

## 7. Desain Antarmuka (Sesuai Prinsip Antislop: Clean, Functional, Accessible)

### Tampilan Form Generator (`ai-form.blade.php`)
- Form rapi, berjarak proporsional, tanpa ornamen/kartu berlebihan.
- Combo box model dengan `<optgroup label="Vendor">` memudahkan dosen memilih model hemat (Flash) atau model penalaran tinggi (Sonnet/GPT-4o).
- Panduan singkat format pengisian RPS di bawah textarea.

### Tampilan Pratinjau & Review (`ai-preview.blade.php`)
- Setiap butir soal ditampilkan dalam form input yang bisa diedit langsung (editable input/textarea).
- Opsi pilihan ganda dapat diganti status kuncinya (`radio button`).
- Checkbox "Sertakan soal ini" per butir soal (default: checked).
- Tombol aksi jelas: "Simpan Soal Terpilih ke Kuis" dan "Batalkan".

---

## 8. Penanganan Error & Edge Cases
- **API Timeout / Model Down:** Tangkap `ConnectionException` atau status HTTP != 200, kembalikan notifikasi error ke form tanpa mereset inputan RPS/Topik dosen.
- **Model Non-JSON Output:** Validasi ketat `json_decode()`. Jika model mengembalikan markdown codeblock (```json ... ```), regex stripper akan membersihkannya sebelum decoding.
- **Model List Gagal Dimuat:** Fallback instan ke model default yang ada di `.env` sehingga proses generate tetap dapat berjalan.
