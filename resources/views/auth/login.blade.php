@props(['judul' => null, 'masterLogin' => false])

@php($judul ??= $masterLogin ? 'Login Master' : 'Masuk')

<x-layouts.guest :judul="$judul">
    <div class="min-h-screen w-full grid grid-cols-1 lg:grid-cols-12 bg-surface font-sans">
        {{-- Left Side: Visual & Contextual Storytelling --}}
        <div @class([
            'relative flex-col justify-between overflow-hidden bg-gradient-to-br from-brand-soft via-surface-sunken to-brand-soft/40 p-10 xl:p-14 border-r border-outline',
            'hidden lg:col-span-6 lg:flex' => $appSettings->login_layout !== 'centered',
            'hidden' => $appSettings->login_layout === 'centered',
        ])>
            {{-- Top Branding & Status --}}
            <div class="relative z-10 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-page p-2 shadow-xs border border-outline">
                        <img src="{{ $appSettings->logoUrl() }}" alt="Logo {{ $appSettings->site_name }}" class="h-full w-full object-contain">
                    </div>
                    <div>
                        <p class="font-display text-lg font-bold text-ink leading-tight">{{ $appSettings->site_name }}</p>
                        <p class="text-xs text-ink-subtle">{{ $appSettings->site_tagline }}</p>
                    </div>
                </div>

                <div class="inline-flex items-center gap-2 rounded-full bg-page/90 px-3.5 py-1.5 border border-outline shadow-xs backdrop-blur-sm">
                    <span @class(['h-2 w-2 rounded-full', 'bg-sun-deep' => $appSettings->maintenance_enabled, 'bg-leaf animate-pulse' => ! $appSettings->maintenance_enabled])></span>
                    <span class="text-xs font-bold text-ink-muted">{{ $appSettings->maintenance_enabled ? 'Maintenance aktif' : 'Layanan siap' }}</span>
                </div>
            </div>

            {{-- Center: Clean Illustration Frame --}}
            <div class="relative z-10 my-auto flex flex-col items-center justify-center py-6">
                <div class="w-full max-w-md overflow-hidden rounded-2xl border border-outline bg-page/60 p-3 shadow-lift backdrop-blur-xs">
                    <img
                        src="{{ asset('images/login-illustration.png') }}"
                        alt="Ilustrasi peserta mengerjakan kuis"
                        class="w-full h-auto rounded-xl object-cover select-none pointer-events-none"
                    >
                </div>
            </div>

            {{-- Bottom Contextual Feature Callouts --}}
            <div class="relative z-10 grid grid-cols-2 gap-4 pt-4 border-t border-outline/70">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-page border border-outline text-brand-deep">
                        <x-icon name="shield-check" :size="18" />
                    </span>
                    <div>
                        <p class="text-xs font-bold text-ink">Mode Layar Penuh</p>
                        <p class="text-[11px] text-ink-subtle leading-normal">Pengawasan integritas sesi otomatis.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-page border border-outline text-brand-deep">
                        <x-icon name="zap" :size="18" />
                    </span>
                    <div>
                        <p class="text-xs font-bold text-ink">Sinkron Realtime</p>
                        <p class="text-[11px] text-ink-subtle leading-normal">Jawaban tersimpan instan ke server.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Side: Login Form --}}
        <main @class([
            'flex min-h-screen w-full flex-col justify-center p-6 sm:p-12 lg:p-14 xl:p-18',
            'bg-surface lg:col-span-6' => $appSettings->login_layout !== 'centered',
            'bg-gradient-to-br from-brand-soft via-surface to-surface-sunken lg:col-span-12' => $appSettings->login_layout === 'centered',
        ])>
            <div
                x-data="{ showPassword: false, submitting: false }"
                class="w-full max-w-md mx-auto my-auto flex flex-col justify-center"
            >
                {{-- Mobile Brand Header --}}
                <div @class([
                    'items-center justify-center gap-3 mb-8',
                    'flex lg:hidden' => $appSettings->login_layout !== 'centered',
                    'flex' => $appSettings->login_layout === 'centered',
                ])>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-page p-2 shadow-xs border border-outline">
                        <img src="{{ $appSettings->logoUrl() }}" alt="Logo {{ $appSettings->site_name }}" class="h-full w-full object-contain">
                    </div>
                    <span class="font-display text-2xl font-bold tracking-tight text-ink">
                        {{ $appSettings->site_name }}
                    </span>
                </div>

                {{-- Card Wrapper --}}
                <div class="card p-6 sm:p-8 bg-page border border-outline shadow-lift">
                    {{-- Title --}}
                    <div>
                        <h1 class="font-display text-2xl sm:text-3xl font-extrabold tracking-tight text-ink">
                            {{ $masterLogin ? 'Akses master platform' : $appSettings->login_title }}
                        </h1>
                        <p class="mt-2 text-sm text-ink-muted">
                            {{ $masterLogin ? 'Kelola organisasi dan status layanan dengan akun master.' : $appSettings->login_description }}
                        </p>
                    </div>

                    {{-- Alert Notifications --}}
                    @if (session('sukses'))
                        <div class="mt-5 flex items-start gap-3 rounded-xl bg-leaf-soft border border-leaf-deep/20 p-3.5 text-sm text-leaf-deep" role="alert">
                            <x-icon name="check" :size="18" class="flex-none mt-0.5" />
                            <div>{{ session('sukses') }}</div>
                        </div>
                    @endif

                    @if (session('peringatan'))
                        <div class="mt-5 flex items-start gap-3 rounded-xl bg-sun-soft border border-sun-deep/20 p-3.5 text-sm text-sun-deep" role="alert">
                            <x-icon name="alert" :size="18" class="flex-none mt-0.5" />
                            <div>{{ session('peringatan') }}</div>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mt-5 rounded-xl bg-coral-soft border border-coral-deep/20 p-3.5 text-sm text-coral-deep" role="alert">
                            <div class="flex items-center gap-2 font-bold">
                                <x-icon name="alert" :size="18" class="flex-none" />
                                <span>Gagal masuk</span>
                            </div>
                            <ul class="mt-1.5 list-inside list-disc space-y-1 text-xs">
                                @foreach ($errors->all() as $pesan)
                                    <li>{{ $pesan }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Login Form --}}
                    <form
                        method="POST"
                        action="{{ route($masterLogin ? 'master.login.store' : 'login.store') }}"
                        class="mt-6 space-y-4"
                        @submit="submitting = true"
                    >
                        @csrf

                        {{-- Username Field --}}
                        <div>
                            <label for="username" class="block text-xs font-bold uppercase tracking-wider text-ink-muted">
                                Username
                            </label>
                            <div class="relative mt-1.5">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-ink-faint">
                                    <x-icon name="user" :size="18" />
                                </span>
                                <input
                                    id="username"
                                    name="username"
                                    type="text"
                                    inputmode="text"
                                    autocomplete="username"
                                    required
                                    autofocus
                                    value="{{ old('username') }}"
                                    placeholder="{{ $masterLogin ? 'Username master' : 'NIM / Username' }}"
                                    class="block w-full rounded-xl border border-outline bg-page py-2.5 pl-10 pr-4 text-base text-ink placeholder:text-ink-faint focus:border-brand focus:outline-none focus:ring-3 focus:ring-brand-soft transition @error('username') border-coral-deep @enderror"
                                >
                            </div>
                            @error('username')
                                <p class="mt-1 text-xs font-semibold text-coral-deep">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Password Field --}}
                        <div>
                            <label for="password" class="block text-xs font-bold uppercase tracking-wider text-ink-muted">
                                Password
                            </label>
                            <div class="relative mt-1.5">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-ink-faint">
                                    <x-icon name="lock" :size="18" />
                                </span>
                                <input
                                    id="password"
                                    name="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    required
                                    placeholder="Kata sandi"
                                    class="block w-full rounded-xl border border-outline bg-page py-2.5 pl-10 pr-10 text-base text-ink placeholder:text-ink-faint focus:border-brand focus:outline-none focus:ring-3 focus:ring-brand-soft transition @error('password') border-coral-deep @enderror"
                                >
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-ink-faint hover:text-ink focus:outline-none transition-colors"
                                    :aria-label="showPassword ? 'Sembunyikan password' : 'Lihat password'"
                                >
                                    <template x-if="!showPassword">
                                        <x-icon name="eye" :size="18" />
                                    </template>
                                    <template x-if="showPassword">
                                        <x-icon name="eye-off" :size="18" />
                                    </template>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-1 text-xs font-semibold text-coral-deep">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Remember Me --}}
                        <div class="flex items-center justify-between gap-3 pt-1">
                            <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-ink-muted select-none">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1"
                                    class="h-4 w-4 rounded border-outline text-brand focus:ring-brand-soft"
                                >
                                <span>Ingat saya di perangkat ini</span>
                            </label>
                            <a href="{{ route('password.request') }}" class="text-xs font-semibold text-brand-deep underline-offset-2 hover:underline">
                                Lupa password?
                            </a>
                        </div>

                        {{-- Submit Button --}}
                        <button
                            type="submit"
                            :disabled="submitting"
                            class="btn btn-primary w-full mt-3 font-extrabold"
                        >
                            <span x-show="!submitting">{{ $masterLogin ? 'Masuk sebagai master' : 'Masuk akun' }}</span>
                            <span x-show="submitting" x-cloak class="flex items-center gap-2">
                                <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>Memverifikasi...</span>
                            </span>
                        </button>
                    </form>

                    {{-- Help Note --}}
                    <div class="mt-6 border-t border-outline/70 pt-4 text-center">
                        @if (! $masterLogin)
                            <p class="mb-2 text-xs text-ink-subtle">
                                Belum punya workspace?
                                <a href="{{ route('register.organization') }}" class="font-bold text-brand-deep underline-offset-2 hover:underline">Daftarkan organisasi</a>.
                            </p>
                        @endif
                        <p class="text-xs text-ink-subtle">
                            @if ($masterLogin)
                                Bukan master? <a href="{{ route('login') }}" class="font-bold text-brand-deep underline-offset-2 hover:underline">Kembali ke login pengguna</a>.
                            @else
                                Kendala akun? Hubungi admin kelas. Master platform <a href="{{ route('master.login') }}" class="font-bold text-brand-deep underline-offset-2 hover:underline">masuk di sini</a>.
                            @endif
                        </p>
                        <p class="mt-3 text-xs text-ink-subtle">
                            <a href="{{ route('terms.show') }}" class="underline-offset-2 hover:underline">Syarat</a>
                            ·
                            <a href="{{ route('privacy.show') }}" class="underline-offset-2 hover:underline">Privasi</a>
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</x-layouts.guest>
