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

    @if ($errors->has('ai_error'))
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <div class="flex items-center gap-2 font-semibold">
                <x-icon name="alert" :size="18" class="text-red-600" />
                <span>Gagal Generate Soal</span>
            </div>
            <p class="mt-1">{{ $errors->first('ai_error') }}</p>
        </div>
    @endif

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

            <!-- Jumlah Soal (Grid) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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

    <!-- Modal Progress Generator AI -->
    <div
        id="progress-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs transition-opacity duration-200"
        aria-modal="true"
        role="dialog"
    >
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
            <!-- Header Progress -->
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 animate-pulse">
                    <x-icon name="sparkles" :size="24" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-slate-900">
                        AI Sedang Merumuskan Soal
                    </h3>
                    <p id="progress-model-info" class="text-xs text-slate-500">
                        Memproses permintaan via 9router...
                    </p>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="mt-5">
                <div class="flex justify-between text-xs font-medium text-slate-600">
                    <span id="progress-percent-label">Memulai...</span>
                    <span id="progress-timer">0s</span>
                </div>
                <div class="mt-1.5 h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                    <div
                        id="progress-bar-fill"
                        class="h-full bg-indigo-600 transition-all duration-500 ease-out"
                        style="width: 10%"
                    ></div>
                </div>
            </div>

            <!-- Detailed Steps Activity List -->
            <div class="mt-6 space-y-3">
                <div class="progress-step flex items-start gap-3 text-xs" data-step="1">
                    <div class="step-icon mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-white font-bold">
                        1
                    </div>
                    <div>
                        <div class="step-title font-semibold text-slate-800">
                            Menghubungkan ke Gateway 9router
                        </div>
                        <div class="step-sub text-slate-500">
                            Mengirim parameter soal, topik, dan teks RPS.
                        </div>
                    </div>
                </div>

                <div class="progress-step flex items-start gap-3 text-xs opacity-40" data-step="2">
                    <div class="step-icon mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-600 font-bold">
                        2
                    </div>
                    <div>
                        <div class="step-title font-semibold text-slate-800">
                            Menganalisis Silabus & Taksonomi Bloom
                        </div>
                        <div class="step-sub text-slate-500">
                            Memetakan capaian pembelajaran ke tingkat kognitif yang dipilih.
                        </div>
                    </div>
                </div>

                <div class="progress-step flex items-start gap-3 text-xs opacity-40" data-step="3">
                    <div class="step-icon mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-600 font-bold">
                        3
                    </div>
                    <div>
                        <div class="step-title font-semibold text-slate-800">
                            Merumuskan Soal, Pilihan Distractor, & Kunci
                        </div>
                        <div class="step-sub text-slate-500">
                            Membuat 4 opsi pilihan ganda dan rubrik penilaian essay.
                        </div>
                    </div>
                </div>

                <div class="progress-step flex items-start gap-3 text-xs opacity-40" data-step="4">
                    <div class="step-icon mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-600 font-bold">
                        4
                    </div>
                    <div>
                        <div class="step-title font-semibold text-slate-800">
                            Validasi Struktur JSON & Lembar Pratinjau
                        </div>
                        <div class="step-sub text-slate-500">
                            Memastikan integritas format data sebelum ditampilkan ke halaman review.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Tip -->
            <div class="mt-6 rounded-lg bg-slate-50 p-3 text-center text-xs text-slate-500 border border-slate-100">
                Mohon jangan menutup atau me-refresh browser. Waktu proses berkisar antara 3 - 10 detik tergantung model AI yang digunakan.
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rpsText = document.getElementById('rps_text');
            const charCount = document.getElementById('char-count');
            const form = document.getElementById('ai-generate-form');
            const btnSubmit = document.getElementById('btn-submit');
            const btnText = document.getElementById('btn-text');
            const modal = document.getElementById('progress-modal');
            const progressBar = document.getElementById('progress-bar-fill');
            const percentLabel = document.getElementById('progress-percent-label');
            const timerLabel = document.getElementById('progress-timer');
            const modelSelect = document.getElementById('model');
            const modelInfo = document.getElementById('progress-model-info');

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

            // Form submit & progress modal
            if (form && btnSubmit && modal) {
                form.addEventListener('submit', function (e) {
                    // Check at least one bloom checkbox
                    const anyBloomChecked = Array.from(bloomCheckboxes).some(cb => cb.checked);
                    if (!anyBloomChecked) {
                        e.preventDefault();
                        alert('Silakan pilih minimal satu tingkat Taksonomi Bloom.');
                        return;
                    }

                    // Check total questions > 0
                    const pg = parseInt(document.getElementById('jumlah_pg').value) || 0;
                    const essay = parseInt(document.getElementById('jumlah_essay').value) || 0;
                    if (pg + essay < 1) {
                        e.preventDefault();
                        alert('Jumlah soal pilihan ganda atau essay minimal 1.');
                        return;
                    }

                    // Show progress modal
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    btnSubmit.disabled = true;

                    if (modelSelect && modelInfo) {
                        modelInfo.textContent = 'Model: ' + modelSelect.value;
                    }

                    let seconds = 0;
                    const timerInterval = setInterval(() => {
                        seconds++;
                        if (timerLabel) timerLabel.textContent = seconds + 's';
                    }, 1000);

                    // Step transitions based on elapsed time
                    const steps = document.querySelectorAll('.progress-step');

                    const setStepActive = (stepNum, percent, label) => {
                        if (progressBar) progressBar.style.width = percent + '%';
                        if (percentLabel) percentLabel.textContent = label;

                        steps.forEach(st => {
                            const n = parseInt(st.dataset.step);
                            const icon = st.querySelector('.step-icon');
                            if (n < stepNum) {
                                st.classList.remove('opacity-40');
                                if (icon) {
                                    icon.className = 'step-icon mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white font-bold text-xs';
                                    icon.innerHTML = '✓';
                                }
                            } else if (n === stepNum) {
                                st.classList.remove('opacity-40');
                                if (icon) {
                                    icon.className = 'step-icon mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-white font-bold text-xs animate-pulse';
                                    icon.innerHTML = n;
                                }
                            } else {
                                st.classList.add('opacity-40');
                                if (icon) {
                                    icon.className = 'step-icon mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-600 font-bold text-xs';
                                    icon.innerHTML = n;
                                }
                            }
                        });
                    };

                    setStepActive(1, 15, 'Menghubungkan ke 9router...');

                    setTimeout(() => {
                        setStepActive(2, 40, 'Menganalisis silabus RPS...');
                    }, 1500);

                    setTimeout(() => {
                        setStepActive(3, 70, 'Menyusun soal & kunci jawaban...');
                    }, 3500);

                    setTimeout(() => {
                        setStepActive(4, 90, 'Memvalidasi format JSON...');
                    }, 6000);
                });
            }
        });
    </script>
</x-layouts.admin>
