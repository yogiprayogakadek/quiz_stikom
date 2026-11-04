<x-layouts.admin :judul="'Laporan '.$session->nama" :keterangan="$session->quiz->judul.' · '.($session->mode === 'serempak' ? 'Mode Serempak' : 'Mode Acak')">
    <x-slot:aksi>
        <a href="{{ route('admin.sessions.show', $session) }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Ruang kendali
        </a>
        <a href="{{ route('admin.reports.export', $session) }}" class="btn btn-secondary">
            <x-icon name="download" :size="20" />
            Ekspor CSV
        </a>
    </x-slot:aksi>

    <div class="grid gap-5 sm:grid-cols-3">
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
    </div>

    <section class="mt-8 card" aria-labelledby="tabel-nilai">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 id="tabel-nilai" class="text-2xl">Tabel nilai</h2>
            <a
                href="{{ route('admin.reports.session', $tinjau ? ['session' => $session] : ['session' => $session, 'tinjau' => 1]) }}"
                class="btn {{ $tinjau ? 'btn-primary' : 'btn-secondary' }}"
            >
                {{ $tinjau ? 'Tampilkan semua peserta' : 'Hanya perlu ditinjau' }}
            </a>
        </div>

        @if ($attempts->isEmpty())
            <p class="mt-4 text-base text-ink-muted">
                {{ $tinjau ? 'Tidak ada peserta yang perlu ditinjau.' : 'Belum ada peserta di sesi ini.' }}
            </p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="table">
                    <caption class="sr-only">Nilai peserta</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nama</th>
                            <th scope="col">Username</th>
                            <th scope="col">Status</th>
                            <th scope="col">Benar</th>
                            <th scope="col">Skor</th>
                            <th scope="col">Jawaban</th>
                            <th scope="col">Kejadian</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attempts as $attempt)
                            <tr>
                                <td class="font-bold text-ink">{{ $attempt->user?->nama ?? '(peserta dihapus)' }}</td>
                                <td class="font-mono text-base">{{ $attempt->user?->username ?? '-' }}</td>
                                <td>
                                    <span class="pill {{ $attempt->status === 'selesai' ? 'pill-leaf' : ($attempt->status === 'berjalan' ? 'pill-brand' : 'pill-neutral') }}">
                                        {{ ucfirst($attempt->status) }}
                                    </span>
                                </td>
                                <td class="font-mono">{{ $attempt->jumlah_benar }}</td>
                                <td class="font-mono">{{ $attempt->skor_total }}</td>
                                <td class="font-mono">{{ $attempt->answers_count }}</td>
                                <td>
                                    @if ($attempt->events_count >= 3)
                                        <span class="pill pill-coral">Perlu ditinjau</span>
                                    @else
                                        <span class="text-ink-muted">{{ $attempt->events_count }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.reports.attempt', $attempt) }}" class="btn btn-ghost px-3">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="mt-6 card" aria-labelledby="persentase-soal">
        <h2 id="persentase-soal" class="text-2xl">Persentase benar per soal</h2>
        <p class="mt-1 text-base text-ink-muted">Diurutkan dari soal tersulit. Penyebut mengikuti jumlah peserta yang benar-benar menerima soal tersebut.</p>

        @if ($statistikSoal === [])
            <p class="mt-4 text-base text-ink-muted">Belum ada data jawaban.</p>
        @else
            <ul class="mt-4 space-y-4">
                @foreach ($statistikSoal as $stat)
                    <li>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <p class="text-base font-bold text-ink">{{ \Illuminate\Support\Str::limit($stat['teks'], 160) }}</p>
                            <p class="font-mono text-base text-ink-muted">{{ $stat['benar'] }}/{{ $stat['peserta'] }} benar</p>
                        </div>
                        <div class="mt-2 flex items-center gap-3">
                            <div class="progress flex-1">
                                <span style="width: {{ $stat['persen'] }}%"></span>
                            </div>
                            <span class="w-16 text-right font-mono text-base font-bold text-ink">{{ $stat['persen'] }}%</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.admin>
