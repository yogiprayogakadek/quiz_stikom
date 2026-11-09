@props(['kelas', 'showTitle' => false])

@php
    $palettes = [
        ['#12305b', '#1e5f8f', '#7dd3fc'],
        ['#173f35', '#28745d', '#86efac'],
        ['#5a2f19', '#a5542c', '#fdba74'],
        ['#3f315f', '#7457a8', '#c4b5fd'],
    ];
    $seed = abs(crc32($kelas->id.'|'.$kelas->nama));
    [$base, $mid, $accent] = $palettes[$seed % count($palettes)];
    $words = preg_split('/\s+/u', trim($kelas->nama), -1, PREG_SPLIT_NO_EMPTY);
    $initials = collect($words)->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
@endphp

<div {{ $attributes->merge(['class' => 'relative isolate aspect-[16/7] overflow-hidden bg-slate-900']) }}>
    @if ($appSettings->class_cover_mode === 'ai' && $kelas->cover_path)
        <img src="{{ asset('storage/'.$kelas->cover_path) }}" alt="" class="absolute inset-0 h-full w-full object-cover" width="800" height="350">
        <span class="absolute inset-0 bg-slate-950/45" aria-hidden="true"></span>
    @else
        <svg viewBox="0 0 800 350" class="absolute inset-0 h-full w-full" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <rect width="800" height="350" fill="{{ $base }}" />
            <path d="M0 275C125 205 220 315 355 240s235-25 445-105v215H0Z" fill="{{ $mid }}" />
            <g fill="none" stroke="{{ $accent }}" stroke-width="12" opacity=".75">
                <rect x="500" y="58" width="190" height="112" rx="14" />
                <path d="M530 93h130M530 127h92" />
            </g>
            <circle cx="710" cy="285" r="92" fill="{{ $accent }}" opacity=".18" />
        </svg>
    @endif

    <div class="relative flex h-full items-end justify-between gap-4 p-4 sm:p-5">
        @if ($showTitle)
            <div class="min-w-0 text-white">
                <p class="text-xs font-bold text-white/75">Kelas</p>
                <p class="truncate font-display text-lg font-extrabold sm:text-xl">{{ $kelas->nama }}</p>
            </div>
        @else
            <span></span>
        @endif
        <span class="flex h-11 min-w-11 shrink-0 items-center justify-center rounded-xl bg-white/95 px-2 font-display text-sm font-extrabold" style="color: {{ $base }}" aria-hidden="true">
            {{ $initials ?: 'K' }}
        </span>
    </div>
</div>
