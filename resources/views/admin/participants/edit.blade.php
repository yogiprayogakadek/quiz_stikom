<x-layouts.admin
    :judul="$shared ? 'Akun '.$participant->nama : 'Edit '.$participant->nama"
    :keterangan="$shared ? 'Akun ini terhubung ke beberapa workspace dan tidak dapat diubah dari sini.' : 'Perbarui nama, username, atau password peserta.'"
>
    <x-slot:aksi>
        <a href="{{ route('admin.participants.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

    @if ($shared)
        <section class="card max-w-2xl space-y-4" aria-labelledby="akun-terkunci-heading">
            <h2 id="akun-terkunci-heading" class="text-2xl">Akun terhubung ke beberapa workspace</h2>
            <p class="text-base text-ink-muted">
                NIM <span class="rounded-md border border-slate-200 bg-slate-100 px-2 py-0.5 font-mono text-sm font-semibold text-slate-700">{{ $participant->username }}</span>
                dipakai di lebih dari satu workspace. Admin workspace tidak dapat mengubah nama, NIM, email, atau mereset password akun ini. Hubungi master platform untuk perubahan data akun.
            </p>
            <p class="text-sm text-ink-muted">
                Anda tetap dapat mengeluarkan peserta ini dari workspace tanpa menghapus akun globalnya.
            </p>
            <div class="flex flex-wrap gap-3 pt-2">
                <a href="{{ route('admin.participants.index') }}" class="btn btn-secondary">Kembali ke daftar peserta</a>
            </div>
        </section>
    @else
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
                <label for="email" class="label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $participant->email) }}" class="input" maxlength="255" autocomplete="email" placeholder="nama@contoh.com" @error('email') aria-invalid="true" @enderror>
                <p class="help mt-2">Kosongkan jika akun lama belum punya email. Mengubah email akan meminta verifikasi ulang.</p>
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
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
    @endif
</x-layouts.admin>
