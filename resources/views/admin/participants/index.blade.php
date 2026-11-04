<x-layouts.admin judul="Peserta" keterangan="Kelola akun peserta untuk masuk ke kuis.">
    <x-slot:aksi>
        <a href="{{ route('admin.participants.template') }}" class="btn btn-secondary">
            <x-icon name="download" :size="20" />
            Contoh CSV
        </a>
        <a href="{{ route('admin.participants.import') }}" class="btn btn-secondary">
            <x-icon name="upload" :size="20" />
            Impor CSV
        </a>
        <a href="{{ route('admin.participants.create') }}" class="btn btn-primary">
            <x-icon name="plus" :size="20" />
            Tambah peserta
        </a>
    </x-slot:aksi>

    <form method="GET" action="{{ route('admin.participants.index') }}" class="flex flex-col gap-3 sm:flex-row">
        <div class="flex-1">
            <label for="cari" class="sr-only">Cari peserta</label>
            <input id="cari" name="q" type="search" value="{{ $cari }}" class="input" placeholder="Cari nama atau username">
        </div>
        <button type="submit" class="btn btn-secondary">Cari</button>
        @if ($cari !== '')
            <a href="{{ route('admin.participants.index') }}" class="btn btn-ghost">Bersihkan</a>
        @endif
    </form>

    @if ($participants->isEmpty())
        <x-empty-state
            class="mt-6"
            :judul="$cari !== '' ? 'Peserta tidak ditemukan' : 'Belum ada peserta'"
            :keterangan="$cari !== '' ? 'Coba kata kunci lain atau bersihkan pencarian.' : 'Tambahkan satu per satu atau impor daftar kelas dari CSV.'"
        >
            @if ($cari === '')
                <a href="{{ route('admin.participants.create') }}" class="btn btn-primary mt-2">Tambah peserta pertama</a>
            @endif
        </x-empty-state>
    @else
        <div class="mt-6 overflow-x-auto rounded-card border border-outline bg-page">
            <table class="table">
                <caption class="sr-only">Daftar peserta</caption>
                <thead>
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Username</th>
                        <th scope="col">Attempt</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($participants as $peserta)
                        <tr>
                            <td>
                                <p class="font-bold text-ink">{{ $peserta->nama }}</p>
                            </td>
                            <td class="font-mono text-base">{{ $peserta->username }}</td>
                            <td>{{ $peserta->attempts_count }}</td>
                            <td>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.participants.edit', $peserta) }}" class="btn btn-ghost px-3">Edit</a>
                                    <form method="POST" action="{{ route('admin.participants.reset-password', $peserta) }}" onsubmit="return confirm('Reset password {{ $peserta->username }}? Password lama tidak berlaku lagi.')">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost px-3">Reset password</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.participants.destroy', $peserta) }}" onsubmit="return confirm('Hapus peserta {{ $peserta->username }}? Tindakan ini tidak bisa dibatalkan dari halaman ini.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost px-3 text-coral-deep">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $participants->links() }}
        </div>
    @endif
</x-layouts.admin>
