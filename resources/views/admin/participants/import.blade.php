<x-layouts.admin judul="Impor peserta" keterangan="Tambahkan banyak akun sekaligus dari file CSV." >
    <x-slot:aksi>
        <a href="{{ route('admin.participants.template') }}" class="btn btn-secondary">
            <x-icon name="download" :size="20" />
            Unduh contoh CSV
        </a>
        <a href="{{ route('admin.participants.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

    <div class="grid gap-6 lg:grid-cols-[1fr_1.1fr]">
        <section class="card">
            <h2 class="text-2xl">Unggah file</h2>
            <p class="mt-2 text-base text-ink-muted">
                File harus berformat CSV dengan header <span class="font-mono text-base text-ink">nama,username,password</span>.
                Kolom password boleh kosong. Password kosong akan dibuat otomatis 8 karakter.
            </p>

            <x-import-progress>
                <form method="POST" action="{{ route('admin.participants.import.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4" @submit="handleSubmit($event)">
                    @csrf
                    <div>
                        <label for="file" class="label">File CSV</label>
                        <input id="file" name="file" type="file" accept=".csv,text/csv" class="input py-2" required @change="handleFileSelect($event)" @error('file') aria-invalid="true" @enderror>
                        @error('file') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="btn btn-primary" :disabled="uploading">
                        <x-icon name="upload" :size="20" />
                        <span x-text="uploading ? 'Mengimpor...' : 'Impor sekarang'">Impor sekarang</span>
                    </button>
                </form>
            </x-import-progress>

            <div class="mt-6 rounded-2xl bg-surface p-4 text-base text-ink-muted">
                <p class="font-bold text-ink">Yang terjadi saat impor</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Baris rusak dilewati dan dilaporkan dengan nomor baris.</li>
                    <li>Username duplikat di file atau di database dilewati.</li>
                    <li>Ringkasan hasil tampil sekali setelah impor.</li>
                    <li>File kredensial berisi password hasil buatan bisa diunduh di halaman ringkasan.</li>
                </ul>
            </div>
        </section>

        <section class="card">
            <h2 class="text-2xl">Contoh isi file</h2>
            <p class="mt-2 text-base text-ink-muted">Baris pertama wajib berisi nama kolom. Contoh di bawah hanya format, bukan data nyata.</p>

            <div class="mt-4 overflow-x-auto rounded-2xl border border-outline">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">nama</th>
                            <th scope="col">username</th>
                            <th scope="col">password</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Contoh Satu</td>
                            <td class="font-mono">contoh1</td>
                            <td class="text-ink-muted">(kosong, dibuat otomatis)</td>
                        </tr>
                        <tr>
                            <td>Contoh Dua</td>
                            <td class="font-mono">contoh2</td>
                            <td class="font-mono">rahasia123</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="mt-4 text-base text-ink-muted">
                Simpan sebagai CSV dengan pemisah koma. Bila memakai Excel, pilih Save As lalu CSV UTF-8.
            </p>
        </section>
    </div>
</x-layouts.admin>
