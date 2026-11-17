@php
    $mode = $session->mode;
    $persen = $mode === 'serempak'
        ? ($total_soal > 0 ? (int) round($attempt->skor_total / ($total_soal * 1000) * 100) : 0)
        : (int) $attempt->skor_total;
    $bintang = $persen >= 90 ? 3 : ($persen >= 70 ? 2 : 1);
    $essayBelum = $rincian->filter(fn ($baris) => $baris['tipe'] === 'essay'
        && filled($baris['jawaban_saya'])
        && $baris['dinilai_at'] === null)->count();
    $nilaiTertunda = $essayBelum > 0;
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
            <h2 id="skor-akhir" class="sr-only">Status nilai</h2>

            @if ($nilaiTertunda)
                <div class="mx-auto mt-6 max-w-md rounded-2xl bg-sun-soft px-5 py-6" data-score-pending>
                    <x-icon name="clock" :size="32" class="mx-auto text-sun-deep" />
                    <p class="mt-3 text-xl font-bold text-ink">Menunggu penilaian essay</p>
                    <p class="mt-1 text-base text-ink-muted">{{ $essayBelum }} jawaban sedang dinilai. Nilai akhir akan muncul otomatis setelah seluruh penilaian selesai.</p>
                </div>
            @else
                <div data-final-score>
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
                </div>
            @endif

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
                    <p class="mt-1 text-sm text-ink-muted">Halaman ini akan diperbarui otomatis setelah penilaian selesai.</p>
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

    @if ($tampilkanFeedback)
        @include('peserta._feedback', ['attempt' => $attempt, 'inline' => true])
    @endif

    @if ($nilaiAkhir && ! $nilaiTertunda)
        <section class="mt-6 card border-leaf" aria-labelledby="nilai-akhir-heading">
            <p class="pill pill-leaf">Nilai akhir</p>
            <h2 id="nilai-akhir-heading" class="mt-3 text-2xl">Nilai akhir setelah remidi</h2>
            <p class="display-number mt-4 text-5xl text-ink">{{ $nilaiAkhir->nilai_akhir }}</p>
            <p class="mt-2 text-base text-ink-muted">
                Diambil dari nilai {{ $nilaiAkhir->sumber === 'remidi' ? 'remidi' : 'awal' }}.
                Nilai awal {{ $nilaiAkhir->nilai_awal }}@if ($nilaiAkhir->nilai_remidi !== null), nilai remidi {{ $nilaiAkhir->nilai_remidi }}@endif.
            </p>
        </section>
    @elseif (! $nilaiTertunda && $session->isRemidi() && $attempt->status === 'selesai')
        <section class="mt-6 card" aria-labelledby="nilai-akhir-heading">
            <h2 id="nilai-akhir-heading" class="text-2xl">Nilai remidi tersimpan</h2>
            <p class="mt-2 text-base text-ink-muted">Nilai akhir menunggu admin memilih nilai terbaik antara nilai awal dan nilai remidi.</p>
        </section>
    @endif

    @if ($catatanTampil)
        <section class="mt-6 card" aria-labelledby="catatan-pengajar">
            <h2 id="catatan-pengajar" class="text-2xl">Catatan pengajar</h2>
            <p class="mt-1 text-base text-ink-muted">Ditulis per soal setelah seluruh jawaban essay dinilai.</p>
            <ul class="mt-4 space-y-4">
                @foreach ($catatanPengajar as $baris)
                    <li class="rounded-xl border border-outline bg-page p-4">
                        <p class="text-sm font-bold text-ink">Soal {{ $baris['urutan'] }} · Nilai {{ $baris['skor'] }} / {{ $baris['bobot'] }}</p>
                        <p class="mt-1 whitespace-pre-line text-base text-ink">{{ $baris['catatan'] }}</p>
                    </li>
                @endforeach
            </ul>
            <p class="mt-4 text-sm text-ink-muted">Catatan ini bukan kunci jawaban. Kunci dan pembahasan tetap mengikuti pengaturan sesi.</p>
        </section>
    @endif

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
                                    {!! $baris['content_html'] ?? '' !!}
                                    <p class="mt-1 text-sm text-ink-muted">{{ $baris['tipe'] === 'essay' ? 'Essay' : 'Pilihan ganda' }} · Bobot {{ $baris['bobot'] }}</p>
                                    @if ($baris['pembahasan'])
                                        <p class="mt-1 text-base text-ink-muted">{{ $baris['pembahasan'] }}</p>
                                    @endif
                                </td>
                                <td>
                                    @if ($baris['tipe'] === 'essay' && ! empty($baris['sub_soal']))
                                        <div class="space-y-3">
                                            @foreach ($baris['sub_soal'] as $bagian)
                                                <div>
                                                    <p class="text-sm font-bold text-ink">{{ $baris['urutan'] }}{{ $bagian['code'] }}</p>
                                                    <p class="text-sm text-ink-muted">{{ $bagian['teks'] }}</p>
                                                    {!! $bagian['content_html'] ?? '' !!}
                                                    @if ($bagian['is_leaf'])
                                                        @if (isset($baris['jawaban_data'][$bagian['key']]) || filled($baris['jawaban_parts'][$bagian['key']] ?? null))
                                                            {!! \App\Support\QuestionContent::renderAnswer($bagian['answer_format'], $bagian['answer_config'], $baris['jawaban_data'][$bagian['key']] ?? null, $baris['jawaban_parts'][$bagian['key']] ?? null) !!}
                                                        @else
                                                            <p class="mt-1 text-ink-muted">Tidak dijawab</p>
                                                        @endif
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif ($baris['tipe'] === 'essay')
                                        @if (filled($baris['jawaban_saya']))
                                            {!! \App\Support\QuestionContent::renderAnswer($baris['answer_format'], $baris['answer_config'], $baris['jawaban_data']['__main'] ?? null, $baris['jawaban_saya']) !!}
                                        @else
                                            <p class="text-ink-muted">Tidak dijawab</p>
                                        @endif
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
                                        @elseif (filled($baris['jawaban_saya']))
                                            <span class="pill pill-sun">Menunggu penilaian</span>
                                        @else
                                            <span class="pill">Tidak dijawab</span>
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
                                    @if ($baris['tipe'] === 'essay' && ! $baris['dinilai_at'] && filled($baris['jawaban_saya']))
                                        <span class="font-sans text-ink-muted" data-essay-score-pending>Menunggu penilaian</span>
                                    @elseif ($baris['tipe'] === 'essay' && ! filled($baris['jawaban_saya']))
                                        <span class="font-sans text-ink-muted">Tidak dijawab</span>
                                    @elseif ($mode === 'acak')
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
