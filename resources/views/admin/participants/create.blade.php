<x-layouts.admin judul="Tambah peserta" keterangan="Buat akun peserta baru. Password kosong akan dibuat otomatis.">
    <x-slot:aksi>
        <a href="{{ route('admin.participants.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

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
            <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" required maxlength="255" autocomplete="email" placeholder="nama@contoh.com" @error('email') aria-invalid="true" @enderror>
            <p class="help mt-2">Dipakai untuk verifikasi akun dan reset password mandiri.</p>
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
</x-layouts.admin>
