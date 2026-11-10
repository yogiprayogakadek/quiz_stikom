<x-layouts.guest :judul="auth()->user()->must_change_password ? 'Wajib Ganti Password' : 'Ganti Password'">
    <div class="flex min-h-screen items-center justify-center px-5 py-12 sm:px-8">
        <main class="w-full max-w-lg">
            <div class="mb-8 flex items-center justify-center gap-3">
                <img src="{{ $appSettings->logoUrl() }}" alt="Logo {{ $appSettings->site_name }}" class="h-12 w-12 object-contain">
                <span class="font-display text-2xl font-bold text-brand-deep">{{ $appSettings->site_name }}</span>
            </div>

            <div class="card shadow-modal border-2 border-brand/20 p-6 sm:p-8 space-y-6">
                <div>
                    <p class="text-sm font-bold text-brand-deep">Keamanan akun</p>
                    <h1 class="mt-2 text-3xl font-bold text-ink">{{ auth()->user()->must_change_password ? 'Buat password pribadi' : 'Ganti password' }}</h1>
                    <p class="mt-2 text-base leading-relaxed text-ink-muted">
                        {{ auth()->user()->must_change_password
                            ? 'Akun ini masih memakai password sementara. Buat password baru sebelum melanjutkan.'
                            : 'Masukkan password saat ini, lalu buat password baru untuk mengakhiri sesi lain pada akun ini.' }}
                    </p>
                </div>

                <div class="rounded-2xl bg-surface p-4 text-sm text-ink-muted">
                    <p class="font-medium text-ink">
                        Masuk sebagai: <strong class="text-brand-deep">{{ auth()->user()->nama }}</strong> ({{ auth()->user()->username }})
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

                <form method="POST" action="{{ route('password.change.update') }}" class="space-y-5">
                    @csrf

                    @unless (auth()->user()->must_change_password)
                        <div>
                            <label for="current_password" class="label">Password saat ini</label>
                            <input
                                id="current_password"
                                name="current_password"
                                type="password"
                                required
                                autocomplete="current-password"
                                class="input mt-1 w-full"
                            >
                        </div>
                    @endunless

                    <div x-data="{ tampil: false }">
                        <label for="password" class="label">Password Baru</label>
                        <div class="relative mt-1">
                            <input
                                id="password"
                                name="password"
                                :type="tampil ? 'text' : 'password'"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                class="input pr-12 w-full"
                                placeholder="Minimal 8 karakter"
                            >
                            <button
                                type="button"
                                @click="tampil = !tampil"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-ink-muted hover:text-ink"
                                tabindex="-1"
                                :title="tampil ? 'Sembunyikan password' : 'Lihat password'"
                            >
                                <svg x-show="!tampil" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="tampil" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div x-data="{ tampil: false }">
                        <label for="password_confirmation" class="label">Konfirmasi Password Baru</label>
                        <div class="relative mt-1">
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                :type="tampil ? 'text' : 'password'"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                class="input pr-12 w-full"
                                placeholder="Ulangi password baru"
                            >
                            <button
                                type="button"
                                @click="tampil = !tampil"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-ink-muted hover:text-ink"
                                tabindex="-1"
                                :title="tampil ? 'Sembunyikan password' : 'Lihat password'"
                            >
                                <svg x-show="!tampil" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="tampil" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-anak w-full py-3 text-base font-bold shadow-md">
                        Simpan password baru
                    </button>
                </form>

                <div class="pt-2 text-center border-t border-outline">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm font-semibold text-ink-muted hover:text-ink hover:underline">
                            Bukan akun Anda? Keluar
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</x-layouts.guest>
