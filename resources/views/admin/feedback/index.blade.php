<x-layouts.admin judul="Masukan peserta" keterangan="Umpan balik pengalaman ujian peserta di workspace ini beserta identitas dan kelas untuk tindak lanjut pengajaran.">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="card">
            <p class="text-sm font-bold text-ink-muted">Total masukan terkirim</p>
            <p class="display-number mt-2 text-4xl">{{ $totalSemua }}</p>
            <p class="mt-1 text-sm text-ink-muted">Satu kiriman per peserta di workspace ini</p>
        </div>

        <div class="card">
            <p class="text-sm font-bold text-ink-muted">Rata-rata penilaian pengalaman</p>
            @if ($rataRata !== null)
                <p class="display-number mt-2 text-4xl">{{ number_format($rataRata, 1) }} <span class="text-xl text-ink-muted">/ 5</span></p>
            @else
                <p class="mt-2 font-display text-2xl font-bold text-ink-muted">Belum ada penilaian</p>
            @endif
            <p class="mt-1 text-sm text-ink-muted">Dihitung dari seluruh masukan yang sudah masuk</p>
        </div>

        <form method="GET" action="{{ route('admin.feedback.index') }}" class="card space-y-3 sm:col-span-2 lg:col-span-1">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="filter-kelas" class="label">Kelas</label>
                    <select id="filter-kelas" name="kelas" class="input">
                        <option value="">Semua kelas</option>
                        @foreach ($kelasList as $itemKelas)
                            <option value="{{ $itemKelas->id }}" @selected((int) $kelasFilter === (int) $itemKelas->id)>
                                {{ $itemKelas->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter-rating" class="label">Nilai bintang</label>
                    <select id="filter-rating" name="rating" class="input">
                        <option value="">Semua nilai</option>
                        @for ($bintang = 5; $bintang >= 1; $bintang--)
                            <option value="{{ $bintang }}" @selected((int) $ratingFilter === $bintang)>
                                {{ $bintang }} bintang
                            </option>
                        @endfor
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-secondary min-h-11">Terapkan filter</button>
                @if ($kelasFilter !== null || $ratingFilter !== null)
                    <a href="{{ route('admin.feedback.index') }}" class="btn btn-ghost min-h-11">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <section class="mt-6 card" aria-labelledby="daftar-masukan-heading">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-outline pb-4">
            <div>
                <h2 id="daftar-masukan-heading" class="text-2xl">Kotak masuk peserta</h2>
                <p class="mt-1 text-base text-ink-muted">Menampilkan nama peserta, username, kelas, dan sesi kuis saat masukan dikirim.</p>
            </div>
            <span class="pill pill-neutral">{{ $feedbacks->total() }} hasil</span>
        </div>

        @if ($feedbacks->isEmpty())
            <x-empty-state
                class="mt-5"
                :judul="$totalSemua === 0 ? 'Belum ada masukan peserta' : 'Tidak ada masukan pada filter ini'"
                :keterangan="$totalSemua === 0 ? 'Masukan muncul setelah peserta menyelesaikan ujian nyata pertamanya di workspace ini.' : 'Ubah pilihan kelas atau nilai bintang untuk menampilkan kiriman lainnya.'"
            >
                @if ($kelasFilter !== null || $ratingFilter !== null)
                    <div class="mt-4 flex justify-center">
                        <a href="{{ route('admin.feedback.index') }}" class="btn btn-secondary min-h-11">Tampilkan semua masukan</a>
                    </div>
                @endif
            </x-empty-state>
        @else
            <ul class="mt-5 space-y-3">
                @foreach ($feedbacks as $feedback)
                    @php
                        $sesi = $feedback->attempt?->quizSession;
                        $namaKelas = $sesi?->kelas?->nama
                            ?? ($feedback->user?->kelas?->pluck('nama')->filter()->join(', ') ?: null);
                        $judulKuis = $sesi?->quiz?->judul;
                    @endphp
                    <li class="rounded-2xl border border-outline bg-page p-4 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
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

                                @if ($sesi)
                                    <p class="mt-1 text-sm text-ink-muted">Sesi: {{ $sesi->nama }}</p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <div class="flex items-center gap-1" aria-label="Penilaian {{ $feedback->rating }} dari 5">
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
                                    <time class="text-xs text-ink-subtle" datetime="{{ $feedback->responded_at->toIso8601String() }}">
                                        {{ $feedback->responded_at->format('d M Y H:i') }}
                                    </time>
                                @endif
                            </div>
                        </div>

                        <p class="mt-3 whitespace-pre-line text-base text-ink">
                            {{ $feedback->message ?: 'Peserta memberi nilai bintang tanpa komentar tertulis.' }}
                        </p>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $feedbacks->links() }}</div>
        @endif
    </section>
</x-layouts.admin>
