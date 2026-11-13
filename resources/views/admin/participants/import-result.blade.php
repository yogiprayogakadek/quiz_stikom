<x-layouts.admin judul="Ringkasan impor" keterangan="Periksa hasil impor sebelum menutup halaman ini. Kredensial hanya ditampilkan sekali.">
    <x-slot:aksi>
        <a href="{{ route('admin.participants.index') }}" class="btn btn-secondary">Buka daftar peserta</a>
        <a href="{{ route('admin.participants.import') }}" class="btn btn-ghost">Impor file lain</a>
    </x-slot:aksi>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="card border-leaf bg-leaf-soft">
            <p class="text-base font-bold text-leaf-deep">Berhasil diproses</p>
            <p class="display-number mt-2 text-5xl text-ink">{{ $ringkasan['berhasil'] }}</p>
        </div>
        <div class="card border-coral bg-coral-soft">
            <p class="text-base font-bold text-coral-deep">Dilewati</p>
            <p class="display-number mt-2 text-5xl text-ink">{{ $ringkasan['gagal'] }}</p>
        </div>
    </div>

    <p class="mt-3 text-sm text-ink-muted">
        Baris dengan NIM yang sudah terdaftar akan ditautkan ke akun yang sama. Nama, email, dan password akun tersebut tidak diubah.
    </p>

    @if ($unduhan)
        <div class="mt-6 card border-sun bg-sun-soft">
            <h2 class="text-2xl">Kredensial password hasil buatan</h2>
            <p class="mt-2 text-base text-ink">
                File berisi username dan password yang dibuat otomatis. Unduh sekarang karena tautan ini hilang setelah halaman ditutup.
            </p>
            <a href="{{ $unduhan }}" download="kredensial-peserta.csv" class="btn btn-secondary mt-4">
                <x-icon name="download" :size="20" />
                Unduh kredensial CSV
            </a>
        </div>
    @else
        <x-alert jenis="info" class="mt-6">
            Tidak ada password yang dibuat otomatis. Akun baru memakai password dari file; akun yang ditautkan tidak mengubah password.
        </x-alert>
    @endif

    <div class="mt-8">
        <h2 class="text-2xl">Rincian per baris</h2>

        @if (count($ringkasan['baris']) === 0)
            <p class="mt-3 text-base text-ink-muted">File tidak berisi baris data.</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-card border border-outline bg-page">
                <table class="table">
                    <caption class="sr-only">Rincian hasil impor per baris</caption>
                    <thead>
                        <tr>
                            <th scope="col">Baris</th>
                            <th scope="col">Nama</th>
                            <th scope="col">Username</th>
                            <th scope="col">Status</th>
                            <th scope="col">Alasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ringkasan['baris'] as $baris)
                            <tr>
                                <td class="font-mono">{{ $baris['baris'] }}</td>
                                <td>{{ $baris['nama'] !== '' ? $baris['nama'] : '(kosong)' }}</td>
                                <td class="font-mono">{{ $baris['username'] !== '' ? $baris['username'] : '(kosong)' }}</td>
                                <td>
                                    @if ($baris['status'] === 'berhasil')
                                        <span class="pill pill-leaf">Berhasil</span>
                                    @else
                                        <span class="pill pill-coral">Dilewati</span>
                                    @endif
                                </td>
                                <td class="text-ink-muted">{{ $baris['alasan'] ?? 'Masuk ke daftar peserta.' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.admin>
