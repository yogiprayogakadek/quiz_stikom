<x-layouts.guest judul="Lupa Password">
    <div class="flex min-h-screen items-center justify-center px-5 py-12 sm:px-8">
        <main class="w-full max-w-lg">
            <div class="mb-8 flex items-center justify-center gap-3">
                <img src="{{ $appSettings->logoUrl() }}" alt="Logo {{ $appSettings->site_name }}" class="h-12 w-12 object-contain">
                <span class="font-display text-2xl font-bold text-brand-deep">{{ $appSettings->site_name }}</span>
            </div>

            <div class="card shadow-modal border-2 border-brand/20 p-6 sm:p-8 space-y-6">
                <div>
                    <p class="text-sm font-bold text-brand-deep">Pemulihan akun</p>
                    <h1 class="mt-2 text-3xl font-bold text-ink">Lupa password?</h1>
                    <p class="mt-2 text-base leading-relaxed text-ink-muted">
                        Masukkan email yang terdaftar. Tautan untuk membuat password baru akan dikirim ke email tersebut.
                    </p>
                </div>

                @if (session('sukses'))
                    <x-alert jenis="success">{{ session('sukses') }}</x-alert>
                @endif

                @if ($errors->any())
                    <x-alert jenis="error">
                        <ul class="space-y-1">
                            @foreach ($errors->all() as $pesan)
                                <li>{{ $pesan }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="label">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            maxlength="255"
                            autocomplete="email"
                            placeholder="nama@contoh.com"
                            class="input mt-1 w-full"
                            @error('email') aria-invalid="true" @enderror
                        >
                        @error('email') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-full py-3 text-base font-bold">
                        Kirim tautan reset
                    </button>
                </form>

                <div class="border-t border-outline pt-4 text-center">
                    <a href="{{ route('home') }}" class="text-sm font-semibold text-ink-muted hover:text-ink hover:underline">
                        Kembali ke login
                    </a>
                </div>
            </div>
        </main>
    </div>
</x-layouts.guest>
