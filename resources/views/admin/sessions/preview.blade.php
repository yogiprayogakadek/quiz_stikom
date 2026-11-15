<x-layouts.admin
    :judul="'Pratinjau '.$session->nama"
    keterangan="Halaman baca-saja. Tidak ada attempt, timer, atau jawaban peserta yang dibuat dari pratinjau ini."
>
    <x-slot:aksi>
        <a href="{{ route('admin.sessions.show', $session) }}" class="btn btn-secondary">
            <x-icon name="arrow-left" :size="20" />
            Kembali ke sesi
        </a>
    </x-slot:aksi>

    <div
        x-data="{
            tab: 'soal',
            kunci: false,
            previewIndex: 0,
            previewTotal: {{ $soal->count() }},
            pilih(nama) {
                this.tab = nama;
                this.$nextTick(() => document.getElementById('tab-' + nama).focus());
            },
            geser(arah) {
                const urutan = ['soal', 'peserta'];
                const posisi = urutan.indexOf(this.tab);
                this.pilih(urutan[(posisi + arah + urutan.length) % urutan.length]);
            },
            pindahPreview(index) {
                const tujuan = Math.max(0, Math.min(index, this.previewTotal - 1));
                if (tujuan === this.previewIndex) return;
                this.previewIndex = tujuan;
                this.$nextTick(() => {
                    document.getElementById('preview-soal-' + tujuan)?.focus({ preventScroll: true });
                    document.getElementById('preview-panel-soal')?.scrollIntoView({
                        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                        block: 'start'
                    });
                });
            }
        }"
    >
        <section class="card" aria-labelledby="ringkasan-pratinjau">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="ringkasan-pratinjau" class="text-2xl">Ringkasan sesi</h2>
                    <p class="mt-1 text-base text-ink-muted">
                        {{ $session->quiz->judul }} · {{ $session->kelas?->nama ?? 'Terbuka untuk semua peserta' }}
                    </p>
                </div>
                <x-status-badge :status="$session->status" />
            </div>

            <dl class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-outline bg-page p-4">
                    <dt class="text-sm font-bold text-ink-muted">Mode</dt>
                    <dd class="mt-1 text-lg font-bold text-ink">{{ $session->mode === 'serempak' ? 'Serempak' : 'Acak' }}</dd>
                </div>
                <div class="rounded-xl border border-outline bg-page p-4">
                    <dt class="text-sm font-bold text-ink-muted">Soal ditampilkan</dt>
                    <dd class="mt-1 text-lg font-bold text-ink">{{ $soal->count() }} soal</dd>
                    <p class="mt-1 text-sm text-ink-muted">
                        @php $dipakai = $dariSesi ? $soal->count() : $session->quiz->soalTerpilih(); @endphp
                        @if ($dipakai < $totalKuis)
                            Sesi memakai {{ $dipakai }} dari {{ $totalKuis }} soal.
                        @else
                            {{ $dariSesi ? 'Daftar terkunci mengikuti sesi.' : 'Daftar mengikuti kuis.' }}
                        @endif
                    </p>
                </div>
                <div class="rounded-xl border border-outline bg-page p-4">
                    <dt class="text-sm font-bold text-ink-muted">Durasi total</dt>
                    @php
                        $detikTampil = $session->mode === 'serempak'
                            ? (int) $soal->sum('durasi_detik')
                            : (int) $session->durasi_total_detik;
                    @endphp
                    <dd class="mt-1 text-lg font-bold text-ink">
                        {{ $detikTampil > 0 ? intdiv($detikTampil, 60).' menit' : 'Otomatis' }}
                    </dd>
                    <p class="mt-1 text-sm text-ink-muted">Mengikuti durasi soal dan pengaturan sesi.</p>
                </div>
                <div class="rounded-xl border border-outline bg-page p-4">
                    <dt class="text-sm font-bold text-ink-muted">Pengawasan</dt>
                    <dd class="mt-1 text-lg font-bold text-ink">
                        {{ $session->max_pelanggaran ? 'Maks '.$session->max_pelanggaran.' pelanggaran' : 'Tanpa batas' }}
                    </dd>
                    <p class="mt-1 text-sm text-ink-muted">Peringatan {{ $session->durasiPeringatan() }} detik.</p>
                </div>
            </dl>

            @if ($session->mode === 'serempak' && $soal->contains(fn ($item) => $item->isEssay()))
                <x-alert jenis="error" class="mt-4">
                    Mode Serempak tidak bisa dimulai selama kuis memuat soal essay. Pindahkan soal essay ke sesi Mode Acak.
                </x-alert>
            @endif
        </section>

        <div class="mt-6 flex flex-wrap gap-2" role="tablist" aria-label="Isi pratinjau sesi">
            <button
                id="tab-soal"
                type="button"
                role="tab"
                aria-controls="panel-soal"
                aria-selected="true"
                tabindex="0"
                class="pill min-h-11 px-4 transition"
                x-bind:class="tab === 'soal' ? 'pill-brand' : 'pill-neutral'"
                x-bind:aria-selected="tab === 'soal' ? 'true' : 'false'"
                x-bind:tabindex="tab === 'soal' ? '0' : '-1'"
                x-on:click="pilih('soal')"
                x-on:keydown.arrow-right.prevent="geser(1)"
                x-on:keydown.arrow-left.prevent="geser(-1)"
                x-on:keydown.home.prevent="pilih('soal')"
                x-on:keydown.end.prevent="pilih('peserta')"
            >
                <x-icon name="book" :size="18" />
                Ringkasan &amp; soal
            </button>
            <button
                id="tab-peserta"
                type="button"
                role="tab"
                aria-controls="panel-peserta"
                aria-selected="false"
                tabindex="-1"
                class="pill min-h-11 px-4 transition"
                x-bind:class="tab === 'peserta' ? 'pill-brand' : 'pill-neutral'"
                x-bind:aria-selected="tab === 'peserta' ? 'true' : 'false'"
                x-bind:tabindex="tab === 'peserta' ? '0' : '-1'"
                x-on:click="pilih('peserta')"
                x-on:keydown.arrow-right.prevent="geser(1)"
                x-on:keydown.arrow-left.prevent="geser(-1)"
                x-on:keydown.home.prevent="pilih('soal')"
                x-on:keydown.end.prevent="pilih('peserta')"
            >
                <x-icon name="eye" :size="18" />
                Contoh tampilan peserta
            </button>
        </div>

        <section id="panel-soal" role="tabpanel" aria-labelledby="tab-soal" class="mt-5 space-y-4" x-show="tab === 'soal'">
            @if ($soal->isEmpty())
                <x-empty-state
                    judul="Belum ada soal"
                    keterangan="Tambahkan soal ke kuis ini supaya pratinjau bisa menampilkan isinya."
                />
            @else
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-base text-ink-muted">
                        Kunci jawaban dan rubrik disembunyikan sampai tombol di samping ditekan.
                    </p>
                    <button
                        type="button"
                        class="btn btn-secondary"
                        x-on:click="kunci = !kunci"
                        x-bind:aria-pressed="kunci ? 'true' : 'false'"
                    >
                        <x-icon name="eye" :size="20" />
                        <span x-text="kunci ? 'Sembunyikan kunci' : 'Tampilkan kunci'">Tampilkan kunci</span>
                    </button>
                </div>

                @foreach ($soal as $nomor => $item)
                    <article class="card" aria-labelledby="soal-{{ $item->id }}-teks">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-display text-xl font-extrabold text-ink">Soal {{ $nomor + 1 }}</span>
                            <span class="pill {{ $item->isEssay() ? 'pill-sun' : 'pill-brand' }}">{{ $item->isEssay() ? 'Essay' : 'Pilihan ganda' }}</span>
                            <span class="pill pill-neutral">{{ $item->bobot }} poin</span>
                            @if ($item->durasi_detik)
                                <span class="pill pill-neutral">{{ $item->durasi_detik }} detik</span>
                            @endif
                        </div>

                        <h3 id="soal-{{ $item->id }}-teks" class="question-text mt-3 whitespace-pre-line">{{ $item->teks }}</h3>

                        @if ($item->isEssay() && ! empty($item->petunjuk_json))
                            <div class="mt-4 rounded-xl border border-outline bg-page p-4">
                                <p class="text-sm font-bold text-ink">Yang harus dijawab</p>
                                <ul class="mt-2 space-y-1 text-base text-ink-muted">
                                    @foreach ($item->petunjuk_json as $poin)
                                        <li class="flex gap-2">
                                            <span class="mt-2 h-1.5 w-1.5 flex-none rounded-full bg-brand" aria-hidden="true"></span>
                                            <span>{{ $poin }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($item->isEssay() && ! empty($item->sub_soal_json))
                            <div class="mt-4 space-y-3">
                                @foreach ($item->subSoalTerstruktur() as $bagian)
                                    <div class="rounded-xl border border-outline bg-page p-3">
                                        <p class="text-sm font-bold text-ink">Bagian {{ $bagian['code'] }}</p>
                                        <p class="mt-1 whitespace-pre-line text-base text-ink">{{ $bagian['teks'] ?? '' }}</p>
                                        @if (! empty($bagian['petunjuk']))
                                            <ul class="mt-2 space-y-1 text-sm text-ink-muted">
                                                @foreach ((array) $bagian['petunjuk'] as $poin)
                                                    <li class="flex gap-2">
                                                        <span class="mt-1.5 h-1.5 w-1.5 flex-none rounded-full bg-brand" aria-hidden="true"></span>
                                                        <span>{{ $poin }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if (! $item->isEssay())
                            <ol class="mt-4 space-y-2">
                                @foreach ($item->options as $opsi)
                                    @php $benar = (bool) $opsi->is_correct; @endphp
                                    <li
                                        class="flex items-start gap-3 rounded-xl border-2 p-3"
                                        x-bind:class="kunci && {{ $benar ? 'true' : 'false' }} ? 'border-leaf bg-leaf-soft' : 'border-outline bg-page'"
                                    >
                                        <span class="tile-mark">{{ chr(65 + $loop->index) }}</span>
                                        <span class="min-w-0 flex-1 text-base text-ink">{{ $opsi->teks }}</span>
                                        @if ($benar)
                                            <span class="pill pill-leaf ml-auto flex-none" x-show="kunci" x-cloak>Kunci</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif

                        @if ($item->isEssay())
                            <div x-show="kunci" x-cloak>
                                @include('admin.sessions._preview-kunci', ['question' => $item])
                            </div>
                        @endif
                    </article>
                @endforeach
            @endif
        </section>

        <section id="panel-peserta" role="tabpanel" aria-labelledby="tab-peserta" class="mt-5" x-show="tab === 'peserta'" x-cloak>
            @if ($soal->isEmpty())
                <x-empty-state
                    judul="Belum ada soal"
                    keterangan="Contoh tampilan peserta muncul setelah kuis punya soal."
                />
            @else
                <p class="text-base text-ink-muted">
                    Pratinjau baca-saja, bukan attempt. Jawaban dan timer di bawah tidak aktif.
                </p>
                @if ($session->mode === 'serempak')
                    <x-alert jenis="info" class="mt-4">
                        Navigasi ini hanya untuk pemeriksaan admin. Saat sesi berjalan, peserta melihat soal yang dibuka admin dan tidak dapat memilih nomor soal sendiri.
                    </x-alert>
                @endif

                <figure class="mt-4">
                    <div class="overflow-hidden rounded-2xl border border-outline bg-surface">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline bg-page px-4 py-3">
                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                <span class="pill pill-brand">Mode {{ $session->mode === 'serempak' ? 'Serempak' : 'Acak' }}</span>
                                <span class="truncate text-sm font-bold text-ink">{{ $session->nama }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm font-bold text-ink-muted">
                                <x-icon name="clock" :size="18" />
                                <span class="timer">--:--</span>
                            </div>
                        </div>

                        <div class="grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_14rem]">
                            <div id="preview-panel-soal" class="card min-w-0 scroll-mt-24">
                                @foreach ($soal as $nomor => $item)
                                    <section
                                        x-show="previewIndex === {{ $nomor }}"
                                        @if ($nomor > 0) x-cloak @endif
                                        aria-labelledby="preview-soal-{{ $nomor }}"
                                    >
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="pill pill-brand">Soal {{ $nomor + 1 }} / {{ $soal->count() }}</span>
                                            <span class="pill {{ $item->isEssay() ? 'pill-sun' : 'pill-neutral' }}">{{ $item->isEssay() ? 'Essay' : 'Pilihan ganda' }}</span>
                                        </div>

                                        <h3 id="preview-soal-{{ $nomor }}" tabindex="-1" class="question-text mt-3 whitespace-pre-line focus:outline-none">{{ $item->teks }}</h3>

                                        @if ($item->isEssay() && ! empty($item->petunjuk_json))
                                            <div class="mt-4 rounded-xl border border-outline bg-page p-4">
                                                <p class="text-sm font-bold text-ink">Yang harus dijawab</p>
                                                <ul class="mt-2 space-y-1 text-base text-ink-muted">
                                                    @foreach ($item->petunjuk_json as $poin)
                                                        <li class="flex gap-2">
                                                            <span class="mt-2 h-1.5 w-1.5 flex-none rounded-full bg-brand" aria-hidden="true"></span>
                                                            <span>{{ $poin }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @if ($item->isEssay() && ! empty($item->sub_soal_json))
                                            <div class="mt-5 space-y-5">
                                                @foreach ($item->subSoalTerstruktur() as $bagian)
                                                    <div @class(['border-t border-outline pt-5' => ! $loop->first])>
                                                        <h4 class="text-lg font-bold text-ink">Bagian {{ $bagian['code'] }}</h4>
                                                        <p class="mt-1 whitespace-pre-line text-base text-ink-muted">{{ $bagian['teks'] ?? '' }}</p>
                                                        @if (! empty($bagian['petunjuk']))
                                                            <ul class="mt-2 space-y-1 text-sm text-ink-muted">
                                                                @foreach ((array) $bagian['petunjuk'] as $poin)
                                                                    <li class="flex gap-2">
                                                                        <span class="mt-1.5 h-1.5 w-1.5 flex-none rounded-full bg-brand" aria-hidden="true"></span>
                                                                        <span>{{ $poin }}</span>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        @endif
                                                        @if ($bagian['is_leaf'])
                                                            <label class="label mt-3" for="preview-jawaban-{{ $item->id }}-{{ $loop->index }}">Jawaban {{ $bagian['code'] }}</label>
                                                            <textarea id="preview-jawaban-{{ $item->id }}-{{ $loop->index }}" class="input min-h-28 resize-none" disabled placeholder="Jawaban peserta ditulis di sini"></textarea>
                                                        @else
                                                            <p class="mt-2 text-sm font-semibold text-ink-muted">Jawab melalui bagian turunannya.</p>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @elseif ($item->isEssay())
                                            <div class="mt-5">
                                                <label class="label" for="preview-jawaban-{{ $item->id }}">Jawaban Anda</label>
                                                <textarea id="preview-jawaban-{{ $item->id }}" class="input min-h-40 resize-none" disabled placeholder="Jawaban peserta ditulis di sini"></textarea>
                                            </div>
                                        @elseif ($item->options->isNotEmpty())
                                            <div class="mt-5 space-y-3" role="group" aria-label="Opsi jawaban soal {{ $nomor + 1 }}">
                                                @foreach ($item->options as $opsi)
                                                    <div class="tile pointer-events-none" aria-disabled="true">
                                                        <span class="tile-mark">{{ chr(65 + $loop->index) }}</span>
                                                        <span class="tile-text">{{ $opsi->teks }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </section>
                                @endforeach

                                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-outline pt-4">
                                    <button type="button" class="btn btn-secondary" x-on:click="pindahPreview(previewIndex - 1)" x-bind:disabled="previewIndex === 0">
                                        <x-icon name="arrow-left" :size="20" />
                                        Sebelumnya
                                    </button>
                                    <p class="text-sm font-bold text-ink-muted" aria-live="polite">
                                        Soal <span x-text="previewIndex + 1">1</span> dari {{ $soal->count() }}
                                    </p>
                                    <button type="button" class="btn btn-secondary" x-on:click="pindahPreview(previewIndex + 1)" x-bind:disabled="previewIndex === previewTotal - 1">
                                        Berikutnya
                                        <x-icon name="arrow-left" :size="20" class="rotate-180" />
                                    </button>
                                </div>
                            </div>

                            <nav class="card min-w-0" aria-labelledby="preview-navigation-heading">
                                <p id="preview-navigation-heading" class="text-sm font-bold text-ink">Navigasi soal</p>
                                <div class="mt-3 grid grid-cols-6 gap-2 lg:grid-cols-5">
                                    @foreach ($soal as $nomor => $item)
                                        <button
                                            type="button"
                                            class="flex h-11 items-center justify-center rounded-lg border-2 font-mono text-sm font-bold transition"
                                            x-bind:class="previewIndex === {{ $nomor }} ? 'border-brand bg-brand text-white' : 'border-outline bg-page text-ink-muted hover:border-brand hover:text-brand-deep'"
                                            x-bind:aria-current="previewIndex === {{ $nomor }} ? 'true' : 'false'"
                                            x-on:click="pindahPreview({{ $nomor }})"
                                            aria-label="Tampilkan pratinjau soal {{ $nomor + 1 }}"
                                        >
                                            {{ $nomor + 1 }}
                                        </button>
                                    @endforeach
                                </div>
                            </nav>
                        </div>
                    </div>

                    <figcaption class="mt-3 text-sm text-ink-muted">
                        Contoh tata letak peserta. Hanya navigasi pratinjau yang aktif; timer dan jawaban tidak disimpan.
                    </figcaption>
                </figure>
            @endif
        </section>
    </div>
</x-layouts.admin>
