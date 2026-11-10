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
            <p class="text-base font-bold text-ink-muted">Jawaban pilihan ganda benar</p>
            <p class="display-number mt-2 text-5xl">{{ $attempt->jumlah_benar }}</p>
        </div>
        <div class="card">
            <p class="text-base font-bold text-ink-muted">{{ $attempt->quizSession->mode === 'serempak' ? 'Poin sesi' : 'Poin' }}</p>
            @if ($attempt->quizSession->mode === 'serempak')
                <p class="display-number mt-2 text-5xl">{{ $attempt->skor_total }}</p>
            @elseif ($attempt->poin_maksimal > 0)
                <p class="display-number mt-2 text-5xl">{{ $attempt->poin_total }}<span class="text-3xl text-ink-muted"> / {{ $attempt->poin_maksimal }}</span></p>
                <p class="mt-1 text-base text-ink-muted">Nilai {{ $attempt->skor_total }}</p>
            @else
                <p class="display-number mt-2 text-5xl">{{ $attempt->skor_total }}</p>
                <p class="mt-1 text-base text-ink-muted">Nilai</p>
            @endif
        </div>
    </div>

    <section class="mt-6 card" aria-labelledby="log-kejadian">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="log-kejadian" class="text-2xl">Log kejadian</h2>
            <div class="flex flex-wrap items-center gap-2">
                @php
                    $detikLuar = $attempt->events->sum(fn ($event) => (int) $event->durasi_detik);
                @endphp
                @if ($detikLuar > 0)
                    <span class="pill pill-sun">{{ $detikLuar }} detik di luar layar</span>
                @endif
                @if ($attempt->events->count() >= 3)
                    <span class="pill pill-coral">Perlu ditinjau</span>
                @else
                    <span class="pill pill-neutral">{{ $attempt->events->count() }} kejadian</span>
                @endif
            </div>
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
                                <td class="font-mono text-base">{{ $event->created_at?->timezone($orgTimezone)->format('d M Y H:i:s') }}</td>
                                <td class="font-bold text-ink">{{ $label }}</td>
                                <td class="text-ink-muted">
                                    {{ $event->detail ?? '-' }}
                                    @if ($event->durasi_detik)
                                        <span class="mt-1 block text-sm text-slate-500">{{ $event->durasi_detik }} detik di luar layar</span>
                                    @endif
                                </td>
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
                            <th scope="col">Kunci / jawaban benar</th>
                            <th scope="col">Hasil</th>
                            <th scope="col">Poin soal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rincian as $baris)
                            <tr>
                                <td class="font-mono">{{ $baris['urutan'] }}</td>
                                <td>
                                    <p class="font-bold text-ink">{{ \Illuminate\Support\Str::limit($baris['teks'], 140) }}</p>
                                    <p class="mt-1 text-sm text-ink-muted">
                                        {{ $baris['tipe'] === 'essay' ? 'Essay' : 'Pilihan ganda' }} · Bobot {{ $baris['bobot'] }}
                                    </p>
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
                                        <p class="whitespace-pre-line text-ink-muted">{{ $baris['kunci_essay'] ?: '-' }}</p>
                                    @else
                                        {{ $baris['jawaban_benar'] ?? '-' }}
                                    @endif
                                </td>
                                <td>
                                    @if ($baris['tipe'] === 'essay')
                                        @if ($baris['dinilai_at'])
                                            <span class="pill pill-leaf">Dinilai</span>
                                        @else
                                            <span class="pill pill-sun">Belum dinilai</span>
                                        @endif
                                    @elseif ($baris['benar'])
                                        <span class="pill pill-leaf">Benar</span>
                                    @else
                                        <span class="pill pill-coral">Salah</span>
                                    @endif
                                </td>
                                <td class="font-mono">{{ $baris['skor'] }} / {{ $baris['bobot'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @php($barisEssay = $rincian->where('tipe', 'essay'))
    @if ($attempt->status === 'selesai' && $attempt->quizSession->mode === 'acak' && $barisEssay->isNotEmpty())
        <section class="mt-6 card" aria-labelledby="nilai-essay">
            <h2 id="nilai-essay" class="text-2xl">Penilaian essay</h2>
            <p class="mt-1 text-base text-ink-muted">
                Nilai essay dihitung 0 sampai bobot soal. Simpan untuk memperbarui poin dan nilai attempt.
            </p>

            @php($adaJawabanEssay = $barisEssay->contains(fn ($baris) => (string) $baris['jawaban_saya'] !== ''))

            @if (! $adaJawabanEssay)
                <p class="mt-4 text-base text-ink-muted">Peserta tidak menjawab soal essay mana pun.</p>
            @else
            <form method="POST" action="{{ route('admin.attempts.nilai', $attempt) }}" class="mt-4 space-y-4">
                @csrf

                @foreach ($barisEssay as $baris)
                    <div class="rounded-xl border border-outline p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="font-bold text-ink">Soal {{ $baris['urutan'] }} · maks {{ $baris['bobot'] }}</p>
                            @if ($baris['dinilai_at'])
                                <span class="pill pill-leaf">Sudah dinilai</span>
                            @else
                                <span class="pill pill-sun">Belum dinilai</span>
                            @endif
                        </div>
                        <p class="mt-1 text-base text-ink-muted whitespace-pre-line">{{ \Illuminate\Support\Str::limit($baris['teks'], 240) }}</p>
                        <p class="mt-3 whitespace-pre-line">{{ $baris['jawaban_saya'] ?: 'Tidak dijawab' }}</p>

                        @if ($baris['rubrik_essay'] !== [])
                            <div class="mt-3 border-y border-outline py-3">
                                <p class="text-sm font-bold text-ink">Rubrik penilaian</p>
                                <ul class="mt-2 space-y-1 text-sm text-ink-muted">
                                    @foreach ($baris['rubrik_essay'] as $criterion)
                                        <li class="flex justify-between gap-4">
                                            <span>{{ $criterion['label'] }}</span>
                                            <span class="flex-none font-mono text-ink">{{ $criterion['points'] }} poin</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($baris['jawaban_saya'])
                            <div class="mt-3 rounded-xl border border-outline bg-surface p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-bold text-ink">Saran penilaian AI</p>
                                    <button type="submit" form="essay-ai-{{ $baris['answer_id'] }}" class="btn btn-secondary min-h-11 text-xs">
                                        {{ $baris['ai_reviewed_at'] ? 'Buat ulang saran' : 'Minta saran AI' }}
                                    </button>
                                </div>
                                @if ($baris['ai_reviewed_at'])
                                    <p class="mt-2 text-sm text-ink">
                                        Saran nilai <strong>{{ $baris['ai_suggested_score'] }} / {{ $baris['bobot'] }}</strong>, confidence {{ $baris['ai_confidence'] }}%.
                                    </p>
                                    <p class="mt-1 whitespace-pre-line text-sm text-ink-muted">{{ $baris['ai_feedback'] }}</p>
                                    <p class="mt-2 text-xs text-ink-subtle">Nilai final tetap ditentukan admin melalui isian di bawah.</p>
                                @else
                                    <p class="mt-2 text-sm text-ink-muted">AI membaca pertanyaan, rubrik, dan jawaban peserta. Admin tetap memeriksa hasilnya.</p>
                                @endif
                            </div>
                        @endif

                        <div class="mt-3 max-w-xs">
                            <label for="nilai-{{ $baris['question_id'] }}" class="label">Nilai (0–{{ $baris['bobot'] }})</label>
                            <input
                                id="nilai-{{ $baris['question_id'] }}"
                                name="nilai[{{ $baris['question_id'] }}]"
                                type="number"
                                min="0"
                                max="{{ $baris['bobot'] }}"
                                value="{{ old('nilai.'.$baris['question_id'], $baris['skor']) }}"
                                class="input"
                                @if (! $baris['jawaban_saya']) disabled @endif
                                @error('nilai.'.$baris['question_id']) aria-invalid="true" @enderror
                            >
                            @error('nilai.'.$baris['question_id']) <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        @if ($baris['history']->isNotEmpty())
                            <details class="mt-4 border-t border-outline pt-3">
                                <summary class="cursor-pointer text-sm font-bold text-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand">
                                    Riwayat penilaian ({{ $baris['history']->count() }})
                                </summary>
                                <ol class="mt-3 divide-y divide-outline text-sm">
                                    @foreach ($baris['history'] as $log)
                                        <li class="grid gap-1 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:gap-4">
                                            <div>
                                                @if ($log->action === 'essay_score_changed')
                                                    <p class="font-bold text-ink">
                                                        Nilai final {{ $log->metadata['from'] === null ? 'ditetapkan' : 'diubah' }} menjadi {{ $log->metadata['to'] }}.
                                                    </p>
                                                    @if ($log->metadata['from'] !== null)
                                                        <p class="mt-1 text-ink-muted">Nilai sebelumnya {{ $log->metadata['from'] }}.</p>
                                                    @endif
                                                @else
                                                    <p class="font-bold text-ink">AI menyarankan {{ $log->metadata['score'] }} poin dengan confidence {{ $log->metadata['confidence'] }}%.</p>
                                                    <p class="mt-1 text-ink-muted">{{ $log->metadata['provider'] }} · {{ $log->metadata['model'] }}</p>
                                                @endif
                                            </div>
                                            <p class="text-xs text-ink-subtle sm:text-right">
                                                {{ $log->actor?->nama ?? 'Sistem' }}<br>
                                                <time datetime="{{ $log->created_at->timezone($orgTimezone)->toIso8601String() }}">{{ $log->created_at->timezone($orgTimezone)->format('d M Y, H:i') }}</time>
                                            </p>
                                        </li>
                                    @endforeach
                                </ol>
                            </details>
                        @endif
                    </div>
                @endforeach

                <button type="submit" class="btn btn-primary">Simpan penilaian</button>
            </form>
            @foreach ($barisEssay->whereNotNull('answer_id') as $baris)
                @if ($baris['jawaban_saya'])
                    <form id="essay-ai-{{ $baris['answer_id'] }}" method="POST" action="{{ route('admin.answers.ai-suggestion', $baris['answer_id']) }}" data-progress-message="Meminta saran penilaian AI...">
                        @csrf
                    </form>
                @endif
            @endforeach
            @endif
        </section>
    @endif

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
