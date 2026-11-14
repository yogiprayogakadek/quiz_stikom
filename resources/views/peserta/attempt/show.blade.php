<x-layouts.peserta :judul="$session->nama" :keterangan="'Mode Acak · '.$session->quiz->judul" :fokus="true">
    <script>
        window.kuisAcak = (config) => ({
            questions: config.questions,
            index: config.mulaiIndex ?? 0,
            jawaban: config.jawaban,
            jawabanTeks: config.jawabanTeks,
            jawabanParts: config.jawabanParts ?? {},
            teksTersimpan: { ...config.jawabanTeks },
            partsTersimpan: JSON.parse(JSON.stringify(config.jawabanParts ?? {})),
            statusTeks: {},
            statusParts: {},
            janjiTeks: {},
            janjiParts: {},
            timerTeks: {},
            timerParts: {},
            menyimpan: false,
            sedangSinkron: false,
            antreanOffline: [],
            isOnline: navigator.onLine ?? true,
            storageKey: 'kuis_antrean_attempt_' + config.attemptId,
            pesan: '',
            peringatan: '',
            jumlahKejadian: 0,
            sisa: 0,
            batasAkhir: config.batasAkhir ?? 0,
            offset: Date.now() - config.serverNow,
            berhenti: false,
            selesaiOtomatis: false,
            konfirmasiSelesai: false,
            kejadianTerakhir: {},
            keluarSejak: 0,
            sudahMulai: false,
            layarPenuhAktif: false,
            peringatanKeluar: false,
            durasiPeringatan: config.durasiPeringatan ?? 30,
            sisaToleransi: config.durasiPeringatan ?? 30,
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

                if (this.current.sub_soal?.length) {
                    const status = this.current.sub_soal.map((bagian) => this.statusBagian(this.current, bagian));
                    if (status.includes('menyimpan')) return 'Menyimpan...';
                    if (status.includes('menunggu')) return 'Menunggu disimpan';
                    if (status.includes('offline')) return 'Tersimpan lokal (offline)';
                    if (status.includes('gagal')) return 'Gagal menyimpan';
                    if (status.includes('tersimpan')) return 'Tersimpan otomatis';
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
            get yangKosong() {
                const hasil = [];

                this.questions.forEach((soal, nomor) => {
                    if (soal.tipe !== 'essay') {
                        if (!this.sudahDijawab(soal)) {
                            hasil.push('Soal ' + (nomor + 1));
                        }
                        return;
                    }

                    if (soal.sub_soal?.length) {
                        for (const bagian of soal.sub_soal) {
                            if (!this.bagianTerjawab(soal, bagian)) {
                                hasil.push(this.kodeBagian(soal, bagian));
                            }
                        }
                        return;
                    }

                    if (!this.sudahDijawab(soal)) {
                        hasil.push('Soal ' + (nomor + 1));
                    }
                });

                return hasil;
            },
            sudahDijawab(soal) {
                if (soal.tipe === 'essay') {
                    if (soal.sub_soal?.length) {
                        return soal.sub_soal.every((bagian) => this.bagianTerjawab(soal, bagian));
                    }

                    return (this.jawabanTeks[soal.id] ?? '').trim() !== '';
                }

                return this.jawaban[soal.id] !== null && this.jawaban[soal.id] !== undefined;
            },
            terjawabSebagian(soal) {
                if (soal.tipe !== 'essay' || !soal.sub_soal?.length) {
                    return false;
                }

                return soal.sub_soal.some((bagian) => this.bagianTerjawab(soal, bagian)) && !this.sudahDijawab(soal);
            },
            kunciBagian(soal, bagian) {
                return soal.id + ':' + bagian.label;
            },
            statusBagian(soal, bagian) {
                const status = this.statusParts[this.kunciBagian(soal, bagian)];

                if (status) {
                    return {
                        menunggu: 'Menunggu disimpan',
                        menyimpan: 'Menyimpan...',
                        tersimpan: 'Tersimpan otomatis',
                        offline: 'Tersimpan lokal (offline)',
                        gagal: 'Gagal menyimpan'
                    }[status] ?? '';
                }

                return (this.partsTersimpan[soal.id]?.[bagian.label] ?? '').trim() !== '' ? 'Tersimpan otomatis' : '';
            },
            bagianTerjawab(soal, bagian) {
                return (this.jawabanParts[soal.id]?.[bagian.label] ?? '').trim() !== '';
            },
            kodeBagian(soal, bagian) {
                return (this.questions.indexOf(soal) + 1) + (bagian.label ?? '');
            },
            keBagian(soal, bagian) {
                const index = soal.sub_soal.findIndex((item) => item.label === bagian.label);
                this.$nextTick(() => document.getElementById('bagian-' + soal.id + '-' + index)?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            },
            pindah(nomor) {
                this.index = Math.max(0, Math.min(this.questions.length - 1, nomor));
                this.$nextTick(() => this.$refs.kartuSoal?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            },
            async tandai(soal, perubahan) {
                if (!soal || this.berhenti) {
                    return;
                }

                let hasil;
                try {
                    hasil = await window.kuisFetch(config.markUrl, {
                        method: 'POST',
                        body: { question_id: soal.id, ...perubahan },
                        progress: false
                    });
                } catch (e) {
                    this.pesan = 'Penanda soal belum tersimpan. Periksa koneksi lalu coba lagi.';
                    return;
                }

                if (!hasil.ok) {
                    this.pesan = hasil.data?.message ?? 'Penanda soal belum tersimpan.';
                    return;
                }

                soal.ragu_ragu = hasil.data.ragu_ragu;
                soal.highlight_soal = hasil.data.highlight_soal;
                soal.highlight_opsi = hasil.data.highlight_opsi;
            },
            toggleRagu(soal) {
                this.tandai(soal, { ragu_ragu: !soal.ragu_ragu });
            },
            toggleHighlightSoal(soal) {
                this.tandai(soal, { highlight_soal: !soal.highlight_soal });
            },
            toggleHighlightOpsi(soal, opsiId) {
                const opsi = new Set(soal.highlight_opsi || []);
                if (opsi.has(opsiId)) {
                    opsi.delete(opsiId);
                } else {
                    opsi.add(opsiId);
                }
                this.tandai(soal, { highlight_opsi: Array.from(opsi) });
            },
            opsiDisorot(soal, opsiId) {
                return (soal.highlight_opsi || []).includes(opsiId);
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
                    })
                    .listen('.PesertaWaktuDitambah', (data) => {
                        this.batasAkhir = Math.max(this.batasAkhir, Number(data.batas_akhir_at));
                        this.offset = Date.now() - Number(data.server_now);
                        this.notifWaktu60 = false;
                        this.notifWaktu300 = false;
                        this.pesan = 'Waktu ujian ditambah ' + data.minutes + ' menit.';
                    });
            },
            muatAntreanLokal() {
                try {
                    const raw = localStorage.getItem(this.storageKey);
                    if (raw) {
                        this.antreanOffline = JSON.parse(raw);
                        // Pulihkan jawaban lokal jika ada di antrean
                        for (const item of this.antreanOffline) {
                            if (item.tipe === 'essay' && item.part_key) {
                                if (!this.jawabanParts[item.question_id]) {
                                    this.jawabanParts[item.question_id] = {};
                                }
                                this.jawabanParts[item.question_id][item.part_key] = item.jawaban_teks;
                                this.statusParts[item.question_id + ':' + item.part_key] = 'offline';
                            } else if (item.tipe === 'essay') {
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
                // Hapus entry lama untuk soal/bagian yang sama agar tidak menumpuk
                const kunci = (q) => q.question_id + ':' + (q.part_key ?? '');
                const kunciBaru = kunci(item);
                this.antreanOffline = this.antreanOffline.filter((q) => kunci(q) !== kunciBaru);
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
            async prosesAntrean(denganProgress = false) {
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
                        if (item.part_key) {
                            body.part_key = item.part_key;
                        }
                    } else {
                        body.option_id = item.option_id;
                    }

                    try {
                        const res = await window.kuisFetch(config.answerUrl, {
                            method: 'POST',
                            body: body,
                            progress: denganProgress,
                            progressMessage: 'Menyimpan jawaban...'
                        });

                        if (res.ok) {
                            this.antreanOffline.shift();
                            this.simpanAntreanKeStorage();
                            if (item.tipe === 'essay' && item.part_key) {
                                this.partsTersimpan[item.question_id] = res.data?.jawaban_parts ?? {};
                                this.statusParts[item.question_id + ':' + item.part_key] = 'tersimpan';
                                if (res.data?.jawaban_teks !== undefined) {
                                    this.jawabanTeks[item.question_id] = res.data.jawaban_teks;
                                }
                            } else if (item.tipe === 'essay') {
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
                const hitung = () => {
                    this.sisa = Math.max(0, Math.round((this.batasAkhir - (Date.now() - this.offset)) / 1000));

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
                        body: { question_id: soal.id, option_id: opsi.id },
                        progress: false
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
                if (this.berhenti || soal.sub_soal?.length) {
                    return;
                }
                this.jawabanTeks[soal.id] = nilai;
                this.statusTeks[soal.id] = 'menunggu';
                clearTimeout(this.timerTeks[soal.id]);
                this.timerTeks[soal.id] = setTimeout(() => this.simpanTeks(soal), 800);
            },
            async simpanTeks(soal, denganProgress = false) {
                if (!soal || soal.tipe !== 'essay' || this.berhenti || soal.sub_soal?.length) {
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
                        body: { question_id: soal.id, jawaban_teks: nilai },
                        progress: denganProgress,
                        progressMessage: 'Menyimpan jawaban...'
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
            ubahBagian(soal, bagian, nilai) {
                if (this.berhenti) {
                    return;
                }

                if (!this.jawabanParts[soal.id]) {
                    this.jawabanParts[soal.id] = {};
                }
                this.jawabanParts[soal.id][bagian.label] = nilai;

                const key = this.kunciBagian(soal, bagian);
                this.statusParts[key] = 'menunggu';
                clearTimeout(this.timerParts[key]);
                this.timerParts[key] = setTimeout(() => this.simpanBagian(soal, bagian), 800);
            },
            async simpanBagian(soal, bagian, denganProgress = false) {
                if (!soal || soal.tipe !== 'essay' || this.berhenti) {
                    return true;
                }

                const key = this.kunciBagian(soal, bagian);

                if (this.janjiParts[key]) {
                    await this.janjiParts[key];

                    if ((this.jawabanParts[soal.id]?.[bagian.label] ?? '') === (this.partsTersimpan[soal.id]?.[bagian.label] ?? '')) {
                        return true;
                    }
                }

                const nilai = this.jawabanParts[soal.id]?.[bagian.label] ?? '';

                if (nilai === (this.partsTersimpan[soal.id]?.[bagian.label] ?? '')) {
                    this.statusParts[key] = nilai === '' ? '' : 'tersimpan';
                    return true;
                }

                if (!navigator.onLine) {
                    this.statusParts[key] = 'offline';
                    this.tambahAntrean({
                        question_id: soal.id,
                        part_key: bagian.label,
                        jawaban_teks: nilai,
                        tipe: 'essay',
                        waktu: Date.now()
                    });
                    return true;
                }

                this.statusParts[key] = 'menyimpan';
                let hasil;

                try {
                    const janji = window.kuisFetch(config.answerUrl, {
                        method: 'POST',
                        body: { question_id: soal.id, part_key: bagian.label, jawaban_teks: nilai },
                        progress: denganProgress,
                        progressMessage: 'Menyimpan jawaban...'
                    });
                    this.janjiParts[key] = janji;
                    hasil = await janji;
                } catch {
                    delete this.janjiParts[key];
                    this.statusParts[key] = 'offline';
                    this.tambahAntrean({
                        question_id: soal.id,
                        part_key: bagian.label,
                        jawaban_teks: nilai,
                        tipe: 'essay',
                        waktu: Date.now()
                    });
                    return true;
                }

                delete this.janjiParts[key];

                if (hasil.ok) {
                    this.partsTersimpan[soal.id] = hasil.data?.jawaban_parts ?? {};
                    this.statusParts[key] = 'tersimpan';
                    if (hasil.data?.jawaban_teks !== undefined) {
                        this.jawabanTeks[soal.id] = hasil.data.jawaban_teks;
                    }

                    return true;
                }

                if (hasil.status === 403) {
                    this.statusParts[key] = 'gagal';
                    this.pesan = hasil.data?.message ?? 'Jawaban essay tidak diterima. Waktu mungkin sudah habis.';
                    if (hasil.data?.selesai) {
                        this.berhenti = true;
                        window.location.href = config.resultUrl;
                    }

                    return false;
                }

                if (hasil.status === 422) {
                    this.statusParts[key] = 'gagal';
                    this.pesan = hasil.data?.message ?? 'Jawaban tidak diterima.';
                    return false;
                }

                this.statusParts[key] = 'offline';
                this.tambahAntrean({
                    question_id: soal.id,
                    part_key: bagian.label,
                    jawaban_teks: nilai,
                    tipe: 'essay',
                    waktu: Date.now()
                });

                return true;
            },
            async simpanSemuaTeks() {
                for (const soal of this.questions.filter((item) => item.tipe === 'essay')) {
                    if (soal.sub_soal?.length) {
                        for (const bagian of soal.sub_soal) {
                            clearTimeout(this.timerParts[this.kunciBagian(soal, bagian)]);

                            if (!(await this.simpanBagian(soal, bagian, true))) {
                                return false;
                            }
                        }
                        continue;
                    }

                    clearTimeout(this.timerTeks[soal.id]);

                    if (!(await this.simpanTeks(soal, true))) {
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
                    await this.prosesAntrean(true);
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

                const batas = Number(hasil.data?.batas_akhir_at ?? 0);
                const serverNow = Number(hasil.data?.server_now ?? 0);

                if (batas > 0 && serverNow > 0) {
                    const sisaLama = this.sisa;
                    this.batasAkhir = Math.max(this.batasAkhir, batas);
                    this.offset = Date.now() - serverNow;
                    const sisaBaru = Math.max(0, Math.round((this.batasAkhir - (Date.now() - this.offset)) / 1000));

                    if (sisaBaru > sisaLama + 1) {
                        this.notifWaktu60 = false;
                        this.notifWaktu300 = false;
                        this.pesan = 'Waktu ujian ditambah. Timer sudah diperbarui.';
                    }
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
                this.sisaToleransi = this.durasiPeringatan;
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
                            body: { tipe: 'kembali', durasi_detik: detik },
                            progress: false
                        });
                    }
                }

                if (this.timerToleransi) {
                    clearInterval(this.timerToleransi);
                    this.timerToleransi = null;
                }
                this.peringatanKeluar = false;
                this.sisaToleransi = this.durasiPeringatan;
            },
            async eksekusiPelanggaran() {
                if (this.memprosesPelanggaran || this.berhenti) {
                    return;
                }

                this.memprosesPelanggaran = true;
                this.keluarSejak = 0;

                if (this.timerToleransi) {
                    clearInterval(this.timerToleransi);
                    this.timerToleransi = null;
                }

                let hasil;
                try {
                    hasil = await window.kuisFetch(config.pelanggaranUrl, {
                        method: 'POST',
                        body: {},
                        progress: false
                    });
                } catch (e) {
                    this.memprosesPelanggaran = false;
                    this.pesan = 'Koneksi terputus. Tekan Kembali ke Ujian untuk melanjutkan.';
                    return;
                }

                this.memprosesPelanggaran = false;

                if (hasil.data?.final) {
                    this.berhenti = true;
                    window.location.href = hasil.data.redirect_url || config.dashboardUrl;
                    return;
                }

                this.sisaToleransi = 0;
                this.pesan = 'Pelanggaran tercatat. Tekan Kembali ke Ujian untuk melanjutkan.';
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
                    body: { tipe },
                    progress: false
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
            jawabanTeks: {{ Illuminate\Support\Js::from(collect($questions)->mapWithKeys(fn ($q) => [$q['id'] => $q['jawaban_teks'] ?? ''])->all()) }},
            jawabanParts: {{ Illuminate\Support\Js::from(collect($questions)->mapWithKeys(fn ($q) => [$q['id'] => $q['jawaban_parts'] ?? []])->all()) }},
            serverNow: {{ $server_now }},
            batasAkhir: {{ $attempt->batas_akhir_at }},
            sessionId: {{ $session->id }},
            userId: {{ auth()->id() }},
            attemptId: {{ $attempt->id }},
            mulaiIndex: {{ $mulai_index }},
            durasiPeringatan: {{ $session->durasiPeringatan() }},
            answerUrl: '{{ route('peserta.attempt.answer', $attempt) }}',
            eventUrl: '{{ route('peserta.attempt.events', $attempt) }}',
            markUrl: '{{ route('peserta.attempt.tandai', $attempt) }}',
            pelanggaranUrl: '{{ route('peserta.attempt.pelanggaran', $attempt) }}',
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
                        <span class="font-bold text-ink-muted"><span x-text="terjawab"></span> dari <span x-text="questions.length"></span> soal lengkap</span>
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

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="btn btn-ghost px-3"
                        x-bind:class="current.ragu_ragu ? 'bg-coral-soft text-coral-deep' : ''"
                        x-bind:aria-pressed="current.ragu_ragu ? 'true' : 'false'"
                        x-on:click="toggleRagu(current)"
                    >
                        <x-icon name="flag" :size="18" />
                        <span x-text="current.ragu_ragu ? 'Ragu-ragu ditandai' : 'Tandai ragu-ragu'"></span>
                    </button>
                    <button
                        type="button"
                        class="btn btn-ghost px-3"
                        x-bind:class="current.highlight_soal ? 'bg-sun-soft text-sun-deep' : ''"
                        x-bind:aria-pressed="current.highlight_soal ? 'true' : 'false'"
                        x-on:click="toggleHighlightSoal(current)"
                    >
                        <x-icon name="highlight" :size="18" />
                        <span x-text="current.highlight_soal ? 'Sorotan aktif' : 'Sorot soal'"></span>
                    </button>
                </div>

                <h2
                    id="teks-soal"
                    class="question-text mt-4 whitespace-pre-line"
                    x-bind:class="current.highlight_soal ? 'bg-sun-soft' : ''"
                    x-text="current.teks"
                ></h2>

                <template x-if="current.tipe === 'essay' && current.petunjuk?.length">
                    <div class="mt-4 rounded-xl border border-outline bg-page p-4">
                        <p class="text-sm font-bold text-ink">Yang harus dijawab</p>
                        <ul class="mt-2 space-y-1 text-base text-ink-muted">
                            <template x-for="(poin, poinIndex) in current.petunjuk" :key="poinIndex">
                                <li class="flex gap-2">
                                    <span class="mt-2 h-1.5 w-1.5 flex-none rounded-full bg-brand" aria-hidden="true"></span>
                                    <span x-text="poin"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                <template x-if="current.tipe === 'essay' && !current.sub_soal?.length">
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

                <template x-if="current.tipe === 'essay' && current.sub_soal?.length">
                    <div class="mt-5 space-y-6">
                        <div class="flex flex-wrap items-center gap-2 border-b border-outline pb-3">
                            <span class="text-sm font-bold text-ink-muted">Bagian:</span>
                            <template x-for="bagian in current.sub_soal" :key="bagian.label">
                                <button
                                    type="button"
                                    class="pill inline-flex min-h-11 items-center gap-1.5 px-3"
                                    x-bind:class="bagianTerjawab(current, bagian) ? 'pill-leaf' : 'pill-neutral'"
                                    x-on:click="keBagian(current, bagian)"
                                    x-bind:aria-label="'Lompat ke bagian ' + kodeBagian(current, bagian) + (bagianTerjawab(current, bagian) ? ', sudah dijawab' : ', belum dijawab')"
                                >
                                    <span x-text="kodeBagian(current, bagian)"></span>
                                    <svg
                                        x-show="bagianTerjawab(current, bagian)"
                                        class="h-3.5 w-3.5"
                                        viewBox="0 0 16 16"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path d="m3 8.5 3.2 3.2L13 5" />
                                    </svg>
                                </button>
                            </template>
                        </div>

                        <template x-for="(bagian, bagianIndex) in current.sub_soal" :key="bagian.label">
                            <div
                                class="scroll-mt-32"
                                x-bind:class="bagianIndex > 0 ? 'border-t border-outline pt-5' : ''"
                                x-bind:id="'bagian-' + current.id + '-' + bagianIndex"
                            >
                                <h3 class="text-xl font-bold text-ink">
                                    Bagian <span x-text="kodeBagian(current, bagian)"></span>
                                </h3>
                                <p class="mt-1 whitespace-pre-line text-base text-ink-muted" x-text="bagian.teks"></p>
                                <template x-if="bagian.petunjuk?.length">
                                    <ul class="mt-2 space-y-1 text-sm text-ink-muted">
                                        <template x-for="(poin, poinIndex) in bagian.petunjuk" :key="poinIndex">
                                            <li class="flex gap-2">
                                                <span class="mt-1.5 h-1.5 w-1.5 flex-none rounded-full bg-brand" aria-hidden="true"></span>
                                                <span x-text="poin"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                                <label class="label mt-3" x-bind:for="'jawaban-' + current.id + '-' + bagianIndex">Jawaban <span x-text="kodeBagian(current, bagian)"></span></label>
                                <textarea
                                    class="input min-h-[9rem] resize-y"
                                    maxlength="5000"
                                    x-bind:id="'jawaban-' + current.id + '-' + bagianIndex"
                                    x-bind:disabled="berhenti"
                                    x-bind:value="(jawabanParts[current.id] ?? {})[bagian.label] ?? ''"
                                    x-on:input="ubahBagian(current, bagian, $event.target.value)"
                                    x-on:blur="simpanBagian(current, bagian)"
                                    x-bind:placeholder="'Tulis jawaban ' + kodeBagian(current, bagian) + ' di sini'"
                                ></textarea>
                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm text-ink-muted">
                                    <span x-text="(((jawabanParts[current.id] ?? {})[bagian.label] ?? '').length) + ' / 5000 karakter'"></span>
                                    <span x-text="statusBagian(current, bagian)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="current.tipe !== 'essay'">
                    <div class="mt-5 space-y-3" role="group" aria-labelledby="teks-soal">
                        <p class="text-base text-ink-muted">Pilih satu jawaban. Jawaban tersimpan saat diklik.</p>
                        <template x-for="(opsi, opsiIndex) in current.opsi" :key="opsi.id">
                            <div class="relative">
                                <button
                                    type="button"
                                    class="tile w-full pr-12"
                                    x-bind:class="opsiDisorot(current, opsi.id) ? 'ring-2 ring-sun bg-sun-soft' : ''"
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
                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg border border-outline bg-page p-1.5 text-ink-muted hover:bg-sun-soft hover:text-sun-deep"
                                    x-bind:class="opsiDisorot(current, opsi.id) ? 'border-sun bg-sun-soft text-sun-deep' : ''"
                                    x-bind:aria-pressed="opsiDisorot(current, opsi.id) ? 'true' : 'false'"
                                    x-on:click.stop="toggleHighlightOpsi(current, opsi.id)"
                                    aria-label="Sorot opsi ini"
                                    title="Sorot opsi ini"
                                >
                                    <x-icon name="highlight" :size="16" />
                                </button>
                            </div>
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
                        x-bind:class="nomor === index ? (soal.ragu_ragu ? 'border-coral-deep bg-coral-soft text-coral-deep ring-2 ring-brand' : 'border-brand bg-brand text-white') : (soal.ragu_ragu ? 'border-coral-deep bg-coral-soft text-coral-deep' : (sudahDijawab(soal) ? 'border-leaf bg-leaf-soft text-leaf-deep' : (terjawabSebagian(soal) ? 'border-sun-deep bg-sun-soft text-sun-deep' : 'border-outline bg-page text-ink-muted')))"
                        x-bind:aria-current="nomor === index ? 'true' : 'false'"
                        x-bind:aria-label="'Soal ' + (nomor + 1) + (soal.ragu_ragu ? ', ditandai ragu-ragu' : (sudahDijawab(soal) ? ', sudah dijawab lengkap' : (terjawabSebagian(soal) ? ', sebagian dijawab' : ', belum dijawab')))"
                        x-on:click="pindah(nomor)"
                        x-text="nomor + 1"
                    ></button>
                </template>
            </div>
            <p class="mt-3 text-base text-ink-muted">Kotak hijau berarti semua bagian sudah dijawab. Kotak kuning berarti baru sebagian bagian essay dijawab. Kotak merah berarti soal ditandai ragu-ragu.</p>
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
                    Jika Anda keluar dari layar penuh atau berpindah tab/aplikasi selama kuis, sistem mencatat pelanggaran dan memberi waktu untuk kembali.
                    Saat batas pelanggaran sesi tercapai dan waktu habis, jawaban dikumpulkan otomatis.
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
            x-show="peringatanKeluar"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-ink/90 p-4 backdrop-blur-md"
            role="dialog"
            aria-modal="true"
            aria-labelledby="judul-peringatan-layar"
        >
            <div class="card flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden border-4 border-coral-deep bg-page text-center shadow-2xl">
                <div class="space-y-5 overflow-y-auto p-6 sm:p-8">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-coral-soft text-coral-deep">
                        <x-icon name="alert" :size="40" />
                    </div>
                    <div>
                        <span class="pill pill-coral text-xs font-bold uppercase tracking-wider">Pelanggaran Terdeteksi</span>
                        <h2 id="judul-peringatan-layar" class="mt-2 text-2xl font-bold text-ink sm:text-3xl">Anda Keluar Dari Layar Penuh!</h2>
                    </div>
                    <p class="text-base leading-relaxed text-ink-muted">
                        Sistem mendeteksi Anda keluar dari mode layar penuh atau berpindah jendela/tab. Kembali ke ujian sekarang untuk melanjutkan.
                    </p>
                    <div class="rounded-2xl border border-coral-deep/30 bg-coral-soft/60 p-4">
                        <p class="text-sm font-bold text-coral-deep" x-text="sisaToleransi > 0 ? 'Waktu untuk kembali' : 'Waktu kembali habis'">Waktu untuk kembali</p>
                        <p class="timer mt-1 text-5xl font-bold text-coral-deep" x-text="sisaToleransi + ' detik'" x-init="sisaToleransi = durasiPeringatan">{{ $session->durasiPeringatan() }} detik</p>
                        <p class="mt-1 text-xs text-ink-muted">Pelanggaran sudah dicatat saat Anda keluar layar. Jika batas pelanggaran sesi sudah tercapai dan waktu habis, ujian dikumpulkan otomatis.</p>
                    </div>
                    <p x-show="memprosesPelanggaran" x-cloak class="text-sm font-bold text-coral-deep">Mencatat pelanggaran...</p>
                </div>

                <div class="border-t border-outline bg-page p-4 sm:p-5">
                    <button
                        type="button"
                        class="btn btn-primary btn-anak w-full py-3.5 text-lg font-bold shadow-lg"
                        x-on:click="masukLayarPenuh()"
                    >
                        Kembali ke Ujian
                    </button>
                    <p class="mt-2 text-sm text-ink-muted">Tombol ini tetap tersedia. Aktifkan layar penuh untuk menutup peringatan.</p>
                </div>
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
                        <span class="pill pill-sun">Belum lengkap</span>
                        <h2 id="judul-konfirmasi-selesai" class="mt-2 text-2xl">
                            Masih ada <span x-text="belum"></span> soal belum lengkap
                        </h2>
                    </div>
                </div>

                <p id="isi-konfirmasi-selesai" class="mt-4 text-base leading-relaxed text-ink-muted">
                    Soal pilihan ganda yang tidak dijawab dihitung salah, essay kosong bernilai 0. Kamu masih bisa menutup dialog ini dan melanjutkan mengerjakan selama waktu tersisa.
                </p>

                <template x-if="yangKosong.length > 0">
                    <div class="mt-4 rounded-xl border border-sun/40 bg-sun-soft/60 p-4">
                        <p class="text-sm font-bold text-sun-deep">Yang masih kosong</p>
                        <ul class="mt-2 flex flex-wrap gap-2">
                            <template x-for="kode in yangKosong" :key="kode">
                                <li class="pill pill-sun text-xs" x-text="kode"></li>
                            </template>
                        </ul>
                    </div>
                </template>

                <dl class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-leaf-soft px-4 py-3">
                        <dt class="text-sm font-bold text-leaf-deep">Sudah lengkap</dt>
                        <dd class="display-number mt-1 text-3xl text-leaf-deep" x-text="terjawab"></dd>
                    </div>
                    <div class="rounded-2xl bg-sun-soft px-4 py-3">
                        <dt class="text-sm font-bold text-sun-deep">Belum lengkap</dt>
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
