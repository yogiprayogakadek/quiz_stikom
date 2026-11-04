@php
    $terkunci = $session !== null && ! $session->isDraft();
    $modeAwal = old('mode', $session?->mode ?? 'acak');
    $acakOpsi = (bool) old('acak_opsi', $session?->acak_opsi ?? true);
    $pembahasan = (bool) old('tampilkan_pembahasan', $session?->tampilkan_pembahasan ?? false);
    $leaderboard = (bool) old('leaderboard_live', $session?->leaderboard_live ?? true);
@endphp

<div x-data="{ mode: '{{ $modeAwal }}' }" class="space-y-6">
    @if ($terkunci)
        <x-alert jenis="warning">
            Sesi sudah keluar dari status draft. Kuis dan mode terkunci oleh server. Pengaturan lain masih bisa diubah.
        </x-alert>
    @endif

    <div>
        <label for="quiz_id" class="label">Kuis</label>
        @if ($terkunci)
            <input type="hidden" name="quiz_id" value="{{ $session->quiz_id }}">
            <select id="quiz_id" class="input" disabled>
                @foreach ($quizzes as $kuis)
                    <option value="{{ $kuis->id }}" @selected($kuis->id === $session->quiz_id)>{{ $kuis->judul }} ({{ $kuis->questions_count }} soal)</option>
                @endforeach
            </select>
        @else
            <select id="quiz_id" name="quiz_id" class="input" required>
                <option value="">Pilih kuis</option>
                @foreach ($quizzes as $kuis)
                    <option value="{{ $kuis->id }}" @selected((int) old('quiz_id', $session?->quiz_id ?? request('quiz_id')) === $kuis->id)>
                        {{ $kuis->judul }} ({{ $kuis->questions_count }} soal)
                    </option>
                @endforeach
            </select>
        @endif
        @error('quiz_id') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="kelas_id" class="label">Kelas</label>
        @if ($terkunci)
            <input type="hidden" name="kelas_id" value="{{ $session->kelas_id }}">
            <select id="kelas_id" class="input" disabled>
                <option>{{ $session->kelas?->nama ?? 'Terbuka untuk semua peserta' }}</option>
            </select>
        @else
            <select id="kelas_id" name="kelas_id" class="input">
                <option value="">Terbuka untuk semua peserta</option>
                @foreach ($kelas as $k)
                    <option value="{{ $k->id }}" @selected((int) old('kelas_id', $session?->kelas_id ?? request('kelas_id')) === $k->id)>{{ $k->nama }}</option>
                @endforeach
            </select>
            <p class="help mt-2">Sesi kelas hanya tampil untuk anggota kelas itu.</p>
        @endif
        @error('kelas_id') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="nama" class="label">Nama sesi</label>
        <input id="nama" name="nama" type="text" value="{{ old('nama', $session?->nama) }}" class="input" required @error('nama') aria-invalid="true" @enderror>
        <p class="help mt-2">Nama ini tampil di dashboard peserta dan layar proyektor.</p>
        @error('nama') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <fieldset>
        <legend class="label">Mode sesi</legend>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="rounded-2xl border-2 border-outline bg-page p-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                <span class="flex items-center gap-3">
                    <input type="radio" name="{{ $terkunci ? 'mode_terkunci' : 'mode' }}" value="acak" x-model="mode" class="h-5 w-5" @checked($modeAwal === 'acak') @disabled($terkunci)>
                    <span class="font-display text-lg font-bold text-ink">Acak</span>
                </span>
                <span class="mt-2 block text-base text-ink-muted">Setiap peserta mengerjakan sendiri. Soal, urutan, dan opsi diacak per peserta.</span>
            </label>

            <label class="rounded-2xl border-2 border-outline bg-page p-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                <span class="flex items-center gap-3">
                    <input type="radio" name="{{ $terkunci ? 'mode_terkunci' : 'mode' }}" value="serempak" x-model="mode" class="h-5 w-5" @checked($modeAwal === 'serempak') @disabled($terkunci)>
                    <span class="font-display text-lg font-bold text-ink">Serempak</span>
                </span>
                <span class="mt-2 block text-base text-ink-muted">Server membuka soal satu per satu untuk seluruh kelas. Timer dan jeda berjalan otomatis.</span>
            </label>
        </div>
        @if ($terkunci)
            <input type="hidden" name="mode" value="{{ $session->mode }}">
        @endif
        @error('mode') <p class="field-error">{{ $message }}</p> @enderror
    </fieldset>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="max_peserta" class="label">Kuota peserta (opsional)</label>
            <input id="max_peserta" name="max_peserta" type="number" min="1" value="{{ old('max_peserta', $session?->max_peserta) }}" class="input" @error('max_peserta') aria-invalid="true" @enderror>
            <p class="help mt-2">Kosongkan untuk tanpa batas.</p>
            @error('max_peserta') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div x-show="mode === 'serempak'" x-cloak>
            <label for="jeda_detik" class="label">Jeda antarsoal (detik)</label>
            <input id="jeda_detik" name="jeda_detik" type="number" min="1" max="60" value="{{ old('jeda_detik', $session?->jeda_detik ?? 5) }}" class="input" @error('jeda_detik') aria-invalid="true" @enderror>
            <p class="help mt-2">Dipakai untuk menampilkan jawaban benar dan leaderboard sebelum soal berikutnya.</p>
            @error('jeda_detik') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="space-y-3">
        <label class="flex items-start gap-3 rounded-2xl border border-outline bg-page p-4">
            <input type="checkbox" name="acak_opsi" value="1" class="mt-1 h-5 w-5" @checked($acakOpsi)>
            <span>
                <span class="block text-base font-bold text-ink">Acak urutan opsi</span>
                <span class="block text-base text-ink-muted">Berlaku di kedua mode. Urutan opsi disimpan per peserta dan tidak berubah saat refresh.</span>
            </span>
        </label>

        <label class="flex items-start gap-3 rounded-2xl border border-outline bg-page p-4">
            <input type="checkbox" name="tampilkan_pembahasan" value="1" class="mt-1 h-5 w-5" @checked($pembahasan)>
            <span>
                <span class="block text-base font-bold text-ink">Tampilkan pembahasan</span>
                <span class="block text-base text-ink-muted">Tampil setelah attempt selesai atau saat soal ditutup. Tidak pernah dikirim sebelum waktunya.</span>
            </span>
        </label>

        <label class="flex items-start gap-3 rounded-2xl border border-outline bg-page p-4">
            <input type="checkbox" name="leaderboard_live" value="1" class="mt-1 h-5 w-5" @checked($leaderboard)>
            <span>
                <span class="block text-base font-bold text-ink">Tampilkan leaderboard live</span>
                <span class="block text-base text-ink-muted">Di layar proyektor, Mode Acak hanya menampilkan peserta yang sudah selesai.</span>
            </span>
        </label>
    </div>
</div>
