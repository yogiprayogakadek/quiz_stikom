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
            data-audit-tujuan='@json($auditTujuan)'
            x-data="{
                awal: '',
                kini: '',
                catatanAwal: '',
                catatanKini: '',
                saranTujuan: [],
                auditTujuan: [],
                saranTotal: 0,
                saranMassal: false,
                saranBerhenti: false,
                saranSelesai: 0,
                saranGagal: 0,
                saranPesan: '',
                modeSaran: 'seimbang',
                terminalLogs: [],
                init() {
                    this.awal = this.kini = this.$refs.nilai?.value ?? '';
                    this.catatanAwal = this.catatanKini = this.$refs.catatan?.value ?? '';
                    this.saranTujuan = JSON.parse(this.$el.dataset.saranTujuan || '[]');
                    this.auditTujuan = JSON.parse(this.$el.dataset.auditTujuan || '[]');
                    this.saranTotal = this.saranTujuan.length;
                },
                tambahLog(tipe, pesan) {
                    const d = new Date();
                    const waktu = [d.getHours(), d.getMinutes(), d.getSeconds()]
                        .map((n) => String(n).padStart(2, '0'))
                        .join(':');
                    this.terminalLogs.push({ waktu, tipe, pesan });
                    this.$nextTick(() => {
                        const box = this.$refs.terminalBox;
                        if (box) box.scrollTop = box.scrollHeight;
                    });
                },
                get kotor() {
                    return this.kini !== this.awal || this.catatanKini !== this.catatanAwal;
                },
                get saranPersen() {
                    return this.saranTotal > 0 ? Math.round((this.saranSelesai / this.saranTotal) * 100) : 0;
                },
                terapkanMode(mode, score, feedback) {
                    this.kini = score;
                    if (this.$refs.nilai) {
                        this.$refs.nilai.value = score;
                    }
                    if (this.$refs.catatan && feedback) {
                        this.$refs.catatan.value = feedback;
                        this.catatanKini = feedback;
                    }
                },
                async auditSemuaEssay() {
                    if (this.saranMassal || this.auditTujuan.length === 0) return;
                    if (this.kotor) return;

                    const dialog = this.$refs.dialogSaran;
                    if (dialog.open) dialog.close();

                    this.saranMassal = true;
                    this.saranBerhenti = false;
                    this.saranSelesai = 0;
                    this.saranGagal = 0;
                    this.saranPesan = '';
                    this.saranTotal = this.auditTujuan.length;
                    this.terminalLogs = [];
                    this.tambahLog('info', `Memulai audit & evaluasi untuk ${this.saranTotal} butir soal essay...`);
                    dialog.showModal();
                    let pesanTerakhir = '';

                    for (const tujuan of [...this.auditTujuan]) {
                        if (this.saranBerhenti) break;

                        const t0 = performance.now();
                        const labelSoal = tujuan.urutan ? `Soal #${tujuan.urutan}` : `Jawaban #${tujuan.id}`;
                        this.tambahLog('req', `[POST] Memproses ${labelSoal} (ID: ${tujuan.id})...`);

                        let hasil;
                        try {
                            hasil = await window.kuisFetch(tujuan.url, {
                                method: 'POST',
                                progress: false,
                                headers: { Accept: 'application/json' },
                            });
                        } catch {
                            const durasi = ((performance.now() - t0) / 1000).toFixed(1);
                            this.tambahLog('err', `[ERR] ${labelSoal} gagal (${durasi}s): Koneksi terputus.`);
                            this.saranMassal = false;
                            this.saranPesan = 'Koneksi terputus saat audit & saran. Data yang sudah diproses tetap tersimpan.';
                            return;
                        }

                        if (!hasil.ok || !hasil.data?.ok) {
                            const durasi = ((performance.now() - t0) / 1000).toFixed(1);
                            const pesan = hasil.data?.pesan ?? 'Audit & saran gagal diproses.';
                            this.tambahLog('err', `[ERR] ${labelSoal} gagal (${durasi}s): ${pesan}`);
                            if (!hasil.ok || hasil.data?.transient) {
                                this.saranMassal = false;
                                this.saranPesan = pesan;
                                return;
                            }

                            this.saranGagal++;
                            pesanTerakhir = pesan;
                            this.auditTujuan = this.auditTujuan.filter((item) => item.id !== tujuan.id);
                            if (this.saranBerhenti) break;

                            continue;
                        }

                        const durasi = ((performance.now() - t0) / 1000).toFixed(1);
                        const det = hasil.data?.ai_detection?.score !== undefined ? `${hasil.data.ai_detection.score}% AI` : '-';
                        const sug = hasil.data?.suggestions
                            ? `[K:${hasil.data.suggestions.ketat?.score}, S:${hasil.data.suggestions.seimbang?.score}, L:${hasil.data.suggestions.longgar?.score}]`
                            : '';
                        const modelInfo = hasil.data?.model ? ` | ${hasil.data.model}` : '';
                        this.tambahLog('ok', `[OK] ${labelSoal} selesai (${durasi}s) | Deteksi: ${det} | Skor: ${sug}${modelInfo}`);

                        this.saranSelesai++;
                        this.auditTujuan = this.auditTujuan.filter((item) => item.id !== tujuan.id);
                        if (this.saranBerhenti) break;
                    }

                    this.saranMassal = false;

                    if (this.saranBerhenti) {
                        this.tambahLog('info', `Proses dihentikan pengguna. ${this.saranSelesai} selesai.`);
                        const lewat = this.saranGagal > 0 ? ` ${this.saranGagal} essay dilewati.` : '';
                        this.saranPesan = `${this.saranSelesai} selesai.${lewat} Proses dihentikan. Data yang sudah diproses tetap tersimpan.`;
                        return;
                    }

                    if (this.saranGagal > 0) {
                        this.tambahLog('info', `Proses selesai dengan catatan: ${this.saranSelesai} selesai, ${this.saranGagal} dilewati.`);
                        this.saranPesan = `${this.saranSelesai} selesai, ${this.saranGagal} essay dilewati. ${pesanTerakhir}`;
                        return;
                    }

                    this.tambahLog('info', `Semua ${this.saranSelesai} soal berhasil diaudit & dievaluasi.`);

                    if (this.kotor) {
                        this.saranPesan = 'Audit & saran selesai. Simpan penilaian manual dulu, lalu muat ulang halaman.';
                        return;
                    }

                    window.location.reload();
                },
                async mintaSemuaSaran() {
                    if (this.saranMassal || this.saranTujuan.length === 0) return;
                    if (this.kotor) return;

                    const mode = this.modeSaran;
                    const dialog = this.$refs.dialogSaran;
                    if (dialog.open) dialog.close();

                    this.saranMassal = true;
                    this.saranBerhenti = false;
                    this.saranSelesai = 0;
                    this.saranGagal = 0;
                    this.saranPesan = '';
                    this.saranTotal = this.saranTujuan.length;
                    this.terminalLogs = [];
                    this.tambahLog('info', `Memulai pembuatan saran mode ${mode} untuk ${this.saranTotal} butir soal...`);
                    dialog.showModal();
                    let pesanTerakhir = '';

                    for (const tujuan of [...this.saranTujuan]) {
                        if (this.saranBerhenti) break;

                        const t0 = performance.now();
                        const labelSoal = tujuan.urutan ? `Soal #${tujuan.urutan}` : `Jawaban #${tujuan.id}`;
                        this.tambahLog('req', `[POST] Meminta saran ${labelSoal} (ID: ${tujuan.id})...`);

                        let hasil;
                        try {
                            hasil = await window.kuisFetch(tujuan.url, {
                                method: 'POST',
                                progress: false,
                                headers: { Accept: 'application/json' },
                                body: { mode },
                            });
                        } catch {
                            const durasi = ((performance.now() - t0) / 1000).toFixed(1);
                            this.tambahLog('err', `[ERR] ${labelSoal} gagal (${durasi}s): Koneksi terputus.`);
                            this.saranMassal = false;
                            this.saranPesan = 'Koneksi terputus saat meminta saran. Saran yang sudah jadi tetap tersimpan.';
                            return;
                        }

                        if (!hasil.ok || !hasil.data?.ok) {
                            const durasi = ((performance.now() - t0) / 1000).toFixed(1);
                            const pesan = hasil.data?.pesan ?? 'Saran AI gagal dibuat.';
                            this.tambahLog('err', `[ERR] ${labelSoal} gagal (${durasi}s): ${pesan}`);
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

                        const durasi = ((performance.now() - t0) / 1000).toFixed(1);
                        const skorInfo = hasil.data?.score !== undefined ? ` | Skor: ${hasil.data.score}` : '';
                        const confInfo = hasil.data?.confidence !== undefined ? ` (${hasil.data.confidence}% yakin)` : '';
                        this.tambahLog('ok', `[OK] ${labelSoal} selesai (${durasi}s)${skorInfo}${confInfo}`);

                        this.saranSelesai++;
                        this.saranTujuan = this.saranTujuan.filter((item) => item.id !== tujuan.id);
                        if (this.saranBerhenti) break;
                    }

                    this.saranMassal = false;

                    if (this.saranBerhenti) {
                        this.tambahLog('info', `Proses dihentikan pengguna. ${this.saranSelesai} selesai.`);
                        const lewat = this.saranGagal > 0 ? ` ${this.saranGagal} essay dilewati.` : '';
                        this.saranPesan = `${this.saranSelesai} saran dibuat.${lewat} Proses dihentikan. Saran yang sudah jadi tetap tersimpan.`;
                        return;
                    }

                    if (this.saranGagal > 0) {
                        this.tambahLog('info', `Proses selesai: ${this.saranSelesai} dibuat, ${this.saranGagal} dilewati.`);
                        this.saranPesan = `${this.saranSelesai} saran dibuat, ${this.saranGagal} essay dilewati. ${pesanTerakhir}`;
                        return;
                    }

                    this.tambahLog('info', `Semua ${this.saranSelesai} saran berhasil dibuat.`);

                    if (this.kotor) {
                        this.saranPesan = 'Saran sudah dibuat. Simpan penilaian manual dulu, lalu muat ulang halaman.';
                        return;
                    }

                    window.location.reload();
                },
                cekTutupSaran(event) {
                    if (! this.saranMassal) return;
                    event.preventDefault();
                    this.hentikanSaran();
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
                <div class="flex w-full flex-col items-start gap-2 sm:w-auto sm:items-end">
                    <p class="pill pill-neutral">{{ $progresEssay['graded'] }} dari {{ $progresEssay['total'] }} soal dinilai</p>
                    <div class="w-full sm:w-56">
                        <label for="mode-saran-ai" class="label">Mode saran AI</label>
                        <select id="mode-saran-ai" x-model="modeSaran" class="input min-h-11" :disabled="saranMassal" aria-describedby="bantuan-mode-saran-ai">
                            <option value="ketat">Ketat</option>
                            <option value="seimbang" selected>Seimbang</option>
                            <option value="longgar">Longgar</option>
                        </select>
                        <p id="bantuan-mode-saran-ai" class="help mt-1">Ketat menilai hanya poin yang jelas memenuhi rubrik. Longgar lebih toleran pada detail kecil.</p>
                    </div>
                    @if ($auditTujuan->isNotEmpty())
                        <button
                            type="button"
                            class="btn btn-primary min-h-11 text-xs"
                            @click="auditSemuaEssay"
                            :disabled="saranMassal || kotor"
                            :aria-busy="saranMassal"
                        >
                            <x-icon name="sparkles" :size="14" />
                            Audit &amp; Saran Semua Essay
                        </button>
                    @endif
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
                            <span x-text="`Minta saran ${saranTujuan.length} essay`"></span>
                        </button>
                        <p x-show="kotor" x-cloak class="text-xs text-ink-subtle">Simpan penilaian manual dulu sebelum meminta saran semua.</p>
                    @endif
                </div>
            </div>

            <dialog
                x-ref="dialogSaran"
                class="m-auto max-h-[calc(100dvh-2rem)] w-[min(94vw,38rem)] overflow-y-auto rounded-2xl border border-outline bg-surface p-0 text-ink shadow-xl backdrop:bg-ink/40"
                aria-labelledby="judul-dialog-saran"
                @cancel="cekTutupSaran($event)"
            >
                <div class="p-5 sm:p-6">
                    <h3 id="judul-dialog-saran" class="text-xl font-bold" x-text="saranMassal ? 'Membuat saran AI' : 'Saran AI'"></h3>

                    <div x-show="saranMassal" class="mt-4">
                        <div class="flex items-end justify-between gap-3">
                            <p class="text-sm text-ink-muted" aria-live="polite" x-text="`${saranSelesai} dari ${saranTotal} saran selesai`"></p>
                            <p class="font-display text-2xl font-bold text-ink" x-text="`${saranPersen}%`"></p>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-surface-sunken" role="progressbar" :aria-valuenow="saranPersen" aria-valuemin="0" aria-valuemax="100" aria-label="Progres pembuatan saran AI">
                            <div class="h-full rounded-full bg-brand transition-[width] duration-300 ease-out" :style="`width: ${saranPersen}%`"></div>
                        </div>
                        <p class="mt-3 text-sm text-ink-muted">Saran yang sudah jadi tetap tersimpan. Hentikan baru berlaku setelah saran yang sedang diproses selesai.</p>
                    </div>

                    {{-- Konsol Terminal Log --}}
                    <div class="mt-4 overflow-hidden rounded-xl border border-slate-800 bg-slate-950 text-slate-100 shadow-inner">
                        <div class="flex items-center justify-between border-b border-slate-800 bg-slate-900/90 px-3.5 py-2 text-[11px] font-mono text-slate-400">
                            <div class="flex items-center gap-2">
                                <div class="flex gap-1.5" aria-hidden="true">
                                    <span class="h-2.5 w-2.5 rounded-full bg-rose-500/80"></span>
                                    <span class="h-2.5 w-2.5 rounded-full bg-amber-500/80"></span>
                                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500/80"></span>
                                </div>
                                <span class="ml-1 font-semibold text-slate-300">Terminal Log</span>
                            </div>
                            <span x-text="saranMassal ? 'Memproses...' : (saranBerhenti ? 'Dihentikan' : (saranSelesai > 0 ? 'Selesai' : 'Siap'))" class="text-[10px] uppercase tracking-wider font-semibold" :class="saranMassal ? 'text-sky-400' : 'text-slate-500'"></span>
                        </div>
                        <div
                            x-ref="terminalBox"
                            class="max-h-52 overflow-y-auto p-3 font-mono text-xs leading-relaxed space-y-1.5 select-text bg-slate-950"
                        >
                            <template x-for="(log, idx) in terminalLogs" :key="idx">
                                <div class="flex items-start gap-2">
                                    <span class="text-slate-500 select-none text-[11px] font-mono shrink-0" x-text="log.waktu"></span>
                                    <span
                                        class="font-mono break-all"
                                        :class="{
                                            'text-emerald-400 font-medium': log.tipe === 'ok',
                                            'text-rose-400 font-medium': log.tipe === 'err',
                                            'text-sky-300': log.tipe === 'req',
                                            'text-amber-300 font-medium': log.tipe === 'info'
                                        }"
                                        x-text="log.pesan"
                                    ></span>
                                </div>
                            </template>
                            <div x-show="saranMassal" class="flex items-center gap-2 text-slate-500 text-xs pt-1">
                                <span class="inline-block h-3 w-1.5 animate-pulse bg-emerald-400"></span>
                                <span class="italic text-[11px] text-slate-400">Memproses antrean request...</span>
                            </div>
                            <div x-show="terminalLogs.length === 0" class="text-slate-600 italic text-[11px]">
                                Menunggu request dimulai...
                            </div>
                        </div>
                    </div>

                    <div x-show="saranMassal" class="mt-4">
                        <button type="button" class="btn btn-secondary min-h-11 w-full" @click="hentikanSaran" :disabled="saranBerhenti">
                            <span x-text="saranBerhenti ? 'Menghentikan...' : 'Hentikan'"></span>
                        </button>
                    </div>

                    <div x-show="!saranMassal" class="mt-4">
                        <p class="text-sm text-ink-muted" aria-live="polite" x-text="saranPesan"></p>
                        <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button type="button" class="btn btn-secondary min-h-11" @click="$refs.dialogSaran.close()">Tutup</button>
                            <button type="button" class="btn btn-primary min-h-11" x-show="saranSelesai > 0" :disabled="kotor" @click="window.location.reload()">Muat ulang</button>
                        </div>
                        <p x-show="kotor" x-cloak class="mt-2 text-xs text-ink-subtle">Simpan penilaian manual dulu sebelum memuat ulang.</p>
                    </div>
                </div>
            </dialog>

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
                            @php
                                $simScore = $aktifEssay['similarity_score'];
                                $simData = $aktifEssay['similarity_data'];
                                $topMatch = $simData[0] ?? null;
                            @endphp
                            <div class="mt-3 rounded-xl border border-outline bg-surface p-3 space-y-3">
                                <div>
                                    <p class="text-sm font-bold text-ink">Integritas Akademik & Plagiarisme</p>
                                    <p class="text-xs text-ink-subtle">Pemeriksaan kemiripan antar-peserta lokal dan audit teks kecerdasan buatan (AI).</p>
                                </div>

                                {{-- Kemiripan Antar Peserta --}}
                                <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-outline/60">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-semibold text-ink-muted">Kemiripan jawaban:</span>
                                        @if ($simScore !== null)
                                            @if ($simScore >= 80)
                                                <span class="pill pill-coral font-bold text-xs">⚠️ {{ $simScore }}% mirip {{ $topMatch['student_name'] ?? 'peserta lain' }}</span>
                                            @elseif ($simScore >= 50)
                                                <span class="pill pill-sun font-bold text-xs">⚠️ {{ $simScore }}% mirip {{ $topMatch['student_name'] ?? 'peserta lain' }}</span>
                                            @else
                                                <span class="pill pill-leaf text-xs">{{ $simScore }}% mirip (aman)</span>
                                            @endif
                                        @elseif ($aktifEssay['similarity_checked_at'])
                                            <span class="pill pill-neutral text-xs">Tidak ada pembanding</span>
                                        @else
                                            <span class="pill pill-neutral text-xs">Belum dipindai</span>
                                        @endif
                                    </div>
                                    @if ($topMatch && ($topMatch['score'] ?? 0) >= 20)
                                        <button
                                            type="button"
                                            class="btn btn-secondary text-xs !py-1 !px-2.5 min-h-8"
                                            @click="$refs.dialogBandingkan.showModal()"
                                        >
                                            Bandingkan
                                        </button>
                                    @endif
                                </div>

                                {{-- Deteksi Teks Buatan AI --}}
                                <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-outline/60">
                                    <div class="space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-xs font-semibold text-ink-muted">Probabilitas teks AI:</span>
                                            @if ($aktifEssay['ai_generated_at'])
                                                @php $aiScore = $aktifEssay['ai_generated_score']; @endphp
                                                @if ($aiScore >= 80)
                                                    <span class="pill pill-coral font-bold text-xs">🤖 {{ $aiScore }}% AI</span>
                                                @elseif ($aiScore >= 50)
                                                    <span class="pill pill-sun font-bold text-xs">🤖 {{ $aiScore }}% AI</span>
                                                @else
                                                    <span class="pill pill-leaf text-xs">🤖 {{ $aiScore }}% AI (kemungkinan manusia)</span>
                                                @endif
                                            @else
                                                <span class="pill pill-neutral text-xs">Belum diaudit</span>
                                            @endif
                                        </div>
                                        @if ($aktifEssay['ai_generated_at'] && filled($aktifEssay['ai_generated_reason']))
                                            <p class="text-xs text-ink-muted italic">{{ $aktifEssay['ai_generated_reason'] }}</p>
                                        @endif
                                    </div>
                                    @if ($aktifEssay['answer_id'])
                                        <form method="POST" action="{{ route('admin.answers.detect-ai', $aktifEssay['answer_id']) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary text-xs !py-1 !px-2.5 min-h-8">
                                                <x-icon name="sparkles" :size="14" />
                                                {{ $aktifEssay['ai_generated_at'] ? 'Audit Ulang AI' : 'Audit AI' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if (filled($aktifEssay['jawaban_saya']))
                            <div class="mt-3 rounded-xl border border-outline bg-surface p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-bold text-ink">Saran AI</p>
                                        <p class="text-xs text-ink-subtle">Bukan nilai final. Hanya terlihat oleh admin.</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($aktifEssay['answer_id'])
                                            <form method="POST" action="{{ route('admin.answers.audit-and-evaluate', $aktifEssay['answer_id']) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-secondary text-xs !py-1 !px-2.5 min-h-8">
                                                    <x-icon name="sparkles" :size="14" />
                                                    {{ ! empty($aktifEssay['ai_suggestions_json']) ? 'Audit Ulang & Saran' : 'Audit & Buat Saran' }}
                                                </button>
                                            </form>
                                        @endif
                                        <button type="submit" form="essay-ai-{{ $aktifEssay['answer_id'] }}" class="btn btn-ghost min-h-8 text-xs !py-1 !px-2">
                                            {{ $aktifEssay['ai_reviewed_at'] ? 'Buat ulang saran' : 'Minta saran AI' }}
                                        </button>
                                    </div>
                                </div>
                                @if (! empty($aktifEssay['ai_suggestions_json']))
                                    @php $sug = $aktifEssay['ai_suggestions_json']; @endphp
                                    <div class="mt-3 rounded-xl border border-brand/20 bg-brand-soft/20 p-3">
                                        <p class="text-xs font-bold uppercase tracking-wider text-brand-deep">Pilihan Saran Rubrik (Klik untuk Terapkan):</p>
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            @if (isset($sug['ketat']))
                                                <button
                                                    type="button"
                                                    class="pill border border-coral text-coral-deep hover:bg-coral-soft/50 font-bold text-xs transition cursor-pointer"
                                                    @click="terapkanMode('ketat', {{ (int) $sug['ketat']['score'] }}, @js($sug['ketat']['feedback'] ?? ''))"
                                                    title="{{ $sug['ketat']['feedback'] ?? '' }}"
                                                >
                                                    Ketat: {{ $sug['ketat']['score'] }}
                                                </button>
                                            @endif
                                            @if (isset($sug['seimbang']))
                                                <button
                                                    type="button"
                                                    class="pill border border-brand text-brand-deep hover:bg-brand-soft/50 font-bold text-xs transition cursor-pointer"
                                                    @click="terapkanMode('seimbang', {{ (int) $sug['seimbang']['score'] }}, @js($sug['seimbang']['feedback'] ?? ''))"
                                                    title="{{ $sug['seimbang']['feedback'] ?? '' }}"
                                                >
                                                    Seimbang: {{ $sug['seimbang']['score'] }}
                                                </button>
                                            @endif
                                            @if (isset($sug['longgar']))
                                                <button
                                                    type="button"
                                                    class="pill border border-leaf text-leaf-deep hover:bg-leaf-soft/50 font-bold text-xs transition cursor-pointer"
                                                    @click="terapkanMode('longgar', {{ (int) $sug['longgar']['score'] }}, @js($sug['longgar']['feedback'] ?? ''))"
                                                    title="{{ $sug['longgar']['feedback'] ?? '' }}"
                                                >
                                                    Longgar: {{ $sug['longgar']['score'] }}
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                                @if ($aktifEssay['ai_reviewed_at'])
                                    <p class="mt-2 text-sm text-ink">
                                        Saran nilai <strong>{{ $aktifEssay['ai_suggested_score'] }} / {{ $aktifEssay['bobot'] }}</strong>, keyakinan {{ $aktifEssay['ai_confidence'] }}%.
                                    </p>
                                    @if (filled($aktifEssay['ai_mode']))
                                        <p class="mt-1 text-xs text-ink-subtle">Mode {{ ucfirst($aktifEssay['ai_mode']) }}.</p>
                                    @endif
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
                                            $aiMode = $meta['ai_mode'] ?? null;

                                            if ($log->action === 'essay_score_changed' && (blank($aiProvider) || blank($aiModel) || blank($aiMode))) {
                                                $saran = $aktifEssay['history']->first(fn ($item) => $item->action === 'essay_ai_suggested' && $item->created_at->lte($log->created_at));
                                                $aiProvider = $aiProvider ?: ($saran?->metadata['provider'] ?? null);
                                                $aiModel = $aiModel ?: ($saran?->metadata['model'] ?? null);
                                                $aiMode = $aiMode ?: ($saran?->metadata['mode'] ?? null);
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
                                                            @if (filled($aiMode))
                                                                · Mode {{ ucfirst($aiMode) }}
                                                            @endif
                                                        </p>
                                                    @endif
                                                @else
                                                    <p class="font-bold text-ink">AI menyarankan {{ $meta['score'] }} poin dengan keyakinan {{ $meta['confidence'] }}%.</p>
                                                    <p class="mt-1 text-ink-muted">
                                                        {{ $meta['provider'] ?: 'AI' }} · {{ $meta['model'] ?: '-' }}
                                                        @if (filled($meta['mode'] ?? null))
                                                            · Mode {{ ucfirst($meta['mode']) }}
                                                        @endif
                                                    </p>
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
                            <input type="hidden" name="mode" :value="modeSaran">
                        </form>
                    @endif
                </div>
            </div>

            @if (filled($aktifEssay['jawaban_saya']) && ! empty($aktifEssay['similarity_data']))
                <dialog
                    x-ref="dialogBandingkan"
                    class="m-auto max-h-[calc(100dvh-2rem)] w-[min(94vw,56rem)] overflow-y-auto rounded-2xl border border-outline bg-surface p-0 text-ink shadow-xl backdrop:bg-ink/40"
                    aria-labelledby="judul-dialog-bandingkan"
                >
                    <div class="p-5 sm:p-6" x-data="{ matchIdx: 0, matches: {{ json_encode($aktifEssay['similarity_data']) }} }">
                        <div class="flex items-start justify-between gap-4 pb-4 border-b border-outline">
                            <div>
                                <h3 id="judul-dialog-bandingkan" class="text-xl font-bold">Perbandingan Jawaban Essay</h3>
                                <p class="text-sm text-ink-muted mt-0.5">
                                    Soal {{ $aktifEssay['urutan'] }}: {{ \Illuminate\Support\Str::limit($aktifEssay['teks'], 80) }}
                                </p>
                            </div>
                            <button type="button" class="btn btn-ghost !p-2" @click="$refs.dialogBandingkan.close()">✕</button>
                        </div>

                        <template x-if="matches.length > 1">
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold text-ink-muted">Bandingkan dengan:</span>
                                <template x-for="(m, idx) in matches" :key="idx">
                                    <button
                                        type="button"
                                        class="btn text-xs !py-1 !px-2.5 min-h-7"
                                        :class="matchIdx === idx ? 'btn-primary' : 'btn-secondary'"
                                        @click="matchIdx = idx"
                                        x-text="`${m.student_name} (${m.score}%)`"
                                    ></button>
                                </template>
                            </div>
                        </template>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="rounded-xl border border-outline bg-surface-sunken p-4">
                                <div class="flex items-center justify-between pb-2 mb-2 border-b border-outline/70">
                                    <span class="font-bold text-sm text-ink">{{ $attempt->user->nama ?? $attempt->user->name ?? 'Peserta Saat Ini' }}</span>
                                    <span class="pill pill-neutral text-xs">Jawaban ini</span>
                                </div>
                                <div class="text-sm text-ink whitespace-pre-wrap max-h-96 overflow-y-auto">{{ $aktifEssay['jawaban_saya'] }}</div>
                            </div>
                            <div class="rounded-xl border border-outline bg-surface-sunken p-4">
                                <div class="flex items-center justify-between pb-2 mb-2 border-b border-outline/70">
                                    <span class="font-bold text-sm text-ink" x-text="matches[matchIdx]?.student_name"></span>
                                    <span
                                        class="pill text-xs font-bold"
                                        :class="(matches[matchIdx]?.score ?? 0) >= 80 ? 'pill-coral' : ((matches[matchIdx]?.score ?? 0) >= 50 ? 'pill-sun' : 'pill-leaf')"
                                        x-text="`${matches[matchIdx]?.score ?? 0}% mirip`"
                                    ></span>
                                </div>
                                <div class="text-sm text-ink whitespace-pre-wrap max-h-96 overflow-y-auto" x-text="matches[matchIdx]?.text || 'Teks jawaban pembanding'"></div>
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <button type="button" class="btn btn-secondary min-h-11" @click="$refs.dialogBandingkan.close()">Tutup</button>
                        </div>
                    </div>
                </dialog>
            @endif
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
                                        @if (($baris['similarity_score'] ?? null) !== null && $baris['similarity_score'] >= 50)
                                            @php $bm = $baris['similarity_data'][0] ?? null; @endphp
                                            <span class="pill {{ $baris['similarity_score'] >= 80 ? 'pill-coral' : 'pill-sun' }} text-xs mt-1 block w-fit font-bold">
                                                ⚠️ {{ $baris['similarity_score'] }}% mirip {{ $bm['student_name'] ?? '' }}
                                            </span>
                                        @endif
                                        @if ($baris['ai_generated_at'] ?? false)
                                            <span class="pill {{ $baris['ai_generated_score'] >= 80 ? 'pill-coral' : ($baris['ai_generated_score'] >= 50 ? 'pill-sun' : 'pill-leaf') }} text-xs mt-1 block w-fit">
                                                🤖 {{ $baris['ai_generated_score'] }}% AI
                                            </span>
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
