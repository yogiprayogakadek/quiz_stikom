<x-layouts.admin judul="Sesi" keterangan="Kendalikan lobby, jalannya kuis, dan hasil tiap kelas.">
    <x-slot:aksi>
        <a href="{{ route('admin.sessions.create') }}" class="btn btn-primary">
            <x-icon name="plus" :size="20" />
            Buat sesi
        </a>
    </x-slot:aksi>

    <nav aria-label="Filter status sesi" class="flex flex-wrap gap-2">
        @php
            $tab = [
                null => 'Semua',
                'lobby' => 'Lobby',
                'berjalan' => 'Berjalan',
                'draft' => 'Draft',
                'selesai' => 'Selesai',
            ];
        @endphp
        @foreach ($tab as $nilai => $label)
            <a
                href="{{ route('admin.sessions.index', array_filter(['status' => $nilai, 'kelas' => $kelasFilter ?: null])) }}"
                class="btn {{ $status === $nilai ? 'btn-primary' : 'btn-secondary' }}"
                @if ($status === $nilai) aria-current="page" @endif
            >
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.sessions.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
        @if ($status)
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        <div>
            <label for="kelas" class="label text-sm">Kelas</label>
            <select id="kelas" name="kelas" class="input !w-auto !min-h-[42px] !py-2 text-sm" onchange="this.form.submit()">
                <option value="0">Semua kelas</option>
                @foreach ($kelasList as $kelas)
                    <option value="{{ $kelas->id }}" @selected($kelasFilter === $kelas->id)>{{ $kelas->nama }}</option>
                @endforeach
            </select>
        </div>
        <noscript>
            <button type="submit" class="btn btn-secondary">Terapkan</button>
        </noscript>
        @if ($kelasFilter > 0)
            <a href="{{ route('admin.sessions.index', array_filter(['status' => $status])) }}" class="btn btn-ghost">Reset kelas</a>
        @endif
    </form>

    @if ($sessions->isEmpty())
        <x-empty-state
            class="mt-6"
            :judul="$kelasFilter > 0 ? 'Tidak ada sesi di kelas ini' : 'Belum ada sesi di filter ini'"
            :keterangan="$kelasFilter > 0 ? 'Pilih kelas lain, ganti status, atau reset filter kelas.' : 'Buat sesi dari kuis yang sudah disiapkan, lalu buka lobby untuk peserta.'"
        >
            @if ($kelasFilter > 0)
                <a href="{{ route('admin.sessions.index', array_filter(['status' => $status])) }}" class="btn btn-secondary mt-2">Reset filter kelas</a>
            @else
                <a href="{{ route('admin.sessions.create') }}" class="btn btn-primary mt-2">Buat sesi</a>
            @endif
        </x-empty-state>
    @else
        <x-data-table
            search-placeholder="Cari nama sesi, kuis, atau kelas..."
            :per-page="12"
            :per-page-options="[6, 12, 24, 48]"
            empty-message="Tidak ada sesi yang cocok dengan pencarian."
        >
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                @foreach ($sessions as $sesi)
                    <article
                        data-item
                        data-item-key="sesi-{{ $sesi->id }}"
                        data-search="{{ strtolower($sesi->nama.' '.$sesi->quiz->judul.' '.($sesi->kelas?->nama ?? '').' '.$sesi->status.' '.$sesi->mode) }}"
                        class="card card-hover flex flex-col"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <x-status-badge :status="$sesi->status" />
                            <x-mode-badge :mode="$sesi->mode" />
                            @if ($sesi->kelas)
                                <span class="pill pill-brand font-bold">{{ $sesi->kelas->nama }}</span>
                            @endif
                            @if ($sesi->max_peserta !== null)
                                <span class="pill pill-neutral">{{ $sesi->attempts_count }}/{{ $sesi->max_peserta }} peserta</span>
                            @else
                                <span class="pill pill-neutral">{{ $sesi->attempts_count }} peserta</span>
                            @endif
                            <span class="pill {{ $sesi->acak_opsi ? 'pill-leaf' : 'pill-neutral' }} text-xs">{{ $sesi->acak_opsi ? 'Acak Opsi' : 'Opsi Tetap' }}</span>
                        </div>

                        <h2 class="mt-3 text-2xl">{{ $sesi->nama }}</h2>
                        <p class="mt-1 text-base text-ink-muted">{{ $sesi->quiz->judul }}</p>

                        <dl class="mt-4 grid grid-cols-2 gap-3 rounded-2xl bg-surface p-4 text-base">
                            <div>
                                <dt class="text-ink-muted">Jeda</dt>
                                <dd class="font-bold text-ink">{{ $sesi->jeda_detik }} detik</dd>
                            </div>
                            <div>
                                <dt class="text-ink-muted">Pembahasan</dt>
                                <dd class="font-bold text-ink">
                                    {{ ['terbuka' => 'Terbuka', 'otomatis' => 'Otomatis', 'tersembunyi' => 'Tersembunyi'][$sesi->pembahasanMode()] ?? 'Tersembunyi' }}
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-5 flex flex-1 flex-wrap items-end gap-2">
                            <a href="{{ route('admin.sessions.show', $sesi) }}" class="btn btn-primary">Ruang kendali</a>
                            @if ($sesi->status === 'selesai')
                                <a href="{{ route('admin.reports.session', $sesi) }}" class="btn btn-secondary">Laporan</a>
                                @if ($sesi->jenis === 'utama' && ! $sesi->quiz->is_percobaan)
                                    @php $remidi = $sesi->remidi->first(); @endphp
                                    <a href="{{ $remidi ? route('admin.sessions.remidi.laporan', $remidi) : route('admin.sessions.remidi.create', $sesi) }}" class="btn btn-ghost">
                                        {{ $remidi ? 'Laporan remidi' : 'Remidi' }}
                                    </a>
                                @endif
                            @endif
                            @if ($sesi->status === 'draft')
                                <a href="{{ route('admin.sessions.edit', $sesi) }}" class="btn btn-ghost">Pengaturan</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </x-data-table>
    @endif
</x-layouts.admin>
