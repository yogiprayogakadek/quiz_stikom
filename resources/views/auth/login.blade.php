@props(['judul' => 'Masuk'])

<x-layouts.guest :judul="$judul">
    <div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
        <section class="relative hidden flex-col justify-between bg-brand-soft px-12 py-10 lg:flex xl:px-16">
            <div class="flex items-center gap-3">
                <img src="{{ asset('logo.png') }}" alt="Logo STIKOM Quiz" class="h-11 w-11 object-contain">
                <span class="font-display text-2xl font-bold text-brand-deep">STIKOM Quiz</span>
            </div>

            <div class="max-w-lg">
                <h1 class="text-[44px] leading-[1.08] text-ink xl:text-[52px]">
                    Sistem Informasi Quiz &amp; Penilaian Kelas
                </h1>
                <p class="mt-6 text-xl leading-relaxed text-ink-muted">
                    Kerjakan kuis di komputer masing-masing secara terfokus, terpantau real-time.
                </p>

                <dl class="mt-10 space-y-5">
                    <div class="flex gap-4">
                        <dt class="mt-0.5 flex h-9 w-9 flex-none items-center justify-center rounded-full bg-page font-mono text-base font-bold text-brand-deep">1</dt>
                        <dd class="text-lg text-ink">Minta username dan password ke admin kelas.</dd>
                    </div>
                    <div class="flex gap-4">
                        <dt class="mt-0.5 flex h-9 w-9 flex-none items-center justify-center rounded-full bg-page font-mono text-base font-bold text-brand-deep">2</dt>
                        <dd class="text-lg text-ink">Masuk dengan username, bukan email.</dd>
                    </div>
                    <div class="flex gap-4">
                        <dt class="mt-0.5 flex h-9 w-9 flex-none items-center justify-center rounded-full bg-page font-mono text-base font-bold text-brand-deep">3</dt>
                        <dd class="text-lg text-ink">Satu akun aktif di satu perangkat. Masuk di perangkat lain akan mengakhiri sesi lama.</dd>
                    </div>
                </dl>
            </div>

            <p class="text-base text-ink-muted">Berjalan di jaringan lokal kelas. Tanpa internet.</p>
        </section>

        <main class="flex items-center justify-center px-5 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <img src="{{ asset('logo.png') }}" alt="Logo STIKOM Quiz" class="h-11 w-11 object-contain">
                    <span class="font-display text-2xl font-bold text-brand-deep">STIKOM Quiz</span>
                </div>

                <h2 class="text-3xl">Masuk ke akun</h2>
                <p class="mt-2 text-lg text-ink-muted">Gunakan akun yang diberikan admin kelas.</p>

                @if (session('sukses'))
                    <x-alert jenis="success" class="mt-6">{{ session('sukses') }}</x-alert>
                @endif

                @if (session('peringatan'))
                    <x-alert jenis="warning" class="mt-6">{{ session('peringatan') }}</x-alert>
                @endif

                @if ($errors->any())
                    <x-alert jenis="error" class="mt-6">
                        <ul class="space-y-1">
                            @foreach ($errors->all() as $pesan)
                                <li>{{ $pesan }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
                    @csrf

                    <div>
                        <label for="username" class="label">Username</label>
                        <input
                            id="username"
                            name="username"
                            type="text"
                            value="{{ old('username') }}"
                            class="input"
                            autocomplete="username"
                            autocapitalize="none"
                            spellcheck="false"
                            required
                            autofocus
                            @error('username') aria-invalid="true" @enderror
                        >
                        @error('username')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="label">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="input"
                            autocomplete="current-password"
                            required
                            @error('password') aria-invalid="true" @enderror
                        >
                        @error('password')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex min-h-12 items-center gap-3 text-base text-ink-muted">
                        <input type="checkbox" name="remember" value="1" class="h-5 w-5 rounded border-outline-strong text-brand">
                        Ingat saya di perangkat ini
                    </label>

                    <button type="submit" class="btn btn-primary btn-anak w-full">
                        Masuk
                        <x-icon name="arrow-left" class="rotate-180" :size="22" />
                    </button>
                </form>

                <p class="mt-8 border-t border-outline pt-6 text-base text-ink-muted">
                    Lupa password? Hubungi admin kelas untuk reset dari halaman peserta.
                </p>
            </div>
        </main>
    </div>
</x-layouts.guest>
