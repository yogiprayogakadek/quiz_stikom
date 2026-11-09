<x-layouts.display :judul="$session->nama">
    <script>
        window.layarDisplay = (config) => ({
            state: null,
            memuat: true,
            error: '',
            sisa: 0,
            offset: 0,
            target: 0,
            audioAktif: true,
            audioCtx: null,
            peringatanList: [],
            peringatanDilihat: {},
            tampilkanSkor: localStorage.getItem('display_tampilkan_skor') !== 'false',
            skorLimit: localStorage.getItem('display_skor_limit') || '5',
            halamanSkor: 1,
            toggleSkor() {
                this.tampilkanSkor = !this.tampilkanSkor;
                localStorage.setItem('display_tampilkan_skor', this.tampilkanSkor);
            },
            setSkorLimit(nilai) {
                this.skorLimit = nilai;
                this.halamanSkor = 1;
                localStorage.setItem('display_skor_limit', nilai);
            },
            get daftarSkor() {
                const data = this.state?.leaderboard ?? this.state?.top5 ?? [];
                return Array.isArray(data) ? data : [];
            },
            get skorPerHalaman() {
                if (this.skorLimit === 'semua') {
                    return Math.max(1, this.daftarSkor.length);
                }
                return Math.max(1, parseInt(this.skorLimit, 10) || 5);
            },
            get totalHalamanSkor() {
                return Math.max(1, Math.ceil(this.daftarSkor.length / this.skorPerHalaman));
            },
            get halamanSkorAktif() {
                return Math.min(Math.max(1, this.halamanSkor), this.totalHalamanSkor);
            },
            get leaderboardHalaman() {
                const mulai = (this.halamanSkorAktif - 1) * this.skorPerHalaman;
                return this.daftarSkor.slice(mulai, mulai + this.skorPerHalaman);
            },
            keHalamanSkor(arah) {
                this.halamanSkor = Math.min(
                    this.totalHalamanSkor,
                    Math.max(1, this.halamanSkorAktif + arah)
                );
            },
            init() {
                this.muat();
                setInterval(() => this.muat(), 3000);
                setInterval(() => this.hitung(), 250);
                this.pasangEcho();

                const unlockAudio = () => {
                    this.initAudio();
                    window.removeEventListener('click', unlockAudio);
                    window.removeEventListener('keydown', unlockAudio);
                };
                window.addEventListener('click', unlockAudio, { passive: true });
                window.addEventListener('keydown', unlockAudio, { passive: true });
            },
            initAudio() {
                if (!this.audioCtx) {
                    const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
                    if (AudioCtxClass) {
                        this.audioCtx = new AudioCtxClass();
                    }
                }
                if (this.audioCtx && this.audioCtx.state === 'suspended') {
                    this.audioCtx.resume();
                }
            },
            toggleAudio() {
                this.initAudio();
                this.audioAktif = !this.audioAktif;
            },
            bunyikanPeringatan(level = 'warning') {
                if (!this.audioAktif) return;
                try {
                    this.initAudio();
                    if (!this.audioCtx) return;

                    const now = this.audioCtx.currentTime;

                    if (level === 'danger') {
                        // Alarm nada bahaya/diskualifikasi: double pulse sawtooth
                        const osc = this.audioCtx.createOscillator();
                        const gain = this.audioCtx.createGain();
                        osc.type = 'sawtooth';
                        osc.frequency.setValueAtTime(880, now);
                        osc.frequency.setValueAtTime(440, now + 0.12);
                        osc.frequency.setValueAtTime(880, now + 0.24);
                        osc.frequency.setValueAtTime(330, now + 0.36);

                        gain.gain.setValueAtTime(0.3, now);
                        gain.gain.exponentialRampToValueAtTime(0.01, now + 0.55);

                        osc.connect(gain);
                        gain.connect(this.audioCtx.destination);
                        osc.start(now);
                        osc.stop(now + 0.55);
                    } else {
                        // Chime nada peringatan: dua nada harmoni lembut (E5 -> B5)
                        const osc = this.audioCtx.createOscillator();
                        const gain = this.audioCtx.createGain();
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(659.25, now);
                        osc.frequency.setValueAtTime(987.77, now + 0.12);

                        gain.gain.setValueAtTime(0.25, now);
                        gain.gain.exponentialRampToValueAtTime(0.01, now + 0.45);

                        osc.connect(gain);
                        gain.connect(this.audioCtx.destination);
                        osc.start(now);
                        osc.stop(now + 0.45);
                    }
                } catch (e) {
                    console.warn('Audio play error:', e);
                }
            },
            tambahPeringatan(item, bunyikan = true) {
                const kunci = item.id
                    ? 'kejadian-' + item.id
                    : [item.tipe, item.username, item.waktu, item.jumlah_pelanggaran].join('|');

                if (this.peringatanDilihat[kunci]) {
                    return;
                }
                this.peringatanDilihat[kunci] = true;

                const data = {
                    id: kunci,
                    nama: item.nama || 'Peserta',
                    username: item.username || '',
                    pesan: item.pesan || 'Aktivitas mencurigakan terdeteksi',
                    level: item.level || 'warning',
                    waktu: item.waktu || new Date().toLocaleTimeString('id-ID', { hour12: false }),
                    jumlah: item.jumlah_pelanggaran || 1,
                    tipe: item.tipe || 'peringatan',
                };

                this.peringatanList.unshift(data);
                if (this.peringatanList.length > 5) {
                    this.peringatanList.pop();
                }

                if (bunyikan) {
                    this.bunyikanPeringatan(data.level);
                }

                setTimeout(() => {
                    this.hapusPeringatan(data.id);
                }, data.level === 'danger' ? 10000 : 7000);
            },
            hapusPeringatan(id) {
                this.peringatanList = this.peringatanList.filter(p => p.id !== id);
            },
            async muat() {
                try {
                    const response = await fetch(config.stateUrl, {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                    });
                    if (!response.ok) {
                        throw new Error('gagal');
                    }
                    this.terapkan(await response.json());
                    this.error = '';
                } catch {
                    this.error = 'Koneksi ke server terputus. Mencoba lagi...';
                } finally {
                    this.memuat = false;
                }
            },
            terapkan(data) {
                const pertamaKali = this.state === null;
                this.state = data;
                this.halamanSkor = Math.min(Math.max(1, this.halamanSkor), this.totalHalamanSkor);
                this.offset = Date.now() - data.server_now;
                this.target = data.fase === 'jeda'
                    ? (data.ends_at ?? 0) + ((data.jeda_detik ?? 0) * 1000)
                    : (data.ends_at ?? 0);
                this.hitung();

                // Sumber cadangan saat websocket terputus: peringatan terbaru
                // ikut terbawa di polling 3 detik. Id mencegah duplikat.
                (data.peringatan ?? []).forEach((item) => this.tambahPeringatan(item, !pertamaKali));
            },
            hitung() {
                if (!this.target) {
                    this.sisa = 0;
                    return;
                }
                this.sisa = Math.max(0, Math.round((this.target - (Date.now() - this.offset)) / 1000));
            },
            format(n) {
                const menit = Math.floor(n / 60);
                const detik = n % 60;
                return menit + ':' + String(detik).padStart(2, '0');
            },
            pasangEcho() {
                const echo = window.buatEcho();
                if (!echo) {
                    return;
                }
                echo.channel(config.channel).listen('.DisplayUpdated', (payload) => this.terapkan(payload));
                echo.channel(config.channel).listen('.PesertaPeringatan', (payload) => this.tambahPeringatan(payload));
            },
            maxHistogram() {
                if (!this.state?.histogram) {
                    return 0;
                }
                return Math.max(1, ...this.state.histogram);
            },
            tinggiHistogram(jumlah) {
                return Math.max(3, Math.round((jumlah / this.maxHistogram()) * 100)) + '%';
            }
        });
    </script>

    <div
        x-data="layarDisplay({ stateUrl: '{{ $stateUrl }}', channel: '{{ $channel }}' })"
        class="min-h-screen px-6 py-8 sm:px-10 lg:px-16"
    >
        <!-- Real-time Warning Toast Overlay -->
        <div class="fixed top-6 right-6 z-50 flex w-96 max-w-[90vw] flex-col gap-3 pointer-events-none">
            <template x-for="item in peringatanList" :key="item.id">
                <div
                    class="pointer-events-auto flex items-start gap-3 rounded-2xl p-4 shadow-modal transition-all transform duration-300"
                    :class="item.level === 'danger'
                        ? 'border-2 border-coral-deep bg-coral-soft text-ink'
                        : 'border-2 border-sun-deep bg-sun-soft text-ink'"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                >
                    <div class="flex-shrink-0 pt-0.5">
                        <template x-if="item.level === 'danger'">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-coral-deep text-white shadow-sm">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </span>
                        </template>
                        <template x-if="item.level !== 'danger'">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-sun-deep text-white shadow-sm">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                        </template>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span
                                class="inline-block rounded-md px-2 py-0.5 text-xs font-bold uppercase tracking-wider"
                                :class="item.level === 'danger' ? 'bg-coral-deep text-white' : 'bg-sun-deep text-white'"
                                x-text="item.level === 'danger' ? 'Kuis Dihentikan' : 'Peringatan'"
                            ></span>
                            <span class="font-mono text-xs text-ink-muted" x-text="item.waktu"></span>
                        </div>

                        <p class="mt-1 font-bold text-ink leading-snug truncate">
                            <span x-text="item.nama"></span>
                            <span class="font-normal text-xs text-ink-muted" x-text="'(' + item.username + ')'"></span>
                        </p>

                        <p class="mt-0.5 text-sm text-ink-muted leading-tight" x-text="item.pesan"></p>

                        <template x-if="item.jumlah > 1">
                            <p class="mt-1 text-xs font-semibold text-coral-deep" x-text="'Pelanggaran ke-' + item.jumlah"></p>
                        </template>
                    </div>

                    <button
                        type="button"
                        @click="hapusPeringatan(item.id)"
                        class="flex-shrink-0 text-ink-muted hover:text-ink transition p-1"
                        title="Tutup notifikasi"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </template>
        </div>

        <header class="mx-auto flex w-full max-w-[1500px] flex-wrap items-center justify-between gap-4 border-b border-outline pb-6">
            <div class="flex items-center gap-3">
                <img src="{{ $appSettings->logoUrl() }}" alt="Logo {{ $appSettings->site_name }}" class="h-12 w-12 object-contain">
                <div>
                    <p class="font-display text-2xl font-bold text-brand-deep">{{ $appSettings->site_name }}</p>
                    <p class="text-base text-ink-muted">Layar kelas</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <!-- Sound Toggle Button -->
                <button
                    type="button"
                    @click="toggleAudio()"
                    class="flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition"
                    :class="audioAktif
                        ? 'border-brand bg-brand-soft/60 text-brand-deep hover:bg-brand-soft'
                        : 'border-outline bg-surface text-ink-muted opacity-70 hover:opacity-100'"
                    :title="audioAktif ? 'Suara notifikasi aktif (klik untuk membisukan)' : 'Suara notifikasi nonaktif (klik untuk mengaktifkan)'"
                >
                    <template x-if="audioAktif">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-brand-deep" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                            </svg>
                            <span>Suara Aktif</span>
                        </span>
                    </template>
                    <template x-if="!audioAktif">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                            </svg>
                            <span>Suara Bisu</span>
                        </span>
                    </template>
                </button>

                <!-- Score Visibility Toggle Button -->
                <button
                    type="button"
                    @click="toggleSkor()"
                    class="flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition"
                    :class="tampilkanSkor
                        ? 'border-brand bg-brand-soft/60 text-brand-deep hover:bg-brand-soft'
                        : 'border-outline bg-surface text-ink-muted opacity-70 hover:opacity-100'"
                    :title="tampilkanSkor ? 'Skor ditampilkan di layar (klik untuk menyembunyikan)' : 'Skor disembunyikan di layar (klik untuk menampilkan)'"
                >
                    <template x-if="tampilkanSkor">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-brand-deep" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>Skor: Tampil</span>
                        </span>
                    </template>
                    <template x-if="!tampilkanSkor">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                            </svg>
                            <span>Skor: Tersembunyi</span>
                        </span>
                    </template>
                </button>

                <label class="flex items-center gap-2 rounded-full border border-outline bg-surface px-4 py-2 text-sm font-semibold text-ink-muted">
                    <span>Skor</span>
                    <select
                        x-model="skorLimit"
                        @change="setSkorLimit(skorLimit)"
                        class="bg-transparent font-bold text-ink focus:outline-none"
                        aria-label="Jumlah skor yang ditampilkan"
                    >
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="semua">Semua</option>
                    </select>
                </label>

                <div class="text-right">
                    <p class="font-display text-2xl font-bold text-ink" x-text="state?.nama ?? 'Memuat...'"></p>
                    <p class="text-base text-ink-muted" x-text="state?.kuis ?? ''"></p>
                </div>
            </div>
        </header>

        <p x-show="error" x-cloak class="mx-auto mt-4 w-full max-w-[1500px] rounded-2xl bg-coral-soft px-4 py-2 text-base font-bold text-coral-deep" x-text="error"></p>

        <main class="mx-auto mt-8 w-full max-w-[1500px]">
            <template x-if="!state">
                <div class="flex min-h-[60vh] items-center justify-center">
                    <p class="text-2xl text-ink-muted">Memuat layar kuis...</p>
                </div>
            </template>

            <template x-if="state && (state.status === 'lobby' || state.status === 'draft')">
                <section class="text-center">
                    <span class="pill pill-brand text-base">Lobby</span>
                    <h1 class="mt-4 text-[56px] leading-tight lg:text-[72px]">Menunggu sesi dimulai</h1>
                    <p class="display-number mt-8 text-[140px] text-brand-deep" x-text="state.total_peserta">0</p>
                    <p class="text-2xl text-ink-muted">peserta sudah bergabung</p>

                    <template x-if="state.peserta && state.peserta.length">
                        <ul class="mx-auto mt-10 flex max-w-5xl flex-wrap justify-center gap-3">
                            <template x-for="nama in state.peserta" :key="nama">
                                <li class="rounded-full bg-surface px-5 py-2 text-xl font-bold text-ink" x-text="nama"></li>
                            </template>
                        </ul>
                    </template>

                    <p class="mt-10 text-xl text-ink-muted">Peserta masuk lewat dashboard masing-masing, lalu menekan Gabung.</p>
                </section>
            </template>

            <template x-if="state && state.status === 'berjalan' && state.mode === 'acak'">
                <section class="grid gap-8 lg:grid-cols-[1.2fr_1fr]">
                    <div class="rounded-panel border border-outline bg-surface p-8 lg:p-12">
                        <p class="pill pill-leaf text-base">Mode Acak berjalan</p>
                        <h1 class="mt-4 text-4xl text-ink">Progres kelas</h1>

                        <div class="mt-8 flex items-end gap-4">
                            <p class="display-number text-[150px] text-ink" x-text="state.selesai">0</p>
                            <p class="display-number mb-6 text-6xl text-ink-faint" x-text="'/' + state.total">/0</p>
                        </div>
                        <p class="text-2xl text-ink-muted">peserta sudah selesai</p>

                        <div class="mt-6">
                            <div class="progress h-4">
                                <span x-bind:style="'width:' + (state.total > 0 ? Math.round(state.selesai / state.total * 100) : 0) + '%'"></span>
                            </div>
                        </div>

                        <div class="mt-8 rounded-2xl bg-page p-5">
                            <p class="text-xl text-ink-muted">Rata-rata soal terjawab per peserta</p>
                            <p class="display-number mt-2 text-6xl text-ink" x-text="state.rata_terjawab">0</p>
                        </div>

                        <p class="mt-6 text-lg text-ink-muted">Skor peserta yang masih mengerjakan tidak ditampilkan di layar ini.</p>
                    </div>

                    <div class="rounded-panel border border-outline bg-page p-8">
                        <h2 class="text-3xl">Papan skor sementara</h2>

                        <template x-if="daftarSkor.length">
                            <ol class="mt-6 max-h-[24rem] space-y-3 overflow-y-auto pr-2">
                                <template x-for="baris in leaderboardHalaman" :key="baris.posisi">
                                    <li class="flex items-center gap-4 rounded-2xl bg-surface px-5 py-4">
                                        <span class="display-number w-10 text-3xl text-brand-deep" x-text="baris.posisi"></span>
                                        <span class="flex-1 text-2xl font-bold text-ink" x-text="baris.nama"></span>
                                        <span
                                            class="font-mono text-2xl font-bold transition-all"
                                            :class="tampilkanSkor ? 'text-ink' : 'text-ink-faint select-none tracking-widest'"
                                            x-text="tampilkanSkor ? baris.skor : '••••'"
                                        ></span>
                                    </li>
                                </template>
                            </ol>
                        </template>

                        <template x-if="daftarSkor.length === 0">
                            <p
                                class="mt-6 text-xl text-ink-muted"
                                x-text="state.leaderboard_live
                                    ? 'Belum ada peserta yang selesai. Leaderboard muncul setelah peserta pertama menyelesaikan semua soal.'
                                    : 'Leaderboard live dimatikan untuk sesi ini. Skor tetap dihitung dan tersedia di laporan akhir.'"
                            ></p>
                        </template>

                        <div x-show="totalHalamanSkor > 1" x-cloak class="mt-4 flex items-center justify-between gap-3 rounded-2xl bg-surface px-4 py-3">
                            <button type="button" @click="keHalamanSkor(-1)" class="rounded-xl border border-outline px-3 py-2 text-sm font-bold text-ink disabled:opacity-40" :disabled="halamanSkorAktif <= 1">Sebelumnya</button>
                            <span class="text-sm font-bold text-ink-muted" x-text="'Halaman ' + halamanSkorAktif + ' dari ' + totalHalamanSkor"></span>
                            <button type="button" @click="keHalamanSkor(1)" class="rounded-xl border border-outline px-3 py-2 text-sm font-bold text-ink disabled:opacity-40" :disabled="halamanSkorAktif >= totalHalamanSkor">Berikutnya</button>
                        </div>
                    </div>
                </section>
            </template>

            <template x-if="state && state.status === 'berjalan' && state.mode === 'serempak' && state.fase === 'soal_terbuka'">
                <section class="text-center">
                    <p class="pill pill-leaf text-base">Mode Serempak</p>
                    <h1 class="mt-4 text-5xl text-ink">Soal <span x-text="state.urutan"></span> dari <span x-text="state.total_soal"></span></h1>

                    <p class="timer mt-10 text-[170px] leading-none" x-bind:class="sisa <= 10 ? 'text-coral-deep' : 'text-brand-deep'" x-text="format(sisa)">0:00</p>

                    <div class="mx-auto mt-10 max-w-3xl">
                        <p class="display-number text-7xl text-ink"><span x-text="state.sudah_menjawab"></span> <span class="text-ink-faint">/</span> <span x-text="state.total"></span></p>
                        <p class="mt-2 text-2xl text-ink-muted">peserta sudah menjawab</p>
                        <div class="progress mt-5 h-4">
                            <span x-bind:style="'width:' + (state.total > 0 ? Math.round(state.sudah_menjawab / state.total * 100) : 0) + '%'"></span>
                        </div>
                    </div>

                    <p class="mt-10 text-xl text-ink-muted">Jawaban benar baru ditampilkan setelah soal ditutup.</p>
                </section>
            </template>

            <template x-if="state && state.status === 'berjalan' && state.mode === 'serempak' && state.fase === 'jeda'">
                <section class="grid gap-8 lg:grid-cols-[1.15fr_1fr]">
                    <div class="rounded-panel border border-outline bg-page p-8 lg:p-12">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span class="pill pill-sun text-base">Jeda</span>
                            <p class="text-xl text-ink-muted">Soal berikutnya dalam <span class="timer text-ink" x-text="format(sisa)"></span></p>
                        </div>

                        <h1 class="mt-5 text-4xl text-ink">Jawaban benar</h1>
                        <p class="mt-3 text-3xl font-bold text-leaf-deep" x-text="state.jawaban_benar || '-'"></p>

                        <template x-if="state.pembahasan">
                            <div class="mt-6 rounded-2xl border border-outline bg-surface p-5">
                                <p class="text-lg font-bold text-ink-muted">Pembahasan</p>
                                <p class="mt-1 text-2xl text-ink" x-text="state.pembahasan"></p>
                            </div>
                        </template>

                        <h2 class="mt-8 text-2xl text-ink">Sebaran jawaban</h2>
                        <template x-if="state.distribusi && state.distribusi.length">
                            <ul class="mt-4 space-y-3">
                                <template x-for="baris in state.distribusi" :key="baris.teks">
                                    <li>
                                        <div class="flex items-center justify-between gap-3 text-xl">
                                            <span class="text-ink" x-text="baris.teks"></span>
                                            <span class="font-mono font-bold text-ink" x-text="baris.jumlah"></span>
                                        </div>
                                        <div class="mt-1 h-3 overflow-hidden rounded-full bg-surface-sunken">
                                            <span
                                                class="block h-full rounded-full bg-brand"
                                                x-bind:style="'width:' + (state.total > 0 ? Math.round(baris.jumlah / state.total * 100) : 0) + '%'"
                                            ></span>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </template>
                    </div>

                    <div class="rounded-panel border border-outline bg-surface p-8">
                        <h2 class="text-3xl">Papan skor sementara</h2>
                        <template x-if="daftarSkor.length">
                            <ol class="mt-5 max-h-[24rem] space-y-3 overflow-y-auto pr-2">
                                <template x-for="baris in leaderboardHalaman" :key="baris.posisi">
                                    <li class="flex items-center gap-4 rounded-2xl bg-page px-5 py-4">
                                        <span class="display-number w-10 text-3xl text-sun-deep" x-text="baris.posisi"></span>
                                        <span class="flex-1 text-2xl font-bold text-ink" x-text="baris.nama"></span>
                                        <span
                                            class="font-mono text-2xl font-bold transition-all"
                                            :class="tampilkanSkor ? 'text-ink' : 'text-ink-faint select-none tracking-widest'"
                                            x-text="tampilkanSkor ? baris.skor : '••••'"
                                        ></span>
                                    </li>
                                </template>
                            </ol>
                        </template>
                        <template x-if="daftarSkor.length === 0">
                            <p
                                class="mt-5 text-xl text-ink-muted"
                                x-text="state.leaderboard_live
                                    ? 'Belum ada skor yang bisa ditampilkan.'
                                    : 'Leaderboard live dimatikan untuk sesi ini. Skor tetap dihitung dan tersedia di laporan akhir.'"
                            ></p>
                        </template>

                        <div x-show="totalHalamanSkor > 1" x-cloak class="mt-4 flex items-center justify-between gap-3 rounded-2xl bg-page px-4 py-3">
                            <button type="button" @click="keHalamanSkor(-1)" class="rounded-xl border border-outline px-3 py-2 text-sm font-bold text-ink disabled:opacity-40" :disabled="halamanSkorAktif <= 1">Sebelumnya</button>
                            <span class="text-sm font-bold text-ink-muted" x-text="'Halaman ' + halamanSkorAktif + ' dari ' + totalHalamanSkor"></span>
                            <button type="button" @click="keHalamanSkor(1)" class="rounded-xl border border-outline px-3 py-2 text-sm font-bold text-ink disabled:opacity-40" :disabled="halamanSkorAktif >= totalHalamanSkor">Berikutnya</button>
                        </div>
                    </div>
                </section>
            </template>

            <template x-if="state && state.status === 'selesai'">
                <section class="space-y-8">
                    <div class="text-center">
                        <span class="pill pill-sun text-base">Sesi selesai</span>
                        <h1 class="mt-3 text-5xl text-ink">Hasil akhir</h1>
                    </div>

                    <div class="grid gap-8 lg:grid-cols-[1.15fr_1fr]">
                        <div class="rounded-panel border border-outline bg-page p-8">
                            <h2 class="text-3xl">Leaderboard</h2>
                            <template x-if="daftarSkor.length">
                                <ol class="mt-5 max-h-[28rem] space-y-2 overflow-y-auto pr-2">
                                    <template x-for="baris in leaderboardHalaman" :key="baris.posisi">
                                        <li class="flex items-center gap-4 rounded-2xl bg-surface px-5 py-3">
                                            <span class="display-number w-10 text-2xl text-brand-deep" x-text="baris.posisi"></span>
                                            <span class="flex-1 text-xl font-bold text-ink" x-text="baris.nama"></span>
                                            <span
                                                class="font-mono text-xl font-bold transition-all"
                                                :class="tampilkanSkor ? 'text-ink' : 'text-ink-faint select-none tracking-widest'"
                                                x-text="tampilkanSkor ? baris.skor : '••••'"
                                            ></span>
                                        </li>
                                    </template>
                                </ol>
                            </template>
                            <template x-if="daftarSkor.length === 0">
                                <p class="mt-5 text-xl text-ink-muted">Tidak ada peserta yang menyelesaikan sesi ini.</p>
                            </template>
                            <div x-show="totalHalamanSkor > 1" x-cloak class="mt-4 flex items-center justify-between gap-3 rounded-2xl bg-surface px-4 py-3">
                                <button type="button" @click="keHalamanSkor(-1)" class="rounded-xl border border-outline px-3 py-2 text-sm font-bold text-ink disabled:opacity-40" :disabled="halamanSkorAktif <= 1">Sebelumnya</button>
                                <span class="text-sm font-bold text-ink-muted" x-text="'Halaman ' + halamanSkorAktif + ' dari ' + totalHalamanSkor"></span>
                                <button type="button" @click="keHalamanSkor(1)" class="rounded-xl border border-outline px-3 py-2 text-sm font-bold text-ink disabled:opacity-40" :disabled="halamanSkorAktif >= totalHalamanSkor">Berikutnya</button>
                            </div>
                        </div>

                        <div class="rounded-panel border border-outline bg-surface p-8">
                            <h2 class="text-3xl">Sebaran nilai</h2>
                            <div class="mt-6 flex h-64 items-end gap-2">
                                <template x-for="(jumlah, indeks) in state.histogram" :key="indeks">
                                    <div class="flex flex-1 flex-col items-center justify-end gap-2">
                                        <span class="font-mono text-base font-bold text-ink" x-text="jumlah"></span>
                                        <div class="w-full rounded-t-lg bg-brand" x-bind:style="'height:' + tinggiHistogram(jumlah)"></div>
                                        <span class="font-mono text-xs text-ink-muted" x-text="indeks * 10"></span>
                                    </div>
                                </template>
                            </div>
                            <p class="mt-3 text-base text-ink-muted">Kelompok nilai 0 sampai 100. Angka di atas batang adalah jumlah peserta.</p>
                        </div>
                    </div>

                    <div class="rounded-panel border border-outline bg-page p-8">
                        <h2 class="text-3xl">Lima soal tersulit</h2>
                        <template x-if="state.soal_terendah && state.soal_terendah.length">
                            <ul class="mt-5 space-y-4">
                                <template x-for="soal in state.soal_terendah" :key="soal.teks">
                                    <li>
                                        <div class="flex items-start justify-between gap-4">
                                            <p class="text-xl font-bold text-ink" x-text="soal.teks"></p>
                                            <p class="font-mono text-xl font-bold text-ink" x-text="soal.persen + '%'"></p>
                                        </div>
                                        <div class="progress mt-2">
                                            <span x-bind:style="'width:' + soal.persen + '%'"></span>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </template>
                        <template x-if="!state.soal_terendah || state.soal_terendah.length === 0">
                            <p class="mt-5 text-xl text-ink-muted">Belum ada data jawaban per soal.</p>
                        </template>
                    </div>
                </section>
            </template>
        </main>
    </div>
</x-layouts.display>
