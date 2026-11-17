<x-layouts.admin :judul="'Detail '.($attempt->user?->nama ?? 'peserta')" :keterangan="$attempt->quizSession->nama.' · '.$attempt->quizSession->quiz->judul">
    <x-slot:aksi>
        <a href="{{ route('admin.reports.session', $attempt->quiz_session_id) }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Laporan sesi
        </a>
    </x-slot:aksi>

    @php
        $adaSaran = $ruangPenilaian && $saranEssay['total'] > 0;
    @endphp

    <div class="grid gap-5 {{ $adaSaran ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-3' }}">
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
        @if ($adaSaran)
            <div class="card">
                <p class="text-base font-bold text-ink-muted">Saran AI</p>
                <div class="mt-2 flex flex-wrap items-end justify-between gap-2">
                    <p class="font-display text-2xl font-bold text-ink">{{ $saranEssay['tersedia'] }} dari {{ $saranEssay['total'] }} essay</p>
                    <p class="text-sm font-semibold text-ink-muted">{{ $saranEssay['persen'] }}%</p>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface-sunken" role="progressbar" aria-valuenow="{{ $saranEssay['persen'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Essay yang sudah punya saran AI">
                    <div class="h-full rounded-full bg-brand" style="width: {{ $saranEssay['persen'] }}%"></div>
                </div>
                <div class="mt-3 border-t border-outline pt-3">
                    <p class="text-xs font-bold text-ink-muted">Poin saran AI</p>
                    @if ($saranEssay['tersedia'] > 0)
                        <p class="mt-1 font-display text-xl font-bold text-ink">{{ $saranEssay['poin'] }} / {{ $saranEssay['poin_maksimal'] }}</p>
                    @else
                        <p class="mt-1 font-display text-xl font-bold text-ink">Belum ada</p>
                    @endif
                    <p class="mt-1 text-xs text-ink-subtle">Dari essay yang sudah disarankan. Bukan nilai final.</p>
                </div>
            </div>
        @endif
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

    @if ($ruangPenilaian)
        @php
            $statusEssay = [
                'graded' => ['label' => 'Sudah dinilai', 'simbol' => '✓', 'pill' => 'pill-leaf', 'warna' => 'border-leaf-soft bg-leaf-soft text-leaf-deep hover:border-leaf'],
                'pending' => ['label' => 'Belum dinilai', 'simbol' => '•', 'pill' => 'pill-sun', 'warna' => 'border-sun-soft bg-sun-soft text-sun-deep hover:border-sun'],
                'unanswered' => ['label' => 'Tidak dijawab', 'simbol' => '–', 'pill' => 'pill-neutral', 'warna' => 'border-outline bg-surface text-ink-muted hover:border-outline-strong'],
            ];
            $statusAktif = $statusEssay[$aktifEssay['status']];
            $nilaiAwal = old('nilai.'.$aktifEssay['question_id'], $aktifEssay['dinilai_at'] ? $aktifEssay['skor'] : '');
            $catatanAwal = old('catatan.'.$aktifEssay['question_id'], $aktifEssay['catatan_pengajar']);
            $labelPersen = $progresEssay['total'] - $progresEssay['unanswered'];
        @endphp

        <section
            id="penilaian-essay"
            class="mt-6 card"
            aria-labelledby="judul-ruang-penilaian"
            data-saran-tujuan='@json($saranTujuan)'
            x-data="{
                awal: '',
                kini: '',
                catatanAwal: '',
                catatanKini: '',
                saranTujuan: [],
                saranTotal: 0,
                saranMassal: false,
                saranBerhenti: false,
                saranSelesai: 0,
                saranGagal: 0,
                saranPesan: '',
                init() {
                    this.awal = this.kini = this.$refs.nilai?.value ?? '';
                    this.catatanAwal = this.catatanKini = this.$refs.catatan?.value ?? '';
                    this.saranTujuan = JSON.parse(this.$el.dataset.saranTujuan || '[]');
                    this.saranTotal = this.saranTujuan.length;
                },
                get kotor() {
                    return this.kini !== this.awal || this.catatanKini !== this.catatanAwal;
                },
                get saranPersen() {
                    return this.saranTotal > 0 ? Math.round((this.saranSelesai / this.saranTotal) * 100) : 0;
                },
                async mintaSemuaSaran() {
                    if (this.saranMassal || this.saranTujuan.length === 0) return;
                    if (this.kotor) {
                        this.saranPesan = 'Simpan penilaian manual dulu sebelum meminta saran semua.';
                        return;
                    }

                    this.saranMassal = true;
                    this.saranBerhenti = false;
                    this.saranSelesai = 0;
                    this.saranGagal = 0;
                    this.saranPesan = '';
                    this.saranTotal = this.saranTujuan.length;
                    let pesanTerakhir = '';

                    for (const tujuan of [...this.saranTujuan]) {
                        if (this.saranBerhenti) break;

                        let hasil;
                        try {
                            hasil = await window.kuisFetch(tujuan.url, {
                                method: 'POST',
                                progress: false,
                                headers: { Accept: 'application/json' },
                            });
                        } catch {
                            this.saranMassal = false;
                            this.saranPesan = 'Koneksi terputus saat meminta saran. Saran yang sudah jadi tetap tersimpan.';
                            return;
                        }

                        if (!hasil.ok || !hasil.data?.ok) {
                            const pesan = hasil.data?.pesan ?? 'Saran AI gagal dibuat.';
                            if (!hasil.ok || hasil.data?.transient) {
                                this.saranMassal = false;
                                this.saranPesan = pesan;
                                return;
                            }

                            this.saranGagal++;
                            pesanTerakhir = pesan;
                            this.saranTujuan = this.saranTujuan.filter((item) => item.id !== tujuan.id);
                            if (this.saranBerhenti) break;

                            continue;
                        }

                        this.saranSelesai++;
                        this.saranTujuan = this.saranTujuan.filter((item) => item.id !== tujuan.id);
                        if (this.saranBerhenti) break;
                    }

                    this.saranMassal = false;

                    if (this.saranBerhenti) {
                        const lewat = this.saranGagal > 0 ? ` ${this.saranGagal} essay dilewati.` : '';
                        this.saranPesan = `${this.saranSelesai} saran dibuat.${lewat} Proses dihentikan. Saran yang sudah jadi tetap tersimpan.`;
                        return;
                    }

                    if (this.saranGagal > 0) {
                        this.saranPesan = `${this.saranSelesai} saran dibuat, ${this.saranGagal} essay dilewati. ${pesanTerakhir}`;
                        return;
                    }

                    if (this.kotor) {
                        this.saranPesan = 'Saran sudah dibuat. Simpan penilaian manual dulu, lalu muat ulang halaman.';
                        return;
                    }

                    window.location.reload();
                },
                hentikanSaran() {
                    if (! this.saranMassal) return;
                    this.saranBerhenti = true;
                },
                cekKeluar(event) {
                    if (! this.kotor) return;
                    event.preventDefault();
                    event.returnValue = '';
                }
            }"
            @beforeunload.window="cekKeluar($event)"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="judul-ruang-penilaian" class="text-2xl">Ruang penilaian essay</h2>
                    <p class="mt-1 max-w-2xl text-base text-ink-muted">
                        Satu soal per layar. Pilih nomor soal, simpan nilainya, lalu lanjut ke soal berikutnya tanpa menggulir seluruh jawaban.
                    </p>
                </div>
                <div class="flex flex-col items-start gap-2 sm:items-end">
                    <p class="pill pill-neutral">{{ $progresEssay['graded'] }} dari {{ $progresEssay['total'] }} soal dinilai</p>
                    @if ($saranEssay['tanpa'] > 0)
                        <button
                            type="button"
                            class="btn btn-secondary min-h-11 text-xs"
                            x-cloak
                            x-show="saranTujuan.length > 0"
                            @click="mintaSemuaSaran"
                            :disabled="saranMassal || kotor"
                            :aria-busy="saranMassal"
                        >
                            <span x-show="!saranMassal" x-text="`Minta saran ${saranTujuan.length} essay`"></span>
                            <span x-show="saranMassal" x-text="`Membuat saran ${saranSelesai} dari ${saranTotal}...`"></span>
                        </button>
                        <p x-show="kotor" x-cloak class="text-xs text-ink-subtle">Simpan penilaian manual dulu sebelum meminta saran semua.</p>
                    @endif
                </div>
            </div>

            <div x-show="saranMassal" x-cloak class="mt-4 rounded-xl border border-outline bg-surface p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-ink">Membuat saran AI</p>
                        <p class="mt-1 text-sm text-ink-muted" aria-live="polite" x-text="`${saranSelesai} dari ${saranTotal} saran selesai`"></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <p class="font-display text-2xl font-bold text-ink" x-text="`${saranPersen}%`"></p>
                        <button type="button" class="btn btn-ghost min-h-11 text-xs" @click="hentikanSaran" :disabled="saranBerhenti">
                            <span x-text="saranBerhenti ? 'Menghentikan...' : 'Hentikan'"></span>
                        </button>
                    </div>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-surface-sunken" role="progressbar" :aria-valuenow="saranPersen" aria-valuemin="0" aria-valuemax="100" aria-label="Progres pembuatan saran AI">
                    <div class="h-full rounded-full bg-brand transition-[width] duration-300 ease-out" :style="`width: ${saranPersen}%`"></div>
                </div>
                <p class="mt-2 text-xs text-ink-subtle">Saran yang sudah jadi tetap tersimpan. Hentikan baru berlaku setelah saran yang sedang diproses selesai.</p>
            </div>

            <x-alert jenis="warning" x-show="saranPesan" x-cloak class="mt-3" aria-live="polite">
                <p x-text="saranPesan"></p>
                <button type="button" class="btn btn-secondary mt-2 min-h-11 text-xs" x-show="!saranMassal && saranSelesai > 0" :disabled="kotor" @click="window.location.reload()">Muat ulang</button>
            </x-alert>

            <div
                class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4"
                data-hitung-dinilai="{{ $progresEssay['graded'] }}"
                data-hitung-pending="{{ $progresEssay['pending'] }}"
                data-hitung-kosong="{{ $progresEssay['unanswered'] }}"
            >
                <div class="rounded-xl border border-outline bg-surface px-4 py-3">
                    <p class="text-sm font-bold text-ink-muted">Sudah dinilai</p>
                    <p class="display-number mt-1 text-2xl text-leaf-deep">{{ $progresEssay['graded'] }}</p>
                </div>
                <div class="rounded-xl border border-outline bg-surface px-4 py-3">
                    <p class="text-sm font-bold text-ink-muted">Belum dinilai</p>
                    <p class="display-number mt-1 text-2xl text-sun-deep">{{ $progresEssay['pending'] }}</p>
                </div>
                <div class="rounded-xl border border-outline bg-surface px-4 py-3">
                    <p class="text-sm font-bold text-ink-muted">Tidak dijawab</p>
                    <p class="display-number mt-1 text-2xl text-ink-muted">{{ $progresEssay['unanswered'] }}</p>
                </div>
                <div class="rounded-xl border border-outline bg-surface px-4 py-3">
                    <p class="text-sm font-bold text-ink-muted">Progress</p>
                    <p class="mt-1 font-display text-2xl font-bold text-ink">{{ $progresEssay['persen'] }}%</p>
                    <div class="progress mt-2" aria-hidden="true">
                        <span style="width: {{ $progresEssay['persen'] }}%"></span>
                    </div>
                </div>
            </div>

            <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,15rem)_minmax(0,1fr)]">
                <div class="min-w-0">
                    <div class="rounded-card border border-outline bg-page p-3 lg:sticky lg:top-4">
                        <p class="text-sm font-bold text-ink">Soal essay</p>
                        <nav
                            data-essay-nav
                            aria-label="Navigator soal essay"
                            class="mt-2 flex gap-2 overflow-x-auto pb-1 lg:grid lg:grid-cols-4 lg:overflow-visible"
                        >
                            @foreach ($essayRincian as $navigasi)
                                @php
                                    $navigasiStatus = $statusEssay[$navigasi['status']];
                                    $navigasiAktif = $navigasi['question_id'] === $aktifEssay['question_id'];
                                @endphp
                                <a
                                    href="{{ route('admin.reports.attempt', ['attempt' => $attempt, 'soal' => $navigasi['question_id']]) }}#penilaian-essay"
                                    data-essay-soal="{{ $navigasi['question_id'] }}"
                                    data-essay-status="{{ $navigasi['status'] }}"
                                    aria-label="Soal {{ $navigasi['urutan'] }}: {{ $navigasiStatus['label'] }}"
                                    @if ($navigasiAktif) aria-current="page" @endif
                                    class="inline-flex h-11 w-11 flex-none flex-col items-center justify-center rounded-xl border-2 font-mono text-sm font-bold transition {{ $navigasiAktif ? 'border-brand-deep bg-brand-deep text-white ring-2 ring-brand ring-offset-2' : $navigasiStatus['warna'] }}"
                                >
                                    <span aria-hidden="true" class="leading-none">{{ $navigasi['urutan'] }}</span>
                                    <span aria-hidden="true" class="text-[11px] leading-none">{{ $navigasiStatus['simbol'] }}</span>
                                </a>
                            @endforeach
                        </nav>
                        <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm text-ink-muted">
                            <span><span aria-hidden="true" class="font-bold text-leaf-deep">✓</span> Sudah dinilai</span>
                            <span><span aria-hidden="true" class="font-bold text-sun-deep">•</span> Belum dinilai</span>
                            <span><span aria-hidden="true" class="font-bold">–</span> Tidak dijawab</span>
                        </p>
                        <p class="mt-2 text-sm text-ink-muted">
                            {{ $progresEssay['graded'] }} dari {{ $labelPersen }} soal yang perlu dinilai sudah selesai.
                        </p>
                    </div>
                </div>

                <div class="min-w-0">
                    <article data-active-essay="{{ $aktifEssay['question_id'] }}" class="rounded-card border border-outline bg-page p-4 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-display text-xl font-bold text-ink">Soal {{ $aktifEssay['urutan'] }}</p>
                                <p class="text-sm text-ink-muted">Bobot maksimal {{ $aktifEssay['bobot'] }} poin</p>
                            </div>
                            <span class="pill {{ $statusAktif['pill'] }}">
                                <span aria-hidden="true">{{ $statusAktif['simbol'] }}</span>
                                {{ $statusAktif['label'] }}
                            </span>
                        </div>

                        <p class="mt-3 whitespace-pre-line text-base text-ink">{{ $aktifEssay['teks'] }}</p>
                        {!! $aktifEssay['content_html'] ?? '' !!}

                        <div class="mt-3 rounded-xl border border-outline p-3">
                            <p class="text-sm font-bold text-ink">Jawaban peserta</p>

                            @if (! empty($aktifEssay['sub_soal']))
                                <div class="mt-2 space-y-3">
                                    @foreach ($aktifEssay['sub_soal'] as $bagian)
                                        <div class="rounded-xl border border-outline bg-page p-3">
                                            <p class="text-sm font-bold text-ink">{{ $aktifEssay['urutan'] }}{{ $bagian['code'] }}. {{ $bagian['teks'] }}</p>
                                            {!! $bagian['content_html'] ?? '' !!}
                                            @if ($bagian['is_leaf'])
                                                @if (isset($aktifEssay['jawaban_data'][$bagian['key']]) || filled($aktifEssay['jawaban_parts'][$bagian['key']] ?? null))
                                                    {!! \App\Support\QuestionContent::renderAnswer($bagian['answer_format'], $bagian['answer_config'], $aktifEssay['jawaban_data'][$bagian['key']] ?? null, $aktifEssay['jawaban_parts'][$bagian['key']] ?? null) !!}
                                                @else
                                                    <p class="mt-1 text-ink-muted">Tidak dijawab</p>
                                                @endif
                                            @else
                                                <p class="mt-1 text-sm text-ink-muted">Jawaban tersedia pada bagian turunannya.</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @elseif (filled($aktifEssay['jawaban_saya']))
                                <div class="mt-2">
                                    {!! \App\Support\QuestionContent::renderAnswer($aktifEssay['answer_format'], $aktifEssay['answer_config'], $aktifEssay['jawaban_data']['__main'] ?? null, $aktifEssay['jawaban_saya']) !!}
                                </div>
                            @else
                                <p class="mt-2 text-ink-muted">Tidak dijawab</p>
                            @endif
                        </div>

                        <div class="mt-3 rounded-xl border border-outline bg-surface p-3">
                            <p class="text-sm font-bold text-ink">Kunci jawaban</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-ink-muted">{{ $aktifEssay['kunci_essay'] ?: 'Belum ada kunci jawaban untuk soal ini.' }}</p>
                        </div>

                        @if ($aktifEssay['rubrik_essay'] !== [])
                            <div class="mt-3 border-y border-outline py-3">
                                <p class="text-sm font-bold text-ink">Rubrik penilaian</p>
                                <ul class="mt-2 space-y-1 text-sm text-ink-muted">
                                    @foreach ($aktifEssay['rubrik_essay'] as $criterion)
                                        <li class="flex justify-between gap-4">
                                            <span>{{ $criterion['label'] }}</span>
                                            <span class="flex-none font-mono text-ink">{{ $criterion['points'] }} poin</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (filled($aktifEssay['jawaban_saya']))
                            <div class="mt-3 rounded-xl border border-outline bg-surface p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-bold text-ink">Saran AI</p>
                                        <p class="text-xs text-ink-subtle">Bukan nilai final. Hanya terlihat oleh admin.</p>
                                    </div>
                                    <button type="submit" form="essay-ai-{{ $aktifEssay['answer_id'] }}" class="btn btn-secondary min-h-11 text-xs">
                                        {{ $aktifEssay['ai_reviewed_at'] ? 'Buat ulang saran' : 'Minta saran AI' }}
                                    </button>
                                </div>
                                @if ($aktifEssay['ai_reviewed_at'])
                                    <p class="mt-2 text-sm text-ink">
                                        Saran nilai <strong>{{ $aktifEssay['ai_suggested_score'] }} / {{ $aktifEssay['bobot'] }}</strong>, keyakinan {{ $aktifEssay['ai_confidence'] }}%.
                                    </p>
                                    <p class="mt-1 whitespace-pre-line text-sm text-ink-muted">{{ $aktifEssay['ai_feedback'] }}</p>
                                    <p class="mt-2 text-xs text-ink-subtle">Nilai final tetap diisi manual di bagian Penilaian manual.</p>
                                @else
                                    <p class="mt-2 text-sm text-ink-muted">Saran ini hanya untuk admin. Nilai final tetap diisi manual.</p>
                                @endif
                            </div>
                        @endif

                        @if ($aktifEssay['history']->isNotEmpty())
                            <details class="mt-4 border-t border-outline pt-3">
                                <summary class="cursor-pointer text-sm font-bold text-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand">
                                    Riwayat penilaian ({{ $aktifEssay['history']->count() }})
                                </summary>
                                <ol class="mt-3 divide-y divide-outline text-sm">
                                    @foreach ($aktifEssay['history'] as $log)
                                        @php
                                            $meta = $log->metadata;
                                            $aiProvider = $meta['ai_provider'] ?? null;
                                            $aiModel = $meta['ai_model'] ?? null;

                                            if ($log->action === 'essay_score_changed' && (blank($aiProvider) || blank($aiModel))) {
                                                $saran = $aktifEssay['history']->first(fn ($item) => $item->action === 'essay_ai_suggested' && $item->created_at->lte($log->created_at));
                                                $aiProvider = $aiProvider ?: ($saran?->metadata['provider'] ?? null);
                                                $aiModel = $aiModel ?: ($saran?->metadata['model'] ?? null);
                                            }
                                        @endphp
                                        <li class="grid gap-1 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:gap-4">
                                            <div>
                                                @if ($log->action === 'essay_score_changed')
                                                    <p class="font-bold text-ink">
                                                        Nilai final {{ ($meta['from'] ?? null) === null ? 'ditetapkan' : 'diubah' }} menjadi {{ $meta['to'] }}.
                                                    </p>
                                                    @if (($meta['from'] ?? null) !== null)
                                                        <p class="mt-1 text-ink-muted">Nilai sebelumnya {{ $meta['from'] }}.</p>
                                                    @endif
                                                    @if ($meta['auto'] ?? false)
                                                        <p class="mt-1 text-ink-muted">Ditetapkan otomatis oleh sistem setelah saran AI.</p>
                                                    @endif
                                                    @if (($meta['ai_suggested_score'] ?? null) !== null)
                                                        <p class="mt-1 text-ink-muted">
                                                            Saran AI {{ $meta['ai_suggested_score'] }} poin
                                                            @if (filled($aiProvider) || filled($aiModel))
                                                                · {{ $aiProvider ?: 'AI' }}@if (filled($aiModel)) · {{ $aiModel }}@endif
                                                            @endif
                                                        </p>
                                                    @endif
                                                @else
                                                    <p class="font-bold text-ink">AI menyarankan {{ $meta['score'] }} poin dengan confidence {{ $meta['confidence'] }}%.</p>
                                                    <p class="mt-1 text-ink-muted">{{ $meta['provider'] ?: 'AI' }} · {{ $meta['model'] ?: '-' }}</p>
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

                        <div class="mt-4 rounded-xl border border-outline bg-surface p-3">
                            <p class="text-sm font-bold text-ink">Penilaian manual</p>
                            <p class="mt-1 text-xs text-ink-muted">Nilai final dan catatan untuk peserta.</p>

                            <form
                                method="POST"
                                action="{{ route('admin.attempts.nilai', $attempt) }}"
                                class="mt-3"
                                data-progress-message="Menyimpan nilai essay..."
                                @submit="awal = kini; catatanAwal = catatanKini"
                            >
                                @csrf
                                <input type="hidden" name="focus_question_id" value="{{ $aktifEssay['question_id'] }}">

                                <div class="max-w-xs">
                                    <label for="nilai-{{ $aktifEssay['question_id'] }}" class="label">Nilai final (0 sampai {{ $aktifEssay['bobot'] }})</label>
                                    <input
                                        id="nilai-{{ $aktifEssay['question_id'] }}"
                                        name="nilai[{{ $aktifEssay['question_id'] }}]" type="number" min="0" max="{{ $aktifEssay['bobot'] }}" value="{{ $nilaiAwal }}"
                                        class="input"
                                        inputmode="numeric"
                                        x-ref="nilai"
                                        @input="kini = $event.target.value"
                                        @disabled(! $aktifEssay['dijawab'])
                                        @error('nilai.'.$aktifEssay['question_id']) aria-invalid="true" @enderror
                                    >
                                    @error('nilai.'.$aktifEssay['question_id']) <p class="field-error">{{ $message }}</p> @enderror
                                    @if (! $aktifEssay['dijawab'])
                                        <p class="help mt-1">Peserta tidak menjawab soal ini. Nilai tidak diperlukan.</p>
                                    @endif
                                </div>

                                <div class="mt-4">
                                    <label for="catatan-{{ $aktifEssay['question_id'] }}" class="label">Catatan untuk peserta</label>
                                    <textarea
                                        id="catatan-{{ $aktifEssay['question_id'] }}"
                                        name="catatan[{{ $aktifEssay['question_id'] }}]"
                                        rows="3"
                                        maxlength="2000"
                                        class="input"
                                        aria-describedby="bantuan-catatan-{{ $aktifEssay['question_id'] }}"
                                        x-ref="catatan"
                                        @input="catatanKini = $event.target.value"
                                        @disabled(! $aktifEssay['dijawab'])
                                        @error('catatan.'.$aktifEssay['question_id']) aria-invalid="true" @enderror
                                    >{{ $catatanAwal }}</textarea>
                                    <p id="bantuan-catatan-{{ $aktifEssay['question_id'] }}" class="help mt-1">Tampil ke peserta setelah semua essay dinilai. Kosongkan untuk menghapus.</p>
                                    @error('catatan.'.$aktifEssay['question_id']) <p class="field-error">{{ $message }}</p> @enderror
                                </div>

                                <div class="mt-5 flex flex-wrap items-center gap-2" data-nav-sebelumnya="{{ $essaySebelumnya['question_id'] ?? 'tidak-ada' }}">
                                    @if ($essaySebelumnya)
                                        <a href="{{ route('admin.reports.attempt', ['attempt' => $attempt, 'soal' => $essaySebelumnya['question_id']]) }}#penilaian-essay" class="btn btn-secondary">
                                            Sebelumnya
                                        </a>
                                    @else
                                        <button type="button" class="btn btn-secondary" disabled>Sebelumnya</button>
                                    @endif
                                    <button type="submit" name="after_save" value="stay" class="btn btn-primary">Simpan nilai</button>
                                    <button type="submit" name="after_save" value="next" class="btn btn-secondary">Simpan &amp; berikutnya</button>
                                </div>
                            </form>
                        </div>

                        @if ($progresEssay['pending'] === 0 && $pesertaBerikutnya)
                            <div data-peserta-berikutnya="{{ $pesertaBerikutnya->id }}" class="mt-5 rounded-xl border border-leaf bg-leaf-soft p-4">
                                <p class="font-bold text-ink">Semua jawaban essay terisi sudah dinilai.</p>
                                <p class="mt-1 text-sm text-ink-muted">Lanjut ke peserta lain di sesi ini yang masih punya essay belum dinilai.</p>
                                <a href="{{ route('admin.reports.attempt', $pesertaBerikutnya).'#penilaian-essay' }}" class="btn btn-primary mt-3">Peserta berikutnya</a>
                            </div>
                        @endif
                    </article>

                    @if ($aktifEssay['answer_id'] && filled($aktifEssay['jawaban_saya']))
                        <form id="essay-ai-{{ $aktifEssay['answer_id'] }}" method="POST" action="{{ route('admin.answers.ai-suggestion', $aktifEssay['answer_id']) }}" data-progress-message="Meminta saran AI..." class="hidden">
                            @csrf
                        </form>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <details class="mt-6 card" @if (! $ruangPenilaian) open @endif>
        <summary class="cursor-pointer font-display text-2xl font-bold text-ink">Rincian seluruh jawaban</summary>
        <p class="mt-1 text-base text-ink-muted">Berisi semua soal, termasuk pilihan ganda. Buka untuk memeriksa jawaban di luar ruang penilaian.</p>

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
                                    {!! $baris['content_html'] ?? '' !!}
                                    <p class="mt-1 text-sm text-ink-muted">
                                        {{ $baris['tipe'] === 'essay' ? 'Essay' : 'Pilihan ganda' }} · Bobot {{ $baris['bobot'] }}
                                    </p>
                                </td>
                                <td>
                                    @if ($baris['tipe'] === 'essay' && ! empty($baris['sub_soal']))
                                        <div class="space-y-3">
                                            @foreach ($baris['sub_soal'] as $bagian)
                                                <div>
                                                    <p class="text-sm font-bold text-ink">{{ $baris['urutan'] }}{{ $bagian['code'] }}. {{ $bagian['teks'] }}</p>
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
    </details>

    <section class="mt-6 card border-coral" aria-labelledby="reset-attempt" x-data="{ resetMode: 'fresh' }">
        <h2 id="reset-attempt" class="text-2xl">Ulangi attempt</h2>
        <p class="mt-2 text-base text-ink-muted">
            Gunakan untuk gangguan teknis. Mulai dari awal menghapus pekerjaan; lanjut dari jawaban terakhir mempertahankan isi jawaban dan urutan soal.
        </p>

        <form method="POST" action="{{ route('admin.attempts.reset', $attempt) }}" class="mt-4 space-y-4" data-progress-message="Membuka ulang attempt peserta...">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="rounded-xl border-2 border-outline p-4 has-[:checked]:border-coral">
                    <span class="flex items-start gap-3">
                        <input type="radio" name="reset_mode" value="fresh" x-model="resetMode" class="mt-1 h-5 w-5" checked>
                        <span><span class="block font-bold">Mulai dari awal</span><span class="mt-1 block text-sm text-ink-muted">Hapus seluruh pekerjaan dan log attempt.</span></span>
                    </span>
                </label>
                <label class="rounded-xl border-2 border-outline p-4 has-[:checked]:border-brand {{ $attempt->quizSession->mode !== 'acak' || $attempt->quizSession->status !== 'berjalan' ? 'opacity-60' : '' }}">
                    <span class="flex items-start gap-3">
                        <input type="radio" name="reset_mode" value="resume" x-model="resetMode" class="mt-1 h-5 w-5" @disabled($attempt->quizSession->mode !== 'acak' || $attempt->quizSession->status !== 'berjalan')>
                        <span><span class="block font-bold">Lanjut dari jawaban terakhir</span><span class="mt-1 block text-sm text-ink-muted">Pertahankan jawaban dan urutan soal; nilai dihitung ulang.</span></span>
                    </span>
                </label>
            </div>

            <div x-show="resetMode === 'fresh'" x-cloak class="max-w-sm">
                <label for="konfirmasi" class="label">Ketik HAPUS</label>
                <input id="konfirmasi" name="konfirmasi" type="text" class="input" autocomplete="off" placeholder="HAPUS"
                    x-bind:required="resetMode === 'fresh'" x-bind:disabled="resetMode !== 'fresh'">
            </div>
            <div x-show="resetMode === 'resume'" x-cloak class="max-w-sm">
                <label for="retry_duration_minutes" class="label">Durasi baru (menit)</label>
                <input id="retry_duration_minutes" name="retry_duration_minutes" type="number" min="1" max="600" value="60" class="input"
                    x-bind:required="resetMode === 'resume'" x-bind:disabled="resetMode !== 'resume'">
            </div>
            <div class="max-w-xl">
                <label for="catatan" class="label">Catatan (opsional)</label>
                <input id="catatan" name="catatan" type="text" class="input" maxlength="500" placeholder="Contoh: PC peserta mati saat soal 4">
            </div>
            <button type="submit" class="btn btn-primary">Buka ulang attempt</button>
        </form>
    </section>
</x-layouts.admin>
