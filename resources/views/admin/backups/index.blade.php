<x-layouts.admin judul="Backup database" keterangan="Simpan salinan database sebelum ujian atau setelah perubahan penting.">
    <x-slot:aksi>
        <form method="POST" action="{{ route('admin.backups.store') }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                <x-icon name="download" :size="20" />
                Buat backup
            </button>
        </form>
    </x-slot:aksi>

    <section class="card" aria-labelledby="daftar-backup">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="daftar-backup" class="text-2xl">Berkas backup</h2>
                <p class="mt-2 max-w-2xl text-base text-ink-muted">
                    Backup berisi data peserta, soal, dan jawaban. Unduh hanya ke perangkat yang tepercaya.
                </p>
            </div>
            <span class="pill pill-neutral">{{ $keep }} backup terbaru disimpan</span>
        </div>

        @if (empty($backups))
            <x-empty-state
                class="mt-6"
                judul="Belum ada backup"
                keterangan="Buat backup pertama sebelum sesi ujian dimulai."
            />
        @else
            <div class="mt-6 overflow-x-auto">
                <table class="table">
                    <caption class="sr-only">Daftar backup database</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nama berkas</th>
                            <th scope="col">Ukuran</th>
                            <th scope="col">Dibuat</th>
                            <th scope="col"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($backups as $file)
                            <tr>
                                <td class="font-mono text-sm font-bold text-ink">{{ $file['nama'] }}</td>
                                <td class="text-ink-muted">{{ number_format($file['ukuran'] / 1024, 1) }} KB</td>
                                <td class="text-ink-muted">
                                    {{ \Illuminate\Support\Carbon::createFromTimestamp($file['diubah'])->timezone($orgTimezone)->format('d M Y, H:i') }}
                                </td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('admin.backups.download', $file['nama']) }}" class="btn btn-secondary">
                                            <x-icon name="download" :size="16" />
                                            Unduh
                                        </a>
                                        <form method="POST" action="{{ route('admin.backups.destroy', $file['nama']) }}" onsubmit="return confirm('Hapus backup ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost text-rose-600">
                                                <x-icon name="x" :size="16" />
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.admin>
