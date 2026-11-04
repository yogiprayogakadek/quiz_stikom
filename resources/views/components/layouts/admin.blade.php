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
<body class="min-h-screen bg-surface">
    <a href="#utama" class="skip-link">Lewati ke konten utama</a>

    <div
        x-data="{
            menu: false,
            ringkas: false,
            init() {
                this.ringkas = localStorage.getItem('admin-menu-ringkas') === '1';
            },
            toggleRingkas() {
                this.ringkas = !this.ringkas;
                localStorage.setItem('admin-menu-ringkas', this.ringkas ? '1' : '0');
            }
        }"
        x-on:keydown.escape.window="menu = false"
    >
        <div
            x-show="menu"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-40 bg-ink/40 lg:hidden"
            x-on:click="menu = false"
            aria-hidden="true"
        ></div>

        <div x-show="menu" x-cloak id="menu-admin" class="fixed inset-y-0 left-0 z-50 w-72 overflow-y-auto border-r border-outline bg-page p-5 lg:hidden">
            <div class="flex items-center justify-between">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-xl">
                    <img src="{{ asset('logo.png') }}" alt="Logo STIKOM Quiz" class="h-10 w-10 object-contain">
                    <span class="font-display text-xl font-bold text-brand-deep">STIKOM Quiz</span>
                </a>
                <button
                    type="button"
                    class="btn btn-ghost px-3"
                    x-on:click="menu = false"
                    aria-label="Tutup menu"
                >
                    <x-icon name="x" :size="24" />
                </button>
            </div>

            <p class="mt-6 px-3 text-sm font-bold text-ink-subtle">Ruang admin</p>
            <x-nav-admin />
        </div>

        <aside
            class="fixed inset-y-0 left-0 z-30 hidden overflow-y-auto border-r border-outline bg-page p-5 transition-all duration-300 lg:block"
            x-bind:class="{ 'w-24': ringkas, 'w-72': !ringkas }"
        >
            <div class="flex items-center gap-2" x-bind:class="{ 'justify-center': ringkas, 'justify-between': !ringkas }">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-xl" x-show="!ringkas" x-cloak>
                    <img src="{{ asset('logo.png') }}" alt="Logo STIKOM Quiz" class="h-10 w-10 object-contain">
                    <span class="font-display text-xl font-bold text-brand-deep">STIKOM Quiz</span>
                </a>
                <button
                    type="button"
                    class="btn btn-ghost px-2"
                    x-on:click="toggleRingkas()"
                    x-bind:aria-label="ringkas ? 'Perbesar menu' : 'Perkecil menu'"
                    title="Perkecil atau perbesar menu"
                >
                    <x-icon name="menu" :size="22" />
                </button>
            </div>

            <p class="mt-6 px-3 text-sm font-bold text-ink-subtle" x-show="!ringkas" x-cloak>Ruang admin</p>
            <x-nav-admin :ringkas="true" />
        </aside>

        <div class="flex min-h-screen flex-col" x-bind:class="{ 'lg:pl-72': !ringkas, 'lg:pl-24': ringkas }">
            <header class="sticky top-0 z-30 border-b border-outline bg-page lg:hidden">
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <button
                        type="button"
                        class="btn btn-secondary px-3"
                        x-on:click="menu = true"
                        x-bind:aria-expanded="menu ? 'true' : 'false'"
                        aria-controls="menu-admin"
                        aria-label="Buka menu"
                    >
                        <x-icon name="menu" :size="24" />
                    </button>
                    <div class="flex items-center gap-2">
                        <img src="{{ asset('logo.png') }}" alt="Logo STIKOM Quiz" class="h-7 w-7 object-contain">
                        <span class="font-display text-lg font-bold text-brand-deep">STIKOM Quiz</span>
                    </div>
                    <span class="pill pill-brand">Admin</span>
                </div>
            </header>

            <main id="utama" class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 lg:px-10 lg:py-10">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl">{{ $judul }}</h1>
                        @if ($keterangan)
                            <p class="mt-2 max-w-2xl text-base text-ink-muted">{{ $keterangan }}</p>
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

                <div class="mt-8">
                    {{ $slot }}
                </div>
            </main>

            <footer class="mt-auto border-t border-outline px-4 py-6 text-center text-sm text-ink-subtle sm:px-6 lg:px-10">
                STIKOM Quiz berjalan di jaringan lokal kelas.
            </footer>
        </div>
    </div>
</body>
</html>
