@props(['judul' => 'Dashboard', 'keterangan' => null])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $judul }} | STIKOM Quiz</title>
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface" x-data="{ mobileNav: false }" x-on:keydown.escape.window="mobileNav = false">
    <a href="#utama" class="skip-link">Lewati ke konten utama</a>

    <!-- Backdrop Menu Mobile -->
    <div
        x-show="mobileNav"
        x-cloak
        x-transition.opacity.duration.300ms
        class="fixed inset-0 z-40 bg-ink/50 backdrop-blur-sm lg:hidden"
        x-on:click="mobileNav = false"
        aria-hidden="true"
    ></div>

    <!-- Drawer Menu Mobile Peserta -->
    <div
        x-show="mobileNav"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        id="menu-peserta"
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col justify-between border-r border-outline bg-page p-5 shadow-modal lg:hidden"
        role="dialog"
        aria-modal="true"
        aria-label="Menu navigasi peserta"
    >
        <div>
            <div class="flex items-center justify-between pb-4 border-b border-outline">
                <a href="{{ route('peserta.dashboard') }}" class="flex items-center gap-2.5 rounded-xl">
                    <img src="{{ asset('logo.png') }}" alt="Logo STIKOM Quiz" class="h-8 w-8 object-contain">
                    <span class="font-display text-lg font-bold text-brand-deep">STIKOM Quiz</span>
                </a>
                <button
                    type="button"
                    class="btn btn-ghost h-10 w-10 p-0"
                    x-on:click="mobileNav = false"
                    aria-label="Tutup menu"
                >
                    <x-icon name="x" :size="22" />
                </button>
            </div>

            <!-- Profile Info Card -->
            <div class="mt-4 flex items-center gap-3 rounded-2xl bg-surface p-3 border border-outline">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand-deep font-bold font-display">
                    {{ strtoupper(substr(auth()->user()->nama, 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-base font-bold text-ink">{{ auth()->user()->nama }}</p>
                    <p class="text-xs text-ink-muted">Peserta</p>
                </div>
            </div>

            <!-- Nav Links Mobile -->
            <nav class="mt-6 space-y-1.5" aria-label="Navigasi mobile peserta">
                <a
                    href="{{ route('peserta.dashboard') }}"
                    class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-base font-bold transition {{ request()->routeIs('peserta.dashboard') ? 'bg-brand-soft text-brand-deep' : 'text-ink hover:bg-surface' }}"
                    @if (request()->routeIs('peserta.dashboard')) aria-current="page" @endif
                >
                    <x-icon name="home" :size="20" />
                    Dashboard
                </a>
                <a
                    href="{{ route('peserta.kelas.index') }}"
                    class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-base font-bold transition {{ request()->routeIs('peserta.kelas.*') ? 'bg-brand-soft text-brand-deep' : 'text-ink hover:bg-surface' }}"
                    @if (request()->routeIs('peserta.kelas.*')) aria-current="page" @endif
                >
                    <x-icon name="kelas" :size="20" />
                    Kelas
                </a>
            </nav>
        </div>

        <div class="pt-4 border-t border-outline">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl border border-outline bg-surface px-4 py-2.5 font-bold text-coral-deep hover:bg-coral-soft transition">
                    <x-icon name="logout" :size="20" />
                    Keluar
                </button>
            </form>
        </div>
    </div>

    <header class="border-b border-outline bg-page sticky top-0 z-30">
        <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-10">
            <!-- Brand Logo -->
            <a href="{{ route('peserta.dashboard') }}" class="flex items-center gap-2.5 rounded-xl">
                <img src="{{ asset('logo.png') }}" alt="Logo STIKOM Quiz" class="h-9 w-9 object-contain">
                <span class="font-display text-lg font-bold text-brand-deep sm:text-xl">STIKOM Quiz</span>
            </a>

            <!-- Tombol Hamburger Mobile -->
            <div class="flex items-center gap-2 lg:hidden">
                <button
                    type="button"
                    class="btn btn-secondary h-11 w-11 p-0"
                    x-on:click="mobileNav = true"
                    x-bind:aria-expanded="mobileNav ? 'true' : 'false'"
                    aria-controls="menu-peserta"
                    aria-label="Buka menu navigasi"
                >
                    <x-icon name="menu" :size="22" />
                </button>
            </div>

            <!-- Desktop Nav -->
            <div class="hidden lg:flex lg:items-center lg:gap-3">
                <nav aria-label="Navigasi peserta" class="flex items-center gap-2">
                    <a
                        href="{{ route('peserta.dashboard') }}"
                        class="nav-link {{ request()->routeIs('peserta.dashboard') ? 'nav-link-active' : '' }}"
                        @if (request()->routeIs('peserta.dashboard')) aria-current="page" @endif
                    >
                        <x-icon name="home" :size="20" />
                        Dashboard
                    </a>
                    <a
                        href="{{ route('peserta.kelas.index') }}"
                        class="nav-link {{ request()->routeIs('peserta.kelas.*') ? 'nav-link-active' : '' }}"
                        @if (request()->routeIs('peserta.kelas.*')) aria-current="page" @endif
                    >
                        <x-icon name="kelas" :size="20" />
                        Kelas
                    </a>
                </nav>
                <span class="h-8 w-px bg-outline"></span>
                <span class="px-2 text-base font-medium text-ink-muted">{{ auth()->user()->nama }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost text-ink-muted hover:text-coral-deep">
                        <x-icon name="logout" :size="20" />
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main id="utama" class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-10 lg:py-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-3xl lg:text-4xl">{{ $judul }}</h1>
                @if ($keterangan)
                    <p class="mt-2 max-w-2xl text-lg text-ink-muted">{{ $keterangan }}</p>
                @endif
            </div>
            @isset($aksi)
                <div class="flex flex-wrap gap-3">{{ $aksi }}</div>
            @endisset
        </div>

        <div class="mt-6 space-y-4">
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
        </div>

        <div class="mt-8">
            {{ $slot }}
        </div>
    </main>

    <footer class="border-t border-outline px-4 py-6 text-center text-sm text-ink-subtle sm:px-6 lg:px-10">
        STIKOM Quiz berjalan di jaringan lokal kelas.
    </footer>
</body>
</html>
