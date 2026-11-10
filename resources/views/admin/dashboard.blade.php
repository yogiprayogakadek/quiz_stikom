<x-layouts.admin :canvas="true" judul="Dashboard" keterangan="Pusat kendali kuis, sesi ujian aktif, dan data peserta.">
    <script>
        window.adminDashboardSearch = (config) => ({
            cari: '',
            terbuka: false,
            memuat: false,
            hasil: { total: 0, sesi: [], kuis: [], peserta: [], kelas: [] },

            async cariData() {
                const q = this.cari.trim();
                if (q.length === 0) {
                    this.hasil = { total: 0, sesi: [], kuis: [], peserta: [], kelas: [] };
                    this.terbuka = false;
                    return;
                }
                this.memuat = true;
                this.terbuka = true;
                try {
                    const res = await fetch(`${config.searchUrl}?q=${encodeURIComponent(q)}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        this.hasil = await res.json();
                    }
                } catch (e) {
                    console.warn('Gagal mencari:', e);
                } finally {
                    this.memuat = false;
                }
            },
            bukaDropdown() {
                if (this.cari.trim().length > 0) {
                    this.terbuka = true;
                }
            },
            tutupDropdown() {
                this.terbuka = false;
            },
            resetCari() {
                this.cari = '';
                this.hasil = { total: 0, sesi: [], kuis: [], peserta: [], kelas: [] };
                this.terbuka = false;
            }
        });

        window.adminPesertaOnline = (config) => ({
            pesertaList: config.initial || [],
            aktivitasList: config.activities || [],
            notifikasiList: [],
            suaraAktif: true,
            audioCtx: null,
            statusKoneksi: 'connecting',
            sedangSinkron: false,
            gagalSinkron: false,

            get labelKoneksi() {
                if (this.statusKoneksi === 'connected') return 'Realtime tersambung';
                if (this.gagalSinkron) return 'Gagal menyinkronkan';
                return 'Sinkronisasi berkala';
            },

            get kelasKoneksi() {
                if (this.statusKoneksi === 'connected') return 'text-emerald-700';
                if (this.gagalSinkron) return 'text-rose-700';
                return 'text-amber-700';
            },

            init() {
                this.pasangAudio();
                this.pasangReverb();
                // Polling ringan sebagai cadangan bila WebSocket belum terhubung.
                setInterval(() => {
                    if (! document.hidden) {
                        this.sinkronUlang();
                    }
                }, 3000);

                document.addEventListener('visibilitychange', () => {
                    if (! document.hidden) {
                        this.sinkronUlang();
                    }
                });
            },

            pasangAudio() {
                const initAudio = () => {
                    try {
                        const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
                        if (AudioCtxClass && !this.audioCtx) {
                            this.audioCtx = new AudioCtxClass();
                        }
                        if (this.audioCtx && this.audioCtx.state === 'suspended') {
                            this.audioCtx.resume();
                        }
                    } catch (e) {}
                    window.removeEventListener('click', initAudio);
                };
                window.addEventListener('click', initAudio, { passive: true });
            },

            bunyikanChime() {
                if (!this.suaraAktif) return;
                try {
                    const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
                    if (!this.audioCtx && AudioCtxClass) {
                        this.audioCtx = new AudioCtxClass();
                    }
                    if (!this.audioCtx) return;
                    if (this.audioCtx.state === 'suspended') {
                        this.audioCtx.resume();
                    }
                    const now = this.audioCtx.currentTime;
                    const osc = this.audioCtx.createOscillator();
                    const gain = this.audioCtx.createGain();

                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(587.33, now);
                    osc.frequency.exponentialRampToValueAtTime(880, now + 0.15);

                    gain.gain.setValueAtTime(0.12, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);

                    osc.connect(gain);
                    gain.connect(this.audioCtx.destination);

                    osc.start(now);
                    osc.stop(now + 0.35);
                } catch (e) {}
            },

            pasangReverb(percobaan = 0) {
                if (typeof window.buatEcho !== 'function') {
                    if (percobaan < 40) {
                        setTimeout(() => this.pasangReverb(percobaan + 1), 250);
                    }
                    return;
                }
                try {
                    const echo = window.buatEcho();
                    if (! echo) {
                        this.statusKoneksi = 'polling';
                        return;
                    }

                    const connection = echo.connector?.pusher?.connection;
                    if (connection) {
                        this.statusKoneksi = connection.state;
                        connection.bind('state_change', ({ current }) => {
                            this.statusKoneksi = current;
                            if (current === 'connected') this.sinkronUlang();
                        });
                        connection.bind('error', () => {
                            this.statusKoneksi = 'polling';
                        });
                    }

                    echo.private('admin-dashboard')
                        .listen('.PesertaStatusLogin', (data) => {
                            if (data.status === 'login') {
                                this.tambahPeserta(data);
                            } else if (data.status === 'logout') {
                                this.hapusPeserta(data);
                            }
                            this.tambahAktivitas(data);
                        });
                } catch (e) {
                    this.statusKoneksi = 'polling';
                    console.warn('Reverb channel admin-dashboard error:', e);
                }
            },

            tambahAktivitas(data) {
                const id = data.activity_id || data.id;
                if (id && this.aktivitasList.some(item => item.id === id)) return;

                this.aktivitasList.unshift({
                    id: id || `event-${Date.now()}`,
                    user_id: data.user_id,
                    nama: data.nama || 'Peserta',
                    username: data.username || '',
                    status: data.status || 'login',
                    reason: data.reason || null,
                    waktu: data.waktu || new Date().toISOString(),
                });
                this.aktivitasList = this.aktivitasList.slice(0, 10);
            },

            waktuAktivitas(waktu) {
                const tanggal = new Date(waktu);
                if (Number.isNaN(tanggal.getTime())) return '';

                return new Intl.DateTimeFormat('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    day: '2-digit',
                    month: 'short',
                }).format(tanggal);
            },

            tambahNotifikasi(item) {
                const id = 'notif-' + Date.now() + '-' + Math.random().toString(36).substring(2, 7);
                const notif = {
                    id,
                    tipe: item.tipe || 'login',
                    nama: item.nama || 'Peserta',
                    username: item.username || '',
                    pesan: item.pesan || (item.tipe === 'logout' ? 'telah logout dari sistem' : 'telah login ke sistem'),
                    waktu: item.waktu || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                };
                this.notifikasiList.unshift(notif);

                setTimeout(() => {
                    this.hapusNotifikasi(id);
                }, 5000);
            },

            hapusNotifikasi(id) {
                this.notifikasiList = this.notifikasiList.filter(n => n.id !== id);
            },

            tambahPeserta(data) {
                this.pesertaList = this.pesertaList.filter(p => p.id !== data.user_id);
                const itemBaru = {
                    id: data.user_id,
                    nama: data.nama,
                    username: data.username,
                    inisial: (data.nama || 'P').charAt(0).toUpperCase(),
                    isNew: true,
                };
                this.pesertaList.unshift(itemBaru);
                this.bunyikanChime();
                this.tambahNotifikasi({
                    tipe: 'login',
                    nama: data.nama,
                    username: data.username,
                    pesan: 'baru saja login ke sistem'
                });

                setTimeout(() => {
                    itemBaru.isNew = false;
                }, 4000);
            },

            hapusPeserta(data) {
                const userId = typeof data === 'object' ? data.user_id : data;
                const user = this.pesertaList.find(p => p.id === userId);
                const nama = (typeof data === 'object' && data.nama) ? data.nama : (user ? user.nama : 'Peserta');
                const username = (typeof data === 'object' && data.username) ? data.username : (user ? user.username : '');

                this.pesertaList = this.pesertaList.filter(p => p.id !== userId);
                this.tambahNotifikasi({
                    tipe: 'logout',
                    nama: nama,
                    username: username,
                    pesan: 'telah logout dari sistem'
                });
            },

            async sinkronUlang() {
                if (this.sedangSinkron) return;
                this.sedangSinkron = true;
                try {
                    const res = await fetch(config.endpoint, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        const json = await res.json();
                        this.gagalSinkron = false;
                        if (Array.isArray(json.peserta)) {
                            const currentIds = new Set(this.pesertaList.map(p => p.id));
                            const newlyLoggedIn = json.peserta.filter(p => !currentIds.has(p.id));

                            newlyLoggedIn.forEach(p => {
                                this.tambahNotifikasi({
                                    tipe: 'login',
                                    nama: p.nama,
                                    username: p.username,
                                    pesan: 'aktif terdeteksi di sistem'
                                });
                            });
                            if (newlyLoggedIn.length > 0) {
                                this.bunyikanChime();
                            }

                            const newIds = new Set([
                                ...this.pesertaList.filter(p => p.isNew).map(p => p.id),
                                ...newlyLoggedIn.map(p => p.id)
                            ]);

                            this.pesertaList = json.peserta.map(p => ({
                                ...p,
                                isNew: newIds.has(p.id)
                            }));
                        }
                        if (Array.isArray(json.activities)) {
                            const activityIds = new Set(this.aktivitasList.map(item => item.id));
                            json.activities
                                .filter(item => !activityIds.has(item.id))
                                .reverse()
                                .forEach(item => this.tambahAktivitas(item));
                            this.aktivitasList = json.activities;
                        }
                    } else {
                        this.gagalSinkron = true;
                    }
                } catch (e) {
                    this.gagalSinkron = true;
                } finally {
                    this.sedangSinkron = false;
                }
            }
        });
    </script>

    <div
        x-data="adminDashboardSearch({ searchUrl: '{{ route('admin.dashboard.search', [], false) }}' })"
        class="grid grid-cols-1 xl:grid-cols-12 gap-7 items-start w-full"
    >
        {{-- CENTER FEED COLUMN (8 Columns on xl) --}}
        <div class="xl:col-span-8 space-y-7 min-w-0">
            {{-- Top Bar: Search, Quick Actions & Hamburger Toggle --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3.5">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    {{-- Hamburger button desktop --}}
                    <button
                        type="button"
                        @click="toggleSidebar()"
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
                        class="lg:hidden inline-flex items-center justify-center h-11 w-11 rounded-2xl bg-white border border-slate-200/90 text-slate-600 hover:text-slate-900 hover:bg-slate-50 shadow-xs transition shrink-0"
                        aria-label="Buka menu"
                    >
                        <x-icon name="menu" :size="20" />
                    </button>

                    <div class="relative flex-1 min-w-0" @click.outside="tutupDropdown()">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-slate-400">
                            <x-icon name="search" :size="18" />
                        </span>
                        <input
                            type="text"
                            x-model="cari"
                            @input.debounce.250ms="cariData()"
                            @focus="bukaDropdown()"
                            @keydown.escape="tutupDropdown()"
                            placeholder="Cari kuis, peserta, atau sesi ujian..."
                            class="w-full pl-11 pr-10 py-2.5 sm:py-3 rounded-2xl bg-white border border-slate-200/90 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 shadow-xs transition"
                        >
                        {{-- Tombol Bersihkan / Reset --}}
                        <button
                            type="button"
                            x-show="cari"
                            @click="resetCari()"
                            class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 transition"
                            title="Bersihkan pencarian"
                        >
                            <span class="text-lg font-bold leading-none">&times;</span>
                        </button>

                        {{-- Dropdown Hasil Live Search --}}
                        <div
                            x-show="terbuka && cari.trim().length > 0"
                            x-cloak
                            x-transition
                            class="absolute left-0 right-0 mt-2 z-50 rounded-2xl bg-white border border-slate-200/90 shadow-2xl overflow-hidden max-h-96 overflow-y-auto divide-y divide-slate-100"
                        >
                            {{-- State Memuat --}}
                            <div x-show="memuat" class="p-4 text-center text-xs font-semibold text-slate-500 flex items-center justify-center gap-2">
                                <span class="h-3.5 w-3.5 rounded-full border-2 border-blue-600 border-t-transparent animate-spin"></span>
                                <span>Mencari di seluruh sistem...</span>
                            </div>

                            {{-- State Tidak Ada Hasil --}}
                            <div x-show="!memuat && hasil.total === 0" class="p-6 text-center text-xs text-slate-500">
                                <p class="font-bold text-slate-800 text-sm mb-0.5">Tidak ada hasil ditemukan</p>
                                <p>Tidak ada kuis, peserta, atau sesi yang cocok dengan "<span class="font-semibold text-slate-700" x-text="cari"></span>".</p>
                            </div>

                            {{-- Kategori: Sesi Ujian --}}
                            <div x-show="!memuat && hasil.sesi && hasil.sesi.length > 0" class="p-3">
                                <div class="px-2 py-1 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                                    Sesi Ujian (<span x-text="hasil.sesi.length"></span>)
                                </div>
                                <div class="space-y-1 mt-1">
                                    <template x-for="item in hasil.sesi" :key="'sesi-' + item.id">
                                        <a :href="item.url" class="flex items-center justify-between gap-3 p-2 rounded-xl hover:bg-slate-50 transition group">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition">
                                                    <x-icon name="play" :size="14" />
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="text-xs font-bold text-slate-900 truncate" x-text="item.nama"></p>
                                                    <p class="text-[11px] text-slate-500 truncate" x-text="item.sub"></p>
                                                </div>
                                            </div>
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-md uppercase" :class="item.status === 'berjalan' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700'" x-text="item.status"></span>
                                        </a>
                                    </template>
                                </div>
                            </div>

                            {{-- Kategori: Kuis --}}
                            <div x-show="!memuat && hasil.kuis && hasil.kuis.length > 0" class="p-3">
                                <div class="px-2 py-1 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                                    Kuis Bank Soal (<span x-text="hasil.kuis.length"></span>)
                                </div>
                                <div class="space-y-1 mt-1">
                                    <template x-for="item in hasil.kuis" :key="'kuis-' + item.id">
                                        <a :href="item.url" class="flex items-center justify-between gap-3 p-2 rounded-xl hover:bg-slate-50 transition group">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition">
                                                    <x-icon name="book" :size="14" />
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="text-xs font-bold text-slate-900 truncate" x-text="item.judul"></p>
                                                    <p class="text-[11px] text-slate-500 truncate" x-text="item.sub"></p>
                                                </div>
                                            </div>
                                            <span class="text-xs font-bold text-blue-600 group-hover:translate-x-0.5 transition">&rarr;</span>
                                        </a>
                                    </template>
                                </div>
                            </div>

                            {{-- Kategori: Peserta --}}
                            <div x-show="!memuat && hasil.peserta && hasil.peserta.length > 0" class="p-3">
                                <div class="px-2 py-1 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                                    Peserta / Siswa (<span x-text="hasil.peserta.length"></span>)
                                </div>
                                <div class="space-y-1 mt-1">
                                    <template x-for="item in hasil.peserta" :key="'peserta-' + item.id">
                                        <a :href="item.url" class="flex items-center justify-between gap-3 p-2 rounded-xl hover:bg-slate-50 transition group">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-purple-50 text-purple-600 group-hover:bg-purple-600 group-hover:text-white transition">
                                                    <x-icon name="users" :size="14" />
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="text-xs font-bold text-slate-900 truncate" x-text="item.nama"></p>
                                                    <p class="text-[11px] text-slate-500 font-mono truncate" x-text="item.sub"></p>
                                                </div>
                                            </div>
                                            <span class="text-[11px] font-bold text-slate-500">Lihat &rarr;</span>
                                        </a>
                                    </template>
                                </div>
                            </div>

                            {{-- Kategori: Kelas --}}
                            <div x-show="!memuat && hasil.kelas && hasil.kelas.length > 0" class="p-3">
                                <div class="px-2 py-1 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                                    Kelas (<span x-text="hasil.kelas.length"></span>)
                                </div>
                                <div class="space-y-1 mt-1">
                                    <template x-for="item in hasil.kelas" :key="'kelas-' + item.id">
                                        <a :href="item.url" class="flex items-center justify-between gap-3 p-2 rounded-xl hover:bg-slate-50 transition group">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600 group-hover:bg-teal-600 group-hover:text-white transition">
                                                    <x-icon name="kelas" :size="14" />
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="text-xs font-bold text-slate-900 truncate" x-text="item.nama"></p>
                                                    <p class="text-[11px] text-slate-500 truncate" x-text="item.sub"></p>
                                                </div>
                                            </div>
                                            <span class="text-[11px] font-bold text-slate-500">Buka &rarr;</span>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-end sm:self-auto shrink-0">
                    <a
                        href="{{ route('admin.quizzes.create') }}"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200/90 text-xs font-bold text-slate-700 shadow-xs transition"
                    >
                        <x-icon name="plus" :size="14" />
                        <span>Kuis Baru</span>
                    </a>
                    <a
                        href="{{ route('admin.sessions.create') }}"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-xs transition"
                    >
                        <x-icon name="play" :size="14" />
                        <span>Buka Sesi</span>
                    </a>
                </div>
            </div>

            {{-- HERO BANNER (Option A: Modern Dark Slate / High-Contrast Deep Navy) --}}
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-950 via-slate-900 to-[#0b1b36] border border-slate-800/80 p-6 sm:p-8 lg:p-9 shadow-sm">
                {{-- Ambient mesh glow --}}
                <div class="absolute -right-10 -top-10 h-72 w-72 rounded-full bg-blue-500/15 blur-3xl pointer-events-none"></div>
                <div class="absolute -left-12 -bottom-12 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="max-w-xl">
                        {{-- Status Pill --}}
                        <div class="inline-flex items-center gap-2 rounded-full bg-white/10 backdrop-blur-md px-3.5 py-1 text-xs font-semibold text-blue-200 border border-white/15 shadow-2xs mb-4">
                            <span class="h-2 w-2 rounded-full bg-blue-400 animate-pulse"></span>
                            <span>Sistem Ujian Aktif</span>
                        </div>

                        {{-- Headline --}}
                        <h1 class="font-display text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-white leading-tight">
                            Selamat Datang, <br class="hidden sm:inline" />
                            <span class="text-blue-400">{{ auth()->user()->nama }}</span>
                        </h1>
                        <p class="mt-2.5 text-sm sm:text-base text-slate-300 font-normal leading-relaxed">
                            Pantau pelaksanaan kuis dan hasil ujian peserta secara real-time di seluruh kelas.
                        </p>

                        {{-- CTA Actions --}}
                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <a
                                href="{{ route('admin.sessions.create') }}"
                                class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold shadow-xs transition active:scale-[0.98]"
                            >
                                <span>Buka Sesi Ujian</span>
                                <x-icon name="play" :size="16" />
                            </a>
                            <a
                                href="{{ route('admin.quizzes.index') }}"
                                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white/10 hover:bg-white/15 text-slate-200 hover:text-white text-sm font-semibold border border-white/15 backdrop-blur-md transition"
                            >
                                <x-icon name="book" :size="16" />
                                <span>Kelola Kuis</span>
                            </a>
                        </div>
                    </div>

                    {{-- Visual Card: Crisp framed illustration --}}
                    <div class="relative flex-shrink-0 self-center md:self-auto">
                        <div class="relative h-44 w-44 sm:h-48 sm:w-48 rounded-2xl overflow-hidden bg-slate-800/80 p-2 border border-slate-700/70 shadow-md">
                            <img
                                src="{{ asset('images/login-illustration.png') }}"
                                alt="Ilustrasi Admin"
                                class="h-full w-full object-cover rounded-xl select-none pointer-events-none"
                            >
                        </div>
                        <div class="absolute -bottom-2 -left-2 bg-slate-900/95 backdrop-blur-md rounded-xl px-3 py-1.5 shadow-sm border border-slate-700/80 flex items-center gap-2">
                            <x-icon name="clock" :size="16" class="text-blue-400" />
                            <span class="text-xs font-semibold text-slate-200">{{ now()->timezone($orgTimezone)->translatedFormat('d F Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4 MINI BENTO METRICS --}}
            <section aria-labelledby="ringkasan-admin-heading">
                <h2 id="ringkasan-admin-heading" class="sr-only">Ringkasan Statistik</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
                    {{-- Metric 1: Peserta --}}
                    <a href="{{ route('admin.participants.index') }}" class="rounded-2xl bg-[#f3e8ff]/70 border border-purple-200/80 p-4 sm:p-5 shadow-xs flex flex-col justify-between hover:bg-[#f3e8ff] transition">
                        <div class="flex items-center justify-between">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-purple-500/20 text-purple-700">
                                <x-icon name="users" :size="20" />
                            </span>
                            <span class="text-xs font-extrabold text-purple-700">&rarr;</span>
                        </div>
                        <div class="mt-4">
                            <p class="text-2xl sm:text-3xl font-extrabold text-purple-950">{{ $ringkasan['peserta'] }}</p>
                            <p class="text-xs font-bold text-purple-800/80 mt-0.5">Peserta Siswa</p>
                        </div>
                    </a>

                    {{-- Metric 2: Soal --}}
                    <a href="{{ route('admin.quizzes.index') }}" class="rounded-2xl bg-[#ffe4e6]/70 border border-rose-200/80 p-4 sm:p-5 shadow-xs flex flex-col justify-between hover:bg-[#ffe4e6] transition">
                        <div class="flex items-center justify-between">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-500/20 text-rose-700">
                                <x-icon name="pencil" :size="20" />
                            </span>
                            <span class="text-xs font-extrabold text-rose-700">&rarr;</span>
                        </div>
                        <div class="mt-4">
                            <p class="text-2xl sm:text-3xl font-extrabold text-rose-950">{{ $ringkasan['soal'] }}</p>
                            <p class="text-xs font-bold text-rose-800/80 mt-0.5">Total Soal</p>
                        </div>
                    </a>

                    {{-- Metric 3: Kuis Tersedia --}}
                    <a href="{{ route('admin.quizzes.index') }}" class="rounded-2xl bg-sky-50 border border-sky-200/80 p-4 sm:p-5 shadow-xs flex flex-col justify-between hover:bg-sky-100/70 transition">
                        <div class="flex items-center justify-between">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-500/20 text-sky-700">
                                <x-icon name="book" :size="20" />
                            </span>
                            <span class="text-xs font-extrabold text-sky-700">&rarr;</span>
                        </div>
                        <div class="mt-4">
                            <p class="text-2xl sm:text-3xl font-extrabold text-sky-950">{{ $ringkasan['kuis'] }}</p>
                            <p class="text-xs font-bold text-sky-800/80 mt-0.5">Kuis Tersedia</p>
                        </div>
                    </a>

                    {{-- Metric 4: Sesi Aktif --}}
                    <a href="{{ route('admin.sessions.index') }}" class="rounded-2xl bg-[#fef3c7]/70 border border-amber-200/80 p-4 sm:p-5 shadow-xs flex flex-col justify-between hover:bg-[#fef3c7] transition">
                        <div class="flex items-center justify-between">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-500/20 text-amber-700">
                                <x-icon name="play" :size="20" />
                            </span>
                            <span class="text-xs font-extrabold text-amber-700">&rarr;</span>
                        </div>
                        <div class="mt-4">
                            <p class="text-2xl sm:text-3xl font-extrabold text-amber-950">{{ $ringkasan['sesi_aktif'] }}</p>
                            <p class="text-xs font-bold text-amber-800/80 mt-0.5">Sesi Aktif</p>
                        </div>
                    </a>
                </div>
            </section>

            {{-- LIVE SESI MONITOR --}}
            <section aria-labelledby="sesi-aktif-heading">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 id="sesi-aktif-heading" class="font-display text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
                        Sesi Ujian Aktif
                    </h2>
                    <a href="{{ route('admin.sessions.index') }}" class="text-xs sm:text-sm font-extrabold text-blue-600 hover:text-blue-800 hover:underline">
                        Semua Sesi &rarr;
                    </a>
                </div>

                @if ($sesiAktif->isEmpty())
                    <div class="rounded-2xl bg-white border border-slate-200/80 p-8 text-center shadow-xs">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 mb-3">
                            <x-icon name="play" :size="28" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-900">Belum ada sesi yang aktif</h3>
                        <p class="mt-1 text-sm text-slate-500 max-w-md mx-auto">
                            Buka sesi baru untuk memulai ujian serempak atau acak untuk peserta kelas.
                        </p>
                        <div class="mt-5">
                            <a href="{{ route('admin.sessions.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-bold shadow-xs hover:bg-blue-700 transition">
                                <x-icon name="plus" :size="16" />
                                <span>Buka Sesi Baru</span>
                            </a>
                        </div>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($sesiAktif as $sesi)
                            <article
                                x-show="!cari || '{{ strtolower(addslashes($sesi->nama . ' ' . $sesi->quiz->judul . ' ' . ($sesi->kelas?->nama ?? ''))) }}'.includes(cari.toLowerCase().trim())"
                                class="group rounded-2xl bg-white border border-slate-200/80 p-5 sm:p-6 shadow-xs hover:border-slate-300 transition-all flex flex-col md:flex-row md:items-center justify-between gap-5"
                            >
                                <div class="flex items-start gap-4 min-w-0">
                                    <div class="flex h-14 w-14 sm:h-16 sm:w-16 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 ring-1 ring-blue-200/60">
                                        <x-icon name="play" :size="28" />
                                    </div>

                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                            <span class="px-2.5 py-0.5 rounded-lg text-xs font-extrabold uppercase {{ $sesi->mode === 'serempak' ? 'bg-indigo-100 text-indigo-800' : 'bg-blue-100 text-blue-800' }}">
                                                Mode {{ ucfirst($sesi->mode) }}
                                            </span>
                                            <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold {{ $sesi->status === 'berjalan' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                                {{ $sesi->status === 'berjalan' ? 'Sedang Berjalan' : 'Lobby Terbuka' }}
                                            </span>
                                            @if ($sesi->kelas)
                                                <span class="px-2.5 py-0.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-700">
                                                    {{ $sesi->kelas->nama }}
                                                </span>
                                            @endif
                                        </div>

                                        <h3 class="text-lg sm:text-xl font-extrabold text-slate-900 truncate">{{ $sesi->nama }}</h3>
                                        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                                            {{ $sesi->quiz->judul }} · {{ $sesi->attempts_count }} peserta bergabung
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 self-end md:self-center shrink-0">
                                    <a
                                        href="{{ route('admin.sessions.show', $sesi) }}"
                                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow-xs transition active:scale-[0.98]"
                                    >
                                        <span>Buka Monitor</span>
                                        <x-icon name="chart" :size="16" />
                                    </a>
                                </div>
                            </article>
                        @endforeach

                        <div
                            x-show="cari && !memuat && hasil.sesi && hasil.sesi.length === 0"
                            class="rounded-2xl bg-white border border-dashed border-slate-200 p-8 text-center text-slate-500 shadow-xs text-sm"
                        >
                            Tidak ada sesi ujian aktif yang cocok dengan kata kunci "<span class="font-bold text-slate-800" x-text="cari"></span>".
                        </div>
                    </div>
                @endif
            </section>
        </div>

        {{-- RIGHT COLUMN WIDGETS (4 Columns on xl, Sticky on Desktop) --}}
        <div
            x-data="adminPesertaOnline({
                initial: @json($pesertaOnline),
                activities: @json($aktivitasLogin),
                endpoint: '{{ route('admin.dashboard.peserta-online', [], false) }}'
            })"
            class="xl:col-span-4 space-y-6 xl:sticky xl:top-8"
        >
            {{-- Top Stats Counters (Server Status & Login Hari Ini) --}}
            <div class="flex items-center justify-between gap-3">
                <div class="flex-1 rounded-2xl bg-white border border-slate-200/80 p-3.5 shadow-xs flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-100 text-blue-600">
                        <x-icon name="wifi" :size="20" />
                    </span>
                    <div>
                        <p class="text-[11px] font-bold text-slate-500">Koneksi dashboard</p>
                        <p class="text-sm font-extrabold" :class="kelasKoneksi" x-text="labelKoneksi"></p>
                    </div>
                </div>

                <div class="flex-1 rounded-2xl bg-white border border-slate-200/80 p-3.5 shadow-xs flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-600">
                        <x-icon name="users" :size="20" />
                    </span>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Aktivitas</p>
                        <p class="text-base font-extrabold text-slate-900">{{ $loginHariIni }} Ujian</p>
                    </div>
                </div>
            </div>

            {{-- WIDGET: PESERTA LOGIN / ONLINE REALTIME --}}
            <div class="rounded-2xl bg-white border border-slate-200/80 p-5 shadow-xs">
                {{-- Floating Toast Alerts Container (Top Right Corner) --}}
                <div
                    class="fixed top-5 right-5 z-[9999] flex flex-col items-end space-y-2.5 pointer-events-none max-w-sm w-full sm:w-80"
                    aria-live="polite"
                >
                    <template x-for="notif in notifikasiList" :key="notif.id">
                        <div
                            x-show="true"
                            x-transition:enter="transition ease-out duration-300 transform"
                            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="transition ease-in duration-200 transform"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 translate-x-4 scale-95"
                            class="pointer-events-auto w-full rounded-2xl bg-white border p-3.5 shadow-xl flex items-start gap-3 transition-all"
                            :class="notif.tipe === 'login' ? 'border-emerald-200 shadow-emerald-500/10' : 'border-amber-200 shadow-amber-500/10'"
                            role="alert"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl font-bold"
                                :class="notif.tipe === 'login' ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'"
                            >
                                <template x-if="notif.tipe === 'login'">
                                    <x-icon name="user" :size="18" />
                                </template>
                                <template x-if="notif.tipe === 'logout'">
                                    <x-icon name="logout" :size="18" />
                                </template>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-1">
                                    <p
                                        class="text-[11px] font-extrabold uppercase tracking-wider"
                                        :class="notif.tipe === 'login' ? 'text-emerald-700' : 'text-amber-700'"
                                        x-text="notif.tipe === 'login' ? 'Peserta Masuk' : 'Peserta Keluar'"
                                    ></p>
                                    <span class="text-[10px] text-slate-400 font-mono" x-text="notif.waktu"></span>
                                </div>
                                <p class="text-xs font-extrabold text-slate-900 truncate mt-0.5" x-text="notif.nama"></p>
                                <p class="text-[11px] text-slate-500 truncate" x-text="notif.username ? 'NIM: ' + notif.username : notif.pesan"></p>
                            </div>
                            <button
                                type="button"
                                @click="hapusNotifikasi(notif.id)"
                                class="text-slate-400 hover:text-slate-600 p-1 rounded-lg transition shrink-0"
                                aria-label="Tutup"
                            >
                                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-between mb-3.5">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                        <h3 class="font-display text-base font-extrabold text-slate-900">Sedang online</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                            <span x-text="pesertaList.length"></span> Online
                        </span>
                        <button
                            type="button"
                            @click="suaraAktif = !suaraAktif"
                            class="min-h-11 px-2 rounded-lg text-xs font-bold text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition"
                            :title="suaraAktif ? 'Mute notifikasi suara' : 'Aktifkan notifikasi suara'"
                            :aria-pressed="suaraAktif"
                        >
                            <span x-text="suaraAktif ? 'Suara aktif' : 'Suara mati'"></span>
                        </button>
                    </div>
                </div>

                <template x-if="pesertaList.length === 0">
                    <div class="py-6 text-center">
                        <div class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-2">
                            <x-icon name="users" :size="20" />
                        </div>
                        <p class="text-xs text-slate-500">Belum ada peserta yang aktif login.</p>
                    </div>
                </template>

                <div x-show="pesertaList.length > 0" class="space-y-2 max-h-56 overflow-y-auto pr-1">
                    <template x-for="p in pesertaList" :key="'online-' + p.id">
                        <div
                            class="flex items-center justify-between gap-3 p-2.5 rounded-xl border transition-all duration-300"
                            :class="p.isNew ? 'bg-emerald-50/80 border-emerald-300 shadow-xs scale-[1.01]' : 'bg-slate-50/60 border-slate-100 hover:bg-slate-50'"
                        >
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="relative">
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl font-bold font-display text-xs text-emerald-700 bg-emerald-100 border border-emerald-200/60"
                                        x-text="p.inisial"
                                    ></div>
                                    <span class="absolute -bottom-0.5 -right-0.5 h-2 w-2 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-800 truncate" x-text="p.nama"></p>
                                    <p class="text-[10px] text-slate-400 truncate" x-text="'NIM: ' + p.username"></p>
                                </div>
                            </div>
                            <span
                                class="shrink-0 text-[10px] font-extrabold px-2 py-0.5 rounded-md"
                                :class="p.isNew ? 'bg-emerald-600 text-white animate-pulse' : 'bg-white text-emerald-700 border border-emerald-200/60'"
                                x-text="p.isNew ? 'Baru Masuk' : 'Aktif'"
                            ></span>
                        </div>
                    </template>
                </div>

                <div class="mt-5 border-t border-slate-200 pt-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-display text-base font-extrabold text-slate-900">Aktivitas terbaru</h3>
                        <span class="text-xs font-bold text-slate-500" x-text="aktivitasList.length + ' terakhir'"></span>
                    </div>

                    <template x-if="aktivitasList.length === 0">
                        <p class="py-5 text-center text-xs text-slate-500">Belum ada aktivitas login peserta.</p>
                    </template>

                    <ol x-show="aktivitasList.length > 0" class="mt-3 max-h-72 space-y-1 overflow-y-auto pr-1" aria-label="Aktivitas login dan logout peserta">
                        <template x-for="aktivitas in aktivitasList" :key="'activity-' + aktivitas.id">
                            <li class="flex items-center gap-3 rounded-xl px-2 py-2.5 hover:bg-slate-50">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                    :class="aktivitas.status === 'login' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800'"
                                    aria-hidden="true"
                                >
                                    <template x-if="aktivitas.status === 'login'">
                                        <x-icon name="user" :size="17" />
                                    </template>
                                    <template x-if="aktivitas.status === 'logout'">
                                        <x-icon name="logout" :size="17" />
                                    </template>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-extrabold text-slate-900" x-text="aktivitas.nama"></p>
                                    <p class="truncate text-[11px] text-slate-500">
                                        <span x-text="aktivitas.username"></span>
                                        <span aria-hidden="true"> · </span>
                                        <span x-text="aktivitas.status === 'login' ? 'Masuk' : 'Keluar'"></span>
                                    </p>
                                </div>
                                <time class="shrink-0 text-[10px] font-semibold text-slate-500" :datetime="aktivitas.waktu" x-text="waktuAktivitas(aktivitas.waktu)"></time>
                            </li>
                        </template>
                    </ol>

                    <p x-show="gagalSinkron" class="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-xs font-bold text-rose-800" role="status">
                        Data terakhir tetap ditampilkan. Dashboard akan mencoba menyinkronkan lagi.
                    </p>
                </div>
            </div>

            {{-- WIDGET 1: AKTIVITAS UJIAN HARI INI (Donut SVG) --}}
            <div class="rounded-2xl bg-white border border-slate-200/80 p-6 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-display text-base font-extrabold text-slate-900">Aktivitas Hari Ini</h3>
                    <span class="text-xs font-bold text-slate-400">{{ now()->timezone($orgTimezone)->translatedFormat('d M') }}</span>
                </div>

                <div class="flex items-center gap-5">
                    @php
                        $targetPeserta = max(1, $ringkasan['peserta']);
                        $hadirPercent = min(100, round(($loginHariIni / $targetPeserta) * 100));
                        $circumference = 2 * 3.14159 * 36;
                        $dashoffset = $circumference - ($circumference * $hadirPercent / 100);
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
                            <span class="text-sm font-extrabold text-slate-900 leading-none">{{ $hadirPercent }}%</span>
                            <span class="text-[9px] font-bold text-slate-400">Kehadiran</span>
                        </div>
                    </div>

                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-slate-800">Partisipasi Ujian</p>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            {{ $loginHariIni }} dari {{ $ringkasan['peserta'] }} peserta mengikuti sesi ujian hari ini.
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
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-white text-xs font-bold shadow-2xs">
                                <x-icon name="check" :size="14" />
                            </span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase">{{ $hari }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- WIDGET 2: HASIL NILAI TERBARU --}}
            <div class="rounded-2xl bg-white border border-slate-200/80 p-6 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-display text-base font-extrabold text-slate-900">Nilai Terbaru</h3>
                    <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full">Selesai</span>
                </div>

                @if ($leaderboardTerbaru->isEmpty())
                    <p class="text-xs text-slate-500 py-4 text-center">Belum ada peserta yang menyelesaikan ujian.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($leaderboardTerbaru as $idx => $attempt)
                            <div class="flex items-center justify-between gap-3 p-2.5 rounded-2xl hover:bg-slate-50 transition">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-extrabold {{ $idx === 0 ? 'bg-amber-400 text-amber-950' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $idx + 1 }}
                                    </span>
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-200 text-slate-700 text-xs font-bold font-display">
                                        {{ strtoupper(substr($attempt->user->nama ?? 'P', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-slate-800 truncate">
                                            {{ $attempt->user->nama ?? '-' }}
                                        </p>
                                        <p class="text-[10px] text-slate-400 truncate">
                                            {{ $attempt->quizSession->quiz->judul ?? '-' }}
                                        </p>
                                    </div>
                                </div>
                                <span class="text-xs font-extrabold text-blue-700 bg-blue-50 px-2.5 py-1 rounded-lg tabular-nums">
                                    Skor {{ $attempt->skor_total ?? 0 }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- WIDGET 3: QUICK ACTIONS SHORTCUT CARD --}}
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#eff6ff] to-[#dbeafe] border border-blue-200/70 p-5 shadow-xs">
                <div class="flex items-start gap-3.5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-xs">
                        <x-icon name="trophy" :size="22" />
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-blue-950">Aksi Cepat Admin</h4>
                        <p class="text-xs text-blue-800/80 mt-1 leading-relaxed">
                            Siapkan bank soal, atur kelas lokal, atau ekspor rekap nilai peserta.
                        </p>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-blue-200/60 flex items-center justify-between gap-2">
                    <a href="{{ route('admin.quizzes.create') }}" class="flex-1 text-center py-2 px-3 rounded-xl bg-white/90 hover:bg-white text-blue-900 text-xs font-extrabold shadow-2xs transition">
                        + Tambah Kuis
                    </a>
                    <a href="{{ route('admin.classes.index') }}" class="flex-1 text-center py-2 px-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold shadow-2xs transition">
                        Kelola Kelas
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
