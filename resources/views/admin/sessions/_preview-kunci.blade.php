@props(['question'])

<div class="mt-4 rounded-xl border border-sun bg-sun-soft/50 p-4">
    <p class="text-sm font-bold text-ink">Kunci jawaban</p>

    @if ($question->isEssay())
        <p class="mt-1 whitespace-pre-line text-base text-ink">{{ $question->kunci_essay ?: 'Belum diisi.' }}</p>

        @if (! empty($question->rubrik_essay))
            <p class="mt-3 text-sm font-bold text-ink">Rubrik penilaian</p>
            <ul class="mt-2 space-y-1 text-sm text-ink-muted">
                @foreach ($question->rubrik_essay as $kriteria)
                    <li class="flex justify-between gap-4">
                        <span>{{ $kriteria['label'] ?? '' }}</span>
                        <span class="flex-none font-mono text-ink">{{ $kriteria['points'] ?? 0 }} poin</span>
                    </li>
                @endforeach
            </ul>
        @endif
    @else
        @php $benar = $question->options->firstWhere('is_correct', true); @endphp
        <p class="mt-1 text-base text-ink">{{ $benar?->teks ?? 'Kunci belum ditentukan.' }}</p>
    @endif
</div>
