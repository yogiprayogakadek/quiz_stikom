<x-layouts.peserta :judul="$session->nama" :keterangan="'Mode Acak · '.$session->quiz->judul" :fokus="true">
    <script>
        window.kuisAcak = (config) => ({
            questions: config.questions,
            index: 0,
            jawaban: config.jawaban,
            jawabanTeks: config.jawabanTeks,
            teksTersimpan: { ...config.jawabanTeks },
            statusTeks: {},
            janjiTeks: {},
            timerTeks: {},
            menyimpan: false,
            sedangSinkron: false,
            antreanOffline: [],
            isOnline: navigator.onLine ?? true,
            storageKey: 'kuis_antrean_attempt_' + config.attemptId,
            pesan: '',
            peringatan: '',
            jumlahKejadian: 0,
            sisa: 0,
            berhenti: false,
            selesaiOtomatis: false,
            konfirmasiSelesai: false,
            kejadianTerakhir: {},
            sudahMulai: false,
            layarPenuhAktif: false,
            peringatanKeluar: false,
            sisaToleransi: 10,
            timerToleransi: null,
            memprosesPelanggaran: false,
            audioCtx: null,
            notifWaktu300: false,
            notifWaktu60: false,
            get current() {
                return this.questions[this.index] ?? null;
            },
            get terjawab() {
                return this.questions.filter((soal) => this.sudahDijawab(soal)).length;
            },
            get belum() {
                return this.questions.length - this.terjawab;
            },
            get statusTeksAktif() {
                if (this.current?.tipe !== 'essay') {
                    return '';
                }

                return {
                    menunggu: 'Menunggu disimpan',
                    menyimpan: 'Menyimpan...',
                    tersimpan: 'Tersimpan otomatis',
                    offline: 'Tersimpan lokal (offline)',
                    gagal: 'Gagal menyimpan'
                }[this.statusTeks[this.current.id]] ?? '';
            },
            sudahDijawab(soal) {
                if (soal.tipe === 'essay') {
                    return (this.jawabanTeks[soal.id] ?? '').trim() !== '';
                }

                return this.jawaban[soal.id] !== null && this.jawaban[soal.id] !== undefined;
            },
            pindah(nomor) {
                this.index = Math.max(0, Math.min(this.questions.length - 1, nomor));
                this.$nextTick(() => this.$refs.kartuSoal?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
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
                this.muatAntreanLokal();
                this.mulaiTimer();
                this.pasangPengawas();
                this.pasangDeteksiKoneksi();
                this.pasangEcho();
                setInterval(() => this.cekStatus(), 5000);

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
            bunyikanAlert(frekuensi = 520, durasi = 0.2, bipUlang = 1) {
                try {
                    const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtxClass) return;
                    if (!this.audioCtx) this.audioCtx = new AudioCtxClass();
                    if (this.audioCtx.state === 'suspended') this.audioCtx.resume();

                    for (let i = 0; i < bipUlang; i++) {
                        const start = this.audioCtx.currentTime + (i * 0.18);
                        const osc = this.audioCtx.createOscillator();
                        const gain = this.audioCtx.createGain();
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(frekuensi, start);
                        gain.gain.setValueAtTime(0.25, start);
                        gain.gain.exponentialRampToValueAtTime(0.001, start + durasi);
                        osc.connect(gain);
                        gain.connect(this.audioCtx.destination);
                        osc.start(start);
                        osc.stop(start + durasi);
                    }
                } catch (e) {}
            },
            pasangEcho() {
                if (typeof window.buatEcho !== 'function') return;
                const echo = window.buatEcho();
                if (!echo) return;

                const userChannel = 'session-user.' + config.sessionId + '.' + config.userId;
                echo.channel(userChannel)
                    .listen('.PesertaDikeluarkan', (data) => {
                        this.berhenti = true;
                        this.batalkanToleransi();
                        alert(data.pesan || 'Kamu telah dikeluarkan dari sesi oleh pengajar.');
                        window.location.href = config.dashboardUrl + '?dikeluarkan=1';
                    })
                    .listen('.PesertaDipaksaKumpul', (data) => {
                        this.berhenti = true;
                        this.batalkanToleransi();
                        alert(data.pesan || 'Pekerjaanmu telah dikumpulkan oleh pengajar.');
                        window.location.href = config.resultUrl;
                    });
            },
            muatAntreanLokal() {
                try {
                    const raw = localStorage.getItem(this.storageKey);
                    if (raw) {
                        this.antreanOffline = JSON.parse(raw);
                        // Pulihkan jawaban lokal jika ada di antrean
                        for (const item of this.antreanOffline) {
                            if (item.tipe === 'essay') {
                                this.jawabanTeks[item.question_id] = item.jawaban_teks;
                                this.statusTeks[item.question_id] = 'offline';
                            } else if (item.option_id) {
                                this.jawaban[item.question_id] = item.option_id;
                            }
                        }
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
                        localStorage.removeItem(this.storageKey);
                    } else {
                        localStorage.setItem(this.storageKey, JSON.stringify(this.antreanOffline));
                    }
                } catch (e) {
                    // local storage full or unavailable
                }
            },
            tambahAntrean(item) {
                // Hapus entry lama untuk soal yang sama agar tidak menumpuk
                this.antreanOffline = this.antreanOffline.filter((q) => q.question_id !== item.question_id);
                this.antreanOffline.push(item);
                this.simpanAntreanKeStorage();
            },
            pasangDeteksiKoneksi() {
                window.addEventListener('online', () => {
                    this.isOnline = true;
                    this.peringatan = 'Koneksi internet terhubung kembali. Mengirim antrean jawaban ke server...';
                    this.prosesAntrean();
                });
                window.addEventListener('offline', () => {
                    this.isOnline = false;
                    this.peringatan = 'Koneksi internet terputus. Jawaban akan disimpan di perangkat (offline) dan disinkronkan saat terhubung kembali.';
                });
            },
            async prosesAntrean() {
                if (this.sedangSinkron || this.antreanOffline.length === 0) {
                    return;
                }
                this.sedangSinkron = true;

                while (this.antreanOffline.length > 0) {
                    if (!navigator.onLine) {
                        this.isOnline = false;
                        break;
                    }

                    const item = this.antreanOffline[0];
                    const body = { question_id: item.question_id };
                    if (item.tipe === 'essay') {
                        body.jawaban_teks = item.jawaban_teks;
                    } else {
                        body.option_id = item.option_id;
                    }

                    try {
                        const res = await window.kuisFetch(config.answerUrl, {
                            method: 'POST',
                            body: body
                        });

                        if (res.ok) {
                            this.antreanOffline.shift();
                            this.simpanAntreanKeStorage();
                            if (item.tipe === 'essay') {
                                this.teksTersimpan[item.question_id] = item.jawaban_teks;
                                this.statusTeks[item.question_id] = 'tersimpan';
                            }
                        } else if (res.status === 403 && res.data?.selesai) {
                            this.berhenti = true;
                            window.location.href = config.resultUrl;
                            break;
                        } else if (res.status === 419) {
                            // Sesi CSRF kedaluwarsa, arahkan peserta reload
                            this.pesan = 'Sesi login telah kedaluwarsa. Halaman akan dimuat ulang...';
                            setTimeout(() => window.location.reload(), 1500);
                            break;
                        } else if (res.status === 429) {
                            // Rate limit server: jeda 1.5 detik lalu ulangi item yang sama
                            await new Promise((r) => setTimeout(r, 1500));
                            continue;
                        } else if (res.status >= 500) {
                            // Server error sementara, jangan buang jawaban: tunggu 2 detik lalu ulangi
                            await new Promise((r) => setTimeout(r, 2000));
                            break;
                        } else {
                            // Kesalahan validasi data client (422 dll)
                            this.antreanOffline.shift();
                            this.simpanAntreanKeStorage();
                        }
                    } catch (err) {
                        // Jaringan putus saat request
                        this.isOnline = false;
                        break;
                    }

                    // Jeda 750ms per request agar antrean database teratur dan di bawah batas throttle (90 req/menit)
                    await new Promise((r) => setTimeout(r, 750));
                }

                this.sedangSinkron = false;
                if (this.antreanOffline.length === 0 && this.peringatan.includes('Mengirim antrean')) {
                    this.peringatan = '';
                }
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

                    // Alert audio & visual saat waktu kritis mendekati batas
                    if (this.sisa > 0) {
                        if (this.sisa <= 60 && !this.notifWaktu60) {
                            this.notifWaktu60 = true;
                            this.bunyikanAlert(880, 0.25, 2);
                        } else if (this.sisa <= 300 && !this.notifWaktu300) {
                            this.notifWaktu300 = true;
                            this.bunyikanAlert(587.33, 0.2, 1);
                        }
                    }

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

                if (!navigator.onLine) {
                    this.menyimpan = false;
                    this.tambahAntrean({
                        question_id: soal.id,
                        option_id: opsi.id,
                        tipe: 'pilihan_ganda',
                        waktu: Date.now()
                    });
                    return;
                }

                let hasil;
                try {
                    hasil = await window.kuisFetch(config.answerUrl, {
                        method: 'POST',
                        body: { question_id: soal.id, option_id: opsi.id }
                    });
                } catch (e) {
                    // Jaringan bermasalah, simpan ke antrean lokal
                    this.menyimpan = false;
                    this.tambahAntrean({
                        question_id: soal.id,
                        option_id: opsi.id,
                        tipe: 'pilihan_ganda',
                        waktu: Date.now()
                    });
                    return;
                }

                this.menyimpan = false;
                if (hasil.ok) {
                    return;
                }
                if (hasil.status === 403) {
                    this.pesan = hasil.data?.message ?? 'Jawaban tidak diterima. Waktu mungkin sudah habis.';
                    if (hasil.data?.selesai) {
                        this.berhenti = true;
                        window.location.href = config.resultUrl;
                    }
                    return;
                }
                // Jika error server umum, simpan ke antrean offline
                this.tambahAntrean({
                    question_id: soal.id,
                    option_id: opsi.id,
                    tipe: 'pilihan_ganda',
                    waktu: Date.now()
                });
            },
            ubahTeks(soal, nilai) {
                if (this.berhenti) {
                    return;
                }
                this.jawabanTeks[soal.id] = nilai;
                this.statusTeks[soal.id] = 'menunggu';
                clearTimeout(this.timerTeks[soal.id]);
                this.timerTeks[soal.id] = setTimeout(() => this.simpanTeks(soal), 800);
            },
            async simpanTeks(soal) {
                if (!soal || soal.tipe !== 'essay' || this.berhenti) {
                    return true;
                }

                if (this.janjiTeks[soal.id]) {
                    await this.janjiTeks[soal.id];

                    if ((this.jawabanTeks[soal.id] ?? '') === (this.teksTersimpan[soal.id] ?? '')) {
                        return true;
                    }
                }

                const nilai = this.jawabanTeks[soal.id] ?? '';

                if (nilai === (this.teksTersimpan[soal.id] ?? '')) {
                    this.statusTeks[soal.id] = nilai === '' ? '' : 'tersimpan';
                    return true;
                }

                if (!navigator.onLine) {
                    this.statusTeks[soal.id] = 'offline';
                    this.tambahAntrean({
                        question_id: soal.id,
                        jawaban_teks: nilai,
                        tipe: 'essay',
                        waktu: Date.now()
                    });
                    return true;
                }

                this.statusTeks[soal.id] = 'menyimpan';
                let hasil;

                try {
                    const janji = window.kuisFetch(config.answerUrl, {
                        method: 'POST',
                        body: { question_id: soal.id, jawaban_teks: nilai }
                    });
                    this.janjiTeks[soal.id] = janji;
                    hasil = await janji;
                } catch {
                    delete this.janjiTeks[soal.id];
                    this.statusTeks[soal.id] = 'offline';
                    this.tambahAntrean({
                        question_id: soal.id,
                        jawaban_teks: nilai,
                        tipe: 'essay',
                        waktu: Date.now()
                    });
                    return true;
                }

                delete this.janjiTeks[soal.id];

                if (hasil.ok) {
                    this.teksTersimpan[soal.id] = nilai;
                    this.statusTeks[soal.id] = 'tersimpan';

                    return true;
                }

                if (hasil.status === 403) {
                    this.statusTeks[soal.id] = 'gagal';
                    this.pesan = hasil.data?.message ?? 'Jawaban essay tidak diterima. Waktu mungkin sudah habis.';
                    if (hasil.data?.selesai) {
                        this.berhenti = true;
                        window.location.href = config.resultUrl;
                    }

                    return false;
                }

                // Error jaringan / temporary server, simpan offline
                this.statusTeks[soal.id] = 'offline';
                this.tambahAntrean({
                    question_id: soal.id,
                    jawaban_teks: nilai,
                    tipe: 'essay',
                    waktu: Date.now()
                });

                return true;
            },
            async simpanSemuaTeks() {
                for (const soal of this.questions.filter((item) => item.tipe === 'essay')) {
                    clearTimeout(this.timerTeks[soal.id]);

                    if (!(await this.simpanTeks(soal))) {
                        return false;
                    }
                }

                return true;
            },
            async pastikanAntreanTersinkron() {
                if (this.antreanOffline.length > 0) {
                    if (!navigator.onLine) {
                        this.pesan = 'Ada ' + this.antreanOffline.length + ' jawaban tersimpan di perangkat. Harap hubungkan internet Anda kembali untuk menyinkronkan jawaban ke server sebelum menyelesaikan ujian.';
                        return false;
                    }
                    this.pesan = 'Menyinkronkan ' + this.antreanOffline.length + ' jawaban ke server...';
                    await this.prosesAntrean();
                    if (this.antreanOffline.length > 0) {
                        this.pesan = 'Masih ada jawaban yang belum tersinkron ke server. Mohon tunggu beberapa saat.';
                        return false;
                    }
                }
                return true;
            },
            async akhiri(paksa = false) {
                if (this.berhenti) {
                    return;
                }

                if (paksa) {
                    await Promise.race([
                        this.simpanSemuaTeks(),
                        new Promise((resolve) => setTimeout(resolve, 1200))
                    ]);
                    if (navigator.onLine && this.antreanOffline.length > 0) {
                        await Promise.race([
                            this.prosesAntrean(),
                            new Promise((resolve) => setTimeout(resolve, 2000))
                        ]);
                    }
                    this.kirimSelesai();

                    return;
                }

                if (this.belum > 0) {
                    this.konfirmasiSelesai = true;
                    this.$nextTick(() => this.$refs.tombolKembali?.focus());

                    return;
                }

                const tersimpan = await this.simpanSemuaTeks();
                if (!tersimpan) {
                    this.pesan = 'Masih ada jawaban essay yang belum tersimpan. Periksa koneksi lalu coba lagi.';
                    return;
                }

                const sinkron = await this.pastikanAntreanTersinkron();
                if (!sinkron) {
                    return;
                }

                this.kirimSelesai();
            },
            async kumpulkanSekarang() {
                const tersimpan = await this.simpanSemuaTeks();
                if (!tersimpan) {
                    this.pesan = 'Masih ada jawaban essay yang belum tersimpan. Periksa koneksi lalu coba lagi.';
                    return;
                }

                const sinkron = await this.pastikanAntreanTersinkron();
                if (!sinkron) {
                    return;
                }

                this.kirimSelesai();
            },
            tutupKonfirmasi() {
                if (!this.konfirmasiSelesai) {
                    return;
                }
                this.konfirmasiSelesai = false;
                this.$nextTick(() => this.$refs.tombolSelesai?.focus());
            },
            kirimSelesai() {
                if (this.berhenti) {
                    return;
                }
                this.konfirmasiSelesai = false;
                this.berhenti = true;
                this.batalkanToleransi();
                try {
                    localStorage.removeItem(this.storageKey);
                } catch (e) {}
                document.getElementById('form-selesai').submit();
            },
            async cekStatus() {
                if (this.berhenti) {
                    return;
                }
                let hasil;
                try {
                    hasil = await window.kuisFetch(config.statusUrl);
                } catch {
                    return;
                }
                if (!hasil.ok) {
                    if (hasil.status === 404 || hasil.status === 403) {
                        this.berhenti = true;
                        this.batalkanToleransi();
                        window.location.href = config.dashboardUrl + '?dikeluarkan=1';
                    }
                    return;
                }
                if (hasil.data?.selesai) {
                    this.berhenti = true;
                    this.batalkanToleransi();
                    window.location.href = config.resultUrl;
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
            attemptId: {{ $attempt->id }},
            questions: {{ Illuminate\Support\Js::from($questions) }},
            jawaban: {{ Illuminate\Support\Js::from(collect($questions)->mapWithKeys(fn ($q) => [$q['id'] => $q['jawaban_option_id']])->filter()->all()) }},
            jawabanTeks: {{ Illuminate\Support\Js::from(collect($questions)->mapWithKeys(fn ($q) => [$q['id'] => $q['jawaban_teks'] ?? ''])->all()) }},
            serverNow: {{ $server_now }},
            batasAkhir: {{ $attempt->batas_akhir_at }},
            sessionId: {{ $session->id }},
            userId: {{ auth()->id() }},
            attemptId: {{ $attempt->id }},
            answerUrl: '{{ route('peserta.attempt.answer', $attempt) }}',
            eventUrl: '{{ route('peserta.attempt.events', $attempt) }}',
            statusUrl: '{{ route('peserta.attempt.status', $attempt) }}',
            dashboardUrl: '{{ route('peserta.dashboard') }}',
            resultUrl: '{{ route('peserta.attempt.result', $attempt) }}'
        })"
        class="space-y-5"
    >
        <div class="exam-bar -mx-4 px-4 py-3 sm:-mx-6 sm:px-6">
            <div class="mx-auto flex max-w-6xl flex-col gap-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-baseline gap-2">
                        <p class="text-sm font-bold text-ink-muted">Soal</p>
                        <p class="font-display text-2xl font-bold text-ink">
                            <span x-text="index + 1">1</span><span class="text-ink-muted"> / </span><span x-text="questions.length"></span>
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <span x-show="sisa <= 60 && sisa > 0" class="inline-flex h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span>
                                <p class="text-xs font-bold text-ink-muted" x-text="sisa <= 60 && sisa > 0 ? 'Waktu Kritis' : 'Sisa waktu'">Sisa waktu</p>
                            </div>
                            <p
                                class="timer text-2xl font-bold transition-all duration-200"
                                x-bind:class="{
                                    'text-rose-600 animate-pulse font-extrabold': sisa <= 60,
                                    'text-amber-600 font-bold': sisa > 60 && sisa <= 300,
                                    'text-ink': sisa > 300
                                }"
                                x-text="format(sisa)"
                            >0:00</p>
                        </div>
                        <button type="button" class="btn btn-secondary px-3" x-on:click="masukLayarPenuh()" x-bind:disabled="isFullscreen" aria-label="Masuk layar penuh">
                            <x-icon name="eye" :size="20" />
                        </button>
                        <button type="button" class="btn btn-primary" x-ref="tombolSelesai" x-on:click="akhiri(false)">Selesai</button>
                    </div>
                </div>

                <div
                    class="exam-progress"
                    role="progressbar"
                    aria-label="Progres jawaban"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    x-bind:aria-valuenow="questions.length ? Math.round(terjawab / questions.length * 100) : 0"
                >
                    <span x-bind:style="'width: ' + (questions.length ? Math.round(terjawab / questions.length * 100) : 0) + '%'"></span>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-ink-muted"><span x-text="terjawab"></span> dari <span x-text="questions.length"></span> terjawab</span>
                        <template x-if="!isOnline">
                            <span class="pill pill-coral text-xs">Offline</span>
                        </template>
                        <template x-if="antreanOffline.length > 0">
                            <button
                                type="button"
                                class="pill pill-sun text-xs hover:underline cursor-pointer"
                                x-on:click="prosesAntrean()"
                                x-bind:disabled="sedangSinkron"
                                title="Klik untuk sinkronkan sekarang"
                            >
                                <span x-text="antreanOffline.length + ' tersimpan lokal (sinkronkan)'"></span>
                            </button>
                        </template>
                    </div>
                    <span class="exam-status" aria-live="polite" x-text="sedangSinkron ? 'Menyinkronkan ke server...' : (menyimpan ? 'Menyimpan jawaban...' : statusTeksAktif)"></span>
                </div>
            </div>
        </div>

        <div x-show="peringatan" x-cloak class="alert alert-warning flex items-start justify-between gap-3">
            <p x-text="peringatan"></p>
            <button type="button" class="font-bold underline" x-on:click="peringatan = ''">Tutup</button>
        </div>

        <div x-show="pesan" x-cloak class="alert alert-error" role="alert">
            <p x-text="pesan"></p>
        </div>

        <template x-if="current">
            <section x-ref="kartuSoal" class="card scroll-mt-32" aria-labelledby="teks-soal">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="pill pill-brand" x-text="current.tipe === 'essay' ? 'Essay · maks ' + current.bobot : 'Pilihan ganda'"></span>
                    <button type="button" class="btn btn-ghost px-3" x-on:click="masukLayarPenuh()" x-bind:disabled="isFullscreen">
                        <x-icon name="eye" :size="20" />
                        <span class="hidden sm:inline">Layar penuh</span>
                    </button>
                </div>

                <h2 id="teks-soal" class="mt-4 max-w-[65ch] whitespace-pre-line text-2xl leading-snug text-ink sm:text-3xl" x-text="current.teks"></h2>

                <template x-if="current.tipe === 'essay'">
                    <div class="mt-5">
                        <label for="jawaban-essay" class="label">Jawaban Anda</label>
                        <textarea
                            id="jawaban-essay"
                            class="input min-h-[12rem] resize-y"
                            maxlength="5000"
                            x-bind:disabled="berhenti"
                            x-bind:value="jawabanTeks[current.id] ?? ''"
                            x-on:input="ubahTeks(current, $event.target.value)"
                            x-on:blur="simpanTeks(current)"
                            placeholder="Tulis jawaban lengkap di sini"
                        ></textarea>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm text-ink-muted">
                            <span x-text="(jawabanTeks[current.id] ?? '').length + ' / 5000 karakter'"></span>
                            <span x-text="statusTeksAktif"></span>
                        </div>
                    </div>
                </template>

                <template x-if="current.tipe !== 'essay'">
                    <div class="mt-5 space-y-3" role="group" aria-labelledby="teks-soal">
                        <p class="text-base text-ink-muted">Pilih satu jawaban. Jawaban tersimpan saat diklik.</p>
                        <template x-for="(opsi, opsiIndex) in current.opsi" :key="opsi.id">
                            <button
                                type="button"
                                class="tile"
                                x-bind:aria-pressed="jawaban[current.id] === opsi.id ? 'true' : 'false'"
                                x-bind:disabled="menyimpan"
                                x-on:click="pilih(current, opsi)"
                            >
                                <span class="tile-mark" x-text="String.fromCharCode(65 + opsiIndex)"></span>
                                <span class="tile-text" x-text="opsi.teks"></span>
                                <span class="tile-check" aria-hidden="true">
                                    <x-icon name="check" :size="20" />
                                </span>
                            </button>
                        </template>
                    </div>
                </template>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-secondary" x-on:click="pindah(index - 1)" x-bind:disabled="index === 0">
                            <x-icon name="arrow-left" :size="20" />
                            Sebelumnya
                        </button>
                        <button type="button" class="btn btn-secondary" x-on:click="pindah(index + 1)" x-bind:disabled="index === questions.length - 1">
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
                        x-bind:class="nomor === index ? 'border-brand bg-brand text-white' : (sudahDijawab(soal) ? 'border-leaf bg-leaf-soft text-leaf-deep' : 'border-outline bg-page text-ink-muted')"
                        x-bind:aria-current="nomor === index ? 'true' : 'false'"
                        x-bind:aria-label="'Soal ' + (nomor + 1) + (sudahDijawab(soal) ? ', sudah dijawab' : ', belum dijawab')"
                        x-on:click="pindah(nomor)"
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

        {{-- Modal Konfirmasi: Kumpulkan Sebelum Semua Soal Terjawab --}}
        <div
            x-show="konfirmasiSelesai"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-[60] flex items-end justify-center bg-ink/70 p-4 backdrop-blur-sm sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="judul-konfirmasi-selesai"
            aria-describedby="isi-konfirmasi-selesai"
            x-on:keydown.escape.window="tutupKonfirmasi()"
            x-on:click.self="tutupKonfirmasi()"
        >
            <div class="card max-h-[92vh] w-full max-w-md overflow-y-auto border-2 border-sun bg-page p-6 shadow-modal sm:p-7">
                <div class="flex items-start gap-4">
                    <span class="flex h-14 w-14 flex-none items-center justify-center rounded-2xl bg-sun-soft text-sun-deep">
                        <x-icon name="alert" :size="28" />
                    </span>
                    <div class="min-w-0">
                        <span class="pill pill-sun">Belum semua terjawab</span>
                        <h2 id="judul-konfirmasi-selesai" class="mt-2 text-2xl">
                            Masih ada <span x-text="belum"></span> soal kosong
                        </h2>
                    </div>
                </div>

                <p id="isi-konfirmasi-selesai" class="mt-4 text-base leading-relaxed text-ink-muted">
                    Soal pilihan ganda yang tidak dijawab dihitung salah, essay kosong bernilai 0. Kamu masih bisa menutup dialog ini dan melanjutkan mengerjakan selama waktu tersisa.
                </p>

                <dl class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-leaf-soft px-4 py-3">
                        <dt class="text-sm font-bold text-leaf-deep">Sudah dijawab</dt>
                        <dd class="display-number mt-1 text-3xl text-leaf-deep" x-text="terjawab"></dd>
                    </div>
                    <div class="rounded-2xl bg-sun-soft px-4 py-3">
                        <dt class="text-sm font-bold text-sun-deep">Belum dijawab</dt>
                        <dd class="display-number mt-1 text-3xl text-sun-deep" x-text="belum"></dd>
                    </div>
                </dl>

                <p class="mt-4 flex items-center gap-2 text-base font-bold text-ink">
                    <x-icon name="clock" :size="20" />
                    Sisa waktu <span class="timer" x-text="format(sisa)">0:00</span>
                </p>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row">
                    <button
                        type="button"
                        class="btn btn-secondary flex-1"
                        x-ref="tombolKembali"
                        x-on:click="tutupKonfirmasi()"
                    >
                        Kembali kerjakan
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary flex-1"
                        x-on:click="kumpulkanSekarang()"
                    >
                        Kumpulkan sekarang
                    </button>
                </div>
            </div>
        </div>

        <p class="text-base text-ink-muted">
            Menutup jendela tidak menghentikan waktu. Attempt tetap berjalan sampai batas waktu server.
        </p>
    </div>
</x-layouts.peserta>
