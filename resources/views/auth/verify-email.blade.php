<x-layouts.guest judul="Verifikasi Email">
    <div class="flex min-h-screen items-center justify-center px-5 py-12 sm:px-8">
        <main class="w-full max-w-lg">
            <div class="mb-8 flex items-center justify-center gap-3">
                <img src="{{ $appSettings->logoUrl() }}" alt="Logo {{ $appSettings->site_name }}" class="h-12 w-12 object-contain">
                <span class="font-display text-2xl font-bold text-brand-deep">{{ $appSettings->site_name }}</span>
            </div>

            <div class="card shadow-modal border-2 border-brand/20 p-6 sm:p-8 space-y-6">
                <div>
                    <p class="text-sm font-bold text-brand-deep">Verifikasi email</p>
                    <h1 class="mt-2 text-3xl font-bold text-ink">Periksa kotak masuk</h1>
                    <p class="mt-2 text-base leading-relaxed text-ink-muted">
                        Tautan verifikasi sudah dikirim ke <strong class="text-ink">{{ auth()->user()->email }}</strong>.
                        Buka tautan itu untuk mengaktifkan akun.
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

                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full py-3 text-base font-bold">
                        Kirim ulang tautan verifikasi
                    </button>
                </form>

                <p class="text-sm leading-relaxed text-ink-muted">
                    Tidak menerima email? Periksa folder spam. Jika alamat email salah, minta pengelola memperbarui data akun Anda.
                </p>

                <div class="border-t border-outline pt-4 text-center">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm font-semibold text-ink-muted hover:text-ink hover:underline">
                            Keluar dari akun
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</x-layouts.guest>
