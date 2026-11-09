@props(['judul' => 'Dashboard', 'keterangan' => null, 'canvas' => false])

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
<body
    class="min-h-screen bg-[#f8fafc] text-slate-900"
    x-data="{
        mobileNav: false,
        sidebarCollapsed: localStorage.getItem('peserta-sidebar-collapsed') === 'true',
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('peserta-sidebar-collapsed', this.sidebarCollapsed ? 'true' : 'false');
        }
    }"
    x-on:keydown.escape.window="mobileNav = false"
>
    <a href="#utama" class="skip-link">Lewati ke konten utama</a>

    <!-- Mobile Slide-over Backdrop -->
    <div
        x-show="mobileNav"
        x-cloak
        x-transition.opacity.duration.300ms
        class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-xs lg:hidden"
        @click="mobileNav = false"
        aria-hidden="true"
    ></div>

    <!-- Mobile Slide-over Navigation Drawer -->
    <div
        x-show="mobileNav"
        x-cloak
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        id="menu-peserta"
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col justify-between bg-white border-r border-slate-200/80 p-5 shadow-2xl lg:hidden font-sans"
        role="dialog"
        aria-modal="true"
        aria-label="Menu navigasi peserta"
    >
        <div>
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <a href="{{ route('peserta.dashboard') }}" class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-100 p-2 shadow-xs ring-1 ring-slate-200/70">
                        <img src="{{ asset('logo.png') }}" alt="Logo STIKOM Quiz" class="h-full w-full object-contain">
                    </div>
                    <div>
                        <span class="font-display text-lg font-extrabold tracking-tight text-slate-900 block leading-tight">STIKOM Quiz</span>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Learn · Practice · Win</span>
                    </div>
                </a>
                <button
                    type="button"
                    @click="mobileNav = false"
                    class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition"
                    aria-label="Tutup menu"
                >
                    <x-icon name="x" :size="20" />
                </button>
            </div>

            <nav class="mt-6 space-y-1.5" aria-label="Navigasi mobile peserta">
                <a
                    href="{{ route('peserta.dashboard') }}"
                    class="flex items-center gap-3.5 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('peserta.dashboard') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                >
                    <x-icon name="home" :size="20" class="{{ request()->routeIs('peserta.dashboard') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                    <span>Beranda</span>
                </a>
                <a
                    href="{{ route('peserta.kelas.index') }}"
                    class="flex items-center gap-3.5 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('peserta.kelas.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                >
                    <x-icon name="kelas" :size="20" class="{{ request()->routeIs('peserta.kelas.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                    <span>Kelas Saya</span>
                </a>
            </nav>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white font-bold font-display text-base shadow-sm">
                    {{ strtoupper(substr(auth()->user()->nama, 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-extrabold text-slate-800 leading-tight">{{ auth()->user()->nama }}</p>
                    <p class="text-[11px] font-semibold text-slate-400">Peserta</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition" title="Keluar" aria-label="Keluar">
                    <x-icon name="logout" :size="18" />
                </button>
            </form>
        </div>
    </div>

    {{-- Unified Full-Screen Layout Shell --}}
    <div class="min-h-screen w-full bg-[#f8fafc] flex flex-col lg:flex-row font-sans">
        {{-- Left Sidebar Desktop (Collapsible: Icon-Only w-20 or Full w-64/w-72) --}}
        <aside
            class="hidden lg:flex lg:flex-col lg:justify-between lg:h-screen lg:sticky lg:top-0 bg-white border-r border-slate-200/80 p-5 flex-shrink-0 transition-all duration-300 ease-in-out"
            :class="sidebarCollapsed ? 'lg:w-20' : 'lg:w-64 xl:w-72'"
        >
            <div>
                {{-- Logo & App Title (No toggle here - toggle is in the top bar) --}}
                <div class="flex items-center gap-3 min-w-0" :class="sidebarCollapsed ? 'lg:justify-center' : ''">
                    <a href="{{ route('peserta.dashboard') }}" class="flex items-center gap-3 min-w-0" :class="sidebarCollapsed ? 'lg:justify-center' : ''" :title="sidebarCollapsed ? 'STIKOM Quiz' : ''">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-100 p-2 shadow-xs ring-1 ring-slate-200/70">
                            <img src="{{ asset('logo.png') }}" alt="Logo STIKOM Quiz" class="h-full w-full object-contain">
                        </div>
                        <div x-show="!sidebarCollapsed" x-cloak class="min-w-0">
                            <span class="font-display text-xl font-extrabold tracking-tight text-slate-900 block leading-tight truncate">STIKOM Quiz</span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Learn · Practice · Win</span>
                        </div>
                    </a>
                </div>

                {{-- Navigation Links --}}
                <nav class="mt-8 space-y-1.5">
                    <a
                        href="{{ route('peserta.dashboard') }}"
                        class="flex items-center rounded-2xl text-sm font-bold transition {{ request()->routeIs('peserta.dashboard') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0 lg:py-3.5' : 'px-4 py-3 gap-3.5'"
                        :title="sidebarCollapsed ? 'Beranda' : ''"
                    >
                        <x-icon name="home" :size="20" class="{{ request()->routeIs('peserta.dashboard') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                        <span x-show="!sidebarCollapsed" x-cloak>Beranda</span>
                    </a>
                    <a
                        href="{{ route('peserta.kelas.index') }}"
                        class="flex items-center rounded-2xl text-sm font-bold transition {{ request()->routeIs('peserta.kelas.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0 lg:py-3.5' : 'px-4 py-3 gap-3.5'"
                        :title="sidebarCollapsed ? 'Kelas Saya' : ''"
                    >
                        <x-icon name="kelas" :size="20" class="{{ request()->routeIs('peserta.kelas.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                        <span x-show="!sidebarCollapsed" x-cloak>Kelas Saya</span>
                    </a>
                </nav>

                {{-- Info Box (Portal Peserta) --}}
                <div x-show="!sidebarCollapsed" x-cloak class="mt-8 hidden lg:block rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-50/50 border border-blue-200/60 p-4 shadow-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-500/15 text-blue-600">
                            <x-icon name="crown" :size="18" />
                        </span>
                        <span class="font-display text-sm font-extrabold text-blue-950">Portal Peserta</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 leading-relaxed">
                        Akses soal ujian dan kuis lokal dengan PIN kelas dari admin.
                    </p>
                    <a href="{{ route('peserta.kelas.index') }}" class="mt-3 block w-full text-center py-2.5 px-3 rounded-xl bg-[#1e6ffb] hover:bg-blue-600 text-white text-xs font-extrabold shadow-xs transition">
                        Masuk Kelas
                    </a>
                </div>
            </div>

            {{-- User Profile Footer --}}
            <div
                class="mt-8 pt-4 border-t border-slate-100 flex items-center justify-between"
                :class="sidebarCollapsed ? 'lg:flex-col lg:gap-3 lg:justify-center' : ''"
            >
                <div class="flex items-center gap-3 min-w-0" :class="sidebarCollapsed ? 'lg:justify-center' : ''">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white font-bold font-display text-base shadow-sm"
                        title="{{ auth()->user()->nama }}"
                    >
                        {{ strtoupper(substr(auth()->user()->nama, 0, 1)) }}
                    </div>
                    <div x-show="!sidebarCollapsed" x-cloak class="min-w-0 flex-1">
                        <p class="truncate text-sm font-extrabold text-slate-800 leading-tight">{{ auth()->user()->nama }}</p>
                        <p class="text-[11px] font-semibold text-slate-400">Peserta</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition" title="Keluar" aria-label="Keluar">
                        <x-icon name="logout" :size="18" />
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main Content Canvas Full Page --}}
        <main id="utama" class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 xl:p-10 overflow-y-auto">
            @if ($canvas)
                {{ $slot }}
            @else
                {{-- Standard Subpage Header with Single Hamburger Toggle --}}
                <div class="mb-7">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-200/80">
                        <div class="flex items-center gap-3.5 min-w-0">
                            {{-- Single Desktop Hamburger Toggle --}}
                            <button
                                type="button"
                                @click="toggleSidebar()"
                                class="hidden lg:inline-flex items-center justify-center h-11 w-11 rounded-2xl bg-white border border-slate-200/90 text-slate-600 hover:text-slate-900 hover:bg-slate-50 shadow-xs transition shrink-0"
                                :title="sidebarCollapsed ? 'Perbesar Menu' : 'Perkecil Menu'"
                                :aria-label="sidebarCollapsed ? 'Perbesar Menu' : 'Perkecil Menu'"
                            >
                                <x-icon name="menu" :size="20" />
                            </button>

                            {{-- Single Mobile Hamburger Toggle --}}
                            <button
                                type="button"
                                @click="mobileNav = true"
                                class="lg:hidden inline-flex items-center justify-center h-11 w-11 rounded-2xl bg-white border border-slate-200/90 text-slate-600 hover:text-slate-900 hover:bg-slate-50 shadow-xs transition shrink-0"
                                aria-label="Buka menu navigasi"
                            >
                                <x-icon name="menu" :size="20" />
                            </button>

                            <div class="min-w-0">
                                <h1 class="font-display text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 leading-tight truncate">
                                    {{ $judul }}
                                </h1>
                                @if ($keterangan)
                                    <p class="mt-1 text-sm text-slate-500 leading-relaxed max-w-3xl">
                                        {{ $keterangan }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        @isset($aksi)
                            <div class="flex flex-wrap items-center gap-2.5 sm:shrink-0 self-start sm:self-auto">
                                {{ $aksi }}
                            </div>
                        @endisset
                    </div>

                    {{-- Flash Messages & Error Alerts --}}
                    <div class="mt-5 space-y-3">
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
                </div>

                {{-- Subpage Slot --}}
                <div>
                    {{ $slot }}
                </div>
            @endif
        </main>
    </div>
</body>
</html>
