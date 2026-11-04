@props(['status'])

@php
    [$label, $kelas] = match ($status) {
        'draft' => ['Draft', 'pill-neutral'],
        'lobby' => ['Lobby', 'pill-brand'],
        'berjalan' => ['Berjalan', 'pill-leaf'],
        'selesai' => ['Selesai', 'pill-sun'],
        default => [$status, 'pill-neutral'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'pill '.$kelas]) }}>{{ $label }}</span>
