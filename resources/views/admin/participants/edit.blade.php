<x-layouts.admin :judul="'Edit '.$participant->nama" keterangan="Perbarui nama, username, atau password peserta.">
    <x-slot:aksi>
        <a href="{{ route('admin.participants.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

    <form method="POST" action="{{ route('admin.participants.update', $participant) }}" class="card max-w-2xl space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="nama" class="label">Nama lengkap</label>
            <input id="nama" name="nama" type="text" value="{{ old('nama', $participant->nama) }}" class="input" required @error('nama') aria-invalid="true" @enderror>
            @error('nama') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="username" class="label">Username</label>
            <input id="username" name="username" type="text" value="{{ old('username', $participant->username) }}" class="input" required autocapitalize="none" spellcheck="false" @error('username') aria-invalid="true" @enderror>
            @error('username') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="label">Password baru (opsional)</label>
            <input id="password" name="password" type="text" class="input" autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
            <p class="help mt-2">Kosongkan bila password tidak diubah. Mengubah password akan mengakhiri sesi login peserta yang sedang aktif.</p>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="btn btn-primary">Simpan perubahan</button>
            <a href="{{ route('admin.participants.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.admin>
