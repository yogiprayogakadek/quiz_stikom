@php
    $kkm = $ringkasan['kkm'];
@endphp

<x-layouts.admin :judul="'Laporan Remidi '.$session->nama" :keterangan="'Dibandingkan dengan '.$sesiAwal->nama.' · '.$sesiAwal->quiz->judul">
    <x-slot:aksi>
        <a href="{{ route('admin.sessions.show', $session) }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Ruang kendali
        </a>
        <a href="{{ route('admin.sessions.remidi.export', $session) }}" class="btn btn-secondary">
            <x-icon name="download" :size="20" />
            Ekspor CSV
        </a>
        <form method="POST" action="{{ route('admin.sessions.remidi.terapkan', $session) }}" onsubmit="return confirm('Hitung ulang nilai akhir untuk semua peserta yang sudah menyelesaikan remidi?')">
            @csrf
            <button type="submit" class="btn btn-primary">Hitung ulang nilai</button>
        </form>
    </x-slot:aksi>

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card">
            <p class="text-base font-bold text-ink-muted">Peserta</p>
            <p class="display-number mt-2 text-5xl">{{ $ringkasan['total'] }}</p>
        </div>
        <div class="card">
            <p class="text-base font-bold text-ink-muted">Sudah remidi</p>
            <p class="display-number mt-2 text-5xl">{{ $ringkasan['sudah_remidi'] }}</p>
        </div>
        <div class="card">
            <p class="text-base font-bold text-ink-muted">Rata-rata awal</p>
            <p class="display-number mt-2 text-5xl">{{ number_format($ringkasan['rata_awal'], 1, ',', '.') }}</p>
        </div>
        <div class="card">
            <p class="text-base font-bold text-ink-muted">Rata-rata remidi</p>
            <p class="display-number mt-2 text-5xl">{{ number_format($ringkasan['rata_remidi'], 1, ',', '.') }}</p>
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="card">
            <h2 class="text-2xl">Perbandingan nilai</h2>
            <p class="mt-1 text-base text-ink-muted">
                Naik {{ $ringkasan['naik'] }}, turun {{ $ringkasan['turun'] }}, tetap {{ $ringkasan['tetap'] }}.
                @if ($kkm !== null)
                    Nilai minimum lulus {{ $kkm }}.
                @endif
            </p>

            @if ($baris->isEmpty())
                <div class="mt-5 rounded-2xl border border-outline bg-page px-4 py-6">
                    <p class="text-base text-ink-muted">Belum ada peserta pada sesi remidi ini.</p>
                </div>
            @else
                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[52rem] text-left text-base">
                        <thead>
                            <tr class="border-b border-outline text-sm font-bold text-ink-muted">
                                <th scope="col" class="py-3 pr-3">Peserta</th>
                                <th scope="col" class="py-3 pr-3 text-right">Awal</th>
                                <th scope="col" class="py-3 pr-3 text-right">Remidi</th>
                                <th scope="col" class="py-3 pr-3 text-right">Selisih</th>
                                <th scope="col" class="py-3 pr-3 text-right">Akhir</th>
                                <th scope="col" class="py-3 pr-3">Sumber</th>
                                <th scope="col" class="py-3 pr-3">Status</th>
                                <th scope="col" class="py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline">
                            @foreach ($baris as $row)
                                <tr>
                                    <td class="py-3 pr-3">
                                        <p class="font-bold text-ink">{{ $row['user']->nama }}</p>
                                        <p class="text-sm text-ink-muted">{{ $row['user']->username }}</p>
                                    </td>
                                    <td class="py-3 pr-3 text-right tabular-nums">{{ $row['nilai_awal'] ?? '-' }}</td>
                                    <td class="py-3 pr-3 text-right tabular-nums">{{ $row['nilai_remidi'] ?? '-' }}</td>
                                    <td class="py-3 pr-3 text-right tabular-nums font-bold {{ ($row['selisih'] ?? 0) > 0 ? 'text-leaf-deep' : (($row['selisih'] ?? 0) < 0 ? 'text-coral-deep' : 'text-ink-muted') }}">
                                        @if ($row['selisih'] === null)
                                            -
                                        @else
                                            {{ $row['selisih'] > 0 ? '+' : '' }}{{ $row['selisih'] }}
                                        @endif
                                    </td>
                                    <td class="py-3 pr-3 text-right tabular-nums font-bold">{{ $row['nilai_akhir'] ?? '-' }}</td>
                                    <td class="py-3 pr-3">
                                        <span class="pill {{ $row['sumber'] === 'remidi' ? 'pill-leaf' : 'pill-neutral' }}">
                                            {{ $row['sumber'] === 'remidi' ? 'Remidi' : 'Awal' }}
                                        </span>
                                    </td>
                                    <td class="py-3 pr-3">
                                        <div class="flex flex-wrap gap-1.5">
                                            @if ($row['diterapkan'])
                                                <span class="pill pill-leaf text-xs">Diterapkan</span>
                                            @else
                                                <span class="pill pill-neutral text-xs">Belum</span>
                                            @endif
                                            @if ($row['nilai_remidi'] === null)
                                                <span class="pill pill-sun text-xs">Belum remidi</span>
                                            @endif
                                            @if ($kkm !== null && $row['eligible'])
                                                <span class="pill pill-coral text-xs">Di bawah KKM</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3 text-right">
                                        @if ($row['nilai_remidi'] !== null && $row['nilai_awal'] !== null)
                                            <form method="POST" action="{{ route('admin.sessions.remidi.terapkan', $session) }}">
                                                @csrf
                                                <input type="hidden" name="user_ids[]" value="{{ $row['user']->id }}">
                                                <button type="submit" class="btn btn-secondary !px-3 !py-1.5 text-sm">Hitung ulang</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <aside class="card h-fit">
            <h2 class="text-xl">Peringkat nilai akhir</h2>
            <p class="mt-1 text-sm text-ink-muted">Memakai nilai terbesar antara awal dan remidi.</p>

            @if ($peringkat->isEmpty())
                <p class="mt-4 text-base text-ink-muted">Belum ada nilai akhir.</p>
            @else
                <ol class="mt-4 space-y-2">
                    @foreach ($peringkat as $index => $row)
                        <li class="flex items-center gap-3 rounded-2xl bg-surface px-4 py-3">
                            <span class="display-number w-7 text-2xl text-brand-deep">{{ $index + 1 }}</span>
                            <span class="min-w-0 flex-1 truncate text-base font-bold text-ink">{{ $row['user']->nama }}</span>
                            <span class="text-base font-extrabold tabular-nums text-ink">{{ $row['nilai_akhir'] }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </aside>
    </div>
</x-layouts.admin>
