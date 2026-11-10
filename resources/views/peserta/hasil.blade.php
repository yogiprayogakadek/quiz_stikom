@php
    $mode = $session->mode;
    $persen = $mode === 'serempak'
        ? ($total_soal > 0 ? (int) round($attempt->skor_total / ($total_soal * 1000) * 100) : 0)
        : (int) $attempt->skor_total;
    $bintang = $persen >= 90 ? 3 : ($persen >= 70 ? 2 : 1);
    $essayBelum = $rincian->where('tipe', 'essay')->whereNull('dinilai_at')->count();
    $adaEssay = $rincian->contains(fn ($baris) => $baris['tipe'] === 'essay');
@endphp

<x-layouts.peserta :judul="'Hasil '.$session->nama" :keterangan="$session->quiz->judul">
    <x-slot:aksi>
        <a href="{{ route('peserta.dashboard') }}" class="btn btn-secondary">
            <x-icon name="home" :size="20" />
            Kembali ke dashboard
        </a>
    </x-slot:aksi>

    <div class="grid gap-5 lg:grid-cols-[1.1fr_1fr]">
        <section class="card text-center" aria-labelledby="skor-akhir">
            <p class="pill pill-brand mx-auto">{{ $mode === 'serempak' ? 'Mode Serempak' : 'Mode Acak' }}</p>
            <h2 id="skor-akhir" class="sr-only">Skor akhir</h2>

            <p class="display-number mt-5 text-[72px] text-ink sm:text-[88px]">{{ $attempt->skor_total }}</p>
            <p class="text-lg font-bold text-ink-muted">{{ $mode === 'serempak' ? 'poin' : 'nilai dari 100' }}</p>

            @if ($mode === 'serempak')
                <p class="mt-1 text-base text-ink-muted">Setara {{ $persen }}% dari poin maksimum {{ $total_soal * 1000 }}.</p>
            @elseif ($attempt->poin_maksimal > 0)
                <p class="mt-1 text-base text-ink-muted">Poin {{ $attempt->poin_total }} dari {{ $attempt->poin_maksimal }}.</p>
            @endif

            <div class="mt-5 flex items-center justify-center gap-2" aria-label="{{ $bintang }} dari 3 bintang">
                @for ($i = 1; $i <= 3; $i++)
                    <x-icon
                        name="star"
                        :size="40"
                        class="{{ $i <= $bintang ? 'text-sun fill-current' : 'text-outline-strong' }}"
                    />
                @endfor
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-leaf-soft p-4">
                    <p class="text-sm font-bold text-leaf-deep">{{ $adaEssay ? 'Pilihan ganda benar' : 'Jawaban benar' }}</p>
                    <p class="font-mono text-3xl font-bold text-ink">{{ $attempt->jumlah_benar }}</p>
                </div>
                <div class="rounded-2xl bg-surface p-4">
                    <p class="text-sm font-bold text-ink-muted">Total soal</p>
                    <p class="font-mono text-3xl font-bold text-ink">{{ $total_soal }}</p>
                </div>
            </div>

            <p class="mt-5 text-base text-ink-muted">Attempt ini sudah dikunci. Kuis yang sama tidak bisa diulang.</p>
        </section>

        <section class="card" aria-labelledby="catatan-hasil">
            <h2 id="catatan-hasil" class="text-2xl">Yang perlu diketahui</h2>

            @if (! $tampilkan)
                <p class="mt-3 text-base text-ink-muted">
                    Admin tidak mengaktifkan pembahasan untuk sesi ini. Nilai tetap tersimpan dan bisa dilihat di dashboard.
                </p>
            @else
                <p class="mt-3 text-base text-ink-muted">
                    Pembahasan aktif. Periksa jawaban benar dan alasan di bawah, lalu diskusikan dengan guru bila perlu.
                </p>
            @endif

            @if ($essayBelum > 0)
                <div class="mt-4 rounded-2xl bg-sun-soft px-4 py-3">
                    <p class="font-bold text-ink">{{ $essayBelum }} jawaban essay menunggu penilaian.</p>
                    <p class="mt-1 text-sm text-ink-muted">Poin di atas masih sementara dan belum termasuk nilai essay. Nilai akhir diperbarui setelah admin menilai.</p>
                </div>
            @endif

            <dl class="mt-5 space-y-3 text-base">
                <div class="flex items-center justify-between gap-3 border-b border-outline pb-2">
                    <dt class="text-ink-muted">Mulai</dt>
                    <dd class="font-mono text-ink">{{ $attempt->mulai_at ? \Illuminate\Support\Carbon::createFromTimestampMs($attempt->mulai_at, $orgTimezone)->format('H:i') : '-' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-b border-outline pb-2">
                    <dt class="text-ink-muted">Selesai</dt>
                    <dd class="font-mono text-ink">{{ $attempt->selesai_at ? \Illuminate\Support\Carbon::createFromTimestampMs($attempt->selesai_at, $orgTimezone)->format('H:i') : '-' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-ink-muted">Nilai tersimpan</dt>
                    <dd class="font-mono text-ink">Ya, di server</dd>
                </div>
            </dl>
        </section>
    </div>

    @if ($tampilkan)
        <section class="mt-6 card" aria-labelledby="rincian-hasil">
            <h2 id="rincian-hasil" class="text-2xl">Rincian jawaban</h2>

            <div class="mt-4 overflow-x-auto">
                <table class="table">
                    <caption class="sr-only">Rincian jawaban dan pembahasan</caption>
                    <thead>
                        <tr>
                            <th scope="col">No</th>
                            <th scope="col">Soal</th>
                            <th scope="col">Jawaban Anda</th>
                            <th scope="col">Kunci / jawaban benar</th>
                            <th scope="col">Hasil</th>
                            <th scope="col">Poin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rincian as $baris)
                            <tr>
                                <td class="font-mono">{{ $baris['urutan'] }}</td>
                                <td>
                                    <p class="font-bold text-ink">{{ \Illuminate\Support\Str::limit($baris['teks'], 160) }}</p>
                                    <p class="mt-1 text-sm text-ink-muted">{{ $baris['tipe'] === 'essay' ? 'Essay' : 'Pilihan ganda' }} · Bobot {{ $baris['bobot'] }}</p>
                                    @if ($baris['pembahasan'])
                                        <p class="mt-1 text-base text-ink-muted">{{ $baris['pembahasan'] }}</p>
                                    @endif
                                </td>
                                <td>
                                    @if ($baris['tipe'] === 'essay')
                                        <p class="whitespace-pre-line">{{ $baris['jawaban_saya'] ?: 'Tidak dijawab' }}</p>
                                    @else
                                        {{ $baris['jawaban_saya'] ?? 'Tidak dijawab' }}
                                    @endif
                                </td>
                                <td>
                                    @if ($baris['tipe'] === 'essay')
                                        <p class="whitespace-pre-line text-ink-muted">{{ $baris['jawaban_benar'] ?: '-' }}</p>
                                    @else
                                        {{ $baris['jawaban_benar'] ?? '-' }}
                                    @endif
                                </td>
                                <td>
                                    @if ($baris['tipe'] === 'essay')
                                        @if ($baris['dinilai_at'])
                                            <span class="pill pill-leaf">Dinilai</span>
                                        @else
                                            <span class="pill pill-sun">Menunggu penilaian</span>
                                        @endif
                                    @elseif ($baris['benar'])
                                        <span class="pill pill-leaf">
                                            <x-icon name="check" :size="14" />
                                            Benar
                                        </span>
                                    @else
                                        <span class="pill pill-coral">
                                            <x-icon name="x" :size="14" />
                                            Salah
                                        </span>
                                    @endif
                                </td>
                                <td class="font-mono">
                                    @if ($mode === 'acak')
                                        {{ $baris['skor'] }} / {{ $baris['bobot'] }}
                                    @else
                                        {{ $baris['skor'] }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof window.buatEcho !== 'function') return;
            const echo = window.buatEcho();
            if (!echo) return;

            echo.channel('quiz-sessions')
                .listen('.SesiStatusUpdated', (data) => {
                    if (data.session_id === {{ $session->id }} && data.action === 'pembahasan_updated') {
                        window.location.reload();
                    }
                });

            @if ($adaEssay)
                const userChannel = 'session-user.' + {{ $session->id }} + '.' + {{ auth()->id() }};
                echo.channel(userChannel)
                    .listen('.EssayDinilai', () => {
                        window.location.reload();
                    });
            @endif
        });
    </script>
</x-layouts.peserta>
