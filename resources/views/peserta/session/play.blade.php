<x-layouts.peserta :judul="$session->nama" :keterangan="'Mode Serempak · '.$session->quiz->judul" :fokus="true">
    <script>
        window.kuisSerempak = (config) => ({
            state: null,
            memuat: true,
            error: '',
            menyimpan: false,
            pesan: '',
            peringatan: '',
            jumlahKejadian: 0,
            sisa: 0,
            target: 0,
            offset: 0,
            berhenti: false,
            kejadianTerakhir: {},
            keluarSejak: 0,
            sudahMulai: false,
            layarPenuhAktif: false,
            peringatanKeluar: false,
            sisaToleransi: 10,
            timerToleransi: null,
            memprosesPelanggaran: false,
            audioCtx: null,
            terakhirBipDetik: null,
            isOnline: navigator.onLine ?? true,
            storageKey: 'kuis_serempak_' + config.sessionId,
            antreanOffline: [],
            sedangSinkron: false,
            get isFullscreen() {
                return Boolean(
                    document.fullscreenElement ||
                    document.webkitFullscreenElement ||
                    document.mozFullScreenElement ||
                    document.msFullscreenElement
                );
            },
            init() {
                this.muat();
                this.muatAntreanLokal();
                this.pasangDeteksiKoneksi();
                setInterval(() => this.muat(), 3000);
                setInterval(() => this.hitung(), 250);
                this.pasangEcho();
                this.pasangPengawas();

                const bukaAudio = () => {
                    try {
                        const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
                        if (AudioCtxClass && !this.audioCtx) {
                            this.audioCtx = new AudioCtxClass();
                        }
                        if (this.audioCtx && this.audioCtx.state === 'suspended') {
                            this.audioCtx.resume();
                        }
                    } catch (e) {}
                    window.removeEventListener('click', bukaAudio);
                    window.removeEventListener('keydown', bukaAudio);
                };
                window.addEventListener('click', bukaAudio, { passive: true });
                window.addEventListener('keydown', bukaAudio, { passive: true });
            },
            bunyikanAlert(frekuensi = 660, durasi = 0.12) {
                try {
                    const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtxClass) return;
                    if (!this.audioCtx) this.audioCtx = new AudioCtxClass();
                    if (this.audioCtx.state === 'suspended') this.audioCtx.resume();

                    const now = this.audioCtx.currentTime;
                    const osc = this.audioCtx.createOscillator();
                    const gain = this.audioCtx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(frekuensi, now);
                    gain.gain.setValueAtTime(0.2, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + durasi);
                    osc.connect(gain);
                    gain.connect(this.audioCtx.destination);
                    osc.start(now);
                    osc.stop(now + durasi);
                } catch (e) {}
            },
            async muat() {
                let hasil;
                try {
                    hasil = await window.kuisFetch(config.stateUrl);
                } catch (e) {
                    this.memuat = false;
                    this.error = 'Koneksi terputus. Mencoba memuat status kembali...';
                    return;
                }
                this.memuat = false;
                if (!hasil.ok || !hasil.data) {
                    if (hasil.status === 404 || hasil.status === 403) {
                        this.berhenti = true;
                        this.batalkanToleransi();
                        window.location.href = config.dashboardUrl + '?dikeluarkan=1';
                        return;
                    }
                    this.error = 'Gagal memuat status sesi. Mencoba lagi sebentar.';
                    return;
                }
                this.error = '';
                this.state = hasil.data;
                this.offset = Date.now() - hasil.data.server_now;
                if (hasil.data.selesai) {
                    this.berhenti = true;
                    this.batalkanToleransi();
                    window.location.href = hasil.data.result_url || config.resultUrl;
                    return;
                }
                this.target = hasil.data.fase === 'jeda'
                    ? (hasil.data.ends_at ?? 0) + ((hasil.data.jeda_detik ?? 0) * 1000)
                    : (hasil.data.ends_at ?? 0);

                if (this.state.soal && !this.state.soal.jawaban_option_id) {
                    try {
                        const savedOpsi = localStorage.getItem(this.storageKey + '_q_' + this.state.soal.id);
                        if (savedOpsi) {
                            this.state.soal.jawaban_option_id = parseInt(savedOpsi, 10);
                        }
                    } catch (e) {}
                }

                this.hitung();
            },
            hitung() {
                if (!this.target) {
                    this.sisa = 0;
                    return;
                }
                this.sisa = Math.max(0, Math.round((this.target - (Date.now() - this.offset)) / 1000));

                // Audio alert hitungan mundur kritis (5, 4, 3, 2, 1) saat fase soal
                if (this.state?.fase === 'soal_terbuka' && this.sisa <= 5 && this.sisa > 0) {
                    if (this.terakhirBipDetik !== this.sisa) {
                        this.terakhirBipDetik = this.sisa;
                        this.bunyikanAlert(this.sisa <= 2 ? 880 : 660, 0.12);
                    }
                }
            },
            format(n) {
                const menit = Math.floor(n / 60);
                const detik = n % 60;
                return menit + ':' + String(detik).padStart(2, '0');
            },
            muatAntreanLokal() {
                try {
                    const raw = localStorage.getItem(this.storageKey + '_antrean');
                    if (raw) {
                        this.antreanOffline = JSON.parse(raw);
                    }
                } catch (e) {
                    this.antreanOffline = [];
                }
                if (navigator.onLine && this.antreanOffline.length > 0) {
                    this.prosesAntrean();
                }
            },
            simpanAntreanKeStorage() {
                try {
                    if (this.antreanOffline.length === 0) {
                        localStorage.removeItem(this.storageKey + '_antrean');
                    } else {
                        localStorage.setItem(this.storageKey + '_antrean', JSON.stringify(this.antreanOffline));
                    }
                } catch (e) {}
            },
            pasangDeteksiKoneksi() {
                window.addEventListener('online', () => {
                    this.isOnline = true;
                    this.prosesAntrean();
                });
                window.addEventListener('offline', () => {
                    this.isOnline = false;
                });
            },
            async prosesAntrean() {
                if (this.sedangSinkron || !navigator.onLine || this.antreanOffline.length === 0) {
                    return;
                }
                this.sedangSinkron = true;
                const sisa = [];
                for (const item of this.antreanOffline) {
                    try {
                        const res = await window.kuisFetch(config.answerUrl, {
                            method: 'POST',
                            body: { question_id: item.question_id, option_id: item.option_id }
                        });
                        if (!res.ok) {
                            sisa.push(item);
                        }
                    } catch (err) {
                        sisa.push(item);
                    }
                }
                this.antreanOffline = sisa;
                this.simpanAntreanKeStorage();
                this.sedangSinkron = false;
            },
            async jawab(opsi) {
                if (!this.state?.soal || this.menyimpan || this.berhenti) {
                    return;
                }
                this.menyimpan = true;
                this.pesan = '';
                const qId = this.state.soal.id;
                this.state.soal.jawaban_option_id = opsi.id;
                try {
                    localStorage.setItem(this.storageKey + '_q_' + qId, String(opsi.id));
                } catch (e) {}

                if (!navigator.onLine) {
                    this.isOnline = false;
                    this.menyimpan = false;
                    this.antreanOffline = this.antreanOffline.filter(item => item.question_id !== qId);
                    this.antreanOffline.push({ question_id: qId, option_id: opsi.id });
                    this.simpanAntreanKeStorage();
                    this.pesan = 'Mode offline: Jawaban disimpan di perangkat Anda.';
                    return;
                }

                try {
                    const hasil = await window.kuisFetch(config.answerUrl, {
                        method: 'POST',
                        body: { question_id: qId, option_id: opsi.id }
                    });
                    this.menyimpan = false;
                    if (hasil.ok) {
                        this.state.soal.jawaban_option_id = opsi.id;
                        this.state.sudah_menjawab = hasil.data.sudah_menjawab;
                        this.antreanOffline = this.antreanOffline.filter(item => item.question_id !== qId);
                        this.simpanAntreanKeStorage();
                        return;
                    }
                    this.pesan = hasil.data?.message ?? 'Jawaban gagal dikirim. Coba lagi.';
                } catch (e) {
                    this.menyimpan = false;
                    this.isOnline = false;
                    this.antreanOffline = this.antreanOffline.filter(item => item.question_id !== qId);
                    this.antreanOffline.push({ question_id: qId, option_id: opsi.id });
                    this.simpanAntreanKeStorage();
                    this.pesan = 'Koneksi terputus: Jawaban tersimpan di perangkat.';
                }
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
                    const sesiAktif = this.state && this.state.status === 'berjalan';
                    if (this.sudahMulai && sesiAktif && !this.berhenti && !this.memprosesPelanggaran) {
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
                if (! this.keluarSejak) {
                    this.keluarSejak = Date.now();
                }
                this.timerToleransi = setInterval(() => {
                    this.sisaToleransi--;
                    if (this.sisaToleransi <= 0) {
                        this.eksekusiPelanggaran();
                    }
                }, 1000);
            },
            batalkanToleransi() {
                if (this.keluarSejak) {
                    const detik = Math.round((Date.now() - this.keluarSejak) / 1000);
                    this.keluarSejak = 0;

                    if (detik > 0 && ! this.berhenti && ! this.memprosesPelanggaran) {
                        window.kuisFetch(config.eventUrl, {
                            method: 'POST',
                            body: { tipe: 'kembali', durasi_detik: detik }
                        });
                    }
                }

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
                this.memprosesPelanggaran = true;
                this.berhenti = true;
                this.batalkanToleransi();
                const form = document.getElementById('form-pelanggaran');
                if (form) {
                    form.submit();
                }
            },
            pasangEcho() {
                const echo = window.buatEcho();
                if (!echo) {
                    return;
                }
                echo.private(config.channel)
                    .listen('.SoalDibuka', () => this.muat())
                    .listen('.SoalDitutup', () => this.muat())
                    .listen('.SesiSelesai', () => {
                        this.berhenti = true;
                        this.batalkanToleransi();
                        window.location.href = config.resultUrl;
                    });

                // Notifikasi instan untuk aksi personal admin
                const userChannel = 'session-user.' + {{ $session->id }} + '.' + {{ auth()->id() }};
                echo.channel(userChannel)
                    .listen('.PesertaDikeluarkan', (data) => {
                        this.berhenti = true;
                        this.batalkanToleransi();
                        alert(data.pesan || 'Kamu telah dikeluarkan dari sesi oleh pengajar.');
                        window.location.href = '{{ route("peserta.dashboard", ["dikeluarkan" => 1]) }}';
                    })
                    .listen('.PesertaDipaksaKumpul', (data) => {
                        this.berhenti = true;
                        this.batalkanToleransi();
                        alert(data.pesan || 'Pekerjaanmu telah dikumpulkan oleh pengajar.');
                        window.location.href = config.resultUrl;
                    });
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
                    const sesiAktif = this.state && this.state.status === 'berjalan';
                    if (this.sudahMulai && sesiAktif && !this.berhenti && !this.memprosesPelanggaran) {
                        this.mulaiToleransi();
                    }
                });

                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        this.kirimKejadian('tab_hidden');
                        const sesiAktif = this.state && this.state.status === 'berjalan';
                        if (this.sudahMulai && sesiAktif && !this.berhenti && !this.memprosesPelanggaran) {
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
                    const area = document.getElementById('area-serempak');
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
        id="area-serempak"
        x-data="kuisSerempak({
            stateUrl: '{{ $stateUrl }}',
            answerUrl: '{{ $answerUrl }}',
            resultUrl: '{{ $resultUrl }}',
            dashboardUrl: '{{ route('peserta.dashboard') }}',
            channel: '{{ $channel }}',
            eventUrl: '{{ route('peserta.attempt.events', $attempt) }}'
        })"
        class="space-y-5"
    >
        <div x-show="error" x-cloak class="alert alert-error flex items-center justify-between gap-3" role="alert">
            <p x-text="error"></p>
            <button type="button" class="btn btn-secondary" x-on:click="muat()">Coba lagi</button>
        </div>

        <div x-show="peringatan" x-cloak class="alert alert-warning flex items-start justify-between gap-3">
            <p x-text="peringatan"></p>
            <button type="button" class="font-bold underline" x-on:click="peringatan = ''">Tutup</button>
        </div>

        <div x-show="pesan" x-cloak class="alert alert-error" role="alert">
            <p x-text="pesan"></p>
        </div>

        <template x-if="memuat && !state">
            <div class="card text-center">
                <p class="text-lg text-ink-muted">Memuat status sesi...</p>
            </div>
        </template>

        <template x-if="state && state.status === 'lobby'">
            <section class="card text-center">
                <span class="pill pill-brand">Lobby</span>
                <h2 class="mt-4 text-3xl">Menunggu admin memulai</h2>
                <p class="mt-2 text-lg text-ink-muted">Anda sudah masuk. Soal pertama akan terbuka otomatis saat sesi dimulai.</p>
                <p class="display-number mt-5 text-6xl" x-text="state.total_peserta"></p>
                <p class="text-base text-ink-muted">peserta sudah bergabung</p>

                <template x-if="state.peserta && state.peserta.length">
                    <ul class="mx-auto mt-4 flex max-w-lg flex-wrap justify-center gap-2">
                        <template x-for="nama in state.peserta" :key="nama">
                            <li class="pill pill-neutral" x-text="nama"></li>
                        </template>
                    </ul>
                </template>
            </section>
        </template>

        <template x-if="state && state.status === 'berjalan' && state.fase === 'soal_terbuka' && state.soal">
            <section class="space-y-5">
                <div class="exam-bar -mx-4 px-4 py-3 sm:-mx-6 sm:px-6">
                    <div class="mx-auto flex max-w-6xl flex-col gap-2">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-baseline gap-2">
                                <p class="text-sm font-bold text-ink-muted">Soal</p>
                                <p class="font-display text-2xl font-bold text-ink">
                                    <span x-text="state.soal.urutan"></span><span class="text-ink-muted"> / </span><span x-text="state.soal.total_soal"></span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <span x-show="sisa <= 5 && sisa > 0" class="inline-flex h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span>
                                        <p class="text-xs font-bold text-ink-muted" x-text="sisa <= 5 && sisa > 0 ? 'Waktu Kritis' : 'Sisa waktu'">Sisa waktu</p>
                                    </div>
                                    <p
                                        class="timer text-2xl font-bold transition-all duration-200"
                                        x-bind:class="{
                                            'text-rose-600 animate-pulse scale-105 font-extrabold': sisa <= 5,
                                            'text-amber-600 font-bold': sisa > 5 && sisa <= 10,
                                            'text-ink': sisa > 10
                                        }"
                                        x-text="format(sisa)"
                                    >0:00</p>
                                </div>
                                <button type="button" class="btn btn-secondary px-3" x-on:click="masukLayarPenuh()" x-bind:disabled="isFullscreen" aria-label="Masuk layar penuh">
                                    <x-icon name="eye" :size="20" />
                                </button>
                            </div>
                        </div>

                        <div class="exam-progress" role="progressbar" aria-label="Progres soal" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="state.soal.total_soal ? Math.round(state.soal.urutan / state.soal.total_soal * 100) : 0">
                            <span x-bind:style="'width: ' + (state.soal.total_soal ? Math.round(state.soal.urutan / state.soal.total_soal * 100) : 0) + '%'"></span>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-ink-muted" x-text="state.sudah_menjawab + ' dari ' + state.total_peserta + ' peserta sudah menjawab'"></span>
                                <template x-if="!isOnline">
                                    <span class="pill pill-coral text-xs">Offline</span>
                                </template>
                                <template x-if="antreanOffline.length > 0">
                                    <span class="pill pill-sun text-xs" x-text="antreanOffline.length + ' di perangkat'"></span>
                                </template>
                            </div>
                            <span class="exam-status" aria-live="polite" x-text="sedangSinkron ? 'Menyinkronkan...' : (menyimpan ? 'Mengirim jawaban...' : (state.soal.jawaban_option_id ? 'Tersimpan' : ''))"></span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <span class="pill pill-brand">Pilihan ganda</span>

                    <h2 id="teks-soal-serempak" class="question-text mt-4 whitespace-pre-line" x-text="state.soal.teks"></h2>

                    <div class="mt-5 space-y-3" role="group" aria-labelledby="teks-soal-serempak">
                        <template x-for="(opsi, opsiIndex) in state.soal.opsi" :key="opsi.id">
                            <button
                                type="button"
                                class="tile"
                                x-bind:aria-pressed="state.soal.jawaban_option_id === opsi.id ? 'true' : 'false'"
                                x-on:click="jawab(opsi)"
                                x-bind:disabled="menyimpan"
                            >
                                <span class="tile-mark" x-text="String.fromCharCode(65 + opsiIndex)"></span>
                                <span class="tile-text" x-text="opsi.teks"></span>
                                <span class="tile-check" aria-hidden="true">
                                    <x-icon name="check" :size="20" />
                                </span>
                            </button>
                        </template>
                    </div>

                    <p class="mt-4 text-base text-ink-muted">Jawaban bisa diubah sampai soal ditutup. Tombol yang dipilih berarti tersimpan.</p>
                </div>
            </section>
        </template>

        <template x-if="state && state.status === 'berjalan' && state.fase === 'jeda'">
            <section class="card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="pill pill-sun">Jeda</span>
                    <p class="text-base text-ink-muted">Soal berikutnya dalam <span class="timer text-lg text-ink" x-text="format(sisa)"></span> detik</p>
                </div>

                <h2 class="mt-4 text-2xl">Soal ditutup</h2>

                <template x-if="state.jeda">
                    <div class="mt-4 space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span
                                class="pill"
                                x-bind:class="state.jeda.benar ? 'pill-leaf' : 'pill-coral'"
                                x-text="state.jeda.benar ? 'Jawaban Anda benar' : 'Jawaban Anda belum tepat'"
                            ></span>
                            <span class="pill pill-brand" x-text="'Poin soal: ' + state.jeda.skor_soal"></span>
                        </div>

                        <div class="rounded-2xl bg-leaf-soft p-4">
                            <p class="text-sm font-bold text-leaf-deep">Jawaban benar</p>
                            <p class="mt-1 text-lg font-bold text-ink" x-text="state.jeda.jawaban_benar || '-'"></p>
                        </div>

                        <div class="rounded-2xl bg-surface p-4">
                            <p class="text-sm font-bold text-ink-muted">Jawaban Anda</p>
                            <p class="mt-1 text-lg text-ink" x-text="state.jeda.jawaban_saya || 'Tidak dijawab'"></p>
                        </div>

                        <template x-if="state.jeda.pembahasan">
                            <div class="rounded-2xl border border-outline p-4">
                                <p class="text-sm font-bold text-ink-muted">Pembahasan</p>
                                <p class="mt-1 text-base text-ink" x-text="state.jeda.pembahasan"></p>
                            </div>
                        </template>
                    </div>
                </template>
            </section>
        </template>

        <template x-if="state && state.status === 'draft'">
            <div class="card text-center">
                <p class="text-lg text-ink-muted">Sesi belum dibuka oleh admin.</p>
            </div>
        </template>

        <form id="form-pelanggaran" method="POST" action="{{ route('peserta.session.pelanggaran', $session) }}" class="hidden">
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
                    Jika Anda keluar dari layar penuh atau berpindah tab/aplikasi saat kuis berjalan, sistem akan memberi waktu toleransi sebelum <strong>jawaban otomatis dikumpulkan dan akun Anda di-logout</strong>.
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
    </div>
</x-layouts.peserta>
