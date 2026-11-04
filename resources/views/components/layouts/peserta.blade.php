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

    <header class="border-b border-outline bg-page">
        <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-10">
            <a href="{{ route('peserta.dashboard') }}" class="flex items-center gap-3 rounded-xl">
                <img src="{{ asset('logo.png') }}" alt="Logo STIKOM Quiz" class="h-10 w-10 object-contain">
                <span class="font-display text-xl font-bold text-brand-deep">STIKOM Quiz</span>
            </a>

            <div class="flex flex-wrap items-center gap-2">
                <nav aria-label="Navigasi peserta">
                    <a
                        href="{{ route('peserta.dashboard') }}"
                        class="nav-link {{ request()->routeIs('peserta.dashboard') ? 'nav-link-active' : '' }}"
                        @if (request()->routeIs('peserta.dashboard')) aria-current="page" @endif
                    >
                        <x-icon name="home" :size="20" />
                        Dashboard
                    </a>
                </nav>
                <span class="hidden h-8 w-px bg-outline sm:block"></span>
                <span class="px-2 text-base text-ink-muted">{{ auth()->user()->nama }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost">
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
