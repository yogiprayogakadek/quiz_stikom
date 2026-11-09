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
                href="{{ route('admin.sessions.index', $nilai ? ['status' => $nilai] : []) }}"
                class="btn {{ $status === $nilai ? 'btn-primary' : 'btn-secondary' }}"
                @if ($status === $nilai) aria-current="page" @endif
            >
                {{ $label }}
            </a>
        @endforeach
    </nav>

    @if ($sessions->isEmpty())
        <x-empty-state
            class="mt-6"
            judul="Belum ada sesi di filter ini"
            keterangan="Buat sesi dari kuis yang sudah disiapkan, lalu buka lobby untuk peserta."
        >
            <a href="{{ route('admin.sessions.create') }}" class="btn btn-primary mt-2">Buat sesi</a>
        </x-empty-state>
    @else
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            @foreach ($sessions as $sesi)
                <article class="card card-hover flex flex-col">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-status-badge :status="$sesi->status" />
                        <x-mode-badge :mode="$sesi->mode" />
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
                            <dd class="font-bold text-ink">{{ $sesi->tampilkan_pembahasan ? 'Tampil' : 'Tidak tampil' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 flex flex-1 flex-wrap items-end gap-2">
                        <a href="{{ route('admin.sessions.show', $sesi) }}" class="btn btn-primary">Ruang kendali</a>
                        @if ($sesi->status === 'selesai')
                            <a href="{{ route('admin.reports.session', $sesi) }}" class="btn btn-secondary">Laporan</a>
                        @endif
                        @if ($sesi->status === 'draft')
                            <a href="{{ route('admin.sessions.edit', $sesi) }}" class="btn btn-ghost">Pengaturan</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-6">{{ $sessions->links() }}</div>
    @endif
</x-layouts.admin>
