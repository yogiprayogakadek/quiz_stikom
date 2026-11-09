@php
    $judulHalaman = isset($quiz) ? 'Soal Kuis: ' . $quiz->judul : 'Bank Soal';
    $ruteIndex = isset($quiz) ? route('admin.quizzes.questions.index', $quiz) : route('admin.quizzes.index');
    $ruteCreate = isset($quiz) ? route('admin.quizzes.questions.create', $quiz) : route('admin.quizzes.index');
    $ruteImport = isset($quiz) ? route('admin.quizzes.questions.import', $quiz) : route('admin.quizzes.index');
    $ruteExport = isset($quiz) ? route('admin.quizzes.questions.export', $quiz) : '#';
    $ruteAi = isset($quiz) ? route('admin.quizzes.questions.ai.form', $quiz) : '#';
@endphp

<x-layouts.admin :judul="$judulHalaman" :keterangan="isset($quiz) ? 'Daftar soal khusus untuk kuis ini. Soal pilihan ganda dinilai otomatis, essay dinilai manual.' : 'Kelola soal per kuis.'">
    <x-slot:aksi>
        @if (isset($quiz))
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.quizzes.index') }}" class="btn btn-ghost">
                    <x-icon name="arrow-left" :size="18" />
                    <span>Kembali</span>
                </a>
                <a href="{{ $ruteAi }}" class="btn btn-secondary">
                    <x-icon name="sparkles" :size="18" />
                    <span>Generate AI</span>
                </a>
                <a href="{{ $ruteImport }}" class="btn btn-secondary">
                    <x-icon name="upload" :size="18" />
                    <span>Impor CSV</span>
                </a>
                <a href="{{ $ruteExport }}" class="btn btn-secondary">
                    <x-icon name="download" :size="18" />
                    <span>Ekspor CSV</span>
                </a>
                <a href="{{ $ruteCreate }}" class="btn btn-primary">
                    <x-icon name="plus" :size="18" />
                    <span>Tambah Soal</span>
                </a>
            </div>
        @else
            <a href="{{ route('admin.quizzes.index') }}" class="btn btn-primary">
                Pilih kuis
            </a>
        @endif
    </x-slot:aksi>

    <form method="GET" action="{{ $ruteIndex }}" class="flex flex-col gap-3 sm:flex-row">
        <div class="flex-1">
            <label for="cari" class="sr-only">Cari soal</label>
            <input id="cari" name="q" type="search" value="{{ $cari }}" class="input" placeholder="Cari teks soal">
        </div>
        <button type="submit" class="btn btn-secondary">Cari</button>
        @if ($cari !== '')
            <a href="{{ $ruteIndex }}" class="btn btn-ghost">Bersihkan</a>
        @endif
    </form>

    @if ($questions->isEmpty())
        <x-empty-state
            class="mt-6"
            :judul="$cari !== '' ? 'Soal tidak ditemukan' : 'Kuis ini belum memiliki soal'"
            :keterangan="$cari !== '' ? 'Coba kata kunci lain.' : 'Tambahkan soal pilihan ganda atau essay khusus untuk kuis ini.'"
        >
            @if ($cari === '' && isset($quiz))
                <div class="mt-4 flex flex-wrap justify-center gap-3">
                    <a href="{{ $ruteCreate }}" class="btn btn-primary">Tambah soal pertama</a>
                    <a href="{{ $ruteAi }}" class="btn btn-secondary">
                        <x-icon name="sparkles" :size="18" />
                        <span>Generate AI</span>
                    </a>
                    <a href="{{ $ruteImport }}" class="btn btn-secondary">Impor via CSV</a>
                </div>
            @endif
        </x-empty-state>
    @else
        <ul class="mt-6 space-y-3">
            @foreach ($questions as $soal)
                @php 
                    $soalTerkunci = in_array($soal->id, $terkunci, true);
                    $ruteEdit = isset($quiz) ? route('admin.quizzes.questions.edit', [$quiz, $soal]) : '#';
                    $ruteHapus = isset($quiz) ? route('admin.quizzes.questions.destroy', [$quiz, $soal]) : '#';
                @endphp
                <li class="card">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="pill {{ $soal->tipe === 'essay' ? 'pill-sun' : 'pill-leaf' }}">{{ $soal->tipe === 'essay' ? 'Essay' : 'Pilihan ganda' }}</span>
                                <span class="pill pill-neutral">Bobot {{ $soal->bobot }}</span>
                                @if ($soal->tipe !== 'essay')
                                    <span class="pill pill-neutral">{{ $soal->options_count }} opsi</span>
                                @endif
                                <span class="pill pill-brand">{{ $soal->durasi_detik }} detik</span>
                                @if ($soalTerkunci)
                                    <span class="pill pill-sun">
                                        <x-icon name="lock" :size="14" />
                                        Terkunci di sesi
                                    </span>
                                @endif
                            </div>
                            <p class="mt-3 text-lg font-bold text-ink">{{ \Illuminate\Support\Str::limit($soal->teks, 180) }}</p>
                            @if ($soal->pembahasan)
                                <p class="mt-1 text-base text-ink-muted">Ada pembahasan</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            @if ($soalTerkunci)
                                <span class="text-sm text-ink-subtle">Dipakai sesi yang sudah dibuka</span>
                            @else
                                <a href="{{ $ruteEdit }}" class="btn btn-secondary">Edit</a>
                                <form method="POST" action="{{ $ruteHapus }}" onsubmit="return confirm('Hapus soal ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost text-coral-deep">Hapus</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $questions->links() }}</div>
    @endif
</x-layouts.admin>
