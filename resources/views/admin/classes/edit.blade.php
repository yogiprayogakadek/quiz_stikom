@php
    $anggotaIds = collect(old('peserta_ids', $kelas->peserta->pluck('id')->all()))->map(fn ($id) => (int) $id)->all();
    $quizIds = collect(old('quiz_ids', $kelas->quizzes->pluck('id')->all()))->map(fn ($id) => (int) $id)->all();
@endphp

<x-layouts.admin :judul="'Kelas '.$kelas->nama" keterangan="Pilih peserta dan kuis untuk kelas ini. Peserta bisa bergabung sendiri dengan PIN.">
    <x-slot:aksi>
        @if ($appSettings->class_cover_mode === 'ai')
            <form method="POST" action="{{ route('admin.classes.regenerate-cover', $kelas) }}" data-progress-message="Menjadwalkan cover AI...">
                @csrf
                <button type="submit" class="btn btn-secondary">Generate ulang cover</button>
            </form>
        @endif
        <a href="{{ route('admin.sessions.create', ['kelas_id' => $kelas->id]) }}" class="btn btn-primary">
            <x-icon name="plus" :size="20" />
            Buat sesi
        </a>
        <a href="{{ route('admin.classes.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

    <form method="POST" action="{{ route('admin.classes.update', $kelas) }}" class="card max-w-3xl">
        @csrf
        @method('PUT')

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="nama" class="label">Nama kelas</label>
                <input id="nama" name="nama" type="text" class="input" value="{{ old('nama', $kelas->nama) }}" required maxlength="120">
            </div>
            <div>
                <label for="pin" class="label">PIN kelas</label>
                <input id="pin" name="pin" type="text" class="input" value="{{ old('pin', $kelas->pin) }}" required minlength="4" maxlength="12" autocomplete="off">
            </div>
        </div>

        <div class="mt-6">
            <h2 class="text-xl">Peserta di kelas</h2>
            <p class="mt-1 text-base text-ink-muted">Satu peserta boleh ikut beberapa kelas.</p>

            @if ($peserta->isEmpty())
                <p class="mt-3 text-base text-ink-muted">Belum ada akun peserta. Tambahkan peserta dulu di menu Peserta.</p>
            @else
                <div class="mt-3 grid max-h-72 gap-2 overflow-y-auto rounded-2xl border border-outline p-3 sm:grid-cols-2">
                    @foreach ($peserta as $p)
                        <label class="flex min-h-11 items-center gap-3 rounded-xl px-2 py-2">
                            <input type="checkbox" name="peserta_ids[]" value="{{ $p->id }}" class="h-5 w-5" @checked(in_array($p->id, $anggotaIds, true))>
                            <span class="text-base font-bold text-ink">{{ $p->nama }}</span>
                            <span class="text-sm text-ink-subtle">{{ $p->username }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="mt-6">
            <h2 class="text-xl">Kuis di kelas</h2>
            <p class="mt-1 text-base text-ink-muted">Kuis terpilih bisa langsung dipakai untuk membuat sesi kelas.</p>

            @if ($quizzes->isEmpty())
                <p class="mt-3 text-base text-ink-muted">Belum ada kuis. Buat kuis dulu di menu Kuis.</p>
            @else
                <div class="mt-3 grid max-h-72 gap-2 overflow-y-auto rounded-2xl border border-outline p-3 sm:grid-cols-2">
                    @foreach ($quizzes as $kuis)
                        <label class="flex min-h-11 items-center gap-3 rounded-xl px-2 py-2">
                            <input type="checkbox" name="quiz_ids[]" value="{{ $kuis->id }}" class="h-5 w-5" @checked(in_array($kuis->id, $quizIds, true))>
                            <span class="text-base font-bold text-ink">{{ $kuis->judul }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Simpan perubahan</button>
            <a href="{{ route('admin.classes.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>

    <section class="mt-8" aria-labelledby="sesi-kelas">
        <h2 id="sesi-kelas" class="text-2xl">Sesi kelas ini</h2>

        @if ($kelas->sessions->isEmpty())
            <p class="mt-3 text-base text-ink-muted">Belum ada sesi. Buat sesi untuk kelas ini agar peserta bisa bergabung.</p>
        @else
            <ul class="mt-4 space-y-3">
                @foreach ($kelas->sessions->sortByDesc('created_at') as $sesi)
                    <li class="flex flex-col gap-3 rounded-2xl border border-outline bg-page p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-display text-lg font-bold text-ink">{{ $sesi->nama }}</p>
                            <p class="text-base text-ink-muted">{{ $sesi->quiz?->judul }} · {{ $sesi->status }}</p>
                        </div>
                        <a href="{{ route('admin.sessions.show', $sesi) }}" class="btn btn-secondary">Ruang kendali</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($kelas->sessions->isEmpty())
        <form method="POST" action="{{ route('admin.classes.destroy', $kelas) }}" class="mt-8" onsubmit="return confirm('Hapus kelas ini?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-ghost text-coral-deep">Hapus kelas</button>
        </form>
    @endif
</x-layouts.admin>
