<x-layouts.admin :judul="'Ambil Soal dari Kuis Lain: ' . $quiz->judul" keterangan="Salin butir soal terpilih dari kuis lain di workspace ini lengkap dengan gambar, tabel, kode, dan rubrik.">
    <x-slot:aksi>
        <a href="{{ route('admin.quizzes.questions.index', $quiz) }}" class="btn btn-ghost min-h-11">
            <x-icon name="arrow-left" :size="20" />
            <span>Kembali ke Soal Kuis</span>
        </a>
    </x-slot:aksi>

    <section class="card" aria-label="Filter bank soal">
        <form method="GET" action="{{ route('admin.quizzes.questions.bank', $quiz) }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            <div>
                <label for="sumber_kuis" class="label">Kuis sumber</label>
                <select id="sumber_kuis" name="sumber_kuis" class="input">
                    <option value="">Semua kuis lain ({{ $otherQuizzes->count() }})</option>
                    @foreach ($otherQuizzes as $itemKuis)
                        <option value="{{ $itemKuis->id }}" @selected((int) $sumberQuizId === (int) $itemKuis->id)>
                            {{ $itemKuis->judul }} ({{ $itemKuis->questions_count }} soal)
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="tipe_soal" class="label">Tipe soal</label>
                <select id="tipe_soal" name="tipe" class="input">
                    <option value="">Semua tipe</option>
                    <option value="pilihan_ganda" @selected($tipe === 'pilihan_ganda')>Pilihan ganda</option>
                    <option value="pilihan_majemuk" @selected($tipe === 'pilihan_majemuk')>Pilihan majemuk</option>
                    <option value="isian_singkat" @selected($tipe === 'isian_singkat')>Isian singkat</option>
                    <option value="essay" @selected($tipe === 'essay')>Essay</option>
                </select>
            </div>

            <div>
                <label for="cari_soal" class="label">Kata kunci teks soal</label>
                <input id="cari_soal" type="search" name="q" value="{{ $cari }}" class="input" placeholder="Contoh: subnetting, SQL, HTTP...">
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-secondary min-h-11">Terapkan filter</button>
                @if ($sumberQuizId !== null || $tipe !== '' || $cari !== '')
                    <a href="{{ route('admin.quizzes.questions.bank', $quiz) }}" class="btn btn-ghost min-h-11">Reset</a>
                @endif
            </div>
        </form>
    </section>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-2xl border border-coral-deep bg-coral-soft p-4 text-sm font-semibold text-ink">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($questions->isEmpty())
        <x-empty-state
            class="mt-6"
            judul="Tidak ada soal yang ditemukan"
            :keterangan="$otherQuizzes->isEmpty() ? 'Belum ada kuis lain di workspace ini yang memiliki soal untuk disalin.' : 'Ubah filter pencarian di atas untuk melihat daftar soal dari kuis lain.'"
        >
            <div class="mt-4 flex flex-wrap justify-center gap-3">
                @if ($sumberQuizId !== null || $tipe !== '' || $cari !== '')
                    <a href="{{ route('admin.quizzes.questions.bank', $quiz) }}" class="btn btn-secondary min-h-11">Tampilkan semua soal</a>
                @endif
                <a href="{{ route('admin.quizzes.questions.create', $quiz) }}" class="btn btn-primary min-h-11">Tulis soal baru</a>
            </div>
        </x-empty-state>
    @else
        <form
            method="POST"
            action="{{ route('admin.quizzes.questions.bank.copy', $quiz) }}"
            x-data="{
                terpilih: [],
                semua: false,
                toggleSemua() {
                    this.semua = !this.semua;
                    this.terpilih = this.semua
                        ? Array.from(this.$root.querySelectorAll('input[data-bank-item]')).map((el) => el.value)
                        : [];
                }
            }"
            class="mt-6 space-y-4"
        >
            @csrf

            <div class="card flex flex-wrap items-center justify-between gap-3 py-3.5">
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" @click="toggleSemua()" class="btn btn-secondary min-h-11" :aria-pressed="semua">
                        <span x-text="semua ? 'Batal pilih halaman ini' : 'Pilih semua di halaman ini'">Pilih semua di halaman ini</span>
                    </button>
                    <span class="text-sm font-semibold text-ink-muted">
                        <span class="font-bold text-ink" x-text="terpilih.length">0</span> soal dipilih
                    </span>
                </div>

                <button type="submit" class="btn btn-primary min-h-11" :disabled="terpilih.length === 0">
                    <x-icon name="copy" :size="18" />
                    <span>Salin ke {{ \Illuminate\Support\Str::limit($quiz->judul, 28) }}</span>
                </button>
            </div>

            <ul class="space-y-3">
                @foreach ($questions as $soal)
                    @php
                        $labelTipe = match ($soal->tipe) {
                            'essay' => 'Essay',
                            'pilihan_majemuk' => 'Pilihan majemuk',
                            'isian_singkat' => 'Isian singkat',
                            default => 'Pilihan ganda',
                        };
                        $adaLampiran = ! empty($soal->content_blocks_json) || $soal->media_count > 0;
                    @endphp
                    <li class="card">
                        <label class="flex cursor-pointer items-start gap-3.5">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-outline bg-surface">
                                <input
                                    type="checkbox"
                                    name="question_ids[]"
                                    value="{{ $soal->id }}"
                                    data-bank-item
                                    x-model="terpilih"
                                    class="h-5 w-5 rounded border-outline-strong text-brand"
                                >
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="pill {{ $soal->tipe === 'essay' ? 'pill-sun' : 'pill-leaf' }}">{{ $labelTipe }}</span>
                                    <span class="pill pill-neutral">Bobot {{ $soal->bobot }}</span>
                                    @if ($soal->quiz)
                                        <span class="pill pill-brand">Sumber: {{ $soal->quiz->judul }}</span>
                                    @endif
                                    @if ($adaLampiran)
                                        <span class="pill pill-neutral">Memuat lampiran/media</span>
                                    @endif
                                </span>

                                <span class="mt-2.5 block text-base font-bold text-ink">{{ $soal->teks }}</span>

                                @if ($soal->tipe === 'isian_singkat' && $soal->options->isNotEmpty())
                                    <span class="mt-2 block text-sm text-ink-muted">
                                        Kunci diterima: <strong class="text-leaf-deep">{{ $soal->options->where('is_correct', true)->pluck('teks')->implode(' / ') }}</strong>
                                    </span>
                                @elseif ($soal->options->isNotEmpty())
                                    <span class="mt-2 block text-sm text-ink-muted">
                                        @foreach ($soal->options as $idx => $op)
                                            <span class="{{ $op->is_correct ? 'font-bold text-leaf-deep' : '' }}">
                                                {{ chr(65 + $idx) }}. {{ \Illuminate\Support\Str::limit($op->teks, 60) }}{{ $loop->last ? '' : ' · ' }}
                                            </span>
                                        @endforeach
                                    </span>
                                @endif
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">
                {{ $questions->links() }}
            </div>
        </form>
    @endif
</x-layouts.admin>
