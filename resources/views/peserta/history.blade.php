<x-layouts.peserta judul="Riwayat Kuis" keterangan="Semua ujian yang sudah kamu selesaikan di workspace ini.">
    <x-slot:aksi>
        <a href="{{ route('peserta.dashboard') }}" class="btn btn-secondary">
            <x-icon name="arrow-left" :size="20" />
            Kembali ke beranda
        </a>
    </x-slot:aksi>

    <form method="GET" action="{{ route('peserta.history.index') }}" class="card mb-5" aria-label="Filter riwayat kuis">
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label for="jenis" class="label">Jenis</label>
                <select id="jenis" name="jenis" class="input">
                    <option value="real" @selected($jenis === 'real')>Ujian nyata</option>
                    <option value="simulasi" @selected($jenis === 'simulasi')>Simulasi</option>
                    <option value="semua" @selected($jenis === 'semua')>Semua jenis</option>
                </select>
            </div>
            <div>
                <label for="status" class="label">Status penilaian</label>
                <select id="status" name="status" class="input">
                    <option value="semua" @selected($status === 'semua')>Semua status</option>
                    <option value="final" @selected($status === 'final')>Sudah final</option>
                    <option value="pending" @selected($status === 'pending')>Menunggu penilaian</option>
                </select>
            </div>
            <div>
                <label for="kelas" class="label">Kelas</label>
                <select id="kelas" name="kelas" class="input">
                    <option value="">Semua kelas</option>
                    @foreach ($kelasList as $kelas)
                        <option value="{{ $kelas->id }}" @selected($kelasFilter === $kelas->id)>{{ $kelas->nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4">
            <label for="q" class="label">Cari riwayat</label>
            <input
                id="q"
                type="search"
                name="q"
                class="input"
                maxlength="100"
                value="{{ $cari }}"
                placeholder="Judul kuis, nama sesi, atau kelas"
            >
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="submit" class="btn btn-primary">Terapkan filter</button>
            <a href="{{ route('peserta.history.index') }}" class="btn btn-secondary">Reset filter</a>
        </div>
    </form>

    @if ($riwayat->isEmpty())
        @php
            $filterAktif = [
                'Jenis: '.match ($jenis) {
                    'simulasi' => 'Simulasi',
                    'semua' => 'Semua jenis',
                    default => 'Ujian nyata',
                },
            ];

            if ($status === 'pending') {
                $filterAktif[] = 'Status: menunggu penilaian';
            } elseif ($status === 'final') {
                $filterAktif[] = 'Status: sudah final';
            }

            if ($kelasFilter !== null) {
                $filterAktif[] = 'Kelas: '.($kelasList->firstWhere('id', $kelasFilter)?->nama ?? '-');
            }

            if ($cari !== '') {
                $filterAktif[] = 'Pencarian: "'.$cari.'"';
            }
        @endphp

        @if (! $punyaRiwayat)
            <x-empty-state
                judul="Belum ada riwayat kuis"
                keterangan="Hasil akan muncul di sini setelah kamu menyelesaikan ujian."
            >
                <a href="{{ route('peserta.dashboard') }}" class="btn btn-primary">Cari sesi yang dibuka</a>
            </x-empty-state>
        @else
            <x-empty-state
                judul="Tidak ada riwayat pada filter ini"
                keterangan="Filter aktif: {{ implode(' · ', $filterAktif) }}. Longgarkan filter atau kembali ke riwayat ujian nyata."
            >
                <a href="{{ route('peserta.history.index') }}" class="btn btn-secondary">Reset filter</a>
            </x-empty-state>
        @endif
    @else
        <p class="mb-3 text-sm font-semibold text-ink-muted">
            Menampilkan {{ $riwayat->firstItem() }}–{{ $riwayat->lastItem() }} dari {{ $riwayat->total() }} riwayat.
        </p>

        <div class="grid gap-5 xl:grid-cols-2">
            @foreach ($riwayat as $attempt)
                @php
                    $session = $attempt->quizSession;
                    $quiz = $session->quiz;
                    $pending = (int) $attempt->essay_pending_count;
                    $selesai = $attempt->selesai_at
                        ? \Illuminate\Support\Carbon::createFromTimestampMs($attempt->selesai_at, $orgTimezone)
                        : null;

                    $durasi = null;
                    if ($attempt->selesai_at !== null && $attempt->mulai_at !== null) {
                        $detik = intdiv(max(0, $attempt->selesai_at - $attempt->mulai_at), 1000);
                        $jam = intdiv($detik, 3600);
                        $menit = intdiv($detik % 3600, 60);
                        $durasi = $jam > 0
                            ? $jam.' jam '.$menit.' menit'
                            : ($menit > 0 ? $menit.' menit' : $detik.' detik');
                    }

                    $linkHasil = $session->mode === 'serempak'
                        ? route('peserta.session.result', $session)
                        : route('peserta.attempt.result', $attempt);
                @endphp

                <article
                    class="card flex min-w-0 flex-col gap-4"
                    data-riwayat-attempt="{{ $attempt->id }}"
                    data-status-penilaian="{{ $pending > 0 ? 'pending' : 'final' }}"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="pill {{ $quiz->is_percobaan ? 'pill-brand' : 'pill-neutral' }}">
                            {{ $quiz->is_percobaan ? 'Simulasi' : 'Ujian nyata' }}
                        </span>
                        @if ($session->isRemidi())
                            <span class="pill pill-coral">Remidi</span>
                        @endif
                        <span class="pill pill-neutral">{{ $session->mode === 'serempak' ? 'Mode Serempak' : 'Mode Acak' }}</span>
                    </div>

                    <div class="min-w-0">
                        <h2 class="font-display text-xl font-extrabold tracking-tight break-words text-ink">{{ $quiz->judul }}</h2>
                        <p class="mt-1 text-base font-semibold break-words text-ink-muted">{{ $session->nama }}</p>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 rounded-2xl border border-outline bg-[#f8fafc] p-4 text-sm">
                        <div class="min-w-0">
                            <dt class="font-bold text-ink-faint uppercase tracking-wider text-xs">Kelas</dt>
                            <dd class="mt-1 break-words font-semibold text-ink">{{ $session->kelas?->nama ?? 'Tanpa kelas' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="font-bold text-ink-faint uppercase tracking-wider text-xs">Selesai</dt>
                            <dd class="mt-1 font-semibold text-ink">{{ $selesai?->translatedFormat('d M Y, H:i') ?? '-' }}</dd>
                        </div>
                        @if ($durasi !== null)
                            <div class="min-w-0">
                                <dt class="font-bold text-ink-faint uppercase tracking-wider text-xs">Durasi</dt>
                                <dd class="mt-1 font-semibold text-ink">{{ $durasi }}</dd>
                            </div>
                        @endif
                    </dl>

                    <div class="rounded-2xl border border-outline bg-[#f8fafc] p-4">
                        @if (! $session->nilaiTampil())
                            <p class="text-xs font-bold uppercase tracking-wider text-ink-faint">Nilai akhir</p>
                            <p class="mt-1 font-display text-lg font-extrabold text-ink">Belum dipublikasikan</p>
                            <p class="text-sm font-semibold text-ink-muted">Pekerjaan sudah tersimpan dan menunggu publikasi admin.</p>
                        @elseif ($pending > 0)
                            <p class="text-xs font-bold uppercase tracking-wider text-ink-faint">Nilai akhir</p>
                            <p class="mt-1 font-display text-lg font-extrabold text-ink">Menunggu penilaian essay</p>
                            <p class="text-sm font-semibold text-ink-muted">{{ $pending }} jawaban menunggu dinilai.</p>
                        @else
                            <p class="text-xs font-bold uppercase tracking-wider text-ink-faint">
                                {{ $session->mode === 'serempak' ? 'Poin akhir' : 'Nilai akhir' }}
                            </p>
                            <p
                                class="mt-1 font-display text-3xl font-extrabold text-ink"
                                data-nilai-akhir="{{ (int) $attempt->skor_total }}"
                            >{{ (int) $attempt->skor_total }}</p>
                        @endif
                    </div>

                    <div class="mt-auto">
                        <a
                            href="{{ $linkHasil }}"
                            class="btn btn-primary w-full sm:w-auto"
                            aria-label="Lihat hasil {{ $quiz->judul }}"
                        >
                            <x-icon name="eye" :size="20" />
                            Lihat hasil
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        @if ($riwayat->hasPages())
            <div class="mt-6">{{ $riwayat->links() }}</div>
        @endif
    @endif
</x-layouts.peserta>
