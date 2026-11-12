<x-layouts.admin judul="Masukan peserta" keterangan="Suara peserta dari seluruh workspace. Identitas peserta tidak ditampilkan.">
    <section class="card" aria-labelledby="feedback-inbox-heading">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-outline pb-4">
            <div>
                <h2 id="feedback-inbox-heading" class="text-2xl">Kotak masuk anonim</h2>
                <p class="mt-1 text-base text-ink-muted">Masukan yang dikirim peserta setelah ujian pertamanya di sebuah workspace.</p>
            </div>
            <span class="pill pill-neutral">{{ $feedbacks->total() }} masukan</span>
        </div>

        @if ($feedbacks->isEmpty())
            <x-empty-state
                class="mt-5"
                judul="Belum ada masukan"
                keterangan="Masukan akan muncul di sini setelah peserta menyelesaikan ujian pertamanya."
            />
        @else
            <ul class="mt-5 space-y-3">
                @foreach ($feedbacks as $feedback)
                    <li class="rounded-2xl border border-outline bg-page p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-1" aria-label="{{ $feedback->rating }} dari 5">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <x-icon
                                            name="star"
                                            :size="18"
                                            class="{{ $i <= $feedback->rating ? 'text-sun fill-current' : 'text-outline-strong' }}"
                                        />
                                    @endfor
                                </div>
                                <p class="mt-2 text-sm font-bold text-ink">
                                    {{ $feedback->organization?->name ?? 'Workspace tidak tersedia' }}
                                    @if ($feedback->organization)
                                        <span class="font-normal text-ink-subtle">/o/{{ $feedback->organization->slug }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="pill pill-neutral">Anonim</span>
                                @if ($feedback->responded_at)
                                    <time class="text-xs text-ink-subtle" datetime="{{ $feedback->responded_at->toIso8601String() }}">{{ $feedback->responded_at->format('d M Y H:i') }}</time>
                                @endif
                            </div>
                        </div>

                        <p class="mt-3 whitespace-pre-line text-base text-ink">
                            {{ $feedback->message ?: 'Tanpa komentar.' }}
                        </p>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $feedbacks->links() }}</div>
        @endif
    </section>
</x-layouts.admin>
