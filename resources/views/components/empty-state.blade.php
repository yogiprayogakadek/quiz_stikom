@props(['judul', 'keterangan' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-3 rounded-card border border-dashed border-outline-strong bg-page px-6 py-12 text-center']) }}>
    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-soft text-brand-deep">
        <x-icon name="book" :size="28" />
    </div>
    <p class="font-display text-xl font-bold text-ink">{{ $judul }}</p>
    @if ($keterangan)
        <p class="max-w-md text-base text-ink-muted">{{ $keterangan }}</p>
    @endif
    {{ $slot }}
</div>
