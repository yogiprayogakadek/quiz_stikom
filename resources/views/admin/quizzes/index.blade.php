<x-layouts.admin judul="Kuis" keterangan="Kuis adalah kumpulan soal yang dipakai di sesi.">
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
                        <span class="pill pill-brand">{{ $kuis->questions_count }} soal di pool</span>
                        @if ($kuis->jumlah_soal !== null)
                            <span class="pill pill-sun">{{ $kuis->jumlah_soal }} soal dipakai</span>
                        @else
                            <span class="pill pill-neutral">Semua soal dipakai</span>
                        @endif
                    </div>

                    <h2 class="mt-3 text-2xl">{{ $kuis->judul }}</h2>
                    <p class="mt-1 text-base text-ink-muted">{{ $kuis->sessions_count }} sesi memakai kuis ini.</p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('admin.quizzes.edit', $kuis) }}" class="btn btn-secondary">Edit</a>
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
