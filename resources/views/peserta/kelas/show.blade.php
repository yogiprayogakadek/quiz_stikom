<x-layouts.peserta :judul="$kelas->nama" :keterangan="$anggota ? 'Kuis dan sesi yang dibuka untuk kelas ini.' : 'Masukkan PIN kelas dari admin untuk masuk.'">
    <x-slot:aksi>
        <a href="{{ route('peserta.kelas.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Semua kelas
        </a>
    </x-slot:aksi>

    @unless ($anggota)
        <form method="POST" action="{{ route('peserta.kelas.masuk', $kelas) }}" class="card max-w-md">
            @csrf
            <label for="pin" class="label">PIN kelas</label>
            <input id="pin" name="pin" type="text" class="input" required maxlength="12" autocomplete="off" autofocus>
            <p class="help mt-2">Minta PIN ke admin kelas kalau belum punya.</p>
            <button type="submit" class="btn btn-primary mt-4 w-full">Masuk kelas</button>
        </form>
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            <section aria-labelledby="kuis-kelas">
                <h2 id="kuis-kelas" class="text-2xl">Kuis di kelas</h2>

                @if ($kelas->quizzes->isEmpty())
                    <p class="mt-3 text-base text-ink-muted">Belum ada kuis untuk kelas ini.</p>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach ($kelas->quizzes as $kuis)
                            <li class="rounded-2xl border border-outline bg-page p-4">
                                <p class="font-display text-lg font-bold text-ink">{{ $kuis->judul }}</p>
                                <p class="text-base text-ink-muted">{{ $kuis->questions_count }} soal tersedia</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section aria-labelledby="sesi-kelas">
                <h2 id="sesi-kelas" class="text-2xl">Sesi yang dibuka</h2>

                @if ($sesi->isEmpty())
                    <p class="mt-3 text-base text-ink-muted">Belum ada sesi yang dibuka untuk kelas ini.</p>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach ($sesi as $s)
                            @php
                                $attempt = $s->attempts->first(fn ($a) => $a->sudahBergabung());
                                $penuh = $s->max_peserta !== null && $s->attempts_count >= $s->max_peserta;
                                $serempakBerjalan = $s->mode === 'serempak' && $s->status === 'berjalan';
                            @endphp

                            <li class="rounded-2xl border border-outline bg-page p-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="pill {{ $s->status === 'berjalan' ? 'pill-leaf' : 'pill-sun' }}">
                                        {{ $s->status === 'berjalan' ? 'Berjalan' : 'Lobby' }}
                                    </span>
                                    <span class="pill pill-neutral">{{ $s->mode === 'serempak' ? 'Mode Serempak' : 'Mode Acak' }}</span>
                                    @if ($s->max_peserta !== null)
                                        <span class="pill pill-neutral">{{ $s->attempts_count }}/{{ $s->max_peserta }} peserta</span>
                                    @endif
                                </div>

                                <p class="mt-3 font-display text-lg font-bold text-ink">{{ $s->nama }}</p>
                                <p class="text-base text-ink-muted">{{ $s->quiz?->judul }}</p>

                                <div class="mt-3">
                                    @if ($attempt !== null)
                                        @if ($attempt->status === 'selesai')
                                            <a href="{{ $s->mode === 'acak' ? route('peserta.attempt.result', $attempt) : route('peserta.session.result', $s) }}" class="btn btn-secondary">
                                                Lihat hasil
                                            </a>
                                        @elseif ($attempt->status === 'berjalan')
                                            <a href="{{ $s->mode === 'acak' ? route('peserta.attempt.show', $attempt) : route('peserta.session.play', $s) }}" class="btn btn-primary">
                                                Lanjutkan mengerjakan
                                            </a>
                                        @elseif ($s->mode === 'acak' && $s->status === 'berjalan')
                                            <form method="POST" action="{{ route('peserta.attempt.start', $s) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-primary">Mulai mengerjakan</button>
                                            </form>
                                        @else
                                            <p class="text-base text-ink-muted">Sudah bergabung. Tunggu admin memulai sesi.</p>
                                        @endif
                                    @elseif ($serempakBerjalan)
                                        <p class="text-base text-ink-muted">Sesi Serempak sudah dimulai. Bergabung ditutup.</p>
                                    @elseif ($penuh)
                                        <p class="text-base text-ink-muted">Kuota peserta sesi ini sudah penuh.</p>
                                    @else
                                        <form method="POST" action="{{ route('peserta.session.join', $s) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-primary">Gabung sesi</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    @endunless
</x-layouts.peserta>
