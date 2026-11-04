<x-layouts.peserta :judul="$session->nama" :keterangan="'Mode Acak · '.$session->quiz->judul">
    <script>
        window.kuisAcak = (config) => ({
            questions: config.questions,
            index: 0,
            jawaban: config.jawaban,
            menyimpan: false,
            pesan: '',
            peringatan: '',
            jumlahKejadian: 0,
            sisa: 0,
            berhenti: false,
            selesaiOtomatis: false,
            kejadianTerakhir: {},
            sudahMulai: false,
            layarPenuhAktif: false,
            peringatanKeluar: false,
            sisaToleransi: 10,
            timerToleransi: null,
            memprosesPelanggaran: false,
            get current() {
                return this.questions[this.index] ?? null;
            },
            get terjawab() {
                return Object.values(this.jawaban).filter((nilai) => nilai !== null && nilai !== undefined).length;
            },
            get belum() {
                return this.questions.length - this.terjawab;
            },
            get isFullscreen() {
                return Boolean(
                    document.fullscreenElement ||
                    document.webkitFullscreenElement ||
                    document.mozFullScreenElement ||
                    document.msFullscreenElement
                );
            },
            init() {
                this.mulaiTimer();
                this.pasangPengawas();
            },
            format(n) {
                const menit = Math.floor(n / 60);
                const detik = n % 60;
                return menit + ':' + String(detik).padStart(2, '0');
            },
            mulaiTimer() {
                const selisih = Date.now() - config.serverNow;
                const hitung = () => {
                    this.sisa = Math.max(0, Math.round((config.batasAkhir - (Date.now() - selisih)) / 1000));
                    if (this.sisa <= 0 && !this.selesaiOtomatis) {
                        this.selesaiOtomatis = true;
                        this.akhiri(true);
                    }
                };
                hitung();
                setInterval(hitung, 250);
            },
            pilih(soal, opsi) {
                if (this.berhenti || this.menyimpan) {
                    return;
                }
                const sebelumnya = this.jawaban[soal.id] ?? null;
                this.jawaban[soal.id] = opsi.id;
                this.simpan(soal, opsi, sebelumnya);
            },
            async simpan(soal, opsi, sebelumnya) {
                this.menyimpan = true;
                this.pesan = '';
                const hasil = await window.kuisFetch(config.answerUrl, {
                    method: 'POST',
                    body: { question_id: soal.id, option_id: opsi.id }
                });
                this.menyimpan = false;
                if (hasil.ok) {
                    return;
                }
                if (sebelumnya === null) {
                    delete this.jawaban[soal.id];
                } else {
                    this.jawaban[soal.id] = sebelumnya;
                }
                if (hasil.status === 403) {
                    this.pesan = hasil.data?.message ?? 'Jawaban tidak diterima. Waktu mungkin sudah habis.';
                    if (hasil.data?.selesai) {
                        this.berhenti = true;
                        window.location.href = config.resultUrl;
                    }
                    return;
                }
                this.pesan = hasil.data?.message ?? 'Jawaban gagal disimpan. Coba pilih lagi.';
            },
            akhiri(paksa = false) {
                if (this.berhenti) {
                    return;
                }
                if (!paksa && this.belum > 0) {
                    const lanjut = window.confirm('Masih ada ' + this.belum + ' soal belum dijawab. Selesaikan sekarang?');
                    if (!lanjut) {
                        return;
                    }
                }
                this.berhenti = true;
                this.batalkanToleransi();
                document.getElementById('form-selesai').submit();
            },
            async masukLayarPenuh() {
                const el = document.documentElement;
                try {
                    if (el.requestFullscreen) {
                        await el.requestFullscreen();
                    } else if (el.webkitRequestFullscreen) {
                        await el.webkitRequestFullscreen();
                    } else if (el.mozRequestFullScreen) {
                        await el.mozRequestFullScreen();
                    } else if (el.msRequestFullscreen) {
                        await el.msRequestFullscreen();
                    }
                    this.sudahMulai = true;
                    this.layarPenuhAktif = true;
                    this.batalkanToleransi();
                } catch (e) {
                    this.pesan = 'Mode layar penuh tidak diizinkan oleh browser Anda.';
                }
            },
            cekStatusLayarPenuh() {
                const aktif = this.isFullscreen;
                this.layarPenuhAktif = aktif;

                if (!aktif) {
                    if (this.sudahMulai && !this.berhenti && !this.memprosesPelanggaran) {
                        this.kirimKejadian('fullscreen_exit');
                        this.mulaiToleransi();
                    }
                } else {
                    if (this.peringatanKeluar) {
                        this.batalkanToleransi();
                    }
                }
            },
            mulaiToleransi() {
                if (this.timerToleransi || this.berhenti || this.memprosesPelanggaran) {
                    return;
                }
                this.peringatanKeluar = true;
                this.sisaToleransi = 10;
                this.timerToleransi = setInterval(() => {
                    this.sisaToleransi--;
                    if (this.sisaToleransi <= 0) {
                        this.eksekusiPelanggaran();
                    }
                }, 1000);
            },
            batalkanToleransi() {
                if (this.timerToleransi) {
                    clearInterval(this.timerToleransi);
                    this.timerToleransi = null;
                }
                this.peringatanKeluar = false;
                this.sisaToleransi = 10;
            },
            eksekusiPelanggaran() {
                if (this.memprosesPelanggaran || this.berhenti) {
                    return;
                }
                this.batalkanToleransi();
                this.memprosesPelanggaran = true;
                this.berhenti = true;
                const form = document.getElementById('form-pelanggaran');
                if (form) {
                    form.submit();
                }
            },
            kirimKejadian(tipe) {
                if (this.berhenti) {
                    return;
                }
                const sekarang = Date.now();
                if (this.kejadianTerakhir[tipe] && sekarang - this.kejadianTerakhir[tipe] < 2000) {
                    return;
                }
                this.kejadianTerakhir[tipe] = sekarang;
                window.kuisFetch(config.eventUrl, {
                    method: 'POST',
                    body: { tipe }
                }).then((hasil) => {
                    if (hasil.ok) {
                        this.jumlahKejadian = hasil.data.jumlah;
                        this.peringatan = 'Aktivitas ini tercatat: ' + this.labelKejadian(tipe) + '. Tetap fokus pada soal.';
                    }
                });
            },
            labelKejadian(tipe) {
                return {
                    blur_window: 'jendela kuis ditinggalkan',
                    tab_hidden: 'tab kuis disembunyikan',
                    fullscreen_exit: 'keluar dari layar penuh',
                    fullscreen_exit_timeout: 'toleransi keluar layar penuh habis',
                    copy_attempt: 'upaya menyalin soal'
                }[tipe] ?? tipe;
            },
            pasangPengawas() {
                const fsEvents = ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'];
                fsEvents.forEach((ev) => {
                    document.addEventListener(ev, () => this.cekStatusLayarPenuh());
                });

                window.addEventListener('blur', () => {
                    this.kirimKejadian('blur_window');
                    if (this.sudahMulai && !this.berhenti && !this.memprosesPelanggaran) {
                        this.mulaiToleransi();
                    }
                });

                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        this.kirimKejadian('tab_hidden');
                        if (this.sudahMulai && !this.berhenti && !this.memprosesPelanggaran) {
                            this.mulaiToleransi();
                        }
                    }
                });

                window.addEventListener('beforeunload', (e) => {
                    if (!this.berhenti) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });

                document.addEventListener('copy', (event) => {
                    const area = document.getElementById('area-kuis');
                    if (area && window.getSelection()?.anchorNode && area.contains(window.getSelection().anchorNode)) {
                        event.preventDefault();
                        this.kirimKejadian('copy_attempt');
                    }
                });

                if (this.isFullscreen) {
                    this.sudahMulai = true;
                    this.layarPenuhAktif = true;
                }
            }
        });
    </script>

    <div
        id="area-kuis"
        x-data="kuisAcak({
            questions: {{ Illuminate\Support\Js::from($questions) }},
            jawaban: {{ Illuminate\Support\Js::from(collect($questions)->mapWithKeys(fn ($q) => [$q['id'] => $q['jawaban_option_id']])->filter()->all()) }},
            serverNow: {{ $server_now }},
            batasAkhir: {{ $attempt->batas_akhir_at }},
            answerUrl: '{{ route('peserta.attempt.answer', $attempt) }}',
            eventUrl: '{{ route('peserta.attempt.events', $attempt) }}',
            resultUrl: '{{ route('peserta.attempt.result', $attempt) }}'
        })"
        class="space-y-5"
    >
        <div class="flex flex-col gap-3 rounded-card border border-outline bg-page p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-soft text-brand-deep">
                    <x-icon name="clock" :size="22" />
                </span>
                <div>
                    <p class="text-sm font-bold text-ink-muted">Sisa waktu</p>
                    <p class="timer text-3xl" x-bind:class="sisa <= 30 ? 'text-coral-deep' : 'text-ink'" x-text="format(sisa)">0:00</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="pill pill-leaf" x-text="'Terjawab ' + terjawab + ' dari ' + questions.length"></span>
                <button type="button" class="btn btn-secondary" x-on:click="masukLayarPenuh()">
                    <x-icon name="eye" :size="20" />
                    Layar penuh
                </button>
                <button type="button" class="btn btn-primary" x-on:click="akhiri(false)">Selesai</button>
            </div>
        </div>

        <div x-show="peringatan" x-cloak class="alert alert-warning flex items-start justify-between gap-3">
            <p x-text="peringatan"></p>
            <button type="button" class="font-bold underline" x-on:click="peringatan = ''">Tutup</button>
        </div>

        <div x-show="pesan" x-cloak class="alert alert-error" role="alert">
            <p x-text="pesan"></p>
        </div>

        <div x-show="menyimpan" x-cloak class="text-sm font-bold text-ink-muted">Menyimpan jawaban...</div>

        <template x-if="current">
            <section class="card" aria-labelledby="teks-soal">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="pill pill-brand" x-text="'Soal ' + (index + 1) + ' dari ' + questions.length"></span>
                    <span class="text-sm text-ink-subtle">Pilih satu jawaban. Jawaban tersimpan saat diklik.</span>
                </div>

                <h2 id="teks-soal" class="mt-4 text-2xl leading-snug" x-text="current.teks"></h2>

                <div class="mt-5 space-y-3" role="group" aria-labelledby="teks-soal">
                    <template x-for="(opsi, opsiIndex) in current.opsi" :key="opsi.id">
                        <button
                            type="button"
                            class="tile"
                            x-bind:aria-pressed="jawaban[current.id] === opsi.id ? 'true' : 'false'"
                            x-bind:disabled="menyimpan"
                            x-on:click="pilih(current, opsi)"
                        >
                            <span class="tile-mark" x-text="String.fromCharCode(65 + opsiIndex)"></span>
                            <span x-text="opsi.teks"></span>
                        </button>
                    </template>
                </div>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-secondary" x-on:click="index = Math.max(0, index - 1)" x-bind:disabled="index === 0">
                            <x-icon name="arrow-left" :size="20" />
                            Sebelumnya
                        </button>
                        <button type="button" class="btn btn-secondary" x-on:click="index = Math.min(questions.length - 1, index + 1)" x-bind:disabled="index === questions.length - 1">
                            Berikutnya
                        </button>
                    </div>
                    <p class="text-base text-ink-muted">Jawaban bisa diubah sampai waktu habis.</p>
                </div>
            </section>
        </template>

        <section class="card" aria-labelledby="navigasi-soal">
            <h2 id="navigasi-soal" class="text-xl">Navigasi soal</h2>
            <div class="mt-3 grid grid-cols-6 gap-2 sm:grid-cols-10">
                <template x-for="(soal, nomor) in questions" :key="soal.id">
                    <button
                        type="button"
                        class="flex h-12 items-center justify-center rounded-xl border-2 font-mono text-base font-bold"
                        x-bind:class="nomor === index ? 'border-brand bg-brand text-white' : (jawaban[soal.id] ? 'border-leaf bg-leaf-soft text-leaf-deep' : 'border-outline bg-page text-ink-muted')"
                        x-bind:aria-current="nomor === index ? 'true' : 'false'"
                        x-bind:aria-label="'Soal ' + (nomor + 1) + (jawaban[soal.id] ? ', sudah dijawab' : ', belum dijawab')"
                        x-on:click="index = nomor"
                        x-text="nomor + 1"
                    ></button>
                </template>
            </div>
            <p class="mt-3 text-base text-ink-muted">Kotak hijau berarti jawaban sudah tersimpan di server.</p>
        </section>

        <form id="form-selesai" method="POST" action="{{ route('peserta.attempt.finish', $attempt) }}" class="hidden">
            @csrf
        </form>

        <form id="form-pelanggaran" method="POST" action="{{ route('peserta.attempt.pelanggaran', $attempt) }}" class="hidden">
            @csrf
        </form>

        {{-- Gate Modal: Masuk Layar Penuh --}}
        <div
            x-show="!sudahMulai || (!layarPenuhAktif && !peringatanKeluar && !memprosesPelanggaran)"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-ink/80 p-4 backdrop-blur-sm"
        >
            <div class="card w-full max-w-lg border-2 border-brand bg-page p-6 text-center shadow-2xl sm:p-8 space-y-5">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-soft text-brand-deep">
                    <x-icon name="eye" :size="32" />
                </div>
                <h2 class="text-2xl font-bold text-ink sm:text-3xl">Mode Layar Penuh Wajib</h2>
                <p class="text-base leading-relaxed text-ink-muted">
                    Untuk mencegah kecurangan, kuis ini wajib dikerjakan dalam mode <strong>Layar Penuh (Full Screen)</strong>.
                    Jika Anda keluar dari layar penuh atau berpindah tab/aplikasi selama kuis, sistem akan memberi waktu toleransi sebelum <strong>jawaban otomatis dikumpulkan dan akun Anda di-logout</strong>.
                </p>
                <button
                    type="button"
                    class="btn btn-primary btn-anak w-full py-3 text-lg font-bold shadow-md"
                    x-on:click="masukLayarPenuh()"
                >
                    Aktifkan Layar Penuh & Mulai
                </button>
            </div>
        </div>

        {{-- Warning Modal: Peringatan Keluar Layar Penuh (Countdown Toleransi) --}}
        <div
            x-show="peringatanKeluar && !memprosesPelanggaran"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-ink/90 p-4 backdrop-blur-md"
        >
            <div class="card w-full max-w-lg border-4 border-coral-deep bg-page p-6 text-center shadow-2xl sm:p-8 space-y-6">
                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-coral-soft text-coral-deep">
                    <x-icon name="alert" :size="40" />
                </div>
                <div>
                    <span class="pill pill-coral text-xs font-bold uppercase tracking-wider">Pelanggaran Terdeteksi</span>
                    <h2 class="mt-2 text-2xl font-bold text-ink sm:text-3xl">Anda Keluar Dari Layar Penuh!</h2>
                </div>
                <p class="text-base leading-relaxed text-ink-muted">
                    Sistem mendeteksi Anda keluar dari mode layar penuh atau berpindah jendela/tab. Segera kembali ke layar penuh sekarang!
                </p>
                <div class="rounded-2xl border border-coral-deep/30 bg-coral-soft/60 p-4">
                    <p class="text-sm font-bold text-coral-deep">Toleransi Waktu Tersisa</p>
                    <p class="timer mt-1 text-5xl font-bold text-coral-deep" x-text="sisaToleransi + ' detik'">10 detik</p>
                    <p class="mt-1 text-xs text-ink-muted">Jika waktu habis, kuis otomatis dikumpulkan dan Anda akan dikeluarkan (logout).</p>
                </div>
                <button
                    type="button"
                    class="btn btn-primary btn-anak w-full py-3.5 text-lg font-bold shadow-lg"
                    x-on:click="masukLayarPenuh()"
                >
                    Kembali ke Layar Penuh
                </button>
            </div>
        </div>

        {{-- Processing Modal: Toleransi Habis, Mengumpulkan & Logout --}}
        <div
            x-show="memprosesPelanggaran"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-ink/90 p-4 backdrop-blur-md"
        >
            <div class="card w-full max-w-md border border-outline bg-page p-8 text-center shadow-2xl space-y-4">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-coral-soft text-coral-deep">
                    <x-icon name="alert" :size="32" />
                </div>
                <h2 class="text-2xl font-bold text-ink">Waktu Toleransi Habis</h2>
                <p class="text-base text-ink-muted">
                    Jawaban Anda sedang dikumpulkan otomatis dan sesi akun Anda sedang dikeluarkan...
                </p>
            </div>
        </div>

        <p class="text-base text-ink-muted">
            Menutup jendela tidak menghentikan waktu. Attempt tetap berjalan sampai batas waktu server.
        </p>
    </div>
</x-layouts.peserta>
