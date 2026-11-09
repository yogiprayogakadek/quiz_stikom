<x-layouts.admin :judul="'Generate Soal AI: ' . $quiz->judul" keterangan="Buat soal kuis secara otomatis berdasarkan silabus/RPS dan topik kajian menggunakan 9router.">
    <x-slot:aksi>
        <a href="{{ route('admin.quizzes.questions.index', $quiz) }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="18" />
            <span>Kembali ke Soal</span>
        </a>
    </x-slot:aksi>

    @if ($errors->has('ai_error'))
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <div class="flex items-center gap-2 font-semibold">
                <x-icon name="alert" :size="18" class="text-red-600" />
                <span>Gagal Generate Soal</span>
            </div>
            <p class="mt-1">{{ $errors->first('ai_error') }}</p>
        </div>
    @endif

    @if ($errors->has('soal') || $errors->has('jumlah'))
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <div class="flex items-center gap-2 font-semibold">
                <x-icon name="alert" :size="18" class="text-amber-600" />
                <span>Perhatian</span>
            </div>
            <p class="mt-1">{{ $errors->first('soal') ?: $errors->first('jumlah') }}</p>
        </div>
    @endif

    <div class="card max-w-4xl">
        <form id="ai-generate-form" method="POST" action="{{ route('admin.quizzes.questions.ai.generate', $quiz) }}" class="space-y-6">
            @csrf

            <!-- Model Selection -->
            <div>
                <div class="flex items-center justify-between">
                    <label for="model" class="label">Pilih Model AI (9router)</label>
                    <a href="{{ route('admin.quizzes.questions.ai.form', ['quiz' => $quiz, 'refresh' => 1]) }}" class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-800">
                        <x-icon name="clock" :size="14" />
                        <span>Perbarui Daftar Model</span>
                    </a>
                </div>
                <div class="mt-1">
                    <select id="model" name="model" class="input" required>
                        @foreach ($models as $provider => $modelList)
                            <optgroup label="{{ $provider }}">
                                @foreach ($modelList as $m)
                                    <option value="{{ $m }}" @selected(old('model', config('services.ai.default_model')) === $m)>
                                        {{ $m }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    Model terdeteksi otomatis dari gateway 9router. Pilih model penalaran tinggi untuk soal analisis/studi kasus.
                </p>
            </div>

            <!-- Topik Kajian -->
            <div>
                <label for="topik" class="label">Topik / Materi Spesifik</label>
                <input
                    type="text"
                    id="topik"
                    name="topik"
                    value="{{ old('topik') }}"
                    class="input @error('topik') border-red-500 @enderror"
                    placeholder="Contoh: Inheritance, Polimorfisme, dan Enkapsulasi dalam OOP"
                    required
                >
                @error('topik')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Teks RPS / Silabus -->
            <div>
                <label for="rps_text" class="label">Teks RPS / Capaian Pembelajaran (CPMK / Sub-CPMK)</label>
                <textarea
                    id="rps_text"
                    name="rps_text"
                    rows="6"
                    class="input @error('rps_text') border-red-500 @enderror font-mono text-sm"
                    placeholder="Salin potongan teks silabus atau CPMK mata kuliah terkait di sini..."
                    required
                >{{ old('rps_text') }}</textarea>
                <div class="mt-1 flex items-center justify-between text-xs text-slate-500">
                    <span>Tempelkan potongan materi/indikator dari RPS. Tidak perlu mengunggah seluruh dokumen.</span>
                    <span id="char-count">0 karakter</span>
                </div>
                @error('rps_text')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Parameter Soal (Grid) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="jumlah_pg" class="label">Jumlah Pilihan Ganda</label>
                    <input
                        type="number"
                        id="jumlah_pg"
                        name="jumlah_pg"
                        value="{{ old('jumlah_pg', 5) }}"
                        min="0"
                        max="50"
                        class="input @error('jumlah_pg') border-red-500 @enderror"
                        required
                    >
                    @error('jumlah_pg')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="jumlah_essay" class="label">Jumlah Essay</label>
                    <input
                        type="number"
                        id="jumlah_essay"
                        name="jumlah_essay"
                        value="{{ old('jumlah_essay', 1) }}"
                        min="0"
                        max="20"
                        class="input @error('jumlah_essay') border-red-500 @enderror"
                        required
                    >
                    @error('jumlah_essay')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="level" class="label">Taksonomi Bloom</label>
                    <select id="level" name="level" class="input">
                        <option value="C1 - Mengingat" @selected(old('level') === 'C1 - Mengingat')>C1 - Mengingat (Dasar)</option>
                        <option value="C2 - Memahami" @selected(old('level') === 'C2 - Memahami')>C2 - Memahami (Konseptual)</option>
                        <option value="C3 - Menerapkan" @selected(old('level', 'C3 - Menerapkan') === 'C3 - Menerapkan')>C3 - Menerapkan (Aplikasi)</option>
                        <option value="C4 - Menganalisis" @selected(old('level') === 'C4 - Menganalisis')>C4 - Menganalisis (Kritis)</option>
                        <option value="C5 - Mengevaluasi" @selected(old('level') === 'C5 - Mengevaluasi')>C5 - Mengevaluasi (Penilaian)</option>
                        <option value="C6 - Mencipta" @selected(old('level') === 'C6 - Mencipta')>C6 - Mencipta (Sintesis)</option>
                    </select>
                </div>
            </div>

            <!-- Tombol Aksi -->
            <div class="flex items-center justify-between border-t border-slate-200 pt-5">
                <a href="{{ route('admin.quizzes.questions.index', $quiz) }}" class="btn btn-secondary">
                    Batal
                </a>
                <button type="submit" id="btn-submit" class="btn btn-primary">
                    <x-icon name="sparkles" :size="18" />
                    <span id="btn-text">Generate Soal dengan AI</span>
                </button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rpsText = document.getElementById('rps_text');
            const charCount = document.getElementById('char-count');
            const form = document.getElementById('ai-generate-form');
            const btnSubmit = document.getElementById('btn-submit');
            const btnText = document.getElementById('btn-text');

            if (rpsText && charCount) {
                const updateCount = () => {
                    charCount.textContent = rpsText.value.length + ' karakter';
                };
                rpsText.addEventListener('input', updateCount);
                updateCount();
            }

            if (form && btnSubmit && btnText) {
                form.addEventListener('submit', function () {
                    btnSubmit.disabled = true;
                    btnText.textContent = 'Menghubungi AI... Mohon tunggu';
                });
            }
        });
    </script>
</x-layouts.admin>
