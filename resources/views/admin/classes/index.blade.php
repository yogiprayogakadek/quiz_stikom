<x-layouts.admin judul="Kelas" keterangan="Kelompokkan peserta per kelas. Peserta bergabung dengan PIN, lalu sesi kelas hanya tampil untuk anggotanya.">
    <form method="POST" action="{{ route('admin.classes.store') }}" class="card max-w-3xl">
        @csrf
        <h2 class="text-2xl">Buat kelas</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="nama" class="label">Nama kelas</label>
                <input id="nama" name="nama" type="text" class="input" value="{{ old('nama') }}" required maxlength="120">
            </div>
            <div>
                <label for="pin" class="label">PIN kelas</label>
                <input id="pin" name="pin" type="text" class="input" value="{{ old('pin') }}" required minlength="4" maxlength="12" autocomplete="off">
                <p class="mt-1 text-sm text-ink-subtle">4 sampai 12 huruf atau angka. Bagikan ke peserta.</p>
            </div>
        </div>

        <div class="mt-5">
            <button type="submit" class="btn btn-primary">
                <x-icon name="plus" :size="20" />
                Buat kelas
            </button>
        </div>
    </form>

    @if ($kelas->isEmpty())
        <x-empty-state
            class="mt-8"
            judul="Belum ada kelas"
            keterangan="Buat kelas pertama, lalu pilih peserta dan kuis yang masuk ke kelas itu."
        />
    @else
        <div class="mt-8 grid gap-5 md:grid-cols-2">
            @foreach ($kelas as $k)
                <article class="card card-hover">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="pill pill-brand">{{ $k->peserta_count }} peserta</span>
                        <span class="pill pill-neutral">{{ $k->quizzes_count }} kuis</span>
                        <span class="pill pill-neutral">{{ $k->sessions_count }} sesi</span>
                    </div>

                    <h2 class="mt-3 text-2xl">{{ $k->nama }}</h2>
                    <p class="mt-1 text-base text-ink-muted">PIN: <span class="font-mono font-bold text-ink">{{ $k->pin }}</span></p>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <a href="{{ route('admin.classes.edit', $k) }}" class="btn btn-secondary">Kelola kelas</a>
                        @if ($k->sessions_count === 0)
                            <form method="POST" action="{{ route('admin.classes.destroy', $k) }}" onsubmit="return confirm('Hapus kelas {{ $k->nama }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost text-coral-deep">Hapus</button>
                            </form>
                        @else
                            <span class="self-center text-sm text-ink-subtle">Punya sesi, tidak bisa dihapus</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.admin>
