<x-layouts.admin judul="Masukan peserta" keterangan="Umpan balik peserta dari seluruh workspace beserta nama, kelas, dan konteks kuis.">
    <section class="card" aria-labelledby="feedback-inbox-heading">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-outline pb-4">
            <div>
                <h2 id="feedback-inbox-heading" class="text-2xl">Kotak masuk lintas workspace</h2>
                <p class="mt-1 text-base text-ink-muted">Masukan yang dikirim peserta setelah menyelesaikan ujian nyata di sebuah workspace.</p>
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
                    @php
                        $sesi = $feedback->attempt?->quizSession;
                        $namaKelas = $sesi?->kelas?->nama
                            ?? ($feedback->user?->kelas
                                ?->where('organization_id', $feedback->organization_id)
                                ->pluck('nama')
                                ->filter()
                                ->join(', ') ?: null);
                        $judulKuis = $sesi?->quiz?->judul;
                    @endphp
                    <li class="rounded-2xl border border-outline bg-page p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-base font-bold text-ink">
                                        {{ $feedback->user?->nama ?? 'Peserta tidak tersedia' }}
                                    </p>
                                    @if ($feedback->user?->username)
                                        <span class="font-mono text-xs text-ink-muted">({{ $feedback->user->username }})</span>
                                    @endif
                                    <span class="pill pill-brand text-xs">{{ $namaKelas ?: 'Tanpa kelas' }}</span>
                                    @if ($judulKuis)
                                        <span class="pill pill-neutral text-xs">{{ $judulKuis }}</span>
                                    @endif
                                </div>

                                <p class="mt-1.5 text-sm font-bold text-ink-muted">
                                    {{ $feedback->organization?->name ?? 'Workspace tidak tersedia' }}
                                    @if ($feedback->organization)
                                        <span class="font-normal text-ink-subtle">/o/{{ $feedback->organization->slug }}</span>
                                    @endif
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <div class="flex items-center gap-1" aria-label="{{ $feedback->rating }} dari 5">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <x-icon
                                            name="star"
                                            :size="18"
                                            class="{{ $i <= $feedback->rating ? 'text-sun fill-current' : 'text-outline-strong' }}"
                                        />
                                    @endfor
                                    <span class="ml-1 font-mono text-xs font-bold text-ink">{{ $feedback->rating }}/5</span>
                                </div>
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
