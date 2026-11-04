@php
    $navigasi = [
        ['rute' => 'admin.dashboard', 'aktif' => 'admin.dashboard', 'label' => 'Dashboard', 'ikon' => 'home'],
        ['rute' => 'admin.participants.index', 'aktif' => 'admin.participants.*', 'label' => 'Peserta', 'ikon' => 'users'],
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
            @if (request()->routeIs($item['aktif'])) aria-current="page" @endif
        >
            <x-icon :name="$item['ikon']" :size="22" />
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>

<div class="mt-6 rounded-2xl border border-outline bg-surface p-4">
    <p class="text-sm font-bold text-ink">{{ auth()->user()->nama }}</p>
    <p class="text-sm text-ink-muted">Masuk sebagai admin</p>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn btn-secondary w-full">
            <x-icon name="logout" :size="20" />
            Keluar
        </button>
    </form>
</div>
