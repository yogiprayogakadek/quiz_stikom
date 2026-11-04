@props(['ringkas' => false])

@php
    $navigasi = [
        ['rute' => 'admin.dashboard', 'aktif' => 'admin.dashboard', 'label' => 'Dashboard', 'ikon' => 'home'],
        ['rute' => 'admin.participants.index', 'aktif' => 'admin.participants.*', 'label' => 'Peserta', 'ikon' => 'users'],
        ['rute' => 'admin.classes.index', 'aktif' => 'admin.classes.*', 'label' => 'Kelas', 'ikon' => 'kelas'],
        ['rute' => 'admin.questions.index', 'aktif' => 'admin.questions.*', 'label' => 'Bank soal', 'ikon' => 'pencil'],
        ['rute' => 'admin.quizzes.index', 'aktif' => 'admin.quizzes.*', 'label' => 'Kuis', 'ikon' => 'book'],
        ['rute' => 'admin.sessions.index', 'aktif' => 'admin.sessions.*', 'label' => 'Sesi', 'ikon' => 'play'],
    ];
@endphp

<nav class="mt-2 space-y-1" aria-label="Navigasi admin">
    @foreach ($navigasi as $item)
        <a
            href="{{ route($item['rute']) }}"
            class="nav-link {{ request()->routeIs($item['aktif']) ? 'nav-link-active' : '' }}"
            @if ($ringkas) x-bind:class="{ 'justify-center': ringkas }" @endif
            aria-label="{{ $item['label'] }}"
            title="{{ $item['label'] }}"
            @if (request()->routeIs($item['aktif'])) aria-current="page" @endif
        >
            <x-icon :name="$item['ikon']" :size="22" />
            @if ($ringkas)
                <span x-show="!ringkas" x-cloak>{{ $item['label'] }}</span>
            @else
                {{ $item['label'] }}
            @endif
        </a>
    @endforeach
</nav>

<div class="mt-6 rounded-2xl border border-outline bg-surface p-4">
    <div @if ($ringkas) x-show="!ringkas" x-cloak @endif>
        <p class="text-sm font-bold text-ink">{{ auth()->user()->nama }}</p>
        <p class="text-sm text-ink-muted">Masuk sebagai admin</p>
    </div>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button
            type="submit"
            class="btn btn-secondary w-full"
            @if ($ringkas) x-bind:class="{ 'px-2': ringkas }" @endif
            aria-label="Keluar"
            title="Keluar"
        >
            <x-icon name="logout" :size="20" />
            @if ($ringkas)
                <span x-show="!ringkas" x-cloak>Keluar</span>
            @else
                Keluar
            @endif
        </button>
    </form>
</div>
