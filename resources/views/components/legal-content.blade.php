@props(['content'])

@php
    $blocks = [];
    $paragraph = [];

    foreach (preg_split('/\R/', trim($content)) as $line) {
        if (str_starts_with($line, '## ')) {
            if ($paragraph !== []) {
                $blocks[] = ['paragraph', implode("\n", $paragraph)];
                $paragraph = [];
            }

            $blocks[] = ['heading', trim(substr($line, 3))];
        } elseif (trim($line) === '') {
            if ($paragraph !== []) {
                $blocks[] = ['paragraph', implode("\n", $paragraph)];
                $paragraph = [];
            }
        } else {
            $paragraph[] = rtrim($line);
        }
    }

    if ($paragraph !== []) {
        $blocks[] = ['paragraph', implode("\n", $paragraph)];
    }
@endphp

<div class="space-y-4">
    @foreach ($blocks as [$type, $text])
        @if ($type === 'heading')
            <h2 class="text-xl text-ink">{{ $text }}</h2>
        @else
            <p class="text-base leading-relaxed text-ink-muted">{!! nl2br(e($text)) !!}</p>
        @endif
    @endforeach
</div>
