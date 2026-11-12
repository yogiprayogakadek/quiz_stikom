@php
    $kkm = $session->quiz->remidi_nilai_min;
    $defaultPeserta = $peserta
        ->filter(fn ($baris) => $kkm === null || $baris['nilai'] < $kkm)
        ->pluck('user.id')
        ->values();
@endphp

<x-layouts.admin :judul="'Remidi '.$session->nama" keterangan="Pilih peserta dan sumber soal remidi. Sesi remidi memakai Mode Acak agar nilainya sebanding dengan nilai awal.">
    <x-slot:aksi>
        <a href="{{ route('admin.sessions.show', $session) }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Ruang kendali
        </a>
    </x-slot:aksi>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <form
            method="POST"
            action="{{ route('admin.sessions.remidi.store', $session) }}"
            class="card"
            x-data="{ sumber: @js(old('sumber_soal', 'lama')), dipilih: @js(old('peserta_ids', $defaultPeserta)) }"
        >
            @csrf

            <div class="border-b border-outline pb-4">
                <h2 class="text-2xl">Buat sesi remidi</h2>
                <p class="mt-1 text-base text-ink-muted">Nilai akhir otomatis diambil dari yang terbesar antara nilai awal dan nilai remidi.</p>
            </div>

            <div class="mt-5">
                <label for="nama" class="label">Nama sesi remidi</label>
                <input id="nama" name="nama" class="input" required maxlength="160" value="{{ old('nama', 'Remidi '.$session->nama) }}">
                @error('nama') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <fieldset class="mt-6">
                <legend class="label">Sumber soal</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-outline bg-page p-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft/40">
                        <input type="radio" name="sumber_soal" value="lama" class="mt-1 h-5 w-5" x-model="sumber" @checked(old('sumber_soal', 'lama') === 'lama')>
                        <span>
                            <span class="block text-base font-bold text-ink">Soal lama</span>
                            <span class="block text-sm text-ink-muted">Pakai bank soal kuis {{ $session->quiz->judul }}.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-outline bg-page p-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft/40">
                        <input type="radio" name="sumber_soal" value="baru" class="mt-1 h-5 w-5" x-model="sumber" @checked(old('sumber_soal') === 'baru')>
                        <span>
                            <span class="block text-base font-bold text-ink">Kuis soal baru</span>
                            <span class="block text-sm text-ink-muted">Pilih kuis lain yang soalnya sudah disiapkan.</span>
                        </span>
                    </label>
                </div>
                @error('sumber_soal') <p class="field-error mt-2">{{ $message }}</p> @enderror
            </fieldset>

            <div class="mt-5" x-show="sumber === 'baru'" x-cloak>
                <label for="quiz_id" class="label">Kuis sumber soal baru</label>
                <select id="quiz_id" name="quiz_id" class="input">
                    <option value="">-- Pilih kuis --</option>
                    @foreach ($kuisRemidi as $kuis)
                        <option value="{{ $kuis->id }}" @selected((int) old('quiz_id') === $kuis->id)>
                            {{ $kuis->judul }} ({{ $kuis->questions_count }} soal)
                        </option>
                    @endforeach
                </select>
                <p class="help mt-1">Kuis harus punya soal. Jika belum ada, buat kuis baru lewat kartu di bawah.</p>
                @error('quiz_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <fieldset class="mt-6">
                <legend class="label">Peserta remidi</legend>

                @if ($peserta->isEmpty())
                    <div class="rounded-2xl border border-outline bg-page px-4 py-5">
                        <p class="text-base text-ink-muted">Belum ada peserta yang menyelesaikan sesi ini, jadi remidi belum bisa dibuat.</p>
                    </div>
                @else
                    <p class="mb-3 text-sm text-ink-muted">
                        @if ($kkm === null)
                            Semua peserta tercentang. Isi nilai minimum lulus di pengaturan kuis untuk menandai calon remidi otomatis.
                        @else
                            Peserta dengan nilai di bawah {{ $kkm }} tercentang otomatis.
                        @endif
                    </p>
                    <div class="max-h-80 space-y-2 overflow-y-auto pr-1">
                        @foreach ($peserta as $baris)
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-outline bg-page px-4 py-3 hover:bg-surface">
                                <input type="checkbox" name="peserta_ids[]" value="{{ $baris['user']->id }}" class="h-5 w-5 rounded-md" x-model="dipilih">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-base font-bold text-ink">{{ $baris['user']->nama }}</span>
                                    <span class="block text-sm text-ink-muted">{{ $baris['user']->username }}</span>
                                </span>
                                <span class="pill pill-neutral">{{ $baris['nilai'] }}</span>
                                @if ($kkm !== null && $baris['nilai'] < $kkm)
                                    <span class="pill pill-coral text-xs">Di bawah KKM</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                    @error('peserta_ids') <p class="field-error mt-2">{{ $message }}</p> @enderror
                    @error('peserta_ids.*') <p class="field-error mt-2">{{ $message }}</p> @enderror
                @endif
            </fieldset>

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary" @disabled($peserta->isEmpty())>Buat sesi remidi</button>
                <a href="{{ route('admin.sessions.show', $session) }}" class="btn btn-secondary">Batal</a>
            </div>
        </form>

        <aside class="space-y-5">
            <div class="card">
                <h2 class="text-xl">Sesi awal</h2>
                <dl class="mt-4 space-y-3 text-base">
                    <div>
                        <dt class="text-sm font-bold text-ink-muted">Kuis</dt>
                        <dd class="text-ink">{{ $session->quiz->judul }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-bold text-ink-muted">Kelas</dt>
                        <dd class="text-ink">{{ $session->kelas?->nama ?? 'Tanpa kelas' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-bold text-ink-muted">Peserta selesai</dt>
                        <dd class="text-ink">{{ $peserta->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-bold text-ink-muted">Nilai minimum lulus</dt>
                        <dd class="text-ink">{{ $kkm ?? 'Belum diatur' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="card">
                <h2 class="text-xl">Butuh soal baru?</h2>
                <p class="mt-2 text-sm text-ink-muted">Buat kuis soal remidi dulu, unggah soalnya, lalu kembali ke halaman ini dan pilih kuis tersebut.</p>
                <form method="POST" action="{{ route('admin.sessions.remidi.kuis', $session) }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label for="judul" class="label">Judul kuis baru</label>
                        <input id="judul" name="judul" class="input" required maxlength="160" value="{{ old('judul', 'Remidi '.$session->quiz->judul) }}">
                        @error('judul') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="btn btn-secondary w-full">Buat kuis soal</button>
                </form>
            </div>
        </aside>
    </div>
</x-layouts.admin>
