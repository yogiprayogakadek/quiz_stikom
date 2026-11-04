@props(['jenis' => 'info'])

@php
    $kelas = match ($jenis) {
        'success' => 'alert-success',
        'error' => 'alert-error',
        'warning' => 'alert-warning',
        default => 'alert-info',
    };
    $peran = in_array($jenis, ['error', 'warning'], true) ? 'alert' : 'status';
@endphp

<div role="{{ $peran }}" {{ $attributes->merge(['class' => 'alert '.$kelas]) }}>
    {{ $slot }}
</div>
