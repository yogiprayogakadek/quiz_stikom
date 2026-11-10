<x-layouts.guest judul="Reset Password">
    <div class="flex min-h-screen items-center justify-center px-5 py-12 sm:px-8">
        <main class="w-full max-w-lg">
            <div class="mb-8 flex items-center justify-center gap-3">
                <img src="{{ $appSettings->logoUrl() }}" alt="Logo {{ $appSettings->site_name }}" class="h-12 w-12 object-contain">
                <span class="font-display text-2xl font-bold text-brand-deep">{{ $appSettings->site_name }}</span>
            </div>

            <div class="card shadow-modal border-2 border-brand/20 p-6 sm:p-8 space-y-6">
                <div>
                    <p class="text-sm font-bold text-brand-deep">Keamanan akun</p>
                    <h1 class="mt-2 text-3xl font-bold text-ink">Buat password baru</h1>
                    <p class="mt-2 text-base leading-relaxed text-ink-muted">
                        Tautan ini hanya berlaku sekali. Setelah password disimpan, semua sesi lama pada akun ini akan berakhir.
                    </p>
                </div>

                <div class="rounded-2xl bg-surface p-4 text-sm text-ink-muted">
                    <p class="font-medium text-ink">
                        Akun: <strong class="text-brand-deep">{{ $email }}</strong>
                    </p>
                </div>

                @if ($errors->any())
                    <x-alert jenis="error">
                        <ul class="space-y-1">
                            @foreach ($errors->all() as $pesan)
                                <li>{{ $pesan }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="email" value="{{ $email }}">

                    <div>
                        <label for="password" class="label">Password baru</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Minimal 8 karakter"
                            class="input mt-1 w-full"
                            @error('password') aria-invalid="true" @enderror
                        >
                        @error('password') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="label">Ulangi password baru</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Ulangi password baru"
                            class="input mt-1 w-full"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary w-full py-3 text-base font-bold">
                        Simpan password baru
                    </button>
                </form>

                <div class="border-t border-outline pt-4 text-center">
                    <a href="{{ route('login') }}" class="text-sm font-semibold text-ink-muted hover:text-ink hover:underline">
                        Kembali ke login
                    </a>
                </div>
            </div>
        </main>
    </div>
</x-layouts.guest>
