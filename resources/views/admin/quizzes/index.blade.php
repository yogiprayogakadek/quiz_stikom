<x-layouts.admin judul="Kuis" keterangan="Setiap kuis memiliki soalnya sendiri yang dipakai di sesi.">
    <x-slot:aksi>
        <a href="{{ route('admin.quizzes.create') }}" class="btn btn-primary">
            <x-icon name="plus" :size="20" />
            Buat kuis
        </a>
    </x-slot:aksi>

    <form method="GET" action="{{ route('admin.quizzes.index') }}" class="mb-5 flex flex-wrap items-end gap-3">
        <div>
            <label for="kelas" class="label text-sm">Kelas</label>
            <select id="kelas" name="kelas" class="input !w-auto !min-h-[42px] !py-2 text-sm" onchange="this.form.submit()">
                <option value="0">Semua kelas</option>
                @foreach ($kelasList as $kelas)
                    <option value="{{ $kelas->id }}" @selected($kelasFilter === $kelas->id)>{{ $kelas->nama }}</option>
                @endforeach
            </select>
        </div>
        <noscript>
            <button type="submit" class="btn btn-secondary">Terapkan</button>
        </noscript>
        @if ($kelasFilter > 0)
            <a href="{{ route('admin.quizzes.index') }}" class="btn btn-ghost">Reset kelas</a>
        @endif
    </form>

    @if ($quizzes->isEmpty())
        <x-empty-state
            :judul="$kelasFilter > 0 ? 'Tidak ada kuis di kelas ini' : 'Belum ada kuis'"
            :keterangan="$kelasFilter > 0 ? 'Pilih kelas lain atau reset filter kelas.' : 'Susun kuis dari soal di bank soal. Satu kuis bisa dipakai di beberapa sesi.'"
        >
            @if ($kelasFilter > 0)
                <a href="{{ route('admin.quizzes.index') }}" class="btn btn-secondary mt-2">Reset filter kelas</a>
            @else
                <a href="{{ route('admin.quizzes.create') }}" class="btn btn-primary mt-2">Buat kuis pertama</a>
            @endif
        </x-empty-state>
    @else
        <x-data-table
            search-placeholder="Cari judul kuis atau kelas..."
            :per-page="12"
            :per-page-options="[6, 12, 24, 48]"
            empty-message="Tidak ada kuis yang cocok dengan pencarian."
        >
            <div class="grid gap-5 md:grid-cols-2">
                @foreach ($quizzes as $kuis)
                    <article
                        data-item
                        data-item-key="kuis-{{ $kuis->id }}"
                        data-search="{{ strtolower($kuis->judul.' '.($kuis->kelas?->nama ?? '').' '.($kuis->is_percobaan ? 'percobaan' : '')) }}"
                        class="card card-hover"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($kuis->kelas)
                                <span class="pill pill-brand font-bold">{{ $kuis->kelas->nama }}</span>
                            @endif
                            <span class="pill pill-neutral">{{ $kuis->questions_count }} soal</span>
                            @if ($kuis->jumlah_soal !== null)
                                <span class="pill pill-sun">{{ $kuis->jumlah_soal }} dipakai</span>
                            @endif
                            @if ($kuis->acak_opsi)
                                <span class="pill pill-leaf text-xs">Acak Opsi</span>
                            @endif
                            @if ($kuis->auto_grade_essay)
                                <span class="pill pill-sun text-xs">Nilai AI otomatis</span>
                            @endif
                            @if ($kuis->is_percobaan)
                                <span class="pill pill-neutral text-xs">Percobaan</span>
                            @endif
                            @if ($kuis->remidi_nilai_min !== null)
                                <span class="pill pill-brand text-xs">KKM {{ $kuis->remidi_nilai_min }}</span>
                            @endif
                        </div>

                        <h2 class="mt-3 text-2xl">{{ $kuis->judul }}</h2>
                        <p class="mt-1 text-base text-ink-muted">{{ $kuis->sessions_count }} sesi memakai kuis ini.</p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('admin.quizzes.questions.index', $kuis) }}" class="btn btn-primary">
                                <x-icon name="pencil" :size="18" />
                                Kelola soal ({{ $kuis->questions_count }})
                            </a>
                            <a href="{{ route('admin.quizzes.edit', $kuis) }}" class="btn btn-secondary">Edit kuis</a>
                            @if ($kuis->sessions_count === 0)
                                <form method="POST" action="{{ route('admin.quizzes.destroy', $kuis) }}" onsubmit="return confirm('Hapus kuis ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost text-coral-deep">Hapus</button>
                                </form>
                            @else
                                <span class="self-center text-sm text-ink-subtle">Dipakai sesi, tidak bisa dihapus</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </x-data-table>
    @endif
</x-layouts.admin>
