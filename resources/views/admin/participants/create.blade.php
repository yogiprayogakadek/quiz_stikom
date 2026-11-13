<x-layouts.admin
    :judul="$linkCandidate ? 'Tautkan peserta' : 'Tambah peserta'"
    :keterangan="$linkCandidate ? 'Akun dengan NIM ini sudah terdaftar. Tambahkan ke workspace tanpa mengubah data akun.' : 'Buat akun peserta baru. Password kosong akan dibuat otomatis.'"
>
    <x-slot:aksi>
        <a href="{{ route('admin.participants.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

    @if ($linkCandidate)
        <section class="card max-w-2xl" aria-labelledby="akun-terdaftar-heading">
            <h2 id="akun-terdaftar-heading" class="text-2xl">Akun sudah terdaftar</h2>
            <p class="mt-2 text-base text-ink-muted">
                NIM <span class="rounded-md border border-slate-200 bg-slate-100 px-2 py-0.5 font-mono text-sm font-semibold text-slate-700">{{ $linkCandidate['username'] }}</span>
                sudah terdaftar sebagai <span class="font-bold text-ink">{{ $linkCandidate['nama'] }}</span>
                @if ($linkCandidate['email_masked'])
                    dengan email {{ $linkCandidate['email_masked'] }}.
                @else
                    tanpa email.
                @endif
            </p>
            <p class="mt-2 text-sm text-ink-muted">
                Tambahkan akun yang sama ke workspace ini? Nama, email, dan password tidak berubah.
            </p>

            @if (! $linkCandidate['email_verified'])
                <x-alert jenis="warning" class="mt-4">
                    Email akun ini belum terverifikasi. Setelah ditautkan, minta master platform memverifikasi.
                </x-alert>
            @endif

            <form method="POST" action="{{ route('admin.participants.link') }}" class="mt-5 flex flex-wrap gap-3">
                @csrf
                <button type="submit" class="btn btn-primary">Ya, tambahkan ke workspace</button>
                <a href="{{ route('admin.participants.create', ['batal' => 1]) }}" class="btn btn-secondary">Batal</a>
            </form>
        </section>
    @else
        <form method="POST" action="{{ route('admin.participants.store') }}" class="card max-w-2xl space-y-5">
            @csrf

            <div>
                <label for="nama" class="label">Nama lengkap</label>
                <input id="nama" name="nama" type="text" value="{{ old('nama') }}" class="input" required autofocus @error('nama') aria-invalid="true" @enderror>
                @error('nama') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="username" class="label">Username</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}" class="input" required autocapitalize="none" spellcheck="false" @error('username') aria-invalid="true" @enderror>
                <p class="help mt-2">Dipakai untuk masuk. Huruf, angka, garis bawah, dan tanda hubung.</p>
                @error('username') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" maxlength="255" autocomplete="email" placeholder="nama@contoh.com" @error('email') aria-invalid="true" @enderror>
                <p class="help mt-2">Wajib untuk akun baru. Untuk NIM yang sudah terdaftar, email akun existing tidak diubah.</p>
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="label">Password (opsional)</label>
                <input id="password" name="password" type="text" class="input" autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
                <p class="help mt-2">Kosongkan untuk membuat password acak 8 karakter. Password hasil buatan akan ditampilkan sekali setelah disimpan.</p>
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" class="btn btn-primary">Simpan peserta</button>
                <a href="{{ route('admin.participants.index') }}" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    @endif
</x-layouts.admin>
