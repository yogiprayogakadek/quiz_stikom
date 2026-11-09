@php
    $totalPg = count(array_filter($questions, fn ($q) => ($q['tipe'] ?? '') === 'pilihan_ganda'));
    $totalEssay = count(array_filter($questions, fn ($q) => ($q['tipe'] ?? '') === 'essay'));
@endphp

<x-layouts.admin :judul="'Pratinjau Hasil AI: ' . $quiz->judul" keterangan="Tinjau, sunting redaksi atau kunci jawaban, dan pilih soal yang ingin disimpan ke dalam kuis.">
    <x-slot:aksi>
        <a href="{{ route('admin.quizzes.questions.ai.form', $quiz) }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="18" />
            <span>Generate Ulang</span>
        </a>
    </x-slot:aksi>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="rounded-lg bg-indigo-50 p-2 text-indigo-700">
                <x-icon name="sparkles" :size="20" />
            </div>
            <div>
                <h2 class="text-sm font-semibold text-slate-800">
                    Hasil Generate ({{ count($questions) }} butir soal)
                </h2>
                <p class="text-xs text-slate-500">
                    {{ $totalPg }} Pilihan Ganda, {{ $totalEssay }} Essay • Model: <span class="font-mono text-slate-700">{{ $params['model'] ?? '-' }}</span>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" id="btn-select-all" class="btn btn-ghost text-xs">
                Pilih Semua
            </button>
            <button type="button" id="btn-deselect-all" class="btn btn-ghost text-xs">
                Batalkan Semua
            </button>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.quizzes.questions.ai.store', $quiz) }}">
        @csrf

        <div class="space-y-6">
            @foreach ($questions as $qIndex => $q)
                @php
                    $isPg = ($q['tipe'] ?? '') === 'pilihan_ganda';
                @endphp
                <div class="question-card card border transition-colors duration-150" id="card-{{ $qIndex }}">
                    <!-- Card Header -->
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-3">
                            <label class="inline-flex cursor-pointer items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="questions[{{ $qIndex }}][selected]"
                                    value="1"
                                    class="question-checkbox rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    checked
                                    data-index="{{ $qIndex }}"
                                >
                                <span class="text-sm font-semibold text-slate-800">
                                    Soal #{{ $qIndex + 1 }}
                                </span>
                            </label>
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $isPg ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                                {{ $isPg ? 'Pilihan Ganda' : 'Essay' }}
                            </span>
                        </div>

                        <div class="flex items-center gap-3 text-xs text-slate-600">
                            <div class="flex items-center gap-1">
                                <label for="bobot-{{ $qIndex }}">Bobot:</label>
                                <input
                                    type="number"
                                    id="bobot-{{ $qIndex }}"
                                    name="questions[{{ $qIndex }}][bobot]"
                                    value="{{ $q['bobot'] ?? ($isPg ? 1 : 5) }}"
                                    min="1"
                                    class="input h-7 w-16 px-1.5 py-0.5 text-center text-xs"
                                    required
                                >
                            </div>
                            <div class="flex items-center gap-1">
                                <label for="durasi-{{ $qIndex }}">Durasi (dtk):</label>
                                <input
                                    type="number"
                                    id="durasi-{{ $qIndex }}"
                                    name="questions[{{ $qIndex }}][durasi_detik]"
                                    value="{{ $q['durasi_detik'] ?? ($isPg ? 60 : 120) }}"
                                    min="15"
                                    step="5"
                                    class="input h-7 w-20 px-1.5 py-0.5 text-center text-xs"
                                    required
                                >
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="questions[{{ $qIndex }}][tipe]" value="{{ $q['tipe'] }}">

                    <!-- Card Body -->
                    <div class="mt-4 space-y-4">
                        <!-- Teks Soal -->
                        <div>
                            <label for="teks-{{ $qIndex }}" class="label text-xs">Pertanyaan</label>
                            <textarea
                                id="teks-{{ $qIndex }}"
                                name="questions[{{ $qIndex }}][teks]"
                                rows="3"
                                class="input font-medium text-slate-900"
                                required
                            >{{ $q['teks'] }}</textarea>
                        </div>

                        @if ($isPg)
                            <!-- Opsi Pilihan Ganda -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="label text-xs">Pilihan Jawaban (Tandai radio button untuk kunci jawaban benar)</span>
                                </div>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    @foreach ($q['opsi'] ?? [] as $oIndex => $opsi)
                                        <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50/50 p-2 focus-within:border-indigo-500">
                                            <input
                                                type="radio"
                                                name="questions[{{ $qIndex }}][correct]"
                                                value="{{ $oIndex }}"
                                                @checked(!empty($opsi['is_correct']))
                                                class="text-indigo-600 focus:ring-indigo-500"
                                                required
                                            >
                                            <input
                                                type="text"
                                                name="questions[{{ $qIndex }}][opsi][{{ $oIndex }}][teks]"
                                                value="{{ $opsi['teks'] ?? '' }}"
                                                class="input h-8 flex-1 bg-white text-xs"
                                                placeholder="Teks opsi..."
                                                required
                                            >
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <!-- Kunci / Rubrik Essay -->
                            <div>
                                <label for="kunci-essay-{{ $qIndex }}" class="label text-xs">Kata Kunci / Rubrik Jawaban Essay</label>
                                <textarea
                                    id="kunci-essay-{{ $qIndex }}"
                                    name="questions[{{ $qIndex }}][kunci_essay]"
                                    rows="2"
                                    class="input text-xs text-slate-800"
                                    placeholder="Kata kunci penting untuk panduan penilaian manual..."
                                >{{ $q['kunci_essay'] ?? '' }}</textarea>
                            </div>
                        @endif

                        <!-- Pembahasan -->
                        <div>
                            <label for="pembahasan-{{ $qIndex }}" class="label text-xs text-slate-500">Pembahasan / Penjelasan Jawaban (Opsional)</label>
                            <textarea
                                id="pembahasan-{{ $qIndex }}"
                                name="questions[{{ $qIndex }}][pembahasan]"
                                rows="2"
                                class="input text-xs text-slate-600"
                                placeholder="Pembahasan yang akan tampil saat evaluasi..."
                            >{{ $q['pembahasan'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Sticky Bottom Bar -->
        <div class="sticky bottom-4 mt-8 flex items-center justify-between rounded-xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur">
            <div class="text-sm text-slate-700">
                <span id="selected-count" class="font-bold text-indigo-700">{{ count($questions) }}</span> dari {{ count($questions) }} soal terpilih
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.quizzes.questions.ai.form', $quiz) }}" class="btn btn-secondary">
                    Batalkan
                </a>
                <button type="submit" class="btn btn-primary">
                    <x-icon name="check" :size="18" />
                    <span>Simpan Soal ke Kuis</span>
                </button>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkboxes = document.querySelectorAll('.question-checkbox');
            const selectedCount = document.getElementById('selected-count');
            const btnSelectAll = document.getElementById('btn-select-all');
            const btnDeselectAll = document.getElementById('btn-deselect-all');

            const updateSelectedCount = () => {
                let count = 0;
                checkboxes.forEach(cb => {
                    const card = document.getElementById('card-' + cb.dataset.index);
                    if (cb.checked) {
                        count++;
                        if (card) card.classList.remove('opacity-50', 'bg-slate-50');
                    } else {
                        if (card) card.classList.add('opacity-50', 'bg-slate-50');
                    }
                });
                if (selectedCount) selectedCount.textContent = count;
            };

            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateSelectedCount);
            });

            if (btnSelectAll) {
                btnSelectAll.addEventListener('click', () => {
                    checkboxes.forEach(cb => cb.checked = true);
                    updateSelectedCount();
                });
            }

            if (btnDeselectAll) {
                btnDeselectAll.addEventListener('click', () => {
                    checkboxes.forEach(cb => cb.checked = false);
                    updateSelectedCount();
                });
            }

            updateSelectedCount();
        });
    </script>
</x-layouts.admin>
