<x-layouts.admin :judul="'Laporan '.$session->nama" :keterangan="$session->quiz->judul.' · '.($session->mode === 'serempak' ? 'Mode Serempak' : 'Mode Acak')">
    <x-slot:aksi>
        <a href="{{ route('admin.sessions.show', $session) }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Ruang kendali
        </a>
        <a href="{{ route('admin.reports.berita-acara', $session) }}" target="_blank" class="btn btn-secondary">
            <x-icon name="printer" :size="20" />
            Berita Acara
        </a>
        <a href="{{ route('admin.reports.export', $session) }}" class="btn btn-secondary">
            <x-icon name="download" :size="20" />
            Ekspor CSV
        </a>
        @if ($session->jenis === 'utama' && $session->status === 'selesai' && ! $session->quiz->is_percobaan)
            @php $remidi = $session->remidi->first(); @endphp
            @if ($remidi)
                <a href="{{ route('admin.sessions.remidi.laporan', $remidi) }}" class="btn btn-primary">Laporan remidi</a>
            @else
                <a href="{{ route('admin.sessions.remidi.create', $session) }}" class="btn btn-primary">Buat remidi</a>
            @endif
        @endif
        @if ($session->jenis === 'remidi')
            <a href="{{ route('admin.sessions.remidi.laporan', $session) }}" class="btn btn-primary">Laporan remidi</a>
        @endif
    </x-slot:aksi>

    @if ($session->quiz->is_percobaan)
        <div class="mb-5 rounded-2xl border border-outline bg-surface px-5 py-4">
            <span class="pill pill-neutral">Kuis percobaan</span>
            <span class="ml-2 text-base font-semibold text-ink-muted">Skor tidak masuk leaderboard dan tidak menambah total skor peserta.</span>
        </div>
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card">
            <p class="text-base font-bold text-ink-muted">Peserta</p>
            <p class="display-number mt-2 text-5xl">{{ $totalAttempt }}</p>
        </div>
        <div class="card">
            <p class="text-base font-bold text-ink-muted">Selesai</p>
            <p class="display-number mt-2 text-5xl">{{ $selesai }}</p>
        </div>
        <div class="card {{ $perluTinjau > 0 ? 'border-coral bg-coral-soft' : '' }}">
            <p class="text-base font-bold text-ink-muted">Perlu ditinjau</p>
            <p class="display-number mt-2 text-5xl">{{ $perluTinjau }}</p>
            <p class="mt-2 text-sm text-ink-muted">Peserta dengan 3 kejadian atau lebih. Tidak ada diskualifikasi otomatis.</p>
        </div>
        <div class="card {{ $essayBelumDinilai > 0 ? 'border-sun bg-sun-soft' : '' }}">
            <p class="text-base font-bold text-ink-muted">Essay belum dinilai</p>
            <p class="display-number mt-2 text-5xl">{{ $essayBelumDinilai }}</p>
            <p class="mt-2 text-sm text-ink-muted">Jawaban essay yang menunggu penilaian manual admin.</p>
        </div>
    </div>

    <section class="mt-8 card shadow-xs border border-slate-200/90 bg-white" aria-labelledby="tabel-nilai">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100">
            <div>
                <h2 id="tabel-nilai" class="text-xl font-bold text-slate-900">Tabel Nilai & Hasil Ujian</h2>
                <p class="text-xs text-slate-500">Rekap perolehan skor dan catatan proctoring tiap peserta</p>
            </div>
            <a
                href="{{ route('admin.reports.session', $tinjau ? ['session' => $session] : ['session' => $session, 'tinjau' => 1]) }}"
                class="btn {{ $tinjau ? 'btn-primary' : 'btn-secondary' }} text-xs !py-1.5 !px-3"
            >
                {{ $tinjau ? 'Tampilkan semua peserta' : 'Hanya perlu ditinjau' }}
            </a>
        </div>

        @if ($attempts->isEmpty())
            <p class="mt-4 text-base text-ink-muted">
                {{ $tinjau ? 'Tidak ada peserta yang perlu ditinjau.' : 'Belum ada peserta di sesi ini.' }}
            </p>
        @else
            <div class="mt-4">
                <x-data-table
                    search-placeholder="Cari nama atau username peserta..."
                    :per-page="10"
                    :per-page-options="[10, 25, 50, 100]"
                    empty-message="Tidak ada peserta yang cocok dengan filter."
                >
                    <div class="overflow-x-auto rounded-xl border border-slate-200/90">
                        <table class="table">
                            <caption class="sr-only">Nilai peserta</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Nama</th>
                                    <th scope="col">Username</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">PG benar</th>
                                    <th scope="col">Poin / nilai</th>
                                    <th scope="col">Jawaban</th>
                                    <th scope="col">Kejadian</th>
                                    <th scope="col" class="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($attempts as $attempt)
                                    <tr
                                        data-item
                                        data-search="{{ strtolower(($attempt->user?->nama ?? '') . ' ' . ($attempt->user?->username ?? '')) }}"
                                        class="transition hover:bg-slate-50/75"
                                    >
                                        <td class="font-bold text-slate-900 text-sm">
                                            {{ $attempt->user?->nama ?? '(peserta dihapus)' }}
                                        </td>
                                        <td>
                                            <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200">
                                                {{ $attempt->user?->username ?? '-' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="pill {{ $attempt->status === 'selesai' ? 'pill-leaf' : ($attempt->status === 'berjalan' ? 'pill-brand' : 'pill-neutral') }} text-xs">
                                                {{ ucfirst($attempt->status) }}
                                            </span>
                                            @if ($attempt->essay_belum_dinilai > 0)
                                                <span class="pill pill-sun mt-1 block w-fit text-[11px]">Essay belum dinilai</span>
                                            @endif
                                        </td>
                                        <td class="font-mono text-sm font-semibold">{{ $attempt->jumlah_benar }}</td>
                                        <td class="font-mono text-sm">
                                            @if ($session->mode === 'acak' && $attempt->poin_maksimal > 0)
                                                <span class="font-bold text-slate-900">{{ $attempt->poin_total }}/{{ $attempt->poin_maksimal }}</span>
                                                <span class="block text-xs text-slate-500">Nilai {{ $attempt->skor_total }}</span>
                                            @else
                                                <span class="font-bold text-slate-900">{{ $attempt->skor_total }}</span>
                                            @endif
                                        </td>
                                        <td class="font-mono text-sm">{{ $attempt->answers_count }}</td>
                                        <td>
                                            @if ($attempt->events_count >= 3)
                                                <span class="pill pill-coral text-xs">Perlu ditinjau</span>
                                            @else
                                                <span class="text-slate-500 font-mono text-sm">{{ $attempt->events_count }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="flex items-center justify-end">
                                                <a href="{{ route('admin.reports.attempt', $attempt) }}" class="btn btn-secondary !py-1 !px-3 text-xs">
                                                    Detail
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-data-table>
            </div>
        @endif
    </section>

    <section class="mt-6 card" aria-labelledby="persentase-soal">
        <h2 id="persentase-soal" class="text-2xl">Analisis butir soal pilihan ganda</h2>
        <p class="mt-1 text-base text-ink-muted">Diurutkan dari soal tersulit. Penyebut mengikuti jumlah peserta yang benar-benar menerima soal tersebut. Daya beda membandingkan 27% peserta dengan skor tertinggi dan terendah (butuh minimal 6 peserta). Essay dinilai manual dan tidak masuk statistik ini.</p>

        @if ($essayBelumDinilai > 0)
            <div class="mt-4 rounded-2xl bg-sun-soft px-4 py-3">
                <p class="text-base font-bold text-ink">{{ $essayBelumDinilai }} jawaban essay belum dinilai.</p>
                <p class="mt-1 text-sm text-ink-muted">Buka detail peserta untuk memberi nilai essay. Poin attempt akan dihitung ulang setelah disimpan.</p>
            </div>
        @endif

        @if ($statistikSoal === [])
            <p class="mt-4 text-base text-ink-muted">Belum ada data jawaban pilihan ganda.</p>
        @else
            <ul class="mt-4 space-y-4">
                @foreach ($statistikSoal as $stat)
                    <li>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-center gap-2">
                                <span class="pill {{ ($stat['kategori'] ?? '') === 'Sukar' ? 'pill-coral' : (($stat['kategori'] ?? '') === 'Sedang' ? 'pill-sun' : 'pill-leaf') }} text-xs font-bold">
                                    {{ $stat['kategori'] ?? 'Sedang' }}
                                </span>
                                <p class="text-base font-bold text-ink">{{ \Illuminate\Support\Str::limit($stat['teks'], 160) }}</p>
                            </div>
                            <p class="font-mono text-base text-ink-muted shrink-0">{{ $stat['benar'] }}/{{ $stat['peserta'] }} benar</p>
                        </div>
                        <div class="mt-2 flex items-center gap-3">
                            <div class="progress flex-1">
                                <span style="width: {{ $stat['persen'] }}%"></span>
                            </div>
                            <span class="w-16 text-right font-mono text-base font-bold text-ink">{{ $stat['persen'] }}%</span>
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-muted">
                            <span>Rata-rata waktu: <strong class="text-ink">{{ $stat['rata_detik'] !== null ? $stat['rata_detik'].' dtk' : '—' }}</strong></span>
                            <span>Daya beda: <strong class="text-ink">{{ $stat['daya_beda'] !== null ? number_format($stat['daya_beda'], 2) : 'butuh minimal 6 peserta' }}</strong></span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.admin>
