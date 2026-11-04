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

        <section class="card" aria-labelledby="status-langsung">
            <h2 id="status-langsung" class="text-2xl">Status langsung</h2>

            @if ($session->status === 'lobby' || $session->status === 'draft')
                <p class="mt-3 text-base text-ink-muted">Peserta yang sudah bergabung</p>
                <p class="display-number mt-1 text-5xl">{{ $totalPeserta }}</p>
                @if ($attempts->isNotEmpty())
                    <ul class="mt-3 space-y-1 text-base text-ink">
                        @foreach ($attempts as $attempt)
                            <li class="truncate">{{ $attempt->user?->nama ?? '(peserta dihapus)' }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-base text-ink-muted">Belum ada peserta. Minta peserta masuk dan menekan Gabung dari dashboard mereka.</p>
                @endif
            @elseif ($session->status === 'berjalan' && $session->mode === 'acak')
                @php $selesai = $attempts->where('status', 'selesai')->count(); @endphp
                <p class="mt-3 text-base text-ink-muted">Peserta selesai</p>
                <p class="display-number mt-1 text-5xl">{{ $selesai }}<span class="text-3xl text-ink-muted">/{{ $totalPeserta }}</span></p>
                <p class="mt-3 text-base text-ink-muted">Sisa peserta yang masih mengerjakan: {{ $totalPeserta - $selesai }}</p>
            @elseif ($session->status === 'berjalan' && $session->mode === 'serempak')
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <x-status-badge :status="$session->status" />
                    <span class="pill pill-neutral">{{ $session->fase === 'jeda' ? 'Jeda' : 'Soal terbuka' }}</span>
                    <span class="pill pill-brand">Soal {{ $session->soal_aktif_urutan ?? '-' }} dari {{ $session->sessionQuestions()->count() }}</span>
                </div>
                <p class="mt-3 text-base text-ink-muted">Sudah menjawab</p>
                <p class="display-number mt-1 text-5xl">{{ $sudahMenjawab }}<span class="text-3xl text-ink-muted">/{{ $totalPeserta }}</span></p>

                @if ($session->fase === 'soal_terbuka' && $session->soal_berakhir_at)
                    <div
                        x-data="{
                            sisa: 0,
                            format(n) {
                                const menit = Math.floor(n / 60);
                                const detik = n % 60;
                                return menit + ':' + String(detik).padStart(2, '0');
                            },
                            init() {
                                const akhir = {{ $session->soal_berakhir_at }};
                                const selisih = Date.now() - {{ now()->getTimestampMs() }};
                                const hitung = () => {
                                    this.sisa = Math.max(0, Math.round((akhir - (Date.now() - selisih)) / 1000));
                                };
                                hitung();
                                setInterval(hitung, 250);
                            }
                        }"
                        class="mt-4 rounded-2xl bg-sun-soft px-4 py-3"
                    >
                        <p class="text-base font-bold text-sun-deep">Sisa waktu soal</p>
                        <p class="timer mt-1 text-4xl text-ink" x-text="format(sisa)">0:00</p>
                    </div>
                @else
                    <p class="mt-4 rounded-2xl bg-brand-soft px-4 py-3 text-base text-brand-deep">
                        Jeda {{ $session->jeda_detik }} detik. Soal berikutnya dibuka otomatis oleh server.
                    </p>
                @endif
            @else
                <p class="mt-3 text-base text-ink-muted">Sesi selesai. Semua attempt sudah dikunci.</p>
                <p class="display-number mt-1 text-5xl">{{ $totalPeserta }}</p>
                <p class="text-base text-ink-muted">peserta mengikuti sesi ini.</p>
            @endif
        </section>
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
                    'di_lobby' => $attempts->contains('user_id', $p->id)
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

    <section class="mt-6 card" aria-labelledby="peserta-sesi">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="peserta-sesi" class="text-2xl">Peserta di sesi ini</h2>
            <a href="{{ route('admin.reports.session', $session) }}" class="btn btn-secondary">Lihat laporan</a>
        </div>

        @if ($attempts->isEmpty())
            <p class="mt-3 text-base text-ink-muted">Belum ada peserta yang bergabung.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="table">
                    <caption class="sr-only">Peserta dan status attempt</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nama</th>
                            <th scope="col">Status</th>
                            <th scope="col">Benar</th>
                            <th scope="col">Skor</th>
                            <th scope="col">Kejadian</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attempts as $attempt)
                            <tr>
                                <td class="font-bold text-ink">{{ $attempt->user?->nama ?? '(peserta dihapus)' }}</td>
                                <td>
                                    <span class="pill {{ $attempt->status === 'selesai' ? 'pill-leaf' : ($attempt->status === 'berjalan' ? 'pill-brand' : 'pill-neutral') }}">
                                        {{ ucfirst($attempt->status) }}
                                    </span>
                                </td>
                                <td class="font-mono">{{ $attempt->jumlah_benar }}</td>
                                <td class="font-mono">{{ $attempt->skor_total }}</td>
                                <td>
                                    @if ($attempt->events_count >= 3)
                                        <span class="pill pill-coral">Perlu ditinjau</span>
                                    @else
                                        <span class="text-ink-muted">{{ $attempt->events_count }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('admin.reports.attempt', $attempt) }}" class="btn btn-ghost px-3">Detail</a>

                                        @if ($session->status === 'berjalan' && $attempt->status === 'berjalan')
                                            <form
                                                method="POST"
                                                action="{{ route('admin.sessions.attempts.finish', [$session, $attempt]) }}"
                                                onsubmit="return confirm('Kumpulkan pekerjaan peserta ini sekarang? Nilai dihitung dari jawaban yang sudah tersimpan.')"
                                            >
                                                @csrf
                                                <button type="submit" class="btn btn-secondary px-3 whitespace-nowrap">Kumpulkan</button>
                                            </form>
                                        @endif

                                        @if (in_array($session->status, ['lobby', 'berjalan'], true))
                                            <form
                                                method="POST"
                                                action="{{ route('admin.sessions.attempts.kick', [$session, $attempt]) }}"
                                                onsubmit="return confirm('Keluarkan peserta ini dari sesi? Jawaban yang sudah tersimpan ikut terhapus dan peserta tidak bisa bergabung lagi.')"
                                            >
                                                @csrf
                                                <button type="submit" class="btn btn-soft-danger px-3 whitespace-nowrap">Keluarkan</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($session->mode === 'serempak' && $soalAktif !== null)
        <section class="mt-6 card" aria-labelledby="soal-aktif">
            <h2 id="soal-aktif" class="text-2xl">Soal aktif</h2>
            <p class="mt-3 whitespace-pre-line text-lg text-ink">{{ $soalAktif->question->teks }}</p>
            <p class="mt-2 text-base text-ink-muted">Durasi {{ $soalAktif->question->durasi_detik }} detik. Jawaban benar tidak ditampilkan di sini agar layar admin tetap aman saat dibagikan.</p>
        </section>
    @endif
</x-layouts.admin>
