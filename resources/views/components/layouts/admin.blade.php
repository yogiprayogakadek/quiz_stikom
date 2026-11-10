@props(['judul' => 'Dashboard', 'keterangan' => null, 'canvas' => false])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $judul }} | {{ $appSettings->site_name }}</title>
    <link rel="icon" href="{{ $appSettings->logoUrl() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-brand-style />
</head>
<body
    class="min-h-screen bg-[#f8fafc] text-slate-900"
    x-data="{
        mobileNav: false,
        sidebarCollapsed: localStorage.getItem('admin-sidebar-collapsed') === 'true',
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('admin-sidebar-collapsed', this.sidebarCollapsed ? 'true' : 'false');
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
        id="menu-admin"
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col justify-between bg-white border-r border-slate-200/80 p-5 shadow-2xl lg:hidden font-sans"
        role="dialog"
        aria-modal="true"
        aria-label="Menu navigasi administrator"
    >
        <div>
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-100 p-2 shadow-xs ring-1 ring-slate-200/70">
                        <img src="{{ $appSettings->logoUrl() }}" alt="Logo {{ $appSettings->site_name }}" class="h-full w-full object-contain">
                    </div>
                    <div>
                        <span class="font-display text-lg font-extrabold tracking-tight text-slate-900 block leading-tight">{{ $appSettings->site_name }}</span>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">{{ $appSettings->organization_name ?: 'Panel Administrator' }}</span>
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

            <nav class="mt-6 space-y-1.5" aria-label="Navigasi mobile administrator">
                <a
                    href="{{ route('admin.dashboard') }}"
                    class="flex items-center gap-3.5 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('admin.dashboard') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                >
                    <x-icon name="home" :size="20" class="{{ request()->routeIs('admin.dashboard') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                    <span>Dashboard</span>
                </a>
                <a
                    href="{{ route('admin.participants.index') }}"
                    class="flex items-center gap-3.5 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('admin.participants.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                >
                    <x-icon name="users" :size="20" class="{{ request()->routeIs('admin.participants.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                    <span>Peserta</span>
                </a>
                <a
                    href="{{ route('admin.classes.index') }}"
                    class="flex items-center gap-3.5 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('admin.classes.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                >
                    <x-icon name="kelas" :size="20" class="{{ request()->routeIs('admin.classes.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                    <span>Kelas</span>
                </a>
                <a
                    href="{{ route('admin.quizzes.index') }}"
                    class="flex items-center gap-3.5 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('admin.quizzes.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                >
                    <x-icon name="book" :size="20" class="{{ request()->routeIs('admin.quizzes.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                    <span>Kuis & Soal</span>
                </a>
                <a
                    href="{{ route('admin.sessions.index') }}"
                    class="flex items-center gap-3.5 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('admin.sessions.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                >
                    <x-icon name="play" :size="20" class="{{ request()->routeIs('admin.sessions.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                    <span>Sesi Ujian</span>
                </a>
                <a
                    href="{{ route('admin.backups.index') }}"
                    class="flex items-center gap-3.5 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('admin.backups.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                >
                    <x-icon name="download" :size="20" class="{{ request()->routeIs('admin.backups.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                    <span>Backup</span>
                </a>
                <a
                    href="{{ route('admin.settings.edit') }}"
                    class="flex items-center gap-3.5 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('admin.settings.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    @if (request()->routeIs('admin.settings.*')) aria-current="page" @endif
                >
                    <x-icon name="settings" :size="20" class="{{ request()->routeIs('admin.settings.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                    <span>Pengaturan</span>
                </a>
                @if (auth()->user()->isPlatformAdmin())
                    <a
                        href="{{ route('master.organizations.index') }}"
                        class="flex items-center gap-3.5 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('master.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        @if (request()->routeIs('master.*')) aria-current="page" @endif
                    >
                        <x-icon name="users" :size="20" class="{{ request()->routeIs('master.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                        <span>Organisasi</span>
                    </a>
                @endif
                @if (auth()->user()->isCurrentOrganizationOwner())
                    <a
                        href="{{ route('admin.team.index') }}"
                        class="flex items-center gap-3.5 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('admin.team.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        @if (request()->routeIs('admin.team.*')) aria-current="page" @endif
                    >
                        <x-icon name="shield-check" :size="20" class="{{ request()->routeIs('admin.team.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                        <span>Tim Admin</span>
                    </a>
                @endif
            </nav>
            <x-organization-switcher id="admin-mobile" class="mt-4" />
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-800 to-slate-900 text-white font-bold font-display text-base shadow-sm">
                    {{ strtoupper(substr(auth()->user()->nama, 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-extrabold text-slate-800 leading-tight">{{ auth()->user()->nama }}</p>
                    <p class="text-[11px] font-semibold text-slate-400">Administrator</p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <a href="{{ route('password.change') }}" class="p-2 text-slate-400 hover:text-brand-deep hover:bg-brand-soft rounded-xl transition" title="Ganti password" aria-label="Ganti password">
                    <x-icon name="lock" :size="18" />
                </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition" title="Keluar" aria-label="Keluar">
                    <x-icon name="logout" :size="18" />
                </button>
            </form>
            </div>
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
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 min-w-0" :class="sidebarCollapsed ? 'lg:justify-center' : ''" :title="sidebarCollapsed ? @js($appSettings->site_name) : ''">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-100 p-2 shadow-xs ring-1 ring-slate-200/70">
                            <img src="{{ $appSettings->logoUrl() }}" alt="Logo {{ $appSettings->site_name }}" class="h-full w-full object-contain">
                        </div>
                        <div x-show="!sidebarCollapsed" x-cloak class="min-w-0">
                            <span class="font-display text-xl font-extrabold tracking-tight text-slate-900 block leading-tight truncate">{{ $appSettings->site_name }}</span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block truncate">{{ $appSettings->organization_name ?: 'Panel Administrator' }}</span>
                        </div>
                    </a>
                </div>

                {{-- Navigation Links --}}
                <nav class="mt-8 space-y-1.5">
                    <a
                        href="{{ route('admin.dashboard') }}"
                        class="flex items-center rounded-2xl text-sm font-bold transition {{ request()->routeIs('admin.dashboard') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0 lg:py-3.5' : 'px-4 py-3 gap-3.5'"
                        :title="sidebarCollapsed ? 'Dashboard' : ''"
                    >
                        <x-icon name="home" :size="20" class="{{ request()->routeIs('admin.dashboard') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                        <span x-show="!sidebarCollapsed" x-cloak>Dashboard</span>
                    </a>
                    <a
                        href="{{ route('admin.participants.index') }}"
                        class="flex items-center rounded-2xl text-sm font-bold transition {{ request()->routeIs('admin.participants.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0 lg:py-3.5' : 'px-4 py-3 gap-3.5'"
                        :title="sidebarCollapsed ? 'Peserta' : ''"
                    >
                        <x-icon name="users" :size="20" class="{{ request()->routeIs('admin.participants.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                        <span x-show="!sidebarCollapsed" x-cloak>Peserta</span>
                    </a>
                    <a
                        href="{{ route('admin.classes.index') }}"
                        class="flex items-center rounded-2xl text-sm font-bold transition {{ request()->routeIs('admin.classes.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0 lg:py-3.5' : 'px-4 py-3 gap-3.5'"
                        :title="sidebarCollapsed ? 'Kelas' : ''"
                    >
                        <x-icon name="kelas" :size="20" class="{{ request()->routeIs('admin.classes.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                        <span x-show="!sidebarCollapsed" x-cloak>Kelas</span>
                    </a>
                    <a
                        href="{{ route('admin.quizzes.index') }}"
                        class="flex items-center rounded-2xl text-sm font-bold transition {{ request()->routeIs('admin.quizzes.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0 lg:py-3.5' : 'px-4 py-3 gap-3.5'"
                        :title="sidebarCollapsed ? 'Kuis & Soal' : ''"
                    >
                        <x-icon name="book" :size="20" class="{{ request()->routeIs('admin.quizzes.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                        <span x-show="!sidebarCollapsed" x-cloak>Kuis & Soal</span>
                    </a>
                    <a
                        href="{{ route('admin.sessions.index') }}"
                        class="flex items-center rounded-2xl text-sm font-bold transition {{ request()->routeIs('admin.sessions.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0 lg:py-3.5' : 'px-4 py-3 gap-3.5'"
                        :title="sidebarCollapsed ? 'Sesi Ujian' : ''"
                    >
                        <x-icon name="play" :size="20" class="{{ request()->routeIs('admin.sessions.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                        <span x-show="!sidebarCollapsed" x-cloak>Sesi Ujian</span>
                    </a>
                    <a
                        href="{{ route('admin.backups.index') }}"
                        class="flex items-center rounded-2xl text-sm font-bold transition {{ request()->routeIs('admin.backups.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0 lg:py-3.5' : 'px-4 py-3 gap-3.5'"
                        :title="sidebarCollapsed ? 'Backup' : ''"
                    >
                        <x-icon name="download" :size="20" class="{{ request()->routeIs('admin.backups.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                        <span x-show="!sidebarCollapsed" x-cloak>Backup</span>
                    </a>
                    <a
                        href="{{ route('admin.settings.edit') }}"
                        class="flex items-center rounded-2xl text-sm font-bold transition {{ request()->routeIs('admin.settings.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0 lg:py-3.5' : 'px-4 py-3 gap-3.5'"
                        :title="sidebarCollapsed ? 'Pengaturan' : ''"
                        @if (request()->routeIs('admin.settings.*')) aria-current="page" @endif
                    >
                        <x-icon name="settings" :size="20" class="{{ request()->routeIs('admin.settings.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                        <span x-show="!sidebarCollapsed" x-cloak>Pengaturan</span>
                    </a>
                    @if (auth()->user()->isPlatformAdmin())
                        <a
                            href="{{ route('master.organizations.index') }}"
                            class="flex items-center rounded-2xl text-sm font-bold transition {{ request()->routeIs('master.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                            :class="sidebarCollapsed ? 'lg:justify-center lg:px-0 lg:py-3.5' : 'px-4 py-3 gap-3.5'"
                            :title="sidebarCollapsed ? 'Organisasi' : ''"
                            @if (request()->routeIs('master.*')) aria-current="page" @endif
                        >
                            <x-icon name="users" :size="20" class="{{ request()->routeIs('master.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                            <span x-show="!sidebarCollapsed" x-cloak>Organisasi</span>
                        </a>
                    @endif
                    @if (auth()->user()->isCurrentOrganizationOwner())
                        <a
                            href="{{ route('admin.team.index') }}"
                            class="flex items-center rounded-2xl text-sm font-bold transition {{ request()->routeIs('admin.team.*') ? 'bg-[#e6f0fd] text-[#1e6ffb] shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                            :class="sidebarCollapsed ? 'lg:justify-center lg:px-0 lg:py-3.5' : 'px-4 py-3 gap-3.5'"
                            :title="sidebarCollapsed ? 'Tim Admin' : ''"
                            @if (request()->routeIs('admin.team.*')) aria-current="page" @endif
                        >
                            <x-icon name="shield-check" :size="20" class="{{ request()->routeIs('admin.team.*') ? 'text-[#1e6ffb]' : 'text-slate-400' }}" />
                            <span x-show="!sidebarCollapsed" x-cloak>Tim Admin</span>
                        </a>
                    @endif
                </nav>
                <x-organization-switcher id="admin-desktop" class="mt-5" x-show="!sidebarCollapsed" x-cloak />

                {{-- Server Status Card --}}
                <div x-show="!sidebarCollapsed" x-cloak class="mt-8 hidden lg:block rounded-2xl bg-gradient-to-br from-slate-50 to-blue-50/60 border border-slate-200/80 p-4 shadow-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-500/15 text-blue-600">
                            <x-icon name="shield-check" :size="18" />
                        </span>
                        <span class="font-display text-sm font-extrabold text-slate-900">Status layanan</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 leading-relaxed">
                        Realtime dan antrean kuis siap melayani kelas.
                    </p>
                    <a href="{{ route('admin.sessions.create') }}" class="mt-3 block w-full text-center py-2.5 px-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold shadow-xs transition">
                        + Buka Sesi Ujian
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
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-800 to-slate-900 text-white font-bold font-display text-base shadow-sm"
                        title="{{ auth()->user()->nama }}"
                    >
                        {{ strtoupper(substr(auth()->user()->nama, 0, 1)) }}
                    </div>
                    <div x-show="!sidebarCollapsed" x-cloak class="min-w-0 flex-1">
                        <p class="truncate text-sm font-extrabold text-slate-800 leading-tight">{{ auth()->user()->nama }}</p>
                        <p class="text-[11px] font-semibold text-slate-400">Administrator</p>
                    </div>
                </div>
                <div class="flex items-center gap-1" :class="sidebarCollapsed ? 'lg:flex-col' : ''">
                    <a href="{{ route('password.change') }}" class="p-2 text-slate-400 hover:text-brand-deep hover:bg-brand-soft rounded-xl transition" title="Ganti password" aria-label="Ganti password">
                        <x-icon name="lock" :size="18" />
                    </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition" title="Keluar" aria-label="Keluar">
                        <x-icon name="logout" :size="18" />
                    </button>
                </form>
                </div>
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

                        @if (session('password_baru'))
                            <x-alert jenis="warning">
                                <p class="font-bold">Catat password ini sekarang. Password hanya ditampilkan sekali.</p>
                                <p class="mt-2 font-mono text-lg">
                                    {{ session('password_baru')['username'] }} : {{ session('password_baru')['password'] }}
                                </p>
                            </x-alert>
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
    <x-global-progress />
</body>
</html>
