<x-layouts.admin :judul="$session->nama" :keterangan="$session->quiz->judul.' · '.($session->mode === 'serempak' ? 'Mode Serempak' : 'Mode Acak')">
    <x-slot:aksi>
        @if ($session->status === 'draft')
            <form method="POST" action="{{ route('admin.sessions.lobby', $session) }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <x-icon name="users" :size="20" />
                    Buka lobby
                </button>
            </form>
        @endif

        @if ($session->status === 'lobby')
            <button
                type="button"
                class="btn btn-primary"
                onclick="const f = document.getElementById('form-peserta-sesi'); if (f) { f.action = '{{ route('admin.sessions.start', $session) }}'; f.requestSubmit(); } else { document.getElementById('form-start-fallback').submit(); }"
            >
                <x-icon name="play" :size="20" />
                Mulai sesi
            </button>
            <form id="form-start-fallback" method="POST" action="{{ route('admin.sessions.start', $session) }}" class="hidden">
                @csrf
            </form>
        @endif

        @if ($session->status === 'berjalan' && $session->mode === 'serempak' && $session->fase === 'soal_terbuka')
            <form method="POST" action="{{ route('admin.sessions.skip', $session) }}" onsubmit="return confirm('Tutup soal ini sekarang dan lanjut ke jeda?')">
                @csrf
                <button type="submit" class="btn btn-secondary">Tutup soal sekarang</button>
            </form>
        @endif

        @if (in_array($session->status, ['lobby', 'berjalan'], true))
            <form method="POST" action="{{ route('admin.sessions.close', $session) }}" onsubmit="return confirm('Tutup sesi ini? Semua attempt dikunci dan nilai dihitung dari jawaban tersimpan.')">
                @csrf
                <button type="submit" class="btn btn-soft-danger">Tutup sesi</button>
            </form>
        @endif

        <a href="{{ route('admin.sessions.edit', $session) }}" class="btn btn-secondary">Pengaturan</a>

        @if ($session->status === 'selesai')
            <a href="{{ route('admin.reports.session', $session) }}" class="btn btn-primary">Buka laporan</a>
        @endif
    </x-slot:aksi>

    <section class="card" aria-labelledby="alur-sesi">
        <h2 id="alur-sesi" class="text-2xl">Alur sesi</h2>
        <ol class="mt-4 grid gap-3 sm:grid-cols-4">
            @php
                $tahap = [
                    'draft' => 'Draft',
                    'lobby' => 'Lobby',
                    'berjalan' => 'Berjalan',
                    'selesai' => 'Selesai',
                ];
                $urutan = array_keys($tahap);
                $posisi = array_search($session->status, $urutan, true);
            @endphp
            @foreach ($tahap as $kunci => $label)
                @php
                    $indeks = array_search($kunci, $urutan, true);
                    $selesai = $indeks < $posisi;
                    $aktif = $kunci === $session->status;
                @endphp
                <li class="rounded-2xl border p-3 {{ $aktif ? 'border-brand bg-brand-soft' : ($selesai ? 'border-leaf bg-leaf-soft' : 'border-outline bg-page') }}">
                    <p class="flex items-center gap-2 text-base font-bold {{ $aktif ? 'text-brand-deep' : ($selesai ? 'text-leaf-deep' : 'text-ink-muted') }}">
                        @if ($selesai)
                            <x-icon name="check" :size="18" />
                        @elseif ($aktif)
                            <span class="inline-block h-2.5 w-2.5 rounded-full bg-brand"></span>
                        @else
                            <span class="inline-block h-2.5 w-2.5 rounded-full border-2 border-outline-strong"></span>
                        @endif
                        {{ $label }}
                    </p>
                </li>
            @endforeach
        </ol>

        @if ($session->status === 'draft')
            <p class="mt-4 text-base text-ink-muted">Buka lobby agar peserta bisa bergabung. Setelah lobby dibuka, kuis dan mode terkunci.</p>
        @elseif ($session->status === 'lobby')
            <p class="mt-4 text-base text-ink-muted">Peserta yang bergabung: {{ $totalPeserta }}. Mulai sesi saat kelas siap.</p>
        @endif
    </section>

    <div class="mt-6 grid gap-5 lg:grid-cols-[1.3fr_1fr]">
        <section
            class="card"
            aria-labelledby="layar-proyektor"
            x-data="{
                url: '{{ $session->displayUrl() }}',
                salin(tombol) { window.salinTeks(this.url, tombol); }
            }"
        >
            <h2 id="layar-proyektor" class="text-2xl">Layar proyektor</h2>
            <p class="mt-2 text-base text-ink-muted">Buka di PC proyektor tanpa login. Halaman ini hanya menampilkan progres dan hasil.</p>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                <label for="url-display" class="sr-only">URL layar proyektor</label>
                <input id="url-display" type="text" readonly x-bind:value="url" class="input flex-1 font-mono text-sm">
                <button type="button" class="btn btn-secondary" x-on:click="salin($refs.tombol)" x-ref="tombol">Salin URL</button>
                <a href="{{ $session->displayUrl() }}" target="_blank" rel="noopener" class="btn btn-primary">Buka display</a>
            </div>

            <p class="mt-3 text-sm text-ink-subtle">
                Token layar: <span class="font-mono">{{ \Illuminate\Support\Str::limit($session->display_token, 18) }}</span>
            </p>
        </section>

        <section class="card" id="monitor-status" aria-labelledby="status-langsung">
            @include('admin.sessions._status')
        </section>
    </div>

    <div class="mt-6" id="monitor-pembahasan">
        @include('admin.sessions._pembahasan')
    </div>

    @if (in_array($session->status, ['draft', 'lobby'], true))
        <section
            class="mt-6 card"
            aria-labelledby="pilih-peserta"
            x-data="{
                semua: {{ json_encode($allParticipants->map(fn($p) => [
                    'id' => $p->id,
                    'nama' => $p->nama,
                    'username' => $p->username,
                    'di_lobby' => $attempts->whereNotNull('bergabung_at')->contains('user_id', $p->id)
                ])) }},
                terpilih: {{ json_encode($allowedParticipantIds) }},
                cari: '',
                get filtered() {
                    if (!this.cari.trim()) return this.semua;
                    const q = this.cari.toLowerCase();
                    return this.semua.filter(p => p.nama.toLowerCase().includes(q) || p.username.toLowerCase().includes(q));
                },
                toggleSemua() {
                    if (this.terpilih.length === this.semua.length) {
                        this.terpilih = [];
                    } else {
                        this.terpilih = this.semua.map(p => p.id);
                    }
                },
                isSemuaTerpilih() {
                    return this.semua.length > 0 && this.terpilih.length === this.semua.length;
                }
            }"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="pilih-peserta" class="text-2xl font-bold text-ink">Pilih Peserta yang Hadir &amp; Ikut Kuis</h2>
                    <p class="mt-1 text-base text-ink-muted">
                        Pilih peserta yang hadir di kelas. Hanya peserta yang dicentang yang dapat mengakses dan mengerjakan kuis ini.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="pill pill-brand text-sm font-semibold">
                        <span x-text="terpilih.length"></span> dari <span x-text="semua.length"></span> peserta dipilih
                    </span>
                </div>
            </div>

            <form id="form-peserta-sesi" method="POST" action="{{ route('admin.sessions.start', $session) }}" class="mt-5 space-y-4">
                @csrf

                <!-- Toolbar: Tombol Pilih Semua & Pencarian -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-surface p-3 rounded-2xl border border-outline">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="btn btn-secondary text-sm font-bold py-2 px-3 flex items-center gap-2"
                            @click="toggleSemua()"
                        >
                            <span class="flex h-4 w-4 items-center justify-center rounded border border-ink-muted" :class="isSemuaTerpilih() ? 'bg-brand text-white border-brand' : ''">
                                <svg x-show="isSemuaTerpilih()" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <span x-text="isSemuaTerpilih() ? 'Batalkan Pilih Semua' : 'Pilih Semua Peserta'"></span>
                        </button>
                        <span class="text-xs text-ink-muted hidden sm:inline" x-text="'(' + terpilih.length + ' dipilih)'"></span>
                    </div>

                    <div class="relative w-full sm:w-72">
                        <input
                            type="text"
                            x-model="cari"
                            placeholder="Cari nama atau username..."
                            class="input text-sm py-2 pl-9 w-full"
                        >
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ink-muted">
                            <x-icon name="search" :size="16" />
                        </div>
                    </div>
                </div>

                <!-- List Peserta Grid -->
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 max-h-96 overflow-y-auto p-1 border border-outline rounded-2xl bg-page">
                    <template x-for="p in filtered" :key="p.id">
                        <label
                            class="flex items-center gap-3 p-3 rounded-xl border transition cursor-pointer select-none"
                            :class="terpilih.includes(p.id) ? 'border-brand bg-brand-soft/40' : 'border-outline hover:bg-surface'"
                        >
                            <input
                                type="checkbox"
                                name="peserta_ids[]"
                                :value="p.id"
                                x-model="terpilih"
                                class="h-5 w-5 rounded border-outline text-brand focus:ring-brand flex-shrink-0 cursor-pointer"
                            >
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-sm text-ink truncate" x-text="p.nama"></p>
                                <p class="text-xs text-ink-muted truncate" x-text="p.username"></p>
                            </div>
                            <template x-if="p.di_lobby">
                                <span class="pill pill-leaf text-xs font-bold whitespace-nowrap">Di Lobby</span>
                            </template>
                        </label>
                    </template>
                </div>

                <template x-if="filtered.length === 0">
                    <p class="text-center py-6 text-ink-muted text-sm">Tidak ada peserta yang cocok dengan pencarian.</p>
                </template>

                <!-- Bottom Actions -->
                <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-outline">
                    <p class="text-xs text-ink-muted">
                        Peserta yang tidak dicentang tidak akan dapat mengakses sesi kuis ini.
                    </p>

                    <div class="flex items-center gap-3">
                        <button
                            type="submit"
                            formaction="{{ route('admin.sessions.participants.update', $session) }}"
                            class="btn btn-secondary font-bold"
                        >
                            Simpan Daftar Hadir
                        </button>

                        @if ($session->status === 'lobby')
                            <button
                                type="submit"
                                class="btn btn-primary font-bold flex items-center gap-2"
                                :disabled="terpilih.length === 0"
                                onclick="return confirm('Mulai sesi sekarang? {{ $session->mode === 'serempak' ? 'Soal pertama langsung dibuka untuk peserta terpilih.' : 'Peserta terpilih bisa mulai mengerjakan.' }}')"
                            >
                                <x-icon name="play" :size="18" />
                                <span>Mulai Sesi (<span x-text="terpilih.length"></span> Peserta)</span>
                            </button>
                        @endif
                    </div>
                </div>
            </form>
        </section>
    @endif

    <section class="mt-6 card" id="monitor-peserta" aria-labelledby="peserta-sesi">
        @include('admin.sessions._peserta')
    </section>

    {{-- Live Proctoring Stream Widget --}}
    <section class="mt-6 card shadow-xs border border-slate-200/90 bg-white" id="monitor-proctoring" aria-labelledby="proctoring-heading">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                    <x-icon name="alert" :size="20" />
                </span>
                <div>
                    <h2 id="proctoring-heading" class="text-lg font-bold text-slate-900 leading-tight">Live Proctoring Stream</h2>
                    <p class="text-xs text-slate-500">Pantauan pelanggaran peserta (tab switch, exit fullscreen, copy teks) secara real-time</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span id="online-count">{{ $onlineCount }}</span> Online
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                    <span class="h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span>
                    <span id="proctoring-count">{{ $kejadianTerbaru->count() }}</span> Pelanggaran
                </span>
            </div>
        </div>

        <div id="proctoring-stream-list" class="mt-4 space-y-2.5 max-h-72 overflow-y-auto pr-1">
            @include('admin.sessions._proctoring')
        </div>
    </section>

    @if ($session->mode === 'serempak' && $soalAktif !== null)
        <section class="mt-6 card" aria-labelledby="soal-aktif">
            <h2 id="soal-aktif" class="text-2xl">Soal aktif</h2>
            <p class="mt-3 whitespace-pre-line text-lg text-ink">{{ $soalAktif->question->teks }}</p>
            <p class="mt-2 text-base text-ink-muted">Durasi {{ $soalAktif->question->durasi_detik }} detik. Jawaban benar tidak ditampilkan di sini agar layar admin tetap aman saat dibagikan.</p>
        </section>
    @endif

    @if (in_array($session->status, ['lobby', 'berjalan'], true))
        <script>
            (() => {
                const url = @json(route('admin.sessions.monitor', $session));
                const sessionId = {{ $session->id }};
                const bagian = {
                    status: document.getElementById('monitor-status'),
                    pembahasan: document.getElementById('monitor-pembahasan'),
                    peserta: document.getElementById('monitor-peserta'),
                    proctoring: document.getElementById('proctoring-stream-list'),
                };
                const proctoringCount = document.getElementById('proctoring-count');
                const onlineCount = document.getElementById('online-count');
                let terakhir = null;

                const perbarui = async () => {
                    if (document.hidden) return;

                    try {
                        const res = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                        if (!res.ok || res.redirected) return;

                        const data = await res.json();
                        const kunci = data.status + data.pembahasan + data.peserta + data.proctoring;
                        if (kunci === terakhir) return;

                        terakhir = kunci;
                        if (bagian.status) bagian.status.innerHTML = data.status;
                        if (bagian.pembahasan) bagian.pembahasan.innerHTML = data.pembahasan;
                        if (bagian.peserta) bagian.peserta.innerHTML = data.peserta;
                        if (bagian.proctoring) bagian.proctoring.innerHTML = data.proctoring;
                        if (proctoringCount) proctoringCount.textContent = data.proctoring_count;
                        if (onlineCount) onlineCount.textContent = data.online_count;
                    } catch (e) {
                        // Jaringan putus sesaat. Putaran berikutnya mencoba lagi.
                    }
                };

                // Real-time WebSocket via Reverb
                const mainkanChime = () => {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(520, ctx.currentTime);
                        osc.frequency.exponentialRampToValueAtTime(780, ctx.currentTime + 0.15);
                        gain.gain.setValueAtTime(0.25, ctx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.25);
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start();
                        osc.stop(ctx.currentTime + 0.25);
                    } catch (e) {}
                };

                const tambahKejadian = (data) => {
                    mainkanChime();
                    const countEl = document.getElementById('proctoring-count');
                    if (countEl) {
                        countEl.textContent = parseInt(countEl.textContent || '0', 10) + 1;
                    }
                    const emptyEl = document.getElementById('proctoring-empty');
                    if (emptyEl) emptyEl.remove();

                    const listEl = document.getElementById('proctoring-stream-list');
                    if (listEl) {
                        const item = document.createElement('div');
                        const isDanger = data.level === 'danger';
                        item.className = 'flex items-start justify-between gap-3 p-3 rounded-xl border border-rose-300 bg-rose-50 text-xs transition duration-300 animate-pulse';
                        item.innerHTML = `
                            <div class="flex items-start gap-2.5 min-w-0">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg ${isDanger ? 'bg-rose-200 text-rose-800' : 'bg-amber-200 text-amber-800'} font-bold">
                                    !
                                </span>
                                <div class="min-w-0">
                                    <p class="font-bold text-slate-900">${data.nama || 'Peserta'} <span class="font-mono text-slate-500 font-normal">(${data.username || '-'})</span></p>
                                    <p class="text-slate-600 mt-0.5">${data.pesan || 'Pelanggaran terdeteksi'}</p>
                                </div>
                            </div>
                            <span class="font-mono text-[11px] text-slate-400 shrink-0">${data.waktu || 'Baru saja'}</span>
                        `;
                        listEl.prepend(item);
                        setTimeout(() => item.classList.remove('animate-pulse'), 3000);
                    }
                };

                const pasangEcho = () => {
                    if (typeof window.buatEcho !== 'function') return;
                    const echo = window.buatEcho();
                    if (!echo) return;

                    echo.channel('session-monitor.' + sessionId)
                        .listen('.SessionMonitorUpdated', () => {
                            perbarui();
                        });

                    echo.channel('quiz-sessions')
                        .listen('.SesiStatusUpdated', (data) => {
                            if (data.session_id === sessionId) {
                                perbarui();
                            }
                        });

                    @if ($session->display_token)
                        echo.channel('display.' + @json($session->display_token))
                            .listen('.PesertaPeringatan', (data) => {
                                tambahKejadian(data);
                                perbarui();
                            });
                    @endif
                };

                // buatEcho didefinisikan di app.js (module) yang dieksekusi setelah parsing,
                // jadi pasang listener setelah DOM siap.
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', pasangEcho);
                } else {
                    pasangEcho();
                }
                // Polling cadangan (fallback jika koneksi WebSocket terganggu)
                setInterval(perbarui, 5000);
            })();
        </script>
    @endif
</x-layouts.admin>
