<x-layouts.peserta :judul="'Halo, '.auth()->user()->nama" keterangan="Pilih sesi yang sedang dibuka admin, lalu kerjakan soal sampai selesai.">
    <script>
        window.dashboardPeserta = (config) => ({
            sesiList: config.initialSesi || [],
            csrfToken: config.csrfToken || '',
            notifPesan: '',
            audioCtx: null,

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
        class="space-y-6"
    >
        @if (request()->boolean('dikeluarkan'))
            <x-alert jenis="warning">
                Sesi ini sudah tidak tersedia untukmu. Kalau kamu masih seharusnya mengerjakan, hubungi pengajar.
            </x-alert>
        @endif

        <!-- Notifikasi Real-time Banner -->
        <div
            x-show="notifPesan"
            x-cloak
            x-transition
            class="rounded-2xl border-2 border-leaf-deep bg-leaf-soft p-4 shadow-lift flex items-center justify-between gap-3"
            role="status"
        >
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-leaf-deep text-white font-bold text-lg">
                    ✓
                </span>
                <p class="text-base font-bold text-ink" x-text="notifPesan"></p>
            </div>
            <button type="button" @click="notifPesan = ''" class="btn btn-ghost text-sm font-bold text-leaf-deep hover:underline">
                Tutup
            </button>
        </div>

        <section aria-labelledby="kelas-saya">
            <div class="flex items-center justify-between gap-3">
                <h2 id="kelas-saya" class="text-2xl">Kelas saya</h2>
                <a href="{{ route('peserta.kelas.index') }}" class="btn btn-ghost">Semua kelas</a>
            </div>

            @if ($kelasSaya->isEmpty())
                <p class="mt-3 text-base text-ink-muted">
                    Belum ada kelas. Buka <a href="{{ route('peserta.kelas.index') }}" class="font-bold text-brand-deep underline">daftar kelas</a> dan masuk dengan PIN dari admin.
                </p>
            @else
                <div class="mt-4 grid gap-5 md:grid-cols-2">
                    @foreach ($kelasSaya as $kelas)
                        <article class="card card-hover flex flex-col">
                            <h3 class="text-xl font-bold text-ink">{{ $kelas->nama }}</h3>
                            <p class="mt-1 text-base text-ink-muted">
                                {{ $kelas->quizzes_count }} kuis · {{ $kelas->sessions_count }} sesi
                            </p>
                            <div class="mt-auto pt-4">
                                <a href="{{ route('peserta.kelas.show', $kelas) }}" class="btn btn-secondary">Masuk kelas</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section aria-labelledby="sesi-tersedia">
            <div class="flex items-center justify-between gap-3">
                <h2 id="sesi-tersedia" class="text-2xl">Sesi yang dibuka</h2>
                <span class="pill pill-neutral"><span x-text="sesiList.length"></span> sesi</span>
            </div>

            <!-- Tampilan Kosong (Jika belum ada sesi) -->
            <template x-if="sesiList.length === 0">
                <x-empty-state
                    class="mt-4"
                    judul="Belum ada sesi yang dibuka"
                    keterangan="Tunggu admin membuka lobby. Sesi akan otomatis muncul di layar ini tanpa perlu merefresh halaman."
                />
            </template>

            <!-- Grid Sesi Tersedia (Real-time) -->
            <div x-show="sesiList.length > 0" class="mt-4 grid gap-5 md:grid-cols-2">
                <template x-for="sesi in sesiList" :key="sesi.id">
                    <article class="card card-hover flex flex-col transition-all duration-300">
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Mode Badge -->
                            <span
                                class="pill"
                                :class="sesi.mode === 'serempak' ? 'pill-leaf' : 'pill-brand'"
                                x-text="sesi.mode === 'serempak' ? 'Mode Serempak' : 'Mode Acak'"
                            ></span>

                            <!-- Status Badge -->
                            <span
                                class="pill"
                                :class="sesi.status === 'berjalan' ? 'pill-leaf' : (sesi.status === 'lobby' ? 'pill-sun' : 'pill-neutral')"
                                x-text="sesi.status === 'berjalan' ? 'Berjalan' : (sesi.status === 'lobby' ? 'Lobby' : 'Selesai')"
                            ></span>

                            <!-- Kuota Peserta -->
                            <span
                                class="pill pill-neutral"
                                x-text="sesi.max_peserta !== null ? (sesi.attempts_count + '/' + sesi.max_peserta + ' peserta') : (sesi.attempts_count + ' peserta')"
                            ></span>

                            <!-- Kelas -->
                            <template x-if="sesi.kelas_nama">
                                <span class="pill pill-neutral" x-text="sesi.kelas_nama"></span>
                            </template>
                        </div>

                        <h3 class="mt-4 text-2xl font-bold text-ink" x-text="sesi.nama"></h3>
                        <p class="mt-1 text-base text-ink-muted">
                            <span x-text="sesi.quiz_judul"></span> · <span x-text="sesi.jumlah_soal"></span> soal
                        </p>

                        <div class="mt-4 flex-1 rounded-2xl bg-surface p-4 text-base text-ink-muted">
                            <template x-if="sesi.mode === 'serempak'">
                                <p>Soal dibuka satu per satu oleh server. Timer berjalan bersama untuk seluruh kelas.</p>
                            </template>
                            <template x-if="sesi.mode !== 'serempak'">
                                <p>Kerjakan sendiri dengan urutan soal dan opsi yang diacak untuk Anda. Waktu total mengikuti jumlah durasi soal.</p>
                            </template>
                        </div>

                        <div class="mt-5">
                            <!-- Kondisi 1: Belum Bergabung -->
                            <template x-if="!sesi.attempt">
                                <div>
                                    <template x-if="sesi.mode === 'serempak' && sesi.status === 'berjalan'">
                                        <p class="text-base text-ink-muted">Sesi Serempak sudah dimulai. Bergabung ditutup.</p>
                                    </template>
                                    <template x-if="!(sesi.mode === 'serempak' && sesi.status === 'berjalan') && sesi.penuh">
                                        <p class="text-base text-ink-muted">Kuota peserta sesi ini sudah penuh.</p>
                                    </template>
                                    <template x-if="!(sesi.mode === 'serempak' && sesi.status === 'berjalan') && !sesi.penuh">
                                        <form method="POST" :action="sesi.urls.join">
                                            <input type="hidden" name="_token" :value="csrfToken">
                                            <button type="submit" class="btn btn-primary btn-anak w-full">
                                                Gabung sesi
                                            </button>
                                        </form>
                                    </template>
                                </div>
                            </template>

                            <!-- Kondisi 2: Sudah Selesai -->
                            <template x-if="sesi.attempt && sesi.attempt.status === 'selesai'">
                                <a :href="sesi.urls.result" class="btn btn-secondary btn-anak w-full">
                                    <x-icon name="check" :size="22" />
                                    Lihat hasil
                                </a>
                            </template>

                            <!-- Kondisi 3: Sudah Bergabung, Status Menunggu -->
                            <template x-if="sesi.attempt && sesi.attempt.status === 'menunggu'">
                                <div>
                                    <!-- Jika Sesi Acak dan sudah BERJALAN -> Langsung Tombol Mulai Mengerjakan -->
                                    <template x-if="sesi.mode === 'acak' && sesi.status === 'berjalan'">
                                        <form method="POST" :action="sesi.urls.start">
                                            <input type="hidden" name="_token" :value="csrfToken">
                                            <button type="submit" class="btn btn-primary btn-anak w-full shadow-lift-brand font-bold animate-pulse">
                                                Mulai mengerjakan
                                            </button>
                                        </form>
                                    </template>

                                    <!-- Jika masih Lobby -> Menunggu Admin Memulai -->
                                    <template x-if="!(sesi.mode === 'acak' && sesi.status === 'berjalan')">
                                        <div class="rounded-2xl bg-sun-soft px-4 py-3 text-center text-base font-bold text-sun-deep flex items-center justify-center gap-2">
                                            <svg class="h-5 w-5 animate-spin text-sun-deep" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                            </svg>
                                            <span>Anda sudah bergabung. Tunggu admin memulai sesi.</span>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- Kondisi 4: Sedang Berjalan (Mode Acak) -->
                            <template x-if="sesi.attempt && sesi.attempt.status === 'berjalan' && sesi.mode === 'acak'">
                                <a :href="sesi.urls.show" class="btn btn-primary btn-anak w-full">
                                    Lanjutkan mengerjakan
                                </a>
                            </template>

                            <!-- Kondisi 5: Sedang Berjalan (Mode Serempak) -->
                            <template x-if="sesi.attempt && sesi.attempt.status !== 'selesai' && sesi.attempt.status !== 'menunggu' && sesi.mode === 'serempak'">
                                <a :href="sesi.urls.play" class="btn btn-primary btn-anak w-full">
                                    Masuk ruang sesi
                                </a>
                            </template>
                        </div>
                    </article>
                </template>
            </div>
        </section>

        <section class="mt-10" aria-labelledby="riwayat">
            <h2 id="riwayat" class="text-2xl">Perjalanan Anda</h2>

            @if ($attempts->isEmpty())
                <p class="mt-3 text-base text-ink-muted">Belum ada sesi yang Anda ikuti. Riwayat dan nilai akan tampil di sini.</p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($attempts as $attempt)
                        @php
                            $sesi = $attempt->quizSession;
                            $tautan = match (true) {
                                $attempt->status === 'selesai' && $sesi->mode === 'acak' => route('peserta.attempt.result', $attempt),
                                $attempt->status === 'selesai' => route('peserta.session.result', $sesi),
                                $attempt->status === 'berjalan' && $sesi->mode === 'acak' => route('peserta.attempt.show', $attempt),
                                $attempt->status === 'berjalan' => route('peserta.session.play', $sesi),
                                default => null,
                            };
                            $labelStatus = match ($attempt->status) {
                                'menunggu' => 'Menunggu',
                                'berjalan' => 'Sedang dikerjakan',
                                'selesai' => 'Selesai',
                                default => $attempt->status,
                            };
                        @endphp

                        <li class="flex flex-col gap-3 rounded-2xl border border-outline bg-page p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-display text-lg font-bold text-ink">{{ $sesi->nama }}</p>
                                <p class="text-base text-ink-muted">{{ $sesi->quiz->judul }} · {{ $labelStatus }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                @if ($attempt->status === 'selesai')
                                    <span class="pill {{ $sesi->mode === 'serempak' ? 'pill-sun' : 'pill-leaf' }}">
                                        {{ $sesi->mode === 'serempak' ? $attempt->skor_total.' poin' : $attempt->skor_total.' nilai' }}
                                    </span>
                                @endif
                                @if ($tautan)
                                    <a href="{{ $tautan }}" class="btn btn-secondary">
                                        {{ $attempt->status === 'selesai' ? 'Lihat hasil' : 'Lanjut' }}
                                    </a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.peserta>
