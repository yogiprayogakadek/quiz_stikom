<x-layouts.peserta :canvas="true" :tour-baru="$tourBaru" :judul="'Halo, '.auth()->user()->nama" keterangan="Pilih sesi yang sedang dibuka admin, lalu kerjakan soal sampai selesai.">
    <script>
        window.dashboardPeserta = (config) => ({
            sesiList: config.initialSesi || [],
            csrfToken: config.csrfToken || '',
            notifPesan: '',
            audioCtx: null,
            cari: '',

            get filteredSesiList() {
                if (!this.cari.trim()) return this.sesiList;
                const q = this.cari.toLowerCase();
                return this.sesiList.filter(s =>
                    (s.nama && s.nama.toLowerCase().includes(q)) ||
                    (s.quiz_judul && s.quiz_judul.toLowerCase().includes(q)) ||
                    (s.kelas_nama && s.kelas_nama.toLowerCase().includes(q))
                );
            },

            init() {
                this.pasangEcho();
                // Polling latar belakang berkala (setiap 3 detik) sebagai jaminan cadangan
                setInterval(() => this.muat(), 3000);

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

            bunyikanChime() {
                try {
                    this.initAudio();
                    if (!this.audioCtx) return;
                    const now = this.audioCtx.currentTime;
                    const osc = this.audioCtx.createOscillator();
                    const gain = this.audioCtx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(523.25, now); // C5
                    osc.frequency.setValueAtTime(659.25, now + 0.12); // E5
                    osc.frequency.setValueAtTime(783.99, now + 0.24); // G5
                    gain.gain.setValueAtTime(0.2, now);
                    gain.gain.exponentialRampToValueAtTime(0.01, now + 0.5);
                    osc.connect(gain);
                    gain.connect(this.audioCtx.destination);
                    osc.start(now);
                    osc.stop(now + 0.5);
                } catch (e) {
                    console.warn(e);
                }
            },

            pasangEcho() {
                const echo = window.buatEcho();
                if (!echo) return;

                echo.channel('quiz-sessions')
                    .listen('.SesiStatusUpdated', (data) => {
                        this.muat(data);
                    });
            },

            async muat(eventData = null) {
                try {
                    const res = await window.kuisFetch(config.stateUrl);
                    if (res.ok && res.data) {
                        const listLama = this.sesiList;
                        const listBaru = res.data.sesi_tersedia;

                        // Deteksi sesi baru yang dibuka di lobby
                        if (listBaru.length > listLama.length && listLama.length === 0) {
                            this.notifPesan = 'Sesi baru telah dibuka oleh admin! Silakan klik Gabung sesi.';
                            this.bunyikanChime();
                        } else if (eventData && eventData.status === 'berjalan') {
                            this.notifPesan = 'Sesi "' + eventData.nama + '" sudah dimulai! Silakan mulai mengerjakan.';
                            this.bunyikanChime();
                        } else {
                            // Cek jika ada sesi yang statusnya berubah dari lobby ke berjalan
                            for (const sesiBaru of listBaru) {
                                const sesiLama = listLama.find(s => s.id === sesiBaru.id);
                                if (sesiLama && sesiLama.status === 'lobby' && sesiBaru.status === 'berjalan') {
                                    this.notifPesan = 'Sesi "' + sesiBaru.nama + '" sudah dimulai! Silakan mulai mengerjakan.';
                                    this.bunyikanChime();
                                    break;
                                }
                            }
                        }

                        this.sesiList = listBaru;
                    }
                } catch (e) {
                    console.warn('Gagal memuat status sesi:', e);
                }
            }
        });
    </script>

    <div
        x-data="dashboardPeserta({
            stateUrl: '{{ route('peserta.dashboard.state') }}',
            initialSesi: {{ json_encode($sesiList) }},
            csrfToken: '{{ csrf_token() }}'
        })"
        class="grid grid-cols-1 xl:grid-cols-12 gap-7 items-start w-full"
    >
        {{-- CENTER FEED COLUMN (8 Columns on xl) --}}
        <div class="xl:col-span-8 space-y-7 min-w-0">
            @if ($tampilkanFeedback && $attemptFeedback)
                @include('peserta._feedback', ['attempt' => $attemptFeedback])
            @endif

            {{-- Top Bar: Search, Quick Filter & Hamburger Toggle --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3.5">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    {{-- Hamburger button desktop --}}
                    <button
                        type="button"
                        @click="toggleSidebar()"
                        data-tour="menu-utama"
                        class="hidden lg:inline-flex items-center justify-center h-11 w-11 rounded-2xl bg-white border border-slate-200/90 text-slate-600 hover:text-slate-900 hover:bg-slate-50 shadow-xs transition shrink-0"
                        :title="sidebarCollapsed ? 'Perbesar Menu' : 'Perkecil Menu'"
                        :aria-label="sidebarCollapsed ? 'Perbesar Menu' : 'Perkecil Menu'"
                    >
                        <x-icon name="menu" :size="20" />
                    </button>

                    {{-- Hamburger button mobile --}}
                    <button
                        type="button"
                        @click="mobileNav = true"
                        data-tour="menu-utama"
                        class="lg:hidden inline-flex items-center justify-center h-11 w-11 rounded-2xl bg-white border border-slate-200/90 text-slate-600 hover:text-slate-900 hover:bg-slate-50 shadow-xs transition shrink-0"
                        aria-label="Buka menu"
                    >
                        <x-icon name="menu" :size="20" />
                    </button>

                    <div class="relative flex-1 min-w-0" data-tour="cari">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-slate-400">
                            <x-icon name="search" :size="18" />
                        </span>
                        <input
                            type="text"
                            x-model="cari"
                            placeholder="Cari kuis, materi ujian, atau kelas..."
                            class="w-full pl-11 pr-10 py-2.5 sm:py-3 rounded-2xl bg-white border border-slate-200/90 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 shadow-xs transition"
                        >
                        {{-- Tombol Bersihkan / Reset --}}
                        <button
                            type="button"
                            x-show="cari"
                            @click="cari = ''"
                            class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 transition"
                            title="Bersihkan pencarian"
                        >
                            <span class="text-lg font-bold leading-none">&times;</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-end sm:self-auto shrink-0">
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-2xl bg-white border border-slate-200/90 text-xs font-bold text-slate-700 shadow-xs">
                        <span class="h-2 w-2 rounded-full bg-blue-600"></span>
                        <span x-text="sesiList.length">0</span> Sesi Buka
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-2xl bg-white border border-slate-200/90 text-xs font-bold text-slate-700 shadow-xs">
                        <x-icon name="kelas" :size="14" class="text-blue-600" />
                        {{ $kelasSaya->count() }} Kelas
                    </span>
                </div>
            </div>

            {{-- Real-time Alert Notification Banner --}}
            <div
                x-show="notifPesan"
                x-cloak
                x-transition
                class="rounded-2xl border border-blue-200 bg-blue-50/90 p-4 shadow-xs flex items-center justify-between gap-3"
                role="status"
            >
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-600 text-white shadow-2xs">
                        <x-icon name="check" :size="16" />
                    </span>
                    <p class="text-sm font-bold text-blue-950" x-text="notifPesan"></p>
                </div>
                <button type="button" @click="notifPesan = ''" class="text-xs font-extrabold text-blue-700 hover:text-blue-900 px-3 py-1 rounded-lg hover:bg-blue-100/60 transition">
                    Tutup
                </button>
            </div>

            {{-- HERO BANNER (Option A: Modern Dark Slate / High-Contrast Deep Navy) --}}
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-950 via-slate-900 to-[#0b1b36] border border-slate-800/80 p-6 sm:p-8 lg:p-9 shadow-sm" data-tour="hero">
                {{-- Ambient mesh glow --}}
                <div class="absolute -right-10 -top-10 h-72 w-72 rounded-full bg-blue-500/15 blur-3xl pointer-events-none"></div>
                <div class="absolute -left-12 -bottom-12 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="max-w-xl">
                        {{-- Status Pill --}}
                        <div class="inline-flex items-center gap-2 rounded-full bg-white/10 backdrop-blur-md px-3.5 py-1 text-xs font-semibold text-blue-200 border border-white/15 shadow-2xs mb-4">
                            <span class="h-2 w-2 rounded-full bg-blue-400 animate-pulse"></span>
                            <span>{{ $appSettings->site_tagline }}</span>
                        </div>

                        {{-- Headline --}}
                        <h1 class="font-display text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-white leading-tight">
                            Selamat Datang, <br class="hidden sm:inline" />
                            <span class="text-blue-400">{{ auth()->user()->nama }}</span>
                        </h1>
                        <p class="mt-2.5 text-sm sm:text-base text-slate-300 font-normal leading-relaxed">
                            Akses kuis kelas dan selesaikan ujian tepat waktu untuk raih peringkat terbaik hari ini.
                        </p>

                        {{-- CTA Actions --}}
                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <a
                                href="#sesi-tersedia"
                                class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold shadow-xs transition active:scale-[0.98]"
                            >
                                <span>Mulai Kuis</span>
                                <x-icon name="play" :size="16" />
                            </a>
                            <a
                                href="{{ route('peserta.kelas.index') }}"
                                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white/10 hover:bg-white/15 text-slate-200 hover:text-white text-sm font-semibold border border-white/15 backdrop-blur-md transition"
                            >
                                <x-icon name="kelas" :size="16" />
                                <span>Pilih Kelas</span>
                            </a>
                            <button
                                type="button"
                                @click="window.mulaiTourPeserta?.()"
                                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white/10 hover:bg-white/15 text-slate-200 hover:text-white text-sm font-semibold border border-white/15 backdrop-blur-md transition"
                            >
                                <x-icon name="book" :size="16" />
                                <span>Panduan</span>
                            </button>
                        </div>
                    </div>

                    {{-- Visual Card: Crisp framed illustration --}}
                    <div class="relative flex-shrink-0 self-center md:self-auto">
                        <div class="relative h-44 w-44 sm:h-48 sm:w-48 rounded-2xl overflow-hidden bg-slate-800/80 p-2 border border-slate-700/70 shadow-md">
                            <img
                                src="{{ asset('images/login-illustration.png') }}"
                                alt="Ilustrasi Peserta Ujian"
                                class="h-full w-full object-cover rounded-xl select-none pointer-events-none"
                            >
                        </div>
                        <div class="absolute -bottom-2 -left-2 bg-slate-900/95 backdrop-blur-md rounded-xl px-3 py-1.5 shadow-sm border border-slate-700/80 flex items-center gap-2">
                            <x-icon name="shield-check" :size="16" class="text-blue-400" />
                            <span class="text-xs font-semibold text-slate-200">PIN Terverifikasi</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- CONTINUE LEARNING / SESI DIBUKA --}}
            <section id="sesi-tersedia" aria-labelledby="sesi-tersedia-heading">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 id="sesi-tersedia-heading" class="font-display text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
                        Sesi yang dibuka
                    </h2>
                    <span class="text-xs font-bold px-3 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200/60">
                        <span x-text="sesiList.length">0</span> Sesi Aktif
                    </span>
                </div>

                {{-- Tampilan Kosong (Jika belum ada sesi) --}}
                <template x-if="sesiList.length === 0">
                    <div class="rounded-2xl bg-white border border-slate-200/80 p-8 text-center shadow-xs">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 mb-3">
                            <x-icon name="clock" :size="28" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-900">Belum ada sesi yang dibuka</h3>
                        <p class="mt-1 text-sm text-slate-500 max-w-md mx-auto">
                            Tunggu admin membuka lobby. Sesi kuis akan otomatis tampil di layar ini secara langsung tanpa perlu merefresh halaman.
                        </p>
                    </div>
                </template>

                {{-- Horizontal Session Cards (Real-time Live) --}}
                <div x-show="sesiList.length > 0" class="space-y-4">
                    <template x-if="filteredSesiList.length === 0">
                        <div class="rounded-2xl bg-white border border-dashed border-slate-200 p-8 text-center text-slate-500 shadow-xs text-sm">
                            Tidak ada sesi yang cocok dengan kata kunci "<span class="font-bold text-slate-800" x-text="cari"></span>".
                        </div>
                    </template>

                    <template x-for="sesi in filteredSesiList" :key="sesi.id">
                        <article class="group rounded-2xl bg-white border border-slate-200/80 p-5 sm:p-6 shadow-xs hover:border-slate-300 transition-all flex flex-col md:flex-row md:items-center justify-between gap-5">
                            <div class="flex items-start gap-4 min-w-0">
                                {{-- Thumbnail Box --}}
                                <div
                                    class="flex h-14 w-14 sm:h-16 sm:w-16 shrink-0 items-center justify-center rounded-2xl p-3 shadow-xs"
                                    :class="sesi.mode === 'serempak' ? 'bg-indigo-50 text-indigo-600 ring-1 ring-indigo-200/60' : 'bg-blue-50 text-blue-600 ring-1 ring-blue-200/60'"
                                >
                                    <template x-if="sesi.mode === 'serempak'">
                                        <x-icon name="zap" :size="28" />
                                    </template>
                                    <template x-if="sesi.mode !== 'serempak'">
                                        <x-icon name="book" :size="28" />
                                    </template>
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                        <span
                                            class="px-2.5 py-0.5 rounded-lg text-xs font-extrabold uppercase tracking-wide"
                                            :class="sesi.mode === 'serempak' ? 'bg-indigo-100 text-indigo-800' : 'bg-blue-100 text-blue-800'"
                                            x-text="sesi.mode === 'serempak' ? 'Mode Serempak' : 'Mode Acak'"
                                        ></span>
                                        <span
                                            class="px-2.5 py-0.5 rounded-lg text-xs font-bold"
                                            :class="sesi.status === 'berjalan' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-amber-50 text-amber-700 border border-amber-200'"
                                            x-text="sesi.status === 'berjalan' ? 'Sedang Berjalan' : 'Lobby Menunggu'"
                                        ></span>
                                        <template x-if="sesi.kelas_nama">
                                            <span class="px-2.5 py-0.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-700" x-text="sesi.kelas_nama"></span>
                                        </template>
                                    </div>

                                    <h3 class="text-lg sm:text-xl font-extrabold text-slate-900 truncate" x-text="sesi.nama"></h3>
                                    <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                                        <span x-text="sesi.quiz_judul"></span> · <span x-text="sesi.jumlah_soal"></span> soal ·
                                        <span x-text="sesi.max_peserta !== null ? (sesi.attempts_count + '/' + sesi.max_peserta + ' peserta') : (sesi.attempts_count + ' peserta')"></span>
                                    </p>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-3 self-end md:self-center shrink-0">
                                {{-- Belum Bergabung --}}
                                <template x-if="!sesi.attempt">
                                    <div>
                                        <template x-if="sesi.mode === 'serempak' && sesi.status === 'berjalan'">
                                            <span class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-2 rounded-xl">Sesi Ditutup</span>
                                        </template>
                                        <template x-if="!(sesi.mode === 'serempak' && sesi.status === 'berjalan') && sesi.penuh">
                                            <span class="text-xs font-bold text-rose-600 bg-rose-50 px-3 py-2 rounded-xl">Kuota Penuh</span>
                                        </template>
                                        <template x-if="!(sesi.mode === 'serempak' && sesi.status === 'berjalan') && !sesi.penuh">
                                            <form method="POST" :action="sesi.urls.join">
                                                <input type="hidden" name="_token" :value="csrfToken">
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow-xs transition active:scale-[0.98]"
                                                >
                                                    <span>Gabung sesi</span>
                                                    <x-icon name="play" :size="16" />
                                                </button>
                                            </form>
                                        </template>
                                    </div>
                                </template>

                                {{-- Sudah Bergabung --}}
                                <template x-if="sesi.attempt">
                                    <div class="flex items-center gap-2">
                                        <template x-if="sesi.mode === 'acak' && sesi.attempt.status === 'menunggu'">
                                            <form method="POST" :action="sesi.urls.start">
                                                <input type="hidden" name="_token" :value="csrfToken">
                                                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow-xs transition">
                                                    <span>Mulai Ujian</span>
                                                    <x-icon name="play" :size="16" />
                                                </button>
                                            </form>
                                        </template>
                                        <template x-if="sesi.mode === 'acak' && sesi.attempt.status === 'berjalan'">
                                            <a :href="sesi.urls.show" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow-xs transition">
                                                <span>Lanjut Soal</span>
                                                <x-icon name="play" :size="16" />
                                            </a>
                                        </template>
                                        <template x-if="sesi.mode === 'serempak' && sesi.attempt.status !== 'selesai'">
                                            <a :href="sesi.urls.play" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-sm font-bold shadow-xs transition">
                                                <span>Ruang Serempak</span>
                                                <x-icon name="play" :size="16" />
                                            </a>
                                        </template>
                                        <template x-if="sesi.attempt.status === 'selesai'">
                                            <a :href="sesi.urls.result" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-sm font-bold transition">
                                                <span>Lihat Skor</span>
                                                <x-icon name="chart" :size="16" />
                                            </a>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </article>
                    </template>
                </div>
            </section>

            {{-- 4 MINI BENTO CARDS --}}
            <section aria-labelledby="fitur-kuis-heading">
                <h2 id="fitur-kuis-heading" class="font-display text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight mb-4">
                    Kategori & Fitur Kuis
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
                    {{-- Card 1: Pilihan Ganda --}}
                    <div class="rounded-2xl bg-[#f3e8ff]/70 border border-purple-200/80 p-4 sm:p-5 shadow-xs flex flex-col justify-between hover:bg-[#f3e8ff] transition">
                        <div class="flex items-center justify-between">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-purple-500/20 text-purple-700">
                                <x-icon name="book" :size="20" />
                            </span>
                            <span class="text-xs font-extrabold text-purple-700">&rarr;</span>
                        </div>
                        <div class="mt-4">
                            <h3 class="font-bold text-sm sm:text-base text-purple-950">Pilihan Ganda</h3>
                            <p class="text-[11px] text-purple-800/80 font-medium mt-0.5">Otomatis dinilai</p>
                        </div>
                    </div>

                    {{-- Card 2: Soal Essay --}}
                    <div class="rounded-2xl bg-[#ffe4e6]/70 border border-rose-200/80 p-4 sm:p-5 shadow-xs flex flex-col justify-between hover:bg-[#ffe4e6] transition">
                        <div class="flex items-center justify-between">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-500/20 text-rose-700">
                                <x-icon name="pencil" :size="20" />
                            </span>
                            <span class="text-xs font-extrabold text-rose-700">&rarr;</span>
                        </div>
                        <div class="mt-4">
                            <h3 class="font-bold text-sm sm:text-base text-rose-950">Soal Essay</h3>
                            <p class="text-[11px] text-rose-800/80 font-medium mt-0.5">Uraian & Review</p>
                        </div>
                    </div>

                    {{-- Card 3: Mode Serempak (Sky/Cyan Theme) --}}
                    <div class="rounded-2xl bg-sky-50 border border-sky-200/80 p-4 sm:p-5 shadow-xs flex flex-col justify-between hover:bg-sky-100/70 transition">
                        <div class="flex items-center justify-between">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-500/20 text-sky-700">
                                <x-icon name="zap" :size="20" />
                            </span>
                            <span class="text-xs font-extrabold text-sky-700">&rarr;</span>
                        </div>
                        <div class="mt-4">
                            <h3 class="font-bold text-sm sm:text-base text-sky-950">Mode Serempak</h3>
                            <p class="text-[11px] text-sky-800/80 font-medium mt-0.5">Waktu bersama</p>
                        </div>
                    </div>

                    {{-- Card 4: Mode Acak --}}
                    <div class="rounded-2xl bg-[#fef3c7]/70 border border-amber-200/80 p-4 sm:p-5 shadow-xs flex flex-col justify-between hover:bg-[#fef3c7] transition">
                        <div class="flex items-center justify-between">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-500/20 text-amber-700">
                                <x-icon name="clock" :size="20" />
                            </span>
                            <span class="text-xs font-extrabold text-amber-700">&rarr;</span>
                        </div>
                        <div class="mt-4">
                            <h3 class="font-bold text-sm sm:text-base text-amber-950">Mode Acak</h3>
                            <p class="text-[11px] text-amber-800/80 font-medium mt-0.5">Urutan mandiri</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- KELAS SAYA SECTION --}}
            <section aria-labelledby="kelas-saya-heading">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 id="kelas-saya-heading" class="font-display text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
                        Kelas saya
                    </h2>
                    <a href="{{ route('peserta.kelas.index') }}" class="text-xs sm:text-sm font-extrabold text-blue-600 hover:text-blue-800 hover:underline">
                        Semua kelas &rarr;
                    </a>
                </div>

                @if ($kelasSaya->isEmpty())
                    <div class="rounded-2xl bg-white border border-slate-200/80 p-6 text-center shadow-xs">
                        <p class="text-sm text-slate-600">
                            Belum ada kelas yang diikuti. Buka <a href="{{ route('peserta.kelas.index') }}" class="font-bold text-blue-600 underline">daftar kelas</a> dan masukkan PIN dari admin.
                        </p>
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($kelasSaya as $kelas)
                            <article
                                x-show="!cari || '{{ strtolower(addslashes($kelas->nama . ' ' . $kelas->kode)) }}'.includes(cari.toLowerCase().trim())"
                                class="group flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs transition-all hover:border-slate-300"
                            >
                                <x-class-cover :kelas="$kelas" :show-title="true" />
                                <div class="p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div>
                                            <p class="text-xs text-slate-500 mt-0.5">
                                                {{ $kelas->quizzes_count }} kuis · {{ $kelas->sessions_count }} sesi
                                            </p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-bold bg-slate-100 text-slate-700">
                                        Aktif
                                    </span>
                                </div>
                                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                                    <span class="text-xs font-medium text-slate-400">PIN Terhubung</span>
                                    <a
                                        href="{{ route('peserta.kelas.show', $kelas) }}"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-extrabold transition"
                                    >
                                        <span>Masuk kelas</span>
                                        <span>&rarr;</span>
                                    </a>
                                </div>
                                </div>
                            </article>
                        @endforeach

                        <div
                            x-show="cari && !{{ json_encode($kelasSaya->pluck('nama')->merge($kelasSaya->pluck('kode'))->map(fn($v) => strtolower($v))->all()) }}.some(k => k.includes(cari.toLowerCase().trim()))"
                            class="rounded-2xl bg-white border border-dashed border-slate-200 p-6 text-center text-xs text-slate-500 sm:col-span-2"
                        >
                            Tidak ada kelas saya yang cocok dengan kata kunci "<span class="font-bold text-slate-700" x-text="cari"></span>".
                        </div>
                    </div>
                @endif
            </section>
        </div>

        {{-- RIGHT COLUMN WIDGETS (4 Columns on xl, Sticky on Desktop) --}}
        <div class="xl:col-span-4 space-y-6 xl:sticky xl:top-8">
            {{-- Top Stats Counters (Streak & Poin Gems) --}}
            <div class="flex items-center justify-between gap-3">
                <div class="flex-1 rounded-2xl bg-white border border-slate-200/80 p-3.5 shadow-xs flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-orange-100 text-orange-600">
                        <x-icon name="flame" :size="20" />
                    </span>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Streak Hari</p>
                        <p class="text-base font-extrabold text-slate-900">{{ $stats['streak'] }} Hari</p>
                    </div>
                </div>

                <div class="flex-1 rounded-2xl bg-white border border-slate-200/80 p-3.5 shadow-xs flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-100 text-blue-600">
                        <x-icon name="diamond" :size="20" />
                    </span>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Nilai</p>
                        <p class="text-base font-extrabold text-slate-900">{{ $stats['total_skor'] }}</p>
                    </div>
                </div>
            </div>

            {{-- WIDGET 1: TARGET HARIAN (Donut SVG) --}}
            <div class="rounded-2xl bg-white border border-slate-200/80 p-6 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-display text-base font-extrabold text-slate-900">Target Hari Ini</h3>
                    <span class="text-xs font-bold text-slate-400">Minggu Ini</span>
                </div>

                <div class="flex items-center gap-5">
                    @php
                        $target = $stats['target_harian'];
                        $selesai = min($target, $stats['selesai']);
                        $percent = round(($selesai / max(1, $target)) * 100);
                        $circumference = 2 * 3.14159 * 36;
                        $dashoffset = $circumference - ($circumference * $percent / 100);
                    @endphp
                    <div class="relative h-24 w-24 flex-shrink-0">
                        <svg class="h-full w-full -rotate-90" viewBox="0 0 88 88">
                            <circle cx="44" cy="44" r="36" class="text-slate-100" stroke-width="8" stroke="currentColor" fill="transparent" />
                            <circle
                                cx="44"
                                cy="44"
                                r="36"
                                class="text-blue-600 transition-all duration-1000 ease-out"
                                stroke-width="8"
                                stroke-dasharray="{{ $circumference }}"
                                stroke-dashoffset="{{ $dashoffset }}"
                                stroke-linecap="round"
                                stroke="currentColor"
                                fill="transparent"
                            />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                            <x-icon name="flame" :size="16" class="text-orange-500 mb-0.5" />
                            <span class="text-xs font-extrabold text-slate-900 leading-none">{{ $selesai }}/{{ $target }}</span>
                            <span class="text-[9px] font-bold text-slate-400">Kuis</span>
                        </div>
                    </div>

                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-slate-800">Hebat, Pertahankan!</p>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            {{ $stats['selesai'] > 0 ? 'Kamu sudah menyelesaikan kuis hari ini.' : 'Belum ada kuis selesai hari ini. Selesaikan 1 kuis untuk streak!' }}
                        </p>
                    </div>
                </div>

                {{-- Weekday Checklist (Mon-Fri) --}}
                <div class="mt-5 pt-4 border-t border-slate-100 flex items-center justify-between">
                    @php
                        $hariList = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum'];
                    @endphp
                    @foreach ($hariList as $index => $hari)
                        <div class="flex flex-col items-center gap-1.5">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full {{ $index <= ($stats['selesai'] - 1) || $index === 0 ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-300' }} text-xs font-bold shadow-2xs">
                                <x-icon name="check" :size="14" />
                            </span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase">{{ $hari }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- WIDGET 2: LEADERBOARD / PERINGKAT KELAS --}}
            <div id="peringkat-kelas" class="rounded-2xl bg-white border border-slate-200/80 p-6 shadow-xs" data-tour="leaderboard">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-display text-base font-extrabold text-slate-900">Peringkat Kelas</h3>
                    <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full">Top Nilai</span>
                </div>

                <form method="GET" action="{{ route('peserta.dashboard') }}#peringkat-kelas" class="mb-4">
                    <label for="filter-kelas-peringkat" class="label">Kelas</label>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <select id="filter-kelas-peringkat" name="kelas" class="input min-h-11 w-full sm:flex-1">
                            <option value="">Semua kelas saya</option>
                            @foreach ($kelasSaya as $kelas)
                                <option value="{{ $kelas->id }}" @selected((int) $kelasFilter === (int) $kelas->id)>{{ $kelas->nama }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-secondary min-h-11 w-full sm:w-auto">Terapkan</button>
                    </div>
                </form>

                @if ($leaderboard->isEmpty())
                    <div class="py-4 text-center">
                        <p class="text-xs text-slate-500">Belum ada nilai kuis tercatat untuk filter ini.</p>
                        @if ($kelasFilter !== null)
                            <a href="{{ route('peserta.dashboard') }}#peringkat-kelas" class="mt-2 inline-block text-xs font-extrabold text-blue-600 hover:underline">
                                Tampilkan semua kelas saya
                            </a>
                        @endif
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($leaderboard as $idx => $pesertaTop)
                            @php
                                $isMe = $pesertaTop->id === auth()->id();
                            @endphp
                            <div class="flex items-center justify-between gap-3 p-2.5 rounded-2xl transition {{ $isMe ? 'bg-blue-50/80 border border-blue-200/70' : 'hover:bg-slate-50' }}">
                                <div class="flex items-center gap-3 min-w-0">
                                    {{-- Position Pill --}}
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-extrabold {{ $idx === 0 ? 'bg-amber-400 text-amber-950' : ($idx === 1 ? 'bg-slate-300 text-slate-900' : ($idx === 2 ? 'bg-amber-700 text-white' : 'text-slate-400')) }}">
                                        {{ $idx + 1 }}
                                    </span>

                                    {{-- Avatar --}}
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-200 text-slate-700 text-xs font-bold font-display">
                                        {{ strtoupper(substr($pesertaTop->nama, 0, 1)) }}
                                    </div>

                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-slate-800 truncate">
                                            {{ $pesertaTop->nama }}
                                            @if ($idx === 0)
                                                <x-icon name="crown" :size="14" class="text-amber-500 inline ml-0.5" />
                                            @endif
                                            @if ($isMe)
                                                <span class="text-[10px] font-extrabold text-blue-700 bg-blue-100 px-1.5 py-0.5 rounded ml-1">Kamu</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <span class="text-xs font-extrabold text-slate-900 tabular-nums">
                                    {{ (int) ($pesertaTop->attempts_sum_skor_total ?? 0) }} nilai
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Nilai terbaik per kuis. Kuis percobaan tidak dihitung.</p>
                @endif
            </div>

            {{-- WIDGET 3: TARGET KELULUSAN --}}
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#eff6ff] to-[#dbeafe] border border-blue-200/70 p-5 shadow-xs">
                <div class="flex items-start gap-3.5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-xs">
                        <x-icon name="trophy" :size="22" />
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-blue-950">Target Kelulusan</h4>
                        <p class="text-xs text-blue-800/80 mt-1 leading-relaxed">
                            Selesaikan kuis untuk membuka rekap nilai dan sertifikat kelasmu!
                        </p>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="flex items-center justify-between text-[11px] font-bold text-blue-900 mb-1.5">
                        <span>Progres Ujian</span>
                        <span>{{ min(5, $stats['selesai']) }} / 5 Selesai</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-blue-200 overflow-hidden">
                        <div
                            class="h-full rounded-full bg-blue-600 transition-all duration-500"
                            style="width: {{ min(100, ($stats['selesai'] / 5) * 100) }}%"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.peserta>
