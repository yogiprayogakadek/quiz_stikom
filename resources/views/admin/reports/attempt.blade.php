<x-layouts.admin :judul="'Detail '.($attempt->user?->nama ?? 'peserta')" :keterangan="$attempt->quizSession->nama.' · '.$attempt->quizSession->quiz->judul">
    <x-slot:aksi>
        <a href="{{ route('admin.reports.session', $attempt->quiz_session_id) }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Laporan sesi
        </a>
    </x-slot:aksi>

    <div class="grid gap-5 sm:grid-cols-3">
        <div class="card">
            <p class="text-base font-bold text-ink-muted">Status attempt</p>
            <p class="mt-2 font-display text-2xl font-bold text-ink">{{ ucfirst($attempt->status) }}</p>
            <p class="mt-1 text-base text-ink-muted">Username: <span class="font-mono">{{ $attempt->user?->username ?? '-' }}</span></p>
        </div>
        <div class="card">
            <p class="text-base font-bold text-ink-muted">Jawaban benar</p>
            <p class="display-number mt-2 text-5xl">{{ $attempt->jumlah_benar }}</p>
        </div>
        <div class="card">
            <p class="text-base font-bold text-ink-muted">{{ $attempt->quizSession->mode === 'serempak' ? 'Poin' : 'Nilai' }}</p>
            <p class="display-number mt-2 text-5xl">{{ $attempt->skor_total }}</p>
        </div>
    </div>

    <section class="mt-6 card" aria-labelledby="log-kejadian">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="log-kejadian" class="text-2xl">Log kejadian</h2>
            @if ($attempt->events->count() >= 3)
                <span class="pill pill-coral">Perlu ditinjau</span>
            @else
                <span class="pill pill-neutral">{{ $attempt->events->count() }} kejadian</span>
            @endif
        </div>

        <p class="mt-1 text-base text-ink-muted">Kejadian hanya dicatat. Peserta tidak didiskualifikasi otomatis.</p>

        @if ($attempt->events->isEmpty())
            <p class="mt-4 text-base text-ink-muted">Tidak ada kejadian tercatat. Ini bukan bukti peserta tidak membuka tab lain, hanya tidak ada sinyal yang terkirim.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Waktu</th>
                            <th scope="col">Kejadian</th>
                            <th scope="col">Detail</th>
                            <th scope="col">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attempt->events->sortByDesc('created_at') as $event)
                            @php
                                $label = match ($event->tipe) {
                                    'blur_window' => 'Pindah dari jendela kuis',
                                    'tab_hidden' => 'Tab kuis disembunyikan',
                                    'fullscreen_exit' => 'Keluar dari layar penuh',
                                    'copy_attempt' => 'Upaya menyalin soal',
                                    default => $event->tipe,
                                };
                            @endphp
                            <tr>
                                <td class="font-mono text-base">{{ $event->created_at?->format('d M Y H:i:s') }}</td>
                                <td class="font-bold text-ink">{{ $label }}</td>
                                <td class="text-ink-muted">{{ $event->detail ?? '-' }}</td>
                                <td class="font-mono text-base">{{ $event->ip ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="mt-6 card" aria-labelledby="rincian-jawaban">
        <h2 id="rincian-jawaban" class="text-2xl">Rincian jawaban</h2>

        @if ($rincian->isEmpty())
            <p class="mt-4 text-base text-ink-muted">Attempt ini belum punya soal. Peserta mungkin belum menekan Mulai mengerjakan.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="table">
                    <caption class="sr-only">Rincian jawaban peserta</caption>
                    <thead>
                        <tr>
                            <th scope="col">No</th>
                            <th scope="col">Soal</th>
                            <th scope="col">Jawaban peserta</th>
                            <th scope="col">Jawaban benar</th>
                            <th scope="col">Hasil</th>
                            <th scope="col">Poin soal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rincian as $baris)
                            <tr>
                                <td class="font-mono">{{ $baris['urutan'] }}</td>
                                <td class="font-bold text-ink">{{ \Illuminate\Support\Str::limit($baris['teks'], 140) }}</td>
                                <td>{{ $baris['jawaban_saya'] ?? 'Tidak dijawab' }}</td>
                                <td>{{ $baris['jawaban_benar'] ?? '-' }}</td>
                                <td>
                                    @if ($baris['benar'])
                                        <span class="pill pill-leaf">Benar</span>
                                    @else
                                        <span class="pill pill-coral">Salah</span>
                                    @endif
                                </td>
                                <td class="font-mono">{{ $baris['skor'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="mt-6 card border-coral" aria-labelledby="reset-attempt">
        <h2 id="reset-attempt" class="text-2xl">Reset attempt</h2>
        <p class="mt-2 text-base text-ink-muted">
            Gunakan hanya untuk gangguan teknis. Attempt, jawaban, dan log akan dihapus, lalu peserta bisa bergabung lagi.
            Tindakan ini dicatat di log aplikasi.
        </p>

        <form method="POST" action="{{ route('admin.attempts.reset', $attempt) }}" class="mt-4 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end" onsubmit="return confirm('Hapus attempt ini beserta jawaban dan log kejadiannya?')">
            @csrf
            <div>
                <label for="konfirmasi" class="label">Ketik HAPUS</label>
                <input id="konfirmasi" name="konfirmasi" type="text" class="input" required autocomplete="off" placeholder="HAPUS">
            </div>
            <div>
                <label for="catatan" class="label">Catatan (opsional)</label>
                <input id="catatan" name="catatan" type="text" class="input" maxlength="500" placeholder="Contoh: PC peserta mati saat soal 4">
            </div>
            <button type="submit" class="btn btn-soft-danger">Reset attempt</button>
        </form>
    </section>
</x-layouts.admin>
