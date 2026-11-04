<x-layouts.admin judul="Dashboard" keterangan="Ringkasan kelas dan sesi yang sedang berjalan.">
    <x-slot:aksi>
        <a href="{{ route('admin.questions.create') }}" class="btn btn-secondary">
            <x-icon name="plus" :size="20" />
            Tambah soal
        </a>
        <a href="{{ route('admin.sessions.create') }}" class="btn btn-primary">
            <x-icon name="play" :size="20" />
            Buat sesi
        </a>
    </x-slot:aksi>

    <section aria-labelledby="ringkasan-kelas">
        <h2 id="ringkasan-kelas" class="sr-only">Ringkasan kelas</h2>
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-base font-bold text-ink-muted">Peserta</p>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-soft text-brand-deep">
                        <x-icon name="users" :size="22" />
                    </span>
                </div>
                <p class="display-number mt-3 text-5xl">{{ $ringkasan['peserta'] }}</p>
                <a href="{{ route('admin.participants.index') }}" class="mt-3 inline-block text-base font-bold text-brand-deep underline decoration-2 underline-offset-4">Kelola peserta</a>
            </div>

            <div class="card">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-base font-bold text-ink-muted">Soal di bank</p>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-leaf-soft text-leaf-deep">
                        <x-icon name="pencil" :size="22" />
                    </span>
                </div>
                <p class="display-number mt-3 text-5xl">{{ $ringkasan['soal'] }}</p>
                <a href="{{ route('admin.questions.index') }}" class="mt-3 inline-block text-base font-bold text-brand-deep underline decoration-2 underline-offset-4">Buka bank soal</a>
            </div>

            <div class="card">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-base font-bold text-ink-muted">Kuis siap pakai</p>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-berry-soft text-berry">
                        <x-icon name="book" :size="22" />
                    </span>
                </div>
                <p class="display-number mt-3 text-5xl">{{ $ringkasan['kuis'] }}</p>
                <a href="{{ route('admin.quizzes.index') }}" class="mt-3 inline-block text-base font-bold text-brand-deep underline decoration-2 underline-offset-4">Lihat kuis</a>
            </div>

            <div class="card">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-base font-bold text-ink-muted">Sesi aktif</p>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sun-soft text-sun-deep">
                        <x-icon name="play" :size="22" />
                    </span>
                </div>
                <p class="display-number mt-3 text-5xl">{{ $ringkasan['sesi_aktif'] }}</p>
                <p class="mt-3 text-base text-ink-muted">{{ $loginHariIni }} peserta bergabung hari ini</p>
            </div>
        </div>
    </section>

    @if ($ringkasan['soal'] === 0 || $ringkasan['kuis'] === 0)
        <section class="mt-8 card border-brand-bright bg-brand-soft" aria-labelledby="mulai-dari-sini">
            <h2 id="mulai-dari-sini" class="text-2xl">Siapkan bahan kuis pertama</h2>
            <ol class="mt-4 space-y-3 text-lg text-ink">
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-full bg-page font-mono text-sm font-bold text-brand-deep">1</span>
                    <span>Tulis soal pilihan ganda di <a href="{{ route('admin.questions.index') }}" class="font-bold text-brand-deep underline decoration-2 underline-offset-4">Bank soal</a>. Tiap soal punya 2 sampai 6 opsi dan satu jawaban benar.</span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-full bg-page font-mono text-sm font-bold text-brand-deep">2</span>
                    <span>Susun <a href="{{ route('admin.quizzes.index') }}" class="font-bold text-brand-deep underline decoration-2 underline-offset-4">kuis</a> dari soal tersebut. Tentukan jumlah soal yang dipakai atau pakai semua.</span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-full bg-page font-mono text-sm font-bold text-brand-deep">3</span>
                    <span>Buat <a href="{{ route('admin.sessions.create') }}" class="font-bold text-brand-deep underline decoration-2 underline-offset-4">sesi</a>, pilih mode Acak atau Serempak, lalu buka lobby.</span>
                </li>
            </ol>
        </section>
    @endif

    <div class="mt-8 grid gap-6 xl:grid-cols-[1.4fr_1fr]">
        <section class="card" aria-labelledby="sesi-aktif">
            <div class="flex items-center justify-between gap-3">
                <h2 id="sesi-aktif" class="text-2xl">Sesi aktif</h2>
                <a href="{{ route('admin.sessions.index') }}" class="text-base font-bold text-brand-deep underline decoration-2 underline-offset-4">Semua sesi</a>
            </div>

            @if ($sesiAktif->isEmpty())
                <x-empty-state
                    class="mt-5"
                    judul="Belum ada sesi aktif"
                    keterangan="Buat sesi baru, lalu buka lobby supaya peserta bisa bergabung."
                >
                    <a href="{{ route('admin.sessions.create') }}" class="btn btn-primary mt-2">Buat sesi pertama</a>
                </x-empty-state>
            @else
                <ul class="mt-4 divide-y divide-outline">
                    @foreach ($sesiAktif as $sesi)
                        <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-display text-lg font-bold text-ink">{{ $sesi->nama }}</p>
                                <p class="text-base text-ink-muted">{{ $sesi->quiz->judul }} · {{ $sesi->attempts_count }} peserta</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-mode-badge :mode="$sesi->mode" />
                                <x-status-badge :status="$sesi->status" />
                                <a href="{{ route('admin.sessions.show', $sesi) }}" class="btn btn-secondary">Ruang kendali</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="card" aria-labelledby="sesi-terakhir">
            <h2 id="sesi-terakhir" class="text-2xl">Sesi terakhir ditutup</h2>

            @if ($sesiTerakhir->isEmpty())
                <p class="mt-4 text-base text-ink-muted">Belum ada sesi yang selesai. Hasil akan muncul di sini setelah sesi ditutup.</p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($sesiTerakhir as $sesi)
                        <li class="rounded-2xl border border-outline p-4">
                            <p class="font-display text-lg font-bold text-ink">{{ $sesi->nama }}</p>
                            <p class="text-base text-ink-muted">{{ $sesi->attempts_count }} peserta · ditutup {{ $sesi->ditutup_at?->diffForHumans() }}</p>
                            <a href="{{ route('admin.reports.session', $sesi) }}" class="mt-2 inline-block text-base font-bold text-brand-deep underline decoration-2 underline-offset-4">Buka laporan</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.admin>
