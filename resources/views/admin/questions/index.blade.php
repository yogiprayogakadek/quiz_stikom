<x-layouts.admin judul="Bank soal" keterangan="Setiap soal punya 2 sampai 6 opsi dan tepat satu jawaban benar.">
    <x-slot:aksi>
        <a href="{{ route('admin.questions.import') }}" class="btn btn-secondary">
            <x-icon name="upload" :size="20" />
            Impor CSV
        </a>
        <a href="{{ route('admin.questions.create') }}" class="btn btn-primary">
            <x-icon name="plus" :size="20" />
            Tambah soal
        </a>
    </x-slot:aksi>

    <form method="GET" action="{{ route('admin.questions.index') }}" class="flex flex-col gap-3 sm:flex-row">
        <div class="flex-1">
            <label for="cari" class="sr-only">Cari soal</label>
            <input id="cari" name="q" type="search" value="{{ $cari }}" class="input" placeholder="Cari teks soal">
        </div>
        <button type="submit" class="btn btn-secondary">Cari</button>
        @if ($cari !== '')
            <a href="{{ route('admin.questions.index') }}" class="btn btn-ghost">Bersihkan</a>
        @endif
    </form>

    @if ($questions->isEmpty())
        <x-empty-state
            class="mt-6"
            :judul="$cari !== '' ? 'Soal tidak ditemukan' : 'Bank soal masih kosong'"
            :keterangan="$cari !== '' ? 'Coba kata kunci lain.' : 'Tulis soal pertama, lalu susun kuis dari soal yang sudah ada.'"
        >
            @if ($cari === '')
                <a href="{{ route('admin.questions.create') }}" class="btn btn-primary mt-2">Tambah soal pertama</a>
            @endif
        </x-empty-state>
    @else
        <ul class="mt-6 space-y-3">
            @foreach ($questions as $soal)
                @php $soalTerkunci = in_array($soal->id, $terkunci, true); @endphp
                <li class="card">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="pill pill-neutral">{{ $soal->options_count }} opsi</span>
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
                                <a href="{{ route('admin.questions.edit', $soal) }}" class="btn btn-secondary">Edit</a>
                                <form method="POST" action="{{ route('admin.questions.destroy', $soal) }}" onsubmit="return confirm('Hapus soal ini?')">
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
