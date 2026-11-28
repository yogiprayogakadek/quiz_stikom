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
        @if ($hasEssay)
            <form method="POST" action="{{ route('admin.reports.session.scan-plagiarism', $session) }}" class="inline">
                @csrf
                <button type="submit" class="btn btn-secondary">
                    <x-icon name="shield-check" :size="20" />
                    Pindai Ulang Plagiarisme
                </button>
            </form>
        @endif
        @if ($session->quiz->sourceQuiz)
            <a href="{{ route('admin.quizzes.edit', $session->quiz->sourceQuiz) }}" class="btn btn-secondary">Buka ujian asli</a>
        @endif
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

    @if ($session->status === 'selesai')
        <section class="mb-5 card" aria-labelledby="publikasi-nilai">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 id="publikasi-nilai" class="text-2xl">Publikasi nilai peserta</h2>
                        <span class="pill {{ $session->nilaiTampil() ? 'pill-leaf' : 'pill-neutral' }}">
                            {{ $session->nilaiTampil() ? 'Ditampilkan' : 'Disembunyikan' }}
                        </span>
                    </div>
                    <p class="mt-2 max-w-2xl text-base text-ink-muted">
                        @if ($session->nilaiTampil())
                            Peserta dapat melihat nilai, rincian skor, dan peringkat sesi ini.
                        @elseif ($nilaiSiapDipublikasikan)
                            Seluruh pekerjaan sudah final. Nilai siap ditampilkan kepada peserta.
                        @elseif ($essayMenungguPublikasi > 0)
                            Nilai belum dapat ditampilkan karena {{ $essayMenungguPublikasi }} jawaban essay masih menunggu penilaian.
                        @else
                            Nilai belum dapat ditampilkan karena masih ada peserta yang belum menyelesaikan attempt.
                        @endif
                    </p>
                </div>

                <form
                    method="POST"
                    action="{{ route('admin.sessions.nilai', $session) }}"
                    data-progress-message="Memperbarui publikasi nilai..."
                    @if ($session->nilaiTampil()) onsubmit="return confirm('Sembunyikan nilai sesi ini? Angka nilai dan peringkat akan langsung hilang dari tampilan peserta.')" @endif
                >
                    @csrf
                    <input type="hidden" name="status" value="{{ $session->nilaiTampil() ? 'disembunyikan' : 'ditampilkan' }}">
                    <button
                        type="submit"
                        class="btn {{ $session->nilaiTampil() ? 'btn-secondary' : 'btn-primary' }} w-full sm:w-auto"
                        @disabled(! $session->nilaiTampil() && ! $nilaiSiapDipublikasikan)
                    >
                        <x-icon name="{{ $session->nilaiTampil() ? 'eye-off' : 'eye' }}" :size="20" />
                        {{ $session->nilaiTampil() ? 'Sembunyikan nilai' : 'Tampilkan nilai' }}
                    </button>
                </form>
            </div>
        </section>
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4 {{ $hasEssay ? 'xl:grid-cols-5' : '' }}">
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
        @if ($hasEssay)
            <div class="card {{ $indikasiPlagiat > 0 ? 'border-coral bg-coral-soft' : '' }}">
                <p class="text-base font-bold text-ink-muted">Indikasi kemiripan</p>
                <p class="display-number mt-2 text-5xl">{{ $indikasiPlagiat }}</p>
                <p class="mt-2 text-sm text-ink-muted">Jawaban essay dengan kemiripan &ge; 50% antar-peserta.</p>
            </div>
        @endif
    </div>

    <section class="mt-8 card shadow-xs border border-slate-200/90 bg-white" aria-labelledby="tabel-nilai">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100">
            <div>
                <h2 id="tabel-nilai" class="text-xl font-bold text-slate-900">Tabel Nilai & Hasil Ujian</h2>
                <p class="text-xs text-slate-500">Rekap perolehan skor dan catatan proctoring tiap peserta. Saran AI bukan nilai final.</p>
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
                                    <th scope="col">Saran AI</th>
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
                                        <td class="text-sm">
                                            @if ($attempt->saran_ai_status === 'kosong')
                                                <span class="text-slate-500">-</span>
                                            @else
                                                <span class="pill {{ $attempt->saran_ai_status === 'sudah' ? 'pill-leaf' : ($attempt->saran_ai_status === 'sebagian' ? 'pill-sun' : 'pill-neutral') }} text-xs">
                                                    {{ $attempt->saran_ai_status === 'sudah' ? 'Sudah dicek' : ($attempt->saran_ai_status === 'sebagian' ? 'Sebagian' : 'Belum dicek') }}
                                                </span>
                                                @if ($attempt->saran_ai_status !== 'belum')
                                                    <span class="mt-1 block font-mono text-sm font-semibold text-slate-900">{{ (int) $attempt->essay_ai_poin }} poin</span>
                                                @endif
                                                <span class="block text-xs text-slate-500">{{ $attempt->essay_ai }}/{{ $attempt->essay_dijawab }} essay</span>
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
            <p class="mt-4 text-base text-ink-muted">Belum ada data jawaban soal objektif.</p>
        @else
            <ul class="mt-4 space-y-4">
                @foreach ($statistikSoal as $stat)
                    @php
                        $dialogId = 'dialog-ralat-'.$stat['question_id'];
                        $kunciAwal = collect($stat['options'] ?? [])->firstWhere('is_correct', true)['id'] ?? ($stat['options'][0]['id'] ?? null);
                    @endphp
                    <li class="rounded-2xl border border-outline bg-page p-4" x-data="{ modeRalat: '{{ ($stat['is_bonus'] ?? false) ? 'batalkan_bonus' : 'ubah_kunci' }}' }">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex flex-wrap items-center gap-2 min-w-0">
                                <span class="pill {{ ($stat['is_bonus'] ?? false) ? 'pill-brand' : (($stat['kategori'] ?? '') === 'Sukar' ? 'pill-coral' : (($stat['kategori'] ?? '') === 'Sedang' ? 'pill-sun' : 'pill-leaf')) }} text-xs font-bold">
                                    {{ ($stat['is_bonus'] ?? false) ? 'Bonus / Dianulir' : ($stat['kategori'] ?? 'Sedang') }}
                                </span>
                                <p class="text-base font-bold text-ink">{{ \Illuminate\Support\Str::limit($stat['teks'], 160) }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <p class="font-mono text-base text-ink-muted">{{ $stat['benar'] }}/{{ $stat['peserta'] }} benar</p>
                                @if (! empty($stat['question_id']))
                                    <button
                                        type="button"
                                        class="btn btn-secondary min-h-11 px-3 text-xs"
                                        onclick="document.getElementById('{{ $dialogId }}')?.showModal()"
                                    >
                                        Ralat kunci / bonus
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="mt-2 flex items-center gap-3">
                            <div class="progress flex-1">
                                <span style="width: {{ $stat['persen'] }}%"></span>
                            </div>
                            <span class="w-16 text-right font-mono text-base font-bold text-ink">{{ $stat['persen'] }}%</span>
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-muted">
                            <span>Rata-rata waktu: <strong class="text-ink">{{ $stat['rata_detik'] !== null ? $stat['rata_detik'].' dtk' : '-' }}</strong></span>
                            <span>Daya beda: <strong class="text-ink">{{ $stat['daya_beda'] !== null ? number_format($stat['daya_beda'], 2) : 'butuh minimal 6 peserta' }}</strong></span>
                        </div>

                        @if (! empty($stat['question_id']))
                            <dialog id="{{ $dialogId }}" class="m-auto w-full max-w-lg rounded-2xl border border-outline bg-surface p-6 text-ink shadow-xl backdrop:bg-slate-900/50">
                                <form method="POST" action="{{ route('admin.reports.session.regrade-question', [$session, $stat['question_id']]) }}" class="space-y-4">
                                    @csrf
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-lg font-bold text-ink">Ralat kunci atau anulir soal</h3>
                                            <p class="mt-1 text-sm text-ink-muted">{{ \Illuminate\Support\Str::limit($stat['teks'], 120) }}</p>
                                        </div>
                                        <button type="button" class="btn btn-ghost min-h-11 min-w-11 px-3" aria-label="Tutup dialog ralat" onclick="this.closest('dialog').close()">
                                            <x-icon name="x" :size="18" />
                                        </button>
                                    </div>

                                    <fieldset class="space-y-2">
                                        <legend class="label">Tindakan koreksi</legend>
                                        @if (! empty($stat['options']) && ($stat['tipe'] ?? 'pilihan_ganda') !== 'isian_singkat')
                                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-outline bg-page px-3 py-2.5 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                                                <input type="radio" name="mode_ralat" value="ubah_kunci" x-model="modeRalat" class="h-4 w-4">
                                                <span class="text-sm font-semibold text-ink">Pindahkan kunci jawaban yang benar</span>
                                            </label>
                                        @endif
                                        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-outline bg-page px-3 py-2.5 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                                            <input type="radio" name="mode_ralat" value="anulir_bonus" x-model="modeRalat" class="h-4 w-4">
                                            <span class="text-sm font-semibold text-ink">Anulir soal &amp; beri poin penuh ke semua peserta</span>
                                        </label>
                                        @if ($stat['is_bonus'] ?? false)
                                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-outline bg-page px-3 py-2.5 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                                                <input type="radio" name="mode_ralat" value="batalkan_bonus" x-model="modeRalat" class="h-4 w-4">
                                                <span class="text-sm font-semibold text-ink">Batalkan bonus &amp; kembali ke kunci soal</span>
                                            </label>
                                        @endif
                                    </fieldset>

                                    @if (! empty($stat['options']) && ($stat['tipe'] ?? 'pilihan_ganda') !== 'isian_singkat')
                                        <fieldset x-show="modeRalat === 'ubah_kunci'" x-cloak class="space-y-2 rounded-xl border border-outline bg-page p-3">
                                            <legend class="label">Pilih kunci jawaban baru</legend>
                                            @foreach ($stat['options'] as $opsi)
                                                @if (($stat['tipe'] ?? 'pilihan_ganda') === 'pilihan_majemuk')
                                                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-surface">
                                                        <input type="checkbox" name="correct_option_ids[]" value="{{ $opsi['id'] }}" @checked($opsi['is_correct']) class="h-4 w-4">
                                                        <span class="text-sm text-ink">{{ $opsi['teks'] }}</span>
                                                    </label>
                                                @else
                                                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-surface">
                                                        <input type="radio" name="correct_option_id" value="{{ $opsi['id'] }}" @checked((int) $kunciAwal === (int) $opsi['id']) class="h-4 w-4">
                                                        <span class="text-sm text-ink">{{ $opsi['teks'] }}</span>
                                                    </label>
                                                @endif
                                            @endforeach
                                        </fieldset>
                                    @endif

                                    <div>
                                        <label for="alasan-ralat-{{ $stat['question_id'] }}" class="label">Alasan koreksi (dicatat di audit log)</label>
                                        <textarea id="alasan-ralat-{{ $stat['question_id'] }}" name="alasan" rows="2" maxlength="500" class="input" placeholder="Contoh: Kunci awal tertukar antara pilihan A dan B."></textarea>
                                    </div>

                                    <div class="flex flex-wrap justify-end gap-2 pt-2">
                                        <button type="button" class="btn btn-ghost min-h-11" onclick="this.closest('dialog').close()">Batal</button>
                                        <button type="submit" class="btn btn-primary min-h-11">Simpan &amp; hitung ulang nilai</button>
                                    </div>
                                </form>
                            </dialog>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.admin>
