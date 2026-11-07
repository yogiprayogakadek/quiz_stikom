<x-layouts.admin judul="Dashboard" keterangan="Pusat kendali kuis, sesi ujian aktif, dan data peserta.">
    <x-slot:aksi>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.quizzes.create') }}" class="btn btn-secondary shadow-sm hover:shadow">
                <x-icon name="plus" :size="18" />
                <span>Buat Kuis</span>
            </a>
            <a href="{{ route('admin.sessions.create') }}" class="btn btn-primary shadow-lift-brand hover:shadow-lg">
                <x-icon name="play" :size="18" />
                <span>Buka Sesi Ujian</span>
            </a>
        </div>
    </x-slot:aksi>

    <!-- Welcome Hero Banner -->
    <div class="relative overflow-hidden rounded-panel border border-brand/20 bg-gradient-to-br from-brand-soft/60 via-page to-surface p-6 sm:p-8 mb-8 shadow-lift">
        <div class="relative z-10 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full bg-brand-soft/80 px-3 py-1 text-xs font-bold text-brand-deep mb-3 border border-brand/20">
                    <span class="inline-block h-2 w-2 rounded-full bg-leaf animate-pulse"></span>
                    Sistem Ujian Aktif
                </div>
                <h2 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Selamat Datang, {{ auth()->user()->nama }}</h2>
                <p class="mt-1 text-base text-ink-muted">Pantau pelaksanaan kuis dan hasil ujian peserta secara real-time.</p>
            </div>
            <div class="flex items-center gap-3 self-start sm:self-auto rounded-2xl bg-page/80 backdrop-blur-sm px-4 py-3 border border-outline">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sun-soft text-sun-deep">
                    <x-icon name="clock" :size="20" />
                </span>
                <div>
                    <p class="text-xs font-medium text-ink-muted">Hari ini</p>
                    <p class="text-sm font-bold text-ink">{{ now()->translatedFormat('l, d F Y') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bento Grid Metric Cards -->
    <section aria-labelledby="ringkasan-kelas">
        <h2 id="ringkasan-kelas" class="sr-only">Ringkasan statistik</h2>
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card card-hover border border-outline bg-page p-5 transition-all">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-wider text-ink-subtle">Peserta Terdaftar</p>
                        <p class="display-number mt-2 text-4xl font-extrabold text-ink">{{ $ringkasan['peserta'] }}</p>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-soft text-brand-deep">
                        <x-icon name="users" :size="24" />
                    </span>
                </div>
                <div class="mt-4 pt-3 border-t border-outline flex items-center justify-between">
                    <span class="text-xs text-ink-muted">Total akun siswa</span>
                    <a href="{{ route('admin.participants.index') }}" class="text-sm font-bold text-brand-deep hover:underline">Kelola &rarr;</a>
                </div>
            </div>

            <div class="card card-hover border border-outline bg-page p-5 transition-all">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-wider text-ink-subtle">Total Soal</p>
                        <p class="display-number mt-2 text-4xl font-extrabold text-ink">{{ $ringkasan['soal'] }}</p>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-leaf-soft text-leaf-deep">
                        <x-icon name="pencil" :size="24" />
                    </span>
                </div>
                <div class="mt-4 pt-3 border-t border-outline flex items-center justify-between">
                    <span class="text-xs text-ink-muted">Dalam seluruh kuis</span>
                    <a href="{{ route('admin.quizzes.index') }}" class="text-sm font-bold text-leaf-deep hover:underline">Lihat &rarr;</a>
                </div>
            </div>

            <div class="card card-hover border border-outline bg-page p-5 transition-all">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-wider text-ink-subtle">Kuis Dibuat</p>
                        <p class="display-number mt-2 text-4xl font-extrabold text-ink">{{ $ringkasan['kuis'] }}</p>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-berry-soft text-berry">
                        <x-icon name="book" :size="24" />
                    </span>
                </div>
                <div class="mt-4 pt-3 border-t border-outline flex items-center justify-between">
                    <span class="text-xs text-ink-muted">Tersambung ke kelas</span>
                    <a href="{{ route('admin.quizzes.index') }}" class="text-sm font-bold text-berry hover:underline">Buka &rarr;</a>
                </div>
            </div>

            <div class="card card-hover border border-outline bg-page p-5 transition-all">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-wider text-ink-subtle">Sesi Aktif</p>
                        <p class="display-number mt-2 text-4xl font-extrabold text-ink">{{ $ringkasan['sesi_aktif'] }}</p>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sun-soft text-sun-deep">
                        <x-icon name="play" :size="24" />
                    </span>
                </div>
                <div class="mt-4 pt-3 border-t border-outline flex items-center justify-between">
                    <span class="text-xs text-ink-muted">{{ $loginHariIni }} login hari ini</span>
                    <a href="{{ route('admin.sessions.index') }}" class="text-sm font-bold text-sun-deep hover:underline">Pantau &rarr;</a>
                </div>
            </div>
        </div>
    </section>

    @if ($ringkasan['soal'] === 0 || $ringkasan['kuis'] === 0)
        <section class="mt-8 card border-brand-bright bg-brand-soft" aria-labelledby="mulai-dari-sini">
            <h2 id="mulai-dari-sini" class="text-2xl">Siapkan kuis pertama</h2>
            <ol class="mt-4 space-y-3 text-lg text-ink">
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-full bg-page font-mono text-sm font-bold text-brand-deep">1</span>
                    <span>Buat <a href="{{ route('admin.quizzes.create') }}" class="font-bold text-brand-deep underline decoration-2 underline-offset-4">Kuis baru</a> dengan mengisi judul kuis.</span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-full bg-page font-mono text-sm font-bold text-brand-deep">2</span>
                    <span>Tambahkan soal atau impor file CSV langsung ke dalam kuis tersebut.</span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-full bg-page font-mono text-sm font-bold text-brand-deep">3</span>
                    <span>Buka <a href="{{ route('admin.sessions.create') }}" class="font-bold text-brand-deep underline decoration-2 underline-offset-4">sesi baru</a> untuk kelas Anda dan mulai ujian!</span>
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
