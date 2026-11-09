@php
    $selectedLevels = old('level', ['C2 - Memahami', 'C3 - Menerapkan']);
    if (!is_array($selectedLevels)) {
        $selectedLevels = [$selectedLevels];
    }

    $bloomOptions = [
        ['code' => 'C1 - Mengingat', 'label' => 'C1 - Mengingat', 'desc' => 'Mengingat kembali fakta, istilah, dan konsep dasar.'],
        ['code' => 'C2 - Memahami', 'label' => 'C2 - Memahami', 'desc' => 'Menjelaskan ide, prinsip, dan menerjemahkan konsep.'],
        ['code' => 'C3 - Menerapkan', 'label' => 'C3 - Menerapkan', 'desc' => 'Menggunakan pengetahuan dalam situasi atau kasus nyata.'],
        ['code' => 'C4 - Menganalisis', 'label' => 'C4 - Menganalisis', 'desc' => 'Membedakan komponen, korelasi, dan menganalisis masalah.'],
        ['code' => 'C5 - Mengevaluasi', 'label' => 'C5 - Mengevaluasi', 'desc' => 'Memberikan penilaian, kritik, dan justifikasi solusi.'],
        ['code' => 'C6 - Mencipta', 'label' => 'C6 - Mencipta', 'desc' => 'Merancang, menyusun hipotesis, atau solusi alternatif.'],
    ];
@endphp

<x-layouts.admin :judul="'Generate Soal AI: ' . $quiz->judul" keterangan="Buat soal kuis secara otomatis berdasarkan silabus/RPS dan topik kajian menggunakan 9router.">
    <x-slot:aksi>
        <a href="{{ route('admin.quizzes.questions.index', $quiz) }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="18" />
            <span>Kembali ke Soal</span>
        </a>
    </x-slot:aksi>

    @if ($errors->has('soal') || $errors->has('jumlah') || $errors->has('level'))
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <div class="flex items-center gap-2 font-semibold">
                <x-icon name="alert" :size="18" class="text-amber-600" />
                <span>Perhatian</span>
            </div>
            <p class="mt-1">{{ $errors->first('soal') ?: ($errors->first('jumlah') ?: $errors->first('level')) }}</p>
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

            <!-- Parameter Jumlah & Nilai Soal (Card Group) -->
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="jumlah_pg" class="label font-medium text-slate-800">Jumlah Pilihan Ganda</label>
                        <input
                            type="number"
                            id="jumlah_pg"
                            name="jumlah_pg"
                            value="{{ old('jumlah_pg', 5) }}"
                            min="0"
                            max="50"
                            class="input @error('jumlah_pg') border-red-500 @enderror bg-white"
                            required
                        >
                        @error('jumlah_pg')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="jumlah_essay" class="label font-medium text-slate-800">Jumlah Essay</label>
                        <input
                            type="number"
                            id="jumlah_essay"
                            name="jumlah_essay"
                            value="{{ old('jumlah_essay', 1) }}"
                            min="0"
                            max="20"
                            class="input @error('jumlah_essay') border-red-500 @enderror bg-white"
                            required
                        >
                        @error('jumlah_essay')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 pt-2 border-t border-slate-200/60">
                    <div>
                        <label for="nilai_maksimal" class="label font-medium text-slate-800">
                            Nilai Maksimal Kuis (Target Total)
                        </label>
                        <input
                            type="number"
                            id="nilai_maksimal"
                            name="nilai_maksimal"
                            value="{{ old('nilai_maksimal', 100) }}"
                            min="10"
                            max="1000"
                            class="input @error('nilai_maksimal') border-red-500 @enderror bg-white"
                            placeholder="100"
                            required
                        >
                        <p class="mt-1 text-xs text-slate-500">
                            Total nilai tertinggi peserta (umumnya 100). Bobot tiap soal akan dibagi otomatis hingga tepat jumlah ini.
                        </p>
                        @error('nilai_maksimal')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div id="porsi-wrapper">
                        <div class="flex items-center justify-between">
                            <label for="porsi_pg" class="label font-medium text-slate-800">
                                Porsi Nilai PG vs Essay
                            </label>
                            <span id="porsi-label" class="text-xs font-semibold text-indigo-700">60% PG / 40% Essay</span>
                        </div>
                        <input
                            type="range"
                            id="porsi_pg"
                            name="porsi_pg"
                            value="{{ old('porsi_pg', 60) }}"
                            min="10"
                            max="90"
                            step="5"
                            class="w-full accent-indigo-600 cursor-pointer mt-2"
                        >
                        <p class="mt-1 text-xs text-slate-500">
                            Geser untuk mengatur perbandingan nilai PG dan Essay.
                        </p>
                        @error('porsi_pg')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Live Estimasi Pembagian Bobot -->
                <div id="estimasi-bobot-box" class="rounded-lg bg-indigo-50/70 p-3 text-xs text-indigo-900 border border-indigo-100 flex items-start gap-2.5">
                    <x-icon name="sparkles" :size="16" class="text-indigo-600 shrink-0 mt-0.5" />
                    <div>
                        <span class="font-semibold text-indigo-950">Estimasi Pembagian Bobot:</span>
                        <div id="estimasi-detail" class="mt-0.5 text-indigo-800">
                            Menghitung...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Taksonomi Bloom (Pilih beberapa sekaligus) -->
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <label class="label font-semibold text-slate-900">
                            Tingkat Kognitif (Taksonomi Bloom)
                        </label>
                        <p class="text-xs text-slate-500">
                            Pilih satu atau beberapa tingkat kognitif untuk mendistribusikan variasi soal.
                        </p>
                    </div>

                    <div class="flex items-center gap-1.5 text-xs">
                        <button type="button" id="btn-bloom-all" class="rounded border border-slate-200 bg-white px-2 py-1 font-medium text-slate-700 hover:bg-slate-50">
                            Pilih Semua
                        </button>
                        <button type="button" id="btn-bloom-lots" class="rounded border border-slate-200 bg-white px-2 py-1 font-medium text-slate-700 hover:bg-slate-50">
                            C1-C3 (LOTS)
                        </button>
                        <button type="button" id="btn-bloom-hots" class="rounded border border-slate-200 bg-white px-2 py-1 font-medium text-slate-700 hover:bg-slate-50">
                            C4-C6 (HOTS)
                        </button>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($bloomOptions as $bloom)
                        @php
                            $checked = in_array($bloom['code'], $selectedLevels, true);
                        @endphp
                        <label class="relative flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-xs transition-all hover:border-indigo-300 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/30">
                            <input
                                type="checkbox"
                                name="level[]"
                                value="{{ $bloom['code'] }}"
                                class="bloom-checkbox mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                @checked($checked)
                            >
                            <div class="text-xs">
                                <span class="font-semibold text-slate-900">{{ $bloom['label'] }}</span>
                                <p class="mt-0.5 text-slate-500">{{ $bloom['desc'] }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('level')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
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

            // Quick select Bloom buttons
            const bloomCheckboxes = document.querySelectorAll('.bloom-checkbox');
            const btnAll = document.getElementById('btn-bloom-all');
            const btnLots = document.getElementById('btn-bloom-lots');
            const btnHots = document.getElementById('btn-bloom-hots');

            if (btnAll) {
                btnAll.addEventListener('click', function () {
                    bloomCheckboxes.forEach(cb => cb.checked = true);
                });
            }

            if (btnLots) {
                btnLots.addEventListener('click', function () {
                    bloomCheckboxes.forEach(cb => {
                        const val = cb.value;
                        cb.checked = val.startsWith('C1') || val.startsWith('C2') || val.startsWith('C3');
                    });
                });
            }

            if (btnHots) {
                btnHots.addEventListener('click', function () {
                    bloomCheckboxes.forEach(cb => {
                        const val = cb.value;
                        cb.checked = val.startsWith('C4') || val.startsWith('C5') || val.startsWith('C6');
                    });
                });
            }

            // Character count
            if (rpsText && charCount) {
                const updateCount = () => {
                    charCount.textContent = rpsText.value.length + ' karakter';
                };
                rpsText.addEventListener('input', updateCount);
                updateCount();
            }

            // Live weight distribution preview
            const inputPg = document.getElementById('jumlah_pg');
            const inputEssay = document.getElementById('jumlah_essay');
            const inputMax = document.getElementById('nilai_maksimal');
            const inputPorsi = document.getElementById('porsi_pg');
            const porsiWrapper = document.getElementById('porsi-wrapper');
            const porsiLabel = document.getElementById('porsi-label');
            const estimasiDetail = document.getElementById('estimasi-detail');

            const updateEstimasi = () => {
                const pg = parseInt(inputPg?.value) || 0;
                const essay = parseInt(inputEssay?.value) || 0;
                const max = parseInt(inputMax?.value) || 100;
                const porsiPg = parseInt(inputPorsi?.value) || 60;

                if (porsiLabel) {
                    porsiLabel.textContent = porsiPg + '% PG / ' + (100 - porsiPg) + '% Essay';
                }

                if (pg > 0 && essay > 0) {
                    if (porsiWrapper) porsiWrapper.style.display = 'block';
                    const targetPg = Math.round(max * (porsiPg / 100));
                    const targetEssay = max - targetPg;
                    const bobotPgRata = (targetPg / pg).toFixed(1).replace('.0', '');
                    const bobotEssayRata = (targetEssay / essay).toFixed(1).replace('.0', '');
                    if (estimasiDetail) {
                        estimasiDetail.innerHTML = `<strong>${pg} Pilihan Ganda</strong> (total ${targetPg} poin, ~${bobotPgRata} poin/soal) + <strong>${essay} Essay</strong> (total ${targetEssay} poin, ~${bobotEssayRata} poin/soal) = <strong>Total ${max} Poin</strong>.`;
                    }
                } else if (pg > 0 && essay === 0) {
                    if (porsiWrapper) porsiWrapper.style.display = 'none';
                    const bobotPgRata = (max / pg).toFixed(1).replace('.0', '');
                    if (estimasiDetail) {
                        estimasiDetail.innerHTML = `<strong>${pg} Pilihan Ganda</strong> (seluruh ${max} poin, ~${bobotPgRata} poin/soal).`;
                    }
                } else if (pg === 0 && essay > 0) {
                    if (porsiWrapper) porsiWrapper.style.display = 'none';
                    const bobotEssayRata = (max / essay).toFixed(1).replace('.0', '');
                    if (estimasiDetail) {
                        estimasiDetail.innerHTML = `<strong>${essay} Essay</strong> (seluruh ${max} poin, ~${bobotEssayRata} poin/soal).`;
                    }
                } else {
                    if (estimasiDetail) {
                        estimasiDetail.textContent = 'Masukkan jumlah soal pilihan ganda atau essay minimal 1.';
                    }
                }
            };

            [inputPg, inputEssay, inputMax, inputPorsi].forEach(el => {
                if (el) {
                    el.addEventListener('input', updateEstimasi);
                }
            });
            updateEstimasi();
        });
    </script>
</x-layouts.admin>
