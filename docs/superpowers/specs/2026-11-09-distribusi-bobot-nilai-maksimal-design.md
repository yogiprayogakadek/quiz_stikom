# Desain Fitur: Distribusi Bobot Otomatis Berdasarkan Nilai Maksimal Kuis

## 1. Ringkasan & Tujuan
Fitur ini menambahkan opsi penentuan **Nilai Maksimal Kuis** (misal: 100) pada form generator AI, kemudian secara otomatis menghitung dan membagi bobot poin ke setiap butir soal Pilihan Ganda dan Essay sehingga akumulasi bobot butir soal pas bernilai tepat sama dengan nilai maksimal yang ditentukan.

## 2. Aturan Pembagian Bobot
1. **Hanya Pilihan Ganda ($jumlahEssay == 0$):**
   - 100% nilai maksimal dialokasikan untuk PG.
   - Bobot dasar = `intdiv(nilai_maksimal, jumlah_pg)`.
   - Sisa pembagian (modulo) didistribusikan ke soal-soal awal agar total genap.
2. **Hanya Essay ($jumlahPg == 0$):**
   - 100% nilai maksimal dialokasikan untuk Essay.
   - Bobot dasar = `intdiv(nilai_maksimal, jumlah_essay)`.
   - Sisa pembagian (modulo) didistribusikan ke soal-soal awal.
3. **Kombinasi Pilihan Ganda & Essay ($jumlahPg > 0$ dan $jumlahEssay > 0$):**
   - Terdapat input persentase `porsi_pg` (default 60%).
   - Total bobot PG = `round(nilai_maksimal * (porsi_pg / 100))`.
   - Total bobot Essay = `nilai_maksimal - total_bobot_pg`.
   - Masing-masing didistribusikan ke butir PG dan Essay secara integer dengan distribusi sisa modulo.

## 3. Komponen & Alur
- **`AiQuestionService`**:
  - Method `calculateWeights(int $nilaiMaksimal, int $jumlahPg, int $jumlahEssay, int $porsiPg = 60): array`
  - `generateQuestions`: Menggunakan bobot terhitung untuk tiap soal PG dan Essay saat sanitasi akhir (menjamin akurasi 100% tanpa risiko salah hitung matematika dari AI).
- **`QuestionController`**:
  - Validasi parameter `nilai_maksimal` (min: 10, max: 1000, default: 100) dan `porsi_pg` (min: 10, max: 90, default: 60).
- **`ai-form.blade.php`**:
  - Input `nilai_maksimal` dan `porsi_pg`.
  - Kalkulator interaktif instan (live preview estimasi bobot per butir sebelum generate).
- **`ai-preview.blade.php`**:
  - Indikator live total bobot soal terpilih vs target nilai maksimal.
