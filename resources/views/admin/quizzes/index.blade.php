<x-layouts.admin judul="Kuis" keterangan="Setiap kuis memiliki soalnya sendiri yang dipakai di sesi.">
    <x-slot:aksi>
        <a href="{{ route('admin.quizzes.create') }}" class="btn btn-primary">
            <x-icon name="plus" :size="20" />
            Buat kuis
        </a>
    </x-slot:aksi>

    @if ($quizzes->isEmpty())
        <x-empty-state
            judul="Belum ada kuis"
            keterangan="Susun kuis dari soal di bank soal. Satu kuis bisa dipakai di beberapa sesi."
        >
            <a href="{{ route('admin.quizzes.create') }}" class="btn btn-primary mt-2">Buat kuis pertama</a>
        </x-empty-state>
    @else
        <div class="grid gap-5 md:grid-cols-2">
            @foreach ($quizzes as $kuis)
                <article class="card card-hover">
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

        <div class="mt-6">{{ $quizzes->links() }}</div>
    @endif
</x-layouts.admin>
