<x-layouts.peserta :judul="$session->nama" :keterangan="'Mode Acak · '.$session->quiz->judul" :fokus="true">
    <style>
        @media print {
            body {
                display: none !important;
            }
        }
    </style>
    <script>
        window.kuisAcak = (config) => ({
            questions: config.questions,
            totalSoal: config.totalSoal ?? config.questions.length,
            isolasiSoal: Boolean(config.isolasiSoal),
            timerPerSoal: Boolean(config.timerPerSoal),
            pisahKonteksSoal: Boolean(config.pisahKonteksSoal),
            watermarkLayar: Boolean(config.watermarkLayar),
            screenProctoring: config.screenProctoring || 'off',
            webcamProctoring: Boolean(config.webcamProctoring),
            tabKonteks: 'stimulus',
            sisaSoal: 0,
            memuatSoalBerikutnya: false,
            konfirmasiLanjutKosong: false,
            typingStats: {},
            screenStream: null,
            webcamStream: null,
            screenSiap: (config.screenProctoring || 'off') === 'off',
            webcamSiap: !config.webcamProctoring,
            exitCaptureTimers: [],
            peekTimer: null,
            peekUntil: 0,
            webcamInterval: null,
            lastAutoSnapshotAt: 0,
            index: config.mulaiIndex ?? 0,
            jawaban: config.jawaban,
            jawabanMulti: config.jawabanMulti ?? {},
            jawabanTeks: config.jawabanTeks,
            jawabanParts: config.jawabanParts ?? {},
            jawabanData: config.jawabanData ?? {},
            teksTersimpan: { ...config.jawabanTeks },
            partsTersimpan: JSON.parse(JSON.stringify(config.jawabanParts ?? {})),
            dataTersimpan: JSON.parse(JSON.stringify(config.jawabanData ?? {})),
            statusTeks: {},
            statusParts: {},
            janjiTeks: {},
            janjiParts: {},
            timerTeks: {},
            timerParts: {},
            menyimpan: false,
            mengumpulkan: false,
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
            pengumpulanPaksa: false,
            pesanPengumpulanPaksa: '',
            clipboardToast: '',
            clipboardToastBlocked: false,
            clipboardToastTimer: null,
            pesanMasuk: @js($pesanAktif),
            clipboardPolicy: config.clipboardPolicy,
            audioCtx: null,
            notifWaktu300: false,
            notifWaktu60: false,
            polling: null,
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
                if (this.current?.tipe !== 'essay' && this.current?.tipe !== 'isian_singkat') {
                    return '';
                }

                if (this.current.sub_soal?.length) {
                    const status = this.bagianJawaban(this.current).map((bagian) => this.statusBagian(this.current, bagian));
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
                        for (const bagian of this.bagianJawaban(soal)) {
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
                        return this.bagianJawaban(soal).every((bagian) => this.bagianTerjawab(soal, bagian));
                    }

                    if (soal.answer_format === 'table') {
                        return this.tabelLengkap(soal);
                    }

                    return (this.jawabanTeks[soal.id] ?? '').trim() !== '';
                }

                if (soal.tipe === 'isian_singkat') {
                    return (this.jawabanTeks[soal.id] ?? '').trim() !== '';
                }

                if (soal.tipe === 'pilihan_majemuk') {
                    return Array.isArray(this.jawabanMulti[soal.id]) && this.jawabanMulti[soal.id].length > 0;
                }

                return this.jawaban[soal.id] !== null && this.jawaban[soal.id] !== undefined;
            },
            terjawabSebagian(soal) {
                if (soal.tipe !== 'essay') {
                    return false;
                }

                if (!soal.sub_soal?.length) {
                    return soal.answer_format === 'table' && this.tabelAdaIsi(soal) && !this.tabelLengkap(soal);
                }

                return this.bagianJawaban(soal).some((bagian) => this.bagianAdaIsi(soal, bagian)) && !this.sudahDijawab(soal);
            },
            bagianJawaban(soal) {
                return soal.sub_soal?.filter((bagian) => bagian.is_leaf) ?? [];
            },
            kunciBagian(soal, bagian) {
                return soal.id + ':' + bagian.key;
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

                return (this.partsTersimpan[soal.id]?.[bagian.key] ?? '').trim() !== '' ? 'Tersimpan otomatis' : '';
            },
            bagianTerjawab(soal, bagian) {
                if (bagian.answer_format === 'table') {
                    return this.tabelLengkap(soal, bagian);
                }

                return (this.jawabanParts[soal.id]?.[bagian.key] ?? '').trim() !== '';
            },
            dataKey(bagian = null) {
                return bagian?.key ?? '__main';
            },
            tabelCells(soal, bagian = null) {
                const key = this.dataKey(bagian);
                this.jawabanData[soal.id] ??= {};
                const configJawaban = bagian?.answer_config ?? soal.answer_config ?? {};
                const rows = configJawaban.rows ?? [];
                const columns = configJawaban.columns ?? [];
                const existing = this.jawabanData[soal.id][key]?.value?.cells ?? [];
                const cells = rows.map((_, rowIndex) => columns.map((__, columnIndex) => existing[rowIndex]?.[columnIndex] ?? ''));
                this.jawabanData[soal.id][key] = { format: 'table', value: { cells } };

                return this.jawabanData[soal.id][key].value.cells;
            },
            tabelLengkap(soal, bagian = null) {
                const cells = this.tabelCells(soal, bagian);
                return cells.length > 0 && cells.every((row) => row.length > 0 && row.every((cell) => String(cell).trim() !== ''));
            },
            tabelAdaIsi(soal, bagian = null) {
                return this.tabelCells(soal, bagian).some((row) => row.some((cell) => String(cell).trim() !== ''));
            },
            bagianAdaIsi(soal, bagian) {
                if (bagian.answer_format === 'table') return this.tabelAdaIsi(soal, bagian);
                return (this.jawabanParts[soal.id]?.[bagian.key] ?? '').trim() !== '';
            },
            ubahSelTabel(soal, bagian, rowIndex, columnIndex, nilai) {
                const cells = this.tabelCells(soal, bagian);
                cells[rowIndex][columnIndex] = nilai;
                if (bagian) {
                    const key = this.kunciBagian(soal, bagian);
                    this.statusParts[key] = 'menunggu';
                    clearTimeout(this.timerParts[key]);
                    this.timerParts[key] = setTimeout(() => this.simpanBagian(soal, bagian), 800);
                    return;
                }
                this.statusTeks[soal.id] = 'menunggu';
                clearTimeout(this.timerTeks[soal.id]);
                this.timerTeks[soal.id] = setTimeout(() => this.simpanTeks(soal), 800);
            },
            formatMarkdown(id, prefix, suffix = prefix) {
                const input = document.getElementById(id);
                if (!input || input.disabled) return;
                const start = input.selectionStart;
                const end = input.selectionEnd;
                const selected = input.value.slice(start, end);
                input.setRangeText(prefix + selected + suffix, start, end, 'end');
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.focus();
            },
            sisipkanTab(event) {
                const input = event.target;
                input.setRangeText('    ', input.selectionStart, input.selectionEnd, 'end');
                input.dispatchEvent(new Event('input', { bubbles: true }));
            },
            kodeBagian(soal, bagian) {
                return (this.questions.indexOf(soal) + 1) + (bagian.code ?? bagian.label ?? '');
            },
            keBagian(soal, bagian) {
                const index = soal.sub_soal.findIndex((item) => item.key === bagian.key);
                this.$nextTick(() => document.getElementById('bagian-' + soal.id + '-' + index)?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            },
            pindah(nomor) {
                if (this.isolasiSoal) return;
                this.index = Math.max(0, Math.min(this.questions.length - 1, nomor));
                this.tabKonteks = this.questions[this.index]?.has_context_blocks ? 'stimulus' : 'jawab';
                this.$nextTick(() => this.$refs.kartuSoal?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            },
            catatKetikan(soalId, event) {
                if (!soalId) return;
                const now = Date.now();
                if (!this.typingStats[soalId]) {
                    this.typingStats[soalId] = {
                        opened_at: now,
                        first_key_at: null,
                        last_key_at: null,
                        total_keys: 0,
                        backspace_keys: 0,
                        paste_events: 0,
                        idle_gaps: 0
                    };
                }
                const stat = this.typingStats[soalId];
                if (stat.first_key_at === null) {
                    stat.first_key_at = now;
                } else if (stat.last_key_at && (now - stat.last_key_at) > 8000) {
                    stat.idle_gaps++;
                }
                stat.last_key_at = now;
                stat.total_keys++;
                if (event?.key === 'Backspace' || event?.key === 'Delete') {
                    stat.backspace_keys++;
                }
            },
            catatPaste(soalId) {
                if (!soalId) return;
                const now = Date.now();
                this.typingStats[soalId] ??= {
                    opened_at: now,
                    first_key_at: now,
                    last_key_at: now,
                    total_keys: 0,
                    backspace_keys: 0,
                    paste_events: 0,
                    idle_gaps: 0
                };
                this.typingStats[soalId].paste_events++;
            },
            ringkasTypingMetrics(soalId) {
                const stat = this.typingStats[soalId];
                if (!stat) return undefined;
                const openedAt = stat.opened_at || Date.now();
                const firstKey = stat.first_key_at || openedAt;
                const lastKey = stat.last_key_at || firstKey;
                return {
                    first_input_delay_ms: Math.max(0, firstKey - openedAt),
                    typing_duration_ms: Math.max(0, lastKey - firstKey),
                    total_keys: stat.total_keys,
                    backspace_keys: stat.backspace_keys,
                    paste_events: stat.paste_events,
                    idle_gaps: stat.idle_gaps
                };
            },
            async mintaLanjutSoal(lewatiKonfirmasi = false) {
                if (this.memuatSoalBerikutnya || this.berhenti || !this.current) return;
                if (!lewatiKonfirmasi && !this.sudahDijawab(this.current)) {
                    this.konfirmasiLanjutKosong = true;
                    return;
                }
                this.konfirmasiLanjutKosong = false;
                this.memuatSoalBerikutnya = true;
                try {
                    if (this.current.tipe === 'essay' || this.current.tipe === 'isian_singkat') {
                        await this.simpanSemuaTeks();
                    }
                    const res = await window.kuisFetch(config.nextQuestionUrl, {
                        method: 'POST',
                        body: {},
                        progress: false
                    });
                    if (!res.ok) {
                        if (res.data?.redirect_url) window.location.href = res.data.redirect_url;
                        return;
                    }
                    if (res.data?.selesai && res.data?.redirect_url) {
                        this.berhenti = true;
                        this.hentikanStreamMedia();
                        window.location.href = res.data.redirect_url;
                        return;
                    }
                    if (res.data?.question) {
                        const q = res.data.question;
                        this.questions = [q];
                        this.index = 0;
                        this.offset = Date.now() - Number(res.data.server_now || Date.now());
                        this.tabKonteks = (this.pisahKonteksSoal && q.has_context_blocks) ? 'stimulus' : 'jawab';
                        this.typingStats[q.id] = {
                            opened_at: Date.now(),
                            first_key_at: null,
                            last_key_at: null,
                            total_keys: 0,
                            backspace_keys: 0,
                            paste_events: 0,
                            idle_gaps: 0
                        };
                    }
                } finally {
                    this.memuatSoalBerikutnya = false;
                }
            },
            async siapkanMediaPengawas() {
                const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
                if (this.screenProctoring !== 'off') {
                    if (isMobile && this.screenProctoring === 'optional_mobile') {
                        this.screenSiap = true;
                    } else if (!navigator.mediaDevices?.getDisplayMedia) {
                        if (this.screenProctoring === 'desktop_only') {
                            this.pesan = 'Sesi ini mewajibkan browser laptop/PC yang mendukung berbagi seluruh layar.';
                            return false;
                        }
                        this.screenSiap = true;
                    } else if (!this.screenStream) {
                        try {
                            const stream = await navigator.mediaDevices.getDisplayMedia({
                                video: { frameRate: { ideal: 2, max: 5 } },
                                audio: false
                            });
                            const track = stream.getVideoTracks()[0];
                            const surface = track?.getSettings?.()?.displaySurface;
                            if (this.screenProctoring === 'desktop_only' && surface && surface !== 'monitor') {
                                stream.getTracks().forEach((t) => t.stop());
                                this.pesan = 'Pilih opsi Seluruh Layar (Entire Screen), bukan satu jendela atau satu tab.';
                                return false;
                            }
                            track?.addEventListener('ended', () => {
                                this.screenStream = null;
                                this.screenSiap = false;
                                if (!this.berhenti) {
                                    this.kirimKejadian('blur_window');
                                    this.mulaiToleransi();
                                }
                            });
                            this.screenStream = stream;
                            this.screenSiap = true;
                        } catch (e) {
                            this.pesan = 'Izin berbagi layar diperlukan sebelum memulai sesi ini.';
                            return false;
                        }
                    }
                }
                if (this.webcamProctoring && !this.webcamStream) {
                    if (navigator.mediaDevices?.getUserMedia) {
                        try {
                            this.webcamStream = await navigator.mediaDevices.getUserMedia({
                                video: { width: { ideal: 320 }, height: { ideal: 240 }, facingMode: 'user' },
                                audio: false
                            });
                            this.webcamSiap = true;
                            this.mulaiJadwalWebcam();
                        } catch (e) {
                            this.pesan = 'Izin kamera diperlukan untuk sesi ujian ini.';
                            return false;
                        }
                    } else {
                        this.webcamSiap = true;
                    }
                }
                return true;
            },
            hentikanStreamMedia() {
                this.bersihkanJadwalSnapshotKeluar();
                if (this.peekTimer) clearInterval(this.peekTimer);
                if (this.webcamInterval) clearInterval(this.webcamInterval);
                this.screenStream?.getTracks().forEach((t) => t.stop());
                this.webcamStream?.getTracks().forEach((t) => t.stop());
            },
            async kirimSnapshotStream(stream, source, trigger, paksa = false) {
                if (!stream || this.berhenti || !config.snapshotUrl) return;
                const now = Date.now();
                if (!paksa && (now - this.lastAutoSnapshotAt) < 7500) return;
                const track = stream.getVideoTracks()[0];
                if (!track || track.readyState !== 'live') return;
                this.lastAutoSnapshotAt = now;

                try {
                    const video = document.createElement('video');
                    video.muted = true;
                    video.playsInline = true;
                    video.srcObject = stream;
                    await video.play();

                    const targetW = source === 'screen' ? 960 : 320;
                    const ratio = (video.videoHeight && video.videoWidth) ? (video.videoHeight / video.videoWidth) : 0.5625;
                    const targetH = Math.max(120, Math.round(targetW * ratio));
                    const canvas = document.createElement('canvas');
                    canvas.width = targetW;
                    canvas.height = targetH;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, targetW, targetH);
                    video.pause();
                    video.srcObject = null;

                    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', 0.52));
                    if (!blob) return;

                    const formData = new FormData();
                    formData.append('image', blob, source + '.webp');
                    formData.append('capture_source', source);
                    formData.append('trigger_type', trigger);
                    if (this.current?.urutan) {
                        formData.append('question_urutan', String(this.current.urutan));
                    }

                    await fetch(config.snapshotUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                        },
                        body: formData
                    });
                } catch (e) {}
            },
            jadwalkanSnapshotKeluar(trigger) {
                this.bersihkanJadwalSnapshotKeluar();
                if (this.screenStream) {
                    [1500, 11500, 21500, 31500].forEach((delayMs) => {
                        this.exitCaptureTimers.push(setTimeout(() => {
                            if (this.peringatanKeluar || document.hidden) {
                                this.kirimSnapshotStream(this.screenStream, 'screen', trigger, true);
                            }
                        }, delayMs));
                    });
                }
                if (this.webcamStream) {
                    this.exitCaptureTimers.push(setTimeout(() => {
                        this.kirimSnapshotStream(this.webcamStream, 'webcam', trigger, false);
                    }, 2000));
                }
            },
            bersihkanJadwalSnapshotKeluar() {
                this.exitCaptureTimers.forEach((id) => clearTimeout(id));
                this.exitCaptureTimers = [];
            },
            aktifkanPeekLayar(untilMs, serverNowMs) {
                const sisaMs = Number(untilMs) - Number(serverNowMs || Date.now());
                if (sisaMs <= 0) return;
                this.peekUntil = Date.now() + Math.min(65000, sisaMs);
                if (this.peekTimer) clearInterval(this.peekTimer);
                if (this.screenStream) {
                    this.kirimSnapshotStream(this.screenStream, 'screen', 'admin_peek', true);
                }
                this.peekTimer = setInterval(() => {
                    if (Date.now() > this.peekUntil || this.berhenti) {
                        clearInterval(this.peekTimer);
                        this.peekTimer = null;
                        return;
                    }
                    if (this.screenStream) {
                        this.kirimSnapshotStream(this.screenStream, 'screen', 'admin_peek', true);
                    } else if (this.webcamStream) {
                        this.kirimSnapshotStream(this.webcamStream, 'webcam', 'admin_peek', true);
                    }
                }, 4000);
            },
            mulaiJadwalWebcam() {
                if (this.webcamInterval || !this.webcamStream) return;
                setTimeout(() => this.kirimSnapshotStream(this.webcamStream, 'webcam', 'initial', true), 4000);
                this.webcamInterval = setInterval(() => {
                    if (!this.berhenti && this.webcamStream) {
                        this.kirimSnapshotStream(this.webcamStream, 'webcam', 'periodic', false);
                    }
                }, 90000);
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
                this.polling = window.mulaiPolling(() => this.cekStatus(), { interval: 10000 });
                this.polling.start();

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
                    })
                    .listen('.PesanPengajar', (data) => {
                        this.gabungPesan([data]);
                        this.bunyikanAlert(640, 0.15);
                    })
                    .listen('.PesertaDimintaLayar', (data) => {
                        this.aktifkanPeekLayar(data.screen_peek_until, data.server_now);
                    });
            },
            gabungPesan(daftar) {
                if (!Array.isArray(daftar)) return;
                for (const pesan of daftar) {
                    if (!this.pesanMasuk.some((item) => item.id === pesan.id)) this.pesanMasuk.push(pesan);
                }
            },
            async tutupPesan(id) {
                this.pesanMasuk = this.pesanMasuk.filter((item) => item.id !== id);
                try {
                    await window.kuisFetch(config.tutupUrlPesan.replace('__ID__', id), { method: 'POST', progress: false });
                } catch (e) {}
            },
            muatAntreanLokal() {
                try {
                    const raw = localStorage.getItem(this.storageKey);
                    if (raw) {
                        this.antreanOffline = JSON.parse(raw);
                        // Pulihkan jawaban lokal jika ada di antrean
                        for (const item of this.antreanOffline) {
                            if (item.tipe === 'essay' && item.part_key) {
                                if (item.jawaban_table) {
                                    this.jawabanData[item.question_id] ??= {};
                                    this.jawabanData[item.question_id][item.part_key] = { format: 'table', value: item.jawaban_table };
                                } else {
                                    if (!this.jawabanParts[item.question_id]) {
                                        this.jawabanParts[item.question_id] = {};
                                    }
                                    this.jawabanParts[item.question_id][item.part_key] = item.jawaban_teks;
                                }
                                this.statusParts[item.question_id + ':' + item.part_key] = 'offline';
                            } else if (item.tipe === 'essay') {
                                if (item.jawaban_table) {
                                    this.jawabanData[item.question_id] ??= {};
                                    this.jawabanData[item.question_id].__main = { format: 'table', value: item.jawaban_table };
                                } else {
                                    this.jawabanTeks[item.question_id] = item.jawaban_teks;
                                }
                                this.statusTeks[item.question_id] = 'offline';
                            } else if (item.tipe === 'isian_singkat') {
                                this.jawabanTeks[item.question_id] = item.jawaban_teks;
                                this.statusTeks[item.question_id] = 'offline';
                            } else if (item.tipe === 'pilihan_majemuk' && Array.isArray(item.option_ids)) {
                                this.jawabanMulti[item.question_id] = item.option_ids;
                            } else if (item.option_id) {
                                this.jawaban[item.question_id] = item.option_id;
                            }
                        }
                    }
                } catch (e) {
                    this.antreanOffline = [];
                }

                if (navigator.onLine && this.antreanOffline.length > 0) {
                    setTimeout(() => this.prosesAntrean(), Math.random() * 2000);
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
                    setTimeout(() => this.prosesAntrean(), Math.random() * 2000);
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

                    const queueIndex = this.antreanOffline.findIndex((queued) => !queued.validation_failed);
                    if (queueIndex === -1) break;

                    const item = this.antreanOffline[queueIndex];
                    const body = { question_id: item.question_id };
                    if (item.tipe === 'essay' || item.tipe === 'isian_singkat') {
                        if (item.jawaban_table) body.jawaban_table = item.jawaban_table;
                        else body.jawaban_teks = item.jawaban_teks;
                        if (item.part_key) {
                            body.part_key = item.part_key;
                        }
                    } else if (item.tipe === 'pilihan_majemuk') {
                        body.option_ids = item.option_ids ?? [];
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
                            this.antreanOffline.splice(queueIndex, 1);
                            this.simpanAntreanKeStorage();
                            if (item.tipe === 'essay' && item.part_key) {
                                this.partsTersimpan[item.question_id] = res.data?.jawaban_parts ?? {};
                                this.jawabanData[item.question_id] = res.data?.jawaban_data ?? this.jawabanData[item.question_id] ?? {};
                                this.dataTersimpan[item.question_id] = JSON.parse(JSON.stringify(this.jawabanData[item.question_id] ?? {}));
                                this.statusParts[item.question_id + ':' + item.part_key] = 'tersimpan';
                                if (res.data?.jawaban_teks !== undefined) {
                                    this.jawabanTeks[item.question_id] = res.data.jawaban_teks;
                                }
                            } else if (item.tipe === 'essay' || item.tipe === 'isian_singkat') {
                                this.teksTersimpan[item.question_id] = res.data?.jawaban_teks ?? item.jawaban_teks ?? '';
                                this.jawabanData[item.question_id] = res.data?.jawaban_data ?? this.jawabanData[item.question_id] ?? {};
                                this.dataTersimpan[item.question_id] = JSON.parse(JSON.stringify(this.jawabanData[item.question_id] ?? {}));
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
                            const key = item.question_id + ':' + (item.part_key ?? '');
                            if (item.part_key) this.statusParts[key] = 'gagal';
                            else if (item.tipe === 'essay') this.statusTeks[item.question_id] = 'gagal';
                            this.antreanOffline[queueIndex] = { ...item, validation_failed: true };
                            this.simpanAntreanKeStorage();
                            this.pesan = res.data?.message ?? 'Jawaban lokal belum dapat diterima server. Periksa isinya lalu coba lagi.';
                            continue;
                        }
                    } catch (err) {
                        // Jaringan putus saat request
                        this.isOnline = false;
                        break;
                    }

                    await new Promise((r) => setTimeout(r, 750 + Math.random() * 500));
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
                if (this.current) {
                    this.tabKonteks = (this.pisahKonteksSoal && this.current.has_context_blocks) ? 'stimulus' : 'jawab';
                    this.typingStats[this.current.id] ??= {
                        opened_at: Date.now(),
                        first_key_at: null,
                        last_key_at: null,
                        total_keys: 0,
                        backspace_keys: 0,
                        paste_events: 0,
                        idle_gaps: 0
                    };
                }
                const hitung = () => {
                    const nowServer = Date.now() - this.offset;
                    this.sisa = Math.max(0, Math.round((this.batasAkhir - nowServer) / 1000));

                    if (this.timerPerSoal && this.current?.berakhir_at) {
                        this.sisaSoal = Math.max(0, Math.round((Number(this.current.berakhir_at) - nowServer) / 1000));
                        if (this.sisaSoal <= 0 && !this.memuatSoalBerikutnya && !this.berhenti) {
                            this.mintaLanjutSoal(true);
                        }
                    }

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
            opsiMultiDipilih(soal, opsiId) {
                return (this.jawabanMulti[soal.id] ?? []).includes(opsiId);
            },
            toggleMulti(soal, opsi) {
                if (this.berhenti || this.menyimpan) {
                    return;
                }
                const saatIni = new Set(this.jawabanMulti[soal.id] ?? []);
                if (saatIni.has(opsi.id)) {
                    saatIni.delete(opsi.id);
                } else {
                    saatIni.add(opsi.id);
                }
                const daftarBaru = Array.from(saatIni);
                this.jawabanMulti[soal.id] = daftarBaru;
                this.simpanMulti(soal, daftarBaru);
            },
            async simpanMulti(soal, optionIds) {
                this.menyimpan = true;
                this.pesan = '';

                if (!navigator.onLine) {
                    this.menyimpan = false;
                    this.tambahAntrean({
                        question_id: soal.id,
                        option_ids: optionIds,
                        tipe: 'pilihan_majemuk',
                        waktu: Date.now()
                    });
                    return;
                }

                try {
                    const hasil = await window.kuisFetch(config.answerUrl, {
                        method: 'POST',
                        body: { question_id: soal.id, option_ids: optionIds },
                        progress: false
                    });
                    this.menyimpan = false;
                    if (hasil.ok) return;
                    if (hasil.status === 403) {
                        this.pesan = hasil.data?.message ?? 'Jawaban tidak diterima.';
                        if (hasil.data?.selesai) {
                            this.berhenti = true;
                            window.location.href = config.resultUrl;
                        }
                        return;
                    }
                } catch (e) {
                    this.menyimpan = false;
                }

                this.tambahAntrean({
                    question_id: soal.id,
                    option_ids: optionIds,
                    tipe: 'pilihan_majemuk',
                    waktu: Date.now()
                });
            },
            tanganiPintasan(event) {
                if (this.berhenti || this.peringatanKeluar || this.konfirmasiSelesai || this.konfirmasiLanjutKosong) return;
                if (event.ctrlKey || event.metaKey || event.altKey) return;
                const target = event.target instanceof Element ? event.target : null;
                if (target?.closest('input, textarea, select, [contenteditable="true"], dialog[open]')) return;

                if (event.key === '?') {
                    event.preventDefault();
                    this.$refs.dialogPintasan?.showModal();
                    return;
                }

                if (event.key === 'ArrowLeft' && !this.isolasiSoal && this.index > 0) {
                    event.preventDefault();
                    this.pindah(this.index - 1);
                    return;
                }

                if (event.key === 'ArrowRight' && !this.isolasiSoal && this.index < this.questions.length - 1) {
                    event.preventDefault();
                    this.pindah(this.index + 1);
                    return;
                }

                if ((event.key === 'r' || event.key === 'R') && this.current) {
                    event.preventDefault();
                    this.toggleRagu(this.current);
                    return;
                }

                if ((event.key === 'v' || event.key === 'V') && this.pisahKonteksSoal && this.current?.has_context_blocks) {
                    event.preventDefault();
                    this.tabKonteks = this.tabKonteks === 'stimulus' ? 'jawab' : 'stimulus';
                    return;
                }

                if (this.current && (this.current.tipe === 'pilihan_ganda' || this.current.tipe === 'pilihan_majemuk')) {
                    const lower = event.key.toLowerCase();
                    let optIdx = -1;
                    if (lower >= '1' && lower <= '6') {
                        optIdx = Number(lower) - 1;
                    } else if (lower >= 'a' && lower <= 'f') {
                        optIdx = lower.charCodeAt(0) - 97;
                    }
                    const opsi = optIdx >= 0 ? this.current.opsi?.[optIdx] : null;
                    if (opsi) {
                        event.preventDefault();
                        if (this.current.tipe === 'pilihan_majemuk') {
                            this.toggleMulti(this.current, opsi);
                        } else {
                            this.pilih(this.current, opsi);
                        }
                    }
                }
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
                if (!soal || (soal.tipe !== 'essay' && soal.tipe !== 'isian_singkat') || this.berhenti || soal.sub_soal?.length) {
                    return true;
                }
                const tipeTeks = soal.tipe;

                const isTable = soal.answer_format === 'table';
                if (this.janjiTeks[soal.id]) {
                    await this.janjiTeks[soal.id];

                    if (!isTable && (this.jawabanTeks[soal.id] ?? '') === (this.teksTersimpan[soal.id] ?? '')) {
                        return true;
                    }
                }

                const nilai = isTable ? JSON.stringify(this.tabelCells(soal)) : (this.jawabanTeks[soal.id] ?? '');
                const tersimpan = isTable
                    ? JSON.stringify(this.dataTersimpan[soal.id]?.__main?.value?.cells ?? this.tabelCells(soal).map((row) => row.map(() => '')))
                    : (this.teksTersimpan[soal.id] ?? '');

                if (nilai === tersimpan) {
                    this.statusTeks[soal.id] = nilai === '' ? '' : 'tersimpan';
                    return true;
                }

                if (!navigator.onLine) {
                    this.statusTeks[soal.id] = 'offline';
                    this.tambahAntrean({
                        question_id: soal.id,
                        ...(isTable ? { jawaban_table: { cells: this.tabelCells(soal) } } : { jawaban_teks: nilai }),
                        tipe: tipeTeks,
                        waktu: Date.now()
                    });
                    return true;
                }

                this.statusTeks[soal.id] = 'menyimpan';
                let hasil;

                try {
                    const janji = window.kuisFetch(config.answerUrl, {
                        method: 'POST',
                        body: {
                            question_id: soal.id,
                            ...(isTable ? { jawaban_table: { cells: this.tabelCells(soal) } } : { jawaban_teks: nilai }),
                            ...(this.ringkasTypingMetrics(soal.id) ? { typing_metrics: this.ringkasTypingMetrics(soal.id) } : {})
                        },
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
                        ...(isTable ? { jawaban_table: { cells: this.tabelCells(soal) } } : { jawaban_teks: nilai }),
                        tipe: tipeTeks,
                        waktu: Date.now()
                    });
                    return true;
                }

                delete this.janjiTeks[soal.id];

                if (hasil.ok) {
                    this.teksTersimpan[soal.id] = hasil.data?.jawaban_teks ?? (isTable ? '' : nilai);
                    this.jawabanData[soal.id] = hasil.data?.jawaban_data ?? this.jawabanData[soal.id] ?? {};
                    this.dataTersimpan[soal.id] = JSON.parse(JSON.stringify(this.jawabanData[soal.id] ?? {}));
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

                if (hasil.status === 422) {
                    this.statusTeks[soal.id] = 'gagal';
                    this.tambahAntrean({ question_id: soal.id, ...(isTable ? { jawaban_table: { cells: this.tabelCells(soal) } } : { jawaban_teks: nilai }), tipe: tipeTeks, waktu: Date.now() });
                    this.pesan = hasil.data?.message ?? 'Jawaban belum dapat diterima server. Isi jawaban tetap tersimpan di perangkat.';
                    return false;
                }

                // Error jaringan / temporary server, simpan offline
                this.statusTeks[soal.id] = 'offline';
                this.tambahAntrean({
                    question_id: soal.id,
                    ...(isTable ? { jawaban_table: { cells: this.tabelCells(soal) } } : { jawaban_teks: nilai }),
                    tipe: tipeTeks,
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
                this.jawabanParts[soal.id][bagian.key] = nilai;

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

                const isTable = bagian.answer_format === 'table';
                if (this.janjiParts[key]) {
                    await this.janjiParts[key];

                    if (!isTable && (this.jawabanParts[soal.id]?.[bagian.key] ?? '') === (this.partsTersimpan[soal.id]?.[bagian.key] ?? '')) {
                        return true;
                    }
                }

                const nilai = isTable ? JSON.stringify(this.tabelCells(soal, bagian)) : (this.jawabanParts[soal.id]?.[bagian.key] ?? '');
                const tersimpan = isTable
                    ? JSON.stringify(this.dataTersimpan[soal.id]?.[bagian.key]?.value?.cells ?? this.tabelCells(soal, bagian).map((row) => row.map(() => '')))
                    : (this.partsTersimpan[soal.id]?.[bagian.key] ?? '');

                if (nilai === tersimpan) {
                    this.statusParts[key] = nilai === '' ? '' : 'tersimpan';
                    return true;
                }

                if (!navigator.onLine) {
                    this.statusParts[key] = 'offline';
                    this.tambahAntrean({
                        question_id: soal.id,
                        part_key: bagian.key,
                        ...(isTable ? { jawaban_table: { cells: this.tabelCells(soal, bagian) } } : { jawaban_teks: nilai }),
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
                        body: {
                            question_id: soal.id,
                            part_key: bagian.key,
                            ...(isTable ? { jawaban_table: { cells: this.tabelCells(soal, bagian) } } : { jawaban_teks: nilai }),
                            ...(this.ringkasTypingMetrics(soal.id) ? { typing_metrics: this.ringkasTypingMetrics(soal.id) } : {})
                        },
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
                        part_key: bagian.key,
                        ...(isTable ? { jawaban_table: { cells: this.tabelCells(soal, bagian) } } : { jawaban_teks: nilai }),
                        tipe: 'essay',
                        waktu: Date.now()
                    });
                    return true;
                }

                delete this.janjiParts[key];

                if (hasil.ok) {
                    this.partsTersimpan[soal.id] = hasil.data?.jawaban_parts ?? {};
                    this.jawabanData[soal.id] = hasil.data?.jawaban_data ?? this.jawabanData[soal.id] ?? {};
                    this.dataTersimpan[soal.id] = JSON.parse(JSON.stringify(this.jawabanData[soal.id] ?? {}));
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
                    this.tambahAntrean({ question_id: soal.id, part_key: bagian.key, ...(isTable ? { jawaban_table: { cells: this.tabelCells(soal, bagian) } } : { jawaban_teks: nilai }), tipe: 'essay', waktu: Date.now() });
                    this.pesan = hasil.data?.message ?? 'Jawaban belum dapat diterima server. Isi jawaban tetap tersimpan di perangkat.';
                    return false;
                }

                this.statusParts[key] = 'offline';
                this.tambahAntrean({
                    question_id: soal.id,
                    part_key: bagian.key,
                    ...(isTable ? { jawaban_table: { cells: this.tabelCells(soal, bagian) } } : { jawaban_teks: nilai }),
                    tipe: 'essay',
                    waktu: Date.now()
                });

                return true;
            },
            async simpanSemuaTeks() {
                for (const soal of this.questions.filter((item) => item.tipe === 'essay' || item.tipe === 'isian_singkat')) {
                    if (soal.sub_soal?.length) {
                        for (const bagian of this.bagianJawaban(soal)) {
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
                if (this.mengumpulkan || this.berhenti) {
                    return;
                }

                if (!paksa && this.belum > 0) {
                    this.konfirmasiSelesai = true;
                    this.$nextTick(() => this.$refs.tombolKembali?.focus());

                    return;
                }

                await this.prosesPengumpulan(paksa);
            },
            async kumpulkanSekarang() {
                await this.prosesPengumpulan(false);
            },
            async prosesPengumpulan(paksa = false) {
                if (this.mengumpulkan || this.berhenti) {
                    return;
                }

                this.mengumpulkan = true;
                window.tampilkanProgress('Menyinkronkan jawaban...', 0);

                try {
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
                } finally {
                    if (!this.berhenti) {
                        this.mengumpulkan = false;
                        window.sembunyikanProgress();
                    }
                }
            },
            tutupKonfirmasi() {
                if (!this.konfirmasiSelesai || this.mengumpulkan) {
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
                document.getElementById('form-selesai').requestSubmit();
            },
            async cekStatus() {
                if (this.berhenti) {
                    return true;
                }
                let hasil;
                try {
                    hasil = await window.kuisFetch(config.statusUrl);
                } catch {
                    return false;
                }
                if (!hasil.ok) {
                    if (hasil.status === 404 || hasil.status === 403) {
                        this.berhenti = true;
                        this.batalkanToleransi();
                        window.location.href = config.dashboardUrl + '?dikeluarkan=1';
                    }
                    return false;
                }

                this.gabungPesan(hasil.data?.pesan ?? []);
                if (hasil.data?.screen_peek_active && hasil.data?.screen_peek_until) {
                    this.aktifkanPeekLayar(hasil.data.screen_peek_until, hasil.data.server_now);
                }

                const batas = Number(hasil.data?.batas_akhir_at ?? 0);
                const serverNow = Number(hasil.data?.server_now ?? 0);

                if (batas > 0 && serverNow > 0) {
                    const sisaLama = this.sisa;
                    this.batasAkhir = batas;
                    this.offset = Date.now() - serverNow;
                    const sisaBaru = Math.max(0, Math.round((this.batasAkhir - (Date.now() - this.offset)) / 1000));

                    if (sisaBaru > sisaLama + 1) {
                        this.notifWaktu60 = false;
                        this.notifWaktu300 = false;
                        this.pesan = 'Waktu ujian ditambah. Timer sudah diperbarui.';
                    } else if (sisaBaru + 1 < sisaLama) {
                        this.pesan = 'Waktu ujian dikurangi karena pelanggaran. Timer sudah diperbarui.';
                    }
                }

                if (hasil.data?.selesai) {
                    this.berhenti = true;
                    this.batalkanToleransi();
                    if (hasil.data?.forced_logout) {
                        this.tampilkanPengumpulanPaksa(hasil.data);
                    } else {
                        window.location.href = config.resultUrl;
                    }
                } else if (hasil.data?.proctoring_action && hasil.data.proctoring_action !== 'none') {
                    this.batalkanToleransi();
                    this.pesan = hasil.data.proctoring_action === 'reduce_time'
                        ? 'Waktu ujian dikurangi karena pelanggaran. Timer sudah diperbarui.'
                        : 'Pelanggaran tercatat. Anda dapat melanjutkan ujian.';
                }
                return true;
            },
            async masukLayarPenuh() {
                const mediaOk = await this.siapkanMediaPengawas();
                if (!mediaOk) return;
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
                this.bersihkanJadwalSnapshotKeluar();
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

                await Promise.race([
                    (async () => {
                        await this.simpanSemuaTeks();
                        if (navigator.onLine && this.antreanOffline.length > 0) await this.prosesAntrean(false);
                    })(),
                    new Promise((resolve) => setTimeout(resolve, 1500))
                ]);

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
                    this.tampilkanPengumpulanPaksa(hasil.data);
                    return;
                }

                this.sisaToleransi = 0;
                if (hasil.data?.batas_akhir_at) this.batasAkhir = Number(hasil.data.batas_akhir_at);
                this.pesan = hasil.data?.message ?? 'Pelanggaran tercatat. Tekan Kembali ke Ujian untuk melanjutkan.';
            },
            tampilkanPengumpulanPaksa(data) {
                this.pengumpulanPaksa = true;
                this.pesanPengumpulanPaksa = data?.message ?? 'Pekerjaan dikumpulkan paksa. Anda akan keluar dari sistem.';
                setTimeout(() => {
                    window.location.href = data?.redirect_url || config.dashboardUrl;
                }, 3000);
            },
            tampilkanClipboardToast(pesan, blocked = false) {
                this.clipboardToast = pesan;
                this.clipboardToastBlocked = blocked;
                if (this.clipboardToastTimer) clearTimeout(this.clipboardToastTimer);
                this.clipboardToastTimer = setTimeout(() => {
                    this.clipboardToast = '';
                }, 4000);
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
                        if (!['copy_attempt', 'paste_attempt'].includes(tipe)) {
                            this.peringatan = 'Aktivitas ini tercatat: ' + this.labelKejadian(tipe) + '. Tetap fokus pada soal.';
                        }
                    }
                });
            },
            labelKejadian(tipe) {
                return {
                    blur_window: 'jendela kuis ditinggalkan',
                    tab_hidden: 'tab kuis disembunyikan',
                    fullscreen_exit: 'keluar dari layar penuh',
                    fullscreen_exit_timeout: 'toleransi keluar layar penuh habis',
                    copy_attempt: 'upaya menyalin soal',
                    paste_attempt: 'paste teks ke jawaban'
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
                        this.jadwalkanSnapshotKeluar('blur_window');
                    }
                });

                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        this.kirimKejadian('tab_hidden');
                        if (this.sudahMulai && !this.berhenti && !this.memprosesPelanggaran) {
                            this.mulaiToleransi();
                            this.jadwalkanSnapshotKeluar('tab_hidden');
                        }
                    } else {
                        this.bersihkanJadwalSnapshotKeluar();
                    }
                });

                window.addEventListener('beforeunload', (e) => {
                    if (!this.berhenti) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });

                document.addEventListener('copy', (event) => {
                    const target = event.target instanceof Element ? event.target : null;
                    if (target?.closest('textarea, input, [contenteditable="true"]')) return;

                    const anchor = window.getSelection()?.anchorNode;
                    const element = anchor instanceof Element ? anchor : anchor?.parentElement;
                    if (!element?.closest('[data-protected-question]')) return;

                    if (config.clipboardPolicy === 'allow') {
                        this.tampilkanClipboardToast('Teks soal disalin. Sesi ini mengizinkan copy.');
                        return;
                    }

                    event.preventDefault();
                    const blocked = config.clipboardPolicy === 'block';
                    this.tampilkanClipboardToast(
                        blocked
                            ? 'Copy soal diblokir dan dihitung sebagai pelanggaran.'
                            : 'Copy soal diblokir dan dicatat tanpa menambah pelanggaran.',
                        blocked
                    );
                    this.kirimKejadian('copy_attempt');
                    if (blocked) this.mulaiToleransi();
                });

                document.addEventListener('paste', (event) => {
                    const target = event.target instanceof Element ? event.target : null;
                    if (!target?.closest('[data-answer-input]')) return;

                    if (this.current?.id) {
                        this.catatPaste(this.current.id);
                    }

                    if (config.clipboardPolicy === 'allow') {
                        this.tampilkanClipboardToast('Teks ditempelkan. Sesi ini mengizinkan paste.');
                        return;
                    }

                    const blocked = config.clipboardPolicy === 'block';
                    if (blocked) event.preventDefault();
                    this.tampilkanClipboardToast(
                        blocked
                            ? 'Paste jawaban diblokir dan dihitung sebagai pelanggaran.'
                            : 'Paste jawaban diizinkan dan dicatat tanpa menambah pelanggaran.',
                        blocked
                    );
                    this.kirimKejadian('paste_attempt');
                    if (blocked) this.mulaiToleransi();
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
            totalSoal: {{ (int) ($total_soal ?? count($questions)) }},
            isolasiSoal: @js((bool) ($isolasi_soal ?? false)),
            timerPerSoal: @js((bool) $session->timer_per_soal),
            pisahKonteksSoal: @js((bool) $session->pisah_konteks_soal),
            watermarkLayar: @js((bool) $session->watermark_layar),
            screenProctoring: @js($session->screen_proctoring ?? 'off'),
            webcamProctoring: @js((bool) $session->webcam_proctoring),
            jawaban: {{ Illuminate\Support\Js::from(collect($questions)->mapWithKeys(fn ($q) => [$q['id'] => $q['jawaban_option_id']])->filter()->all()) }},
            jawabanMulti: {{ Illuminate\Support\Js::from(collect($questions)->mapWithKeys(fn ($q) => [$q['id'] => $q['jawaban_option_ids'] ?? []])->all()) }},
            jawabanTeks: {{ Illuminate\Support\Js::from(collect($questions)->mapWithKeys(fn ($q) => [$q['id'] => $q['jawaban_teks'] ?? ''])->all()) }},
            jawabanParts: {{ Illuminate\Support\Js::from(collect($questions)->mapWithKeys(fn ($q) => [$q['id'] => $q['jawaban_parts'] ?? []])->all()) }},
            jawabanData: {{ Illuminate\Support\Js::from(collect($questions)->mapWithKeys(fn ($q) => [$q['id'] => $q['jawaban_data'] ?? []])->all()) }},
            serverNow: {{ $server_now }},
            batasAkhir: {{ $attempt->batas_akhir_at }},
            sessionId: {{ $session->id }},
            userId: {{ auth()->id() }},
            attemptId: {{ $attempt->id }},
            mulaiIndex: {{ $mulai_index }},
            durasiPeringatan: {{ $session->durasiPeringatan() }},
            clipboardPolicy: @js($session->clipboard_policy ?? 'monitor'),
            answerUrl: '{{ route('peserta.attempt.answer', $attempt) }}',
            nextQuestionUrl: '{{ route('peserta.attempt.next-question', $attempt) }}',
            snapshotUrl: '{{ route('peserta.attempt.snapshot', $attempt) }}',
            eventUrl: '{{ route('peserta.attempt.events', $attempt) }}',
            markUrl: '{{ route('peserta.attempt.tandai', $attempt) }}',
            pelanggaranUrl: '{{ route('peserta.attempt.pelanggaran', $attempt) }}',
            statusUrl: '{{ route('peserta.attempt.status', $attempt) }}',
            tutupUrlPesan: '{{ route('peserta.pesan.tutup', ['message' => '__ID__']) }}',
            dashboardUrl: '{{ route('peserta.dashboard') }}',
            resultUrl: '{{ route('peserta.attempt.result', $attempt) }}'
        })"
        class="space-y-5"
        x-on:keydown.window="tanganiPintasan($event)"
        x-bind:data-clipboard-policy="clipboardPolicy"
        x-on:kuis-code-copy-attempt.window="
            const blocked = $event.detail.policy === 'block';
            tampilkanClipboardToast(blocked ? 'Copy kode diblokir dan dihitung sebagai pelanggaran.' : 'Copy kode diblokir dan dicatat tanpa menambah pelanggaran.', blocked);
            kirimKejadian('copy_attempt');
            if (blocked) mulaiToleransi();
        "
    >
        <div class="exam-bar -mx-4 px-4 py-3 sm:-mx-6 sm:px-6">
            <div class="mx-auto flex max-w-6xl flex-col gap-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-baseline gap-2">
                        <p class="text-sm font-bold text-ink-muted">Soal</p>
                        <p class="font-display text-2xl font-bold text-ink">
                            <span x-text="isolasiSoal ? (current?.urutan ?? 1) : (index + 1)">1</span><span class="text-ink-muted"> / </span><span x-text="totalSoal"></span>
                        </p>
                        <template x-if="timerPerSoal && current?.berakhir_at">
                            <span class="ml-2 rounded-xl border border-sun-deep/40 bg-sun-soft px-2.5 py-1 font-mono text-xs font-bold text-sun-deep">
                                Soal ini: <span x-text="format(sisaSoal)">0:00</span>
                            </span>
                        </template>
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
                        <x-exam-tools :tools="$session->examTools()" />
                        <button
                            type="button"
                            class="btn btn-primary"
                            x-ref="tombolSelesai"
                            x-on:click="akhiri(false)"
                            x-bind:disabled="mengumpulkan || berhenti"
                            x-bind:aria-busy="mengumpulkan"
                        >
                            <span x-show="mengumpulkan" x-cloak class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                            <span x-text="mengumpulkan ? 'Mengumpulkan...' : 'Selesai'">Selesai</span>
                        </button>
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
            <section x-ref="kartuSoal" class="card relative overflow-hidden scroll-mt-32" aria-labelledby="teks-soal" data-protected-question>
                @if ($session->watermark_layar)
                    <div aria-hidden="true" class="pointer-events-none select-none absolute inset-0 flex flex-wrap items-center justify-around gap-8 overflow-hidden p-6 opacity-[0.055]">
                        @for ($wm = 0; $wm < 6; $wm++)
                            <span class="-rotate-12 font-mono text-sm font-extrabold uppercase tracking-widest text-ink">
                                {{ auth()->user()->nama }} · {{ auth()->user()->username }}
                            </span>
                        @endfor
                    </div>
                @endif
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="pill pill-brand" x-text="current.tipe === 'essay' ? 'Essay · maks ' + current.bobot : (current.label_tipe || 'Pilihan ganda')"></span>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="btn btn-ghost min-h-11 px-3 text-sm" x-on:click="$refs.dialogPintasan?.showModal()">
                            <span>Pintasan (?)</span>
                        </button>
                        <button type="button" class="btn btn-ghost min-h-11 px-3" x-on:click="masukLayarPenuh()" x-bind:disabled="isFullscreen">
                            <x-icon name="eye" :size="20" />
                            <span class="hidden sm:inline">Layar penuh</span>
                        </button>
                    </div>
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

                <template x-if="pisahKonteksSoal && current.has_context_blocks">
                    <div role="tablist" aria-label="Tampilan data soal dan jawaban" class="mt-4 inline-flex flex-wrap gap-2 rounded-2xl border border-outline bg-surface p-1.5">
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="tabKonteks === 'stimulus'"
                            @click="tabKonteks = 'stimulus'"
                            class="min-h-11 rounded-xl px-4 py-2 text-sm font-bold transition"
                            :class="tabKonteks === 'stimulus' ? 'bg-brand-deep text-white' : 'text-ink-muted hover:text-ink'"
                        >
                            1. Lihat Data / Gambar Soal
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="tabKonteks === 'jawab'"
                            @click="tabKonteks = 'jawab'"
                            class="min-h-11 rounded-xl px-4 py-2 text-sm font-bold transition"
                            :class="tabKonteks === 'jawab' ? 'bg-brand-deep text-white' : 'text-ink-muted hover:text-ink'"
                        >
                            2. Pertanyaan &amp; Jawaban
                        </button>
                    </div>
                </template>

                <h2
                    id="teks-soal"
                    x-show="!pisahKonteksSoal || !current.has_context_blocks || tabKonteks === 'jawab'"
                    class="question-text mt-4 whitespace-pre-line"
                    x-bind:class="current.highlight_soal ? 'bg-sun-soft' : ''"
                    x-text="current.teks"
                ></h2>

                <div
                    x-show="current.content_html && (!pisahKonteksSoal || !current.has_context_blocks || tabKonteks === 'stimulus')"
                    x-html="current.content_html"
                    class="mt-4 space-y-4"
                ></div>

                <div x-show="pisahKonteksSoal && current.has_context_blocks && tabKonteks === 'stimulus'" class="mt-4">
                    <button type="button" @click="tabKonteks = 'jawab'" class="btn btn-primary min-h-11">
                        Buka Pertanyaan &amp; Pilihan Jawaban
                    </button>
                </div>

                <div x-show="!pisahKonteksSoal || !current.has_context_blocks || tabKonteks === 'jawab'">

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
                        <template x-if="current.answer_format === 'rich_text'">
                            <div class="mb-2 flex flex-wrap gap-2" role="toolbar" aria-label="Pemformatan jawaban">
                                <button type="button" class="btn btn-secondary min-h-11 px-3" x-on:click="formatMarkdown('jawaban-essay', '**')"><strong>Tebal</strong></button>
                                <button type="button" class="btn btn-secondary min-h-11 px-3" x-on:click="formatMarkdown('jawaban-essay', '*')"><em>Miring</em></button>
                                <button type="button" class="btn btn-secondary min-h-11 px-3" x-on:click="formatMarkdown('jawaban-essay', '- ', '')">Daftar</button>
                                <button type="button" class="btn btn-secondary min-h-11 px-3" x-on:click="formatMarkdown('jawaban-essay', '`')">Kode singkat</button>
                            </div>
                        </template>
                        <template x-if="current.answer_format !== 'table'">
                            <textarea
                                id="jawaban-essay"
                                data-answer-input
                                class="input min-h-[12rem] resize-y"
                                x-bind:class="current.answer_format === 'code' ? 'font-mono' : ''"
                                maxlength="20000"
                                x-bind:spellcheck="current.answer_format !== 'code'"
                                x-bind:disabled="berhenti"
                                x-bind:value="jawabanTeks[current.id] ?? ''"
                                x-on:keydown.tab="if (current.answer_format === 'code') { $event.preventDefault(); sisipkanTab($event) }"
                                x-on:keydown="catatKetikan(current.id, $event)"
                                x-on:input="ubahTeks(current, $event.target.value)"
                                x-on:blur="simpanTeks(current)"
                                x-bind:placeholder="current.answer_format === 'code' ? 'Tulis kode ' + (current.answer_config.language || '') + ' di sini' : 'Tulis jawaban lengkap di sini'"
                            ></textarea>
                        </template>
                        <template x-if="current.answer_format === 'table'">
                            <div class="overflow-x-auto" tabindex="0">
                                <table class="answer-table">
                                    <thead><tr><th scope="col" x-text="current.answer_config.row_header || 'Baris'"></th><template x-for="column in current.answer_config.columns" :key="column"><th scope="col" x-text="column"></th></template></tr></thead>
                                    <tbody><template x-for="(row, rowIndex) in current.answer_config.rows" :key="rowIndex"><tr><th scope="row" x-text="row"></th><template x-for="(column, columnIndex) in current.answer_config.columns" :key="columnIndex"><td><input data-answer-input class="input min-w-40" maxlength="1000" x-bind:value="tabelCells(current)[rowIndex][columnIndex]" x-bind:placeholder="current.answer_config.cell_hints?.[rowIndex]?.[columnIndex] || ''" x-on:input="ubahSelTabel(current, null, rowIndex, columnIndex, $event.target.value)" x-on:blur="simpanTeks(current)" x-bind:aria-label="row + ', ' + column"></td></template></tr></template></tbody>
                                </table>
                            </div>
                        </template>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm text-ink-muted">
                            <span x-show="current.answer_format !== 'table'" x-text="(jawabanTeks[current.id] ?? '').length + ' / 20000 karakter'"></span>
                            <span x-show="current.answer_format === 'table'" x-text="tabelLengkap(current) ? 'Semua sel terisi' : 'Lengkapi seluruh sel tabel'"></span>
                            <span x-text="statusTeksAktif"></span>
                        </div>
                    </div>
                </template>

                <template x-if="current.tipe === 'essay' && current.sub_soal?.length">
                    <div class="mt-5 space-y-6">
                        <div class="flex flex-wrap items-center gap-2 border-b border-outline pb-3">
                            <span class="text-sm font-bold text-ink-muted">Bagian:</span>
                            <template x-for="bagian in bagianJawaban(current)" :key="bagian.key">
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

                        <template x-for="(bagian, bagianIndex) in current.sub_soal" :key="bagian.key">
                            <div
                                class="scroll-mt-32"
                                x-bind:class="bagianIndex > 0 ? 'border-t border-outline pt-5' : ''"
                                x-bind:id="'bagian-' + current.id + '-' + bagianIndex"
                            >
                                <h3 class="text-xl font-bold text-ink">
                                    Bagian <span x-text="kodeBagian(current, bagian)"></span>
                                </h3>
                                <p class="mt-1 whitespace-pre-line text-base text-ink-muted" x-text="bagian.teks"></p>
                                <div x-show="bagian.content_html" x-html="bagian.content_html" class="mt-3 space-y-4"></div>
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
                                <p x-show="bagian.has_children" class="mt-2 text-sm font-semibold text-ink-muted">Jawab melalui bagian turunannya.</p>
                                <template x-if="bagian.is_leaf">
                                    <div class="mt-3">
                                        <label class="label" x-bind:for="'jawaban-' + current.id + '-' + bagianIndex">Jawaban <span x-text="kodeBagian(current, bagian)"></span></label>
                                        <div x-show="bagian.answer_format === 'rich_text'" class="mb-2 flex flex-wrap gap-2" role="toolbar" x-bind:aria-label="'Pemformatan jawaban ' + kodeBagian(current, bagian)">
                                            <button type="button" class="btn btn-secondary min-h-11 px-3" x-on:click="formatMarkdown('jawaban-' + current.id + '-' + bagianIndex, '**')"><strong>Tebal</strong></button>
                                            <button type="button" class="btn btn-secondary min-h-11 px-3" x-on:click="formatMarkdown('jawaban-' + current.id + '-' + bagianIndex, '*')"><em>Miring</em></button>
                                            <button type="button" class="btn btn-secondary min-h-11 px-3" x-on:click="formatMarkdown('jawaban-' + current.id + '-' + bagianIndex, '- ', '')">Daftar</button>
                                        </div>
                                        <textarea
                                            x-show="bagian.answer_format !== 'table'"
                                            data-answer-input
                                            class="input min-h-[9rem] resize-y"
                                            x-bind:class="bagian.answer_format === 'code' ? 'font-mono' : ''"
                                            maxlength="20000"
                                            x-bind:spellcheck="bagian.answer_format !== 'code'"
                                            x-bind:id="'jawaban-' + current.id + '-' + bagianIndex"
                                            x-bind:disabled="berhenti"
                                            x-bind:value="(jawabanParts[current.id] ?? {})[bagian.key] ?? ''"
                                            x-on:keydown.tab="if (bagian.answer_format === 'code') { $event.preventDefault(); sisipkanTab($event) }"
                                            x-on:keydown="catatKetikan(current.id, $event)"
                                            x-on:input="ubahBagian(current, bagian, $event.target.value)"
                                            x-on:blur="simpanBagian(current, bagian)"
                                            x-bind:placeholder="bagian.answer_format === 'code' ? 'Tulis kode ' + (bagian.answer_config.language || '') + ' di sini' : 'Tulis jawaban ' + kodeBagian(current, bagian) + ' di sini'"
                                        ></textarea>
                                        <div x-show="bagian.answer_format === 'table'" class="overflow-x-auto" tabindex="0">
                                            <table class="answer-table">
                                                <thead><tr><th scope="col" x-text="bagian.answer_config.row_header || 'Baris'"></th><template x-for="column in bagian.answer_config.columns" :key="column"><th scope="col" x-text="column"></th></template></tr></thead>
                                                <tbody><template x-for="(row, rowIndex) in bagian.answer_config.rows" :key="rowIndex"><tr><th scope="row" x-text="row"></th><template x-for="(column, columnIndex) in bagian.answer_config.columns" :key="columnIndex"><td><input data-answer-input class="input min-w-40" maxlength="1000" x-bind:value="tabelCells(current, bagian)[rowIndex][columnIndex]" x-bind:placeholder="bagian.answer_config.cell_hints?.[rowIndex]?.[columnIndex] || ''" x-on:input="ubahSelTabel(current, bagian, rowIndex, columnIndex, $event.target.value)" x-on:blur="simpanBagian(current, bagian)" x-bind:aria-label="row + ', ' + column"></td></template></tr></template></tbody>
                                            </table>
                                        </div>
                                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm text-ink-muted">
                                            <span x-show="bagian.answer_format !== 'table'" x-text="(((jawabanParts[current.id] ?? {})[bagian.key] ?? '').length) + ' / 20000 karakter'"></span>
                                            <span x-show="bagian.answer_format === 'table'" x-text="tabelLengkap(current, bagian) ? 'Semua sel terisi' : 'Lengkapi seluruh sel tabel'"></span>
                                            <span x-text="statusBagian(current, bagian)"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="current.tipe === 'isian_singkat'">
                    <div class="mt-5">
                        <label for="jawaban-isian-singkat" class="label">Jawaban singkat Anda</label>
                        <input
                            id="jawaban-isian-singkat"
                            type="text"
                            data-answer-input
                            class="input max-w-xl font-medium"
                            maxlength="500"
                            autocomplete="off"
                            x-bind:disabled="berhenti"
                            x-bind:value="jawabanTeks[current.id] ?? ''"
                            x-on:input="ubahTeks(current, $event.target.value)"
                            x-on:blur="simpanTeks(current)"
                            placeholder="Ketik jawaban singkat di sini..."
                        >
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm text-ink-muted max-w-xl">
                            <span>Tulis tepat dan ringkas (maksimal 500 karakter).</span>
                            <span aria-live="polite" x-text="statusTeksAktif"></span>
                        </div>
                    </div>
                </template>

                <template x-if="current.tipe === 'pilihan_majemuk'">
                    <div class="mt-5 space-y-3" role="group" aria-labelledby="teks-soal">
                        <p class="text-base text-ink-muted">Pilih semua jawaban yang benar (bisa lebih dari satu). Perubahan langsung tersimpan.</p>
                        <template x-for="(opsi, opsiIndex) in current.opsi" :key="opsi.id">
                            <div class="relative">
                                <button
                                    type="button"
                                    role="checkbox"
                                    class="tile w-full pr-12"
                                    x-bind:class="opsiDisorot(current, opsi.id) ? 'ring-2 ring-sun bg-sun-soft' : ''"
                                    x-bind:aria-checked="opsiMultiDipilih(current, opsi.id) ? 'true' : 'false'"
                                    x-bind:aria-pressed="opsiMultiDipilih(current, opsi.id) ? 'true' : 'false'"
                                    x-bind:disabled="menyimpan"
                                    x-on:click="toggleMulti(current, opsi)"
                                >
                                    <span class="tile-mark" x-text="String.fromCharCode(65 + opsiIndex)"></span>
                                    <span class="tile-text" x-text="opsi.teks"></span>
                                    <span class="tile-check" aria-hidden="true">
                                        <x-icon name="check" :size="20" />
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 min-h-11 min-w-11 flex items-center justify-center rounded-lg border border-outline bg-page p-1.5 text-ink-muted hover:bg-sun-soft hover:text-sun-deep"
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

                <template x-if="current.tipe === 'pilihan_ganda'">
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
                                    class="absolute right-3 top-1/2 -translate-y-1/2 min-h-11 min-w-11 flex items-center justify-center rounded-lg border border-outline bg-page p-1.5 text-ink-muted hover:bg-sun-soft hover:text-sun-deep"
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
                    <template x-if="!isolasiSoal">
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-secondary min-h-11" x-on:click="pindah(index - 1)" x-bind:disabled="index === 0">
                                <x-icon name="arrow-left" :size="20" />
                                <span>Sebelumnya</span>
                            </button>
                            <button type="button" class="btn btn-secondary min-h-11" x-on:click="pindah(index + 1)" x-bind:disabled="index === questions.length - 1">
                                <span>Berikutnya</span>
                            </button>
                        </div>
                    </template>
                    <template x-if="isolasiSoal">
                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                class="btn btn-primary min-h-11"
                                :disabled="memuatSoalBerikutnya || berhenti"
                                @click="mintaLanjutSoal(false)"
                            >
                                <span x-text="memuatSoalBerikutnya ? 'Memuat soal...' : ((current?.urutan ?? 1) >= totalSoal ? 'Kunci & selesaikan ujian' : 'Soal berikutnya (kunci soal ini)')"></span>
                            </button>
                        </div>
                    </template>
                    <p class="text-base text-ink-muted" x-text="isolasiSoal ? 'Soal yang sudah dilewati terkunci dan tidak dapat dibuka kembali.' : 'Jawaban bisa diubah sampai waktu habis.'"></p>
                </div>
                </div>
            </section>
        </template>

        <div
            x-show="konfirmasiLanjutKosong"
            x-cloak
            class="fixed inset-0 z-[65] flex items-center justify-center bg-ink/70 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="judul-lanjut-kosong"
            @keydown.escape.window="konfirmasiLanjutKosong = false"
        >
            <div class="card w-full max-w-md border-2 border-sun-deep bg-page p-6 shadow-modal">
                <h2 id="judul-lanjut-kosong" class="text-xl font-bold text-ink">Soal ini belum dijawab</h2>
                <p class="mt-2 text-base text-ink-muted">
                    Pada sesi sekuensial ini, soal yang dilewati akan langsung terkunci dan tidak dapat dibuka kembali. Yakin ingin lanjut?
                </p>
                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="btn btn-secondary min-h-11" @click="konfirmasiLanjutKosong = false">Kembali jawab</button>
                    <button type="button" class="btn btn-primary min-h-11" @click="mintaLanjutSoal(true)">Tetap kunci &amp; lanjut</button>
                </div>
            </div>
        </div>

        <section x-show="!isolasiSoal" class="card" aria-labelledby="navigasi-soal">
            <h2 id="navigasi-soal" class="text-xl">Navigasi soal</h2>
            <div class="mt-3 grid grid-cols-6 gap-2 sm:grid-cols-10">
                <template x-for="(soal, nomor) in questions" :key="soal.id">
                    <button
                        type="button"
                        class="flex h-12 items-center justify-center rounded-xl border-2 font-mono text-base font-bold"
                        x-bind:class="nomor === index ? (soal.ragu_ragu ? 'border-coral-deep bg-coral-soft text-coral-deep ring-2 ring-brand' : 'border-brand-deep bg-brand-deep text-white') : (soal.ragu_ragu ? 'border-coral-deep bg-coral-soft text-coral-deep' : (sudahDijawab(soal) ? 'border-leaf bg-leaf-soft text-leaf-deep' : (terjawabSebagian(soal) ? 'border-sun-deep bg-sun-soft text-sun-deep' : 'border-outline bg-page text-ink-muted')))"
                        x-bind:aria-current="nomor === index ? 'true' : 'false'"
                        x-bind:aria-label="'Soal ' + (nomor + 1) + (soal.ragu_ragu ? ', ditandai ragu-ragu' : (sudahDijawab(soal) ? ', sudah dijawab lengkap' : (terjawabSebagian(soal) ? ', sebagian dijawab' : ', belum dijawab')))"
                        x-on:click="pindah(nomor)"
                        x-text="nomor + 1"
                    ></button>
                </template>
            </div>
            <p class="mt-3 text-base text-ink-muted">Kotak hijau berarti semua bagian sudah dijawab. Kotak kuning berarti baru sebagian bagian essay dijawab. Kotak merah berarti soal ditandai ragu-ragu.</p>
        </section>

        <form
            id="form-selesai"
            method="POST"
            action="{{ route('peserta.attempt.finish', $attempt) }}"
            class="hidden"
            data-progress-message="Mengunci jawaban dan menghitung hasil..."
            data-progress-delay="0"
        >
            @csrf
        </form>

        <form id="form-pelanggaran" method="POST" action="{{ route('peserta.attempt.pelanggaran', $attempt) }}" class="hidden">
            @csrf
        </form>

        <div class="fixed right-4 top-4 z-[85] flex w-[min(calc(100vw-2rem),24rem)] flex-col gap-3">
            <template x-for="pesan in pesanMasuk" :key="pesan.id">
                <div class="rounded-xl border-2 border-brand bg-page p-4 shadow-xl" role="status" aria-live="polite" x-cloak>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-ink-muted">Pesan pengajar <span x-text="pesan.waktu"></span></p>
                            <p class="mt-1 text-sm font-bold leading-relaxed text-ink" x-text="pesan.teks"></p>
                        </div>
                        <button type="button" class="btn btn-ghost min-h-11 px-3" x-on:click="tutupPesan(pesan.id)" x-bind:aria-label="'Tutup pesan: ' + pesan.teks">Tutup</button>
                    </div>
                </div>
            </template>

            <div
                x-show="clipboardToast"
                x-cloak
                class="rounded-xl border bg-page p-4 shadow-xl"
                x-bind:class="clipboardToastBlocked ? 'border-coral text-coral-deep' : 'border-brand text-ink'"
                role="status"
                aria-live="polite"
            >
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-bold leading-relaxed" x-text="clipboardToast"></p>
                    <button type="button" class="btn btn-ghost min-h-10 px-2" x-on:click="clipboardToast = ''" aria-label="Tutup notifikasi copy dan paste">Tutup</button>
                </div>
            </div>
        </div>

        <div x-show="pengumpulanPaksa" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-ink/90 p-4"
            role="alertdialog" aria-modal="true" aria-labelledby="judul-pengumpulan-paksa">
            <div class="card w-full max-w-md border-2 border-coral bg-page p-6 text-center shadow-2xl sm:p-8">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-coral-soft text-coral-deep">
                    <x-icon name="alert" :size="32" />
                </div>
                <h2 id="judul-pengumpulan-paksa" class="mt-5 text-2xl font-bold text-ink">Pekerjaan dikumpulkan paksa</h2>
                <p class="mt-3 text-base text-ink-muted" x-text="pesanPengumpulanPaksa"></p>
                <p class="mt-4 text-sm font-bold text-coral-deep" aria-live="assertive">Anda akan keluar dari sistem dalam beberapa detik.</p>
            </div>
        </div>

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
                    Saat timer peringatan habis, sistem menerapkan sanksi yang ditetapkan pengajar.
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
                        <p class="mt-1 text-xs text-ink-muted">Pelanggaran sudah dicatat. Saat timer habis, sistem dapat mengurangi waktu atau mengumpulkan pekerjaan.</p>
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
                        x-bind:disabled="mengumpulkan"
                    >
                        Kembali kerjakan
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary flex-1"
                        x-on:click="kumpulkanSekarang()"
                        x-bind:disabled="mengumpulkan || berhenti"
                        x-bind:aria-busy="mengumpulkan"
                    >
                        <span x-show="mengumpulkan" x-cloak class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                        <span x-text="mengumpulkan ? 'Mengumpulkan...' : 'Kumpulkan sekarang'">Kumpulkan sekarang</span>
                    </button>
                </div>
            </div>
        </div>

        <dialog x-ref="dialogPintasan" class="m-auto w-full max-w-md rounded-2xl border border-outline bg-page p-6 text-ink shadow-xl backdrop:bg-ink/50">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-ink">Pintasan keyboard ujian</h2>
                    <p class="mt-1 text-sm text-ink-muted">Aktif saat kursor tidak berada di dalam kolom teks.</p>
                </div>
                <button type="button" class="btn btn-ghost min-h-11 min-w-11 px-3" aria-label="Tutup bantuan pintasan" x-on:click="$refs.dialogPintasan.close()">
                    <x-icon name="x" :size="18" />
                </button>
            </div>
            <dl class="mt-4 space-y-2.5 text-sm">
                <div class="flex items-center justify-between gap-3 border-b border-outline pb-2">
                    <dt class="text-ink-muted">Pilih / centang opsi A sampai F</dt>
                    <dd class="font-mono font-bold text-ink">A - F atau 1 - 6</dd>
                </div>
                <div x-show="!isolasiSoal" class="flex items-center justify-between gap-3 border-b border-outline pb-2">
                    <dt class="text-ink-muted">Soal sebelumnya / berikutnya</dt>
                    <dd class="font-mono font-bold text-ink">&larr; / &rarr;</dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-b border-outline pb-2">
                    <dt class="text-ink-muted">Tandai soal ragu-ragu</dt>
                    <dd class="font-mono font-bold text-ink">R</dd>
                </div>
                <div x-show="pisahKonteksSoal" class="flex items-center justify-between gap-3 border-b border-outline pb-2">
                    <dt class="text-ink-muted">Ganti tab data soal / jawaban</dt>
                    <dd class="font-mono font-bold text-ink">V</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-ink-muted">Buka panduan pintasan ini</dt>
                    <dd class="font-mono font-bold text-ink">?</dd>
                </div>
            </dl>
            <div class="mt-5 flex justify-end">
                <button type="button" class="btn btn-primary min-h-11" x-on:click="$refs.dialogPintasan.close()">Mengerti</button>
            </div>
        </dialog>

        <p class="text-base text-ink-muted">
            Menutup jendela tidak menghentikan waktu. Attempt tetap berjalan sampai batas waktu server.
        </p>
    </div>
</x-layouts.peserta>
