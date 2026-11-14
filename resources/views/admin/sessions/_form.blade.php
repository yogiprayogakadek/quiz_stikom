@php
    $terkunci = $session !== null && ! $session->isDraft();
    $modeAwal = old('mode', $session?->mode ?? 'acak');
    $acakOpsi = (bool) old('acak_opsi', $session?->acak_opsi ?? true);
    $pembahasanMode = old(
        'pembahasan_mode',
        $session?->pembahasan_mode ?? ($session?->tampilkan_pembahasan ? 'terbuka' : 'tersembunyi')
    );
    $leaderboard = (bool) old('leaderboard_live', $session?->leaderboard_live ?? true);
    $maxPelanggaran = old('max_pelanggaran', $session?->max_pelanggaran);
    $durasiPeringatan = old('durasi_peringatan', $session?->durasi_peringatan ?? 30);
@endphp

<div x-data="{ mode: '{{ $modeAwal }}' }" class="space-y-6">
    @if ($terkunci)
        <x-alert jenis="warning">
            Sesi sudah keluar dari status draft. Kuis dan mode terkunci oleh server. Pengaturan lain masih bisa diubah.
        </x-alert>
    @endif

    <div>
        @if ($terkunci)
            <label for="quiz_id" class="label">Kuis</label>
            <input type="hidden" name="quiz_id" value="{{ $session->quiz_id }}">
            <select id="quiz_id" class="input" disabled>
                @foreach ($quizzes as $kuis)
                    <option value="{{ $kuis->id }}" @selected($kuis->id === $session->quiz_id)>{{ $kuis->judul }} ({{ $kuis->questions_count }} soal)</option>
                @endforeach
            </select>
        @else
            @php
                $opsiKuis = $quizzes->map(fn ($kuis) => [
                    'value' => (string) $kuis->id,
                    'label' => $kuis->judul.' ('.$kuis->questions_count.' soal)'.($kuis->questions_count === 0 ? ' - belum ada soal' : ''),
                    'search' => $kuis->judul.' '.$kuis->questions_count.' soal',
                    'disabled' => $kuis->questions_count === 0,
                ])->prepend([
                    'value' => '',
                    'label' => 'Pilih kuis',
                    'search' => '',
                ])->all();
            @endphp
            <x-searchable-select
                id="quiz_id"
                name="quiz_id"
                label="Kuis"
                search-label="Cari kuis"
                placeholder="Judul kuis"
                :options="$opsiKuis"
                :selected="old('quiz_id', $session?->quiz_id ?? request('quiz_id'))"
                required
            />
        @endif
        @error('quiz_id') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        @if ($terkunci)
            <label for="kelas_id" class="label">Kelas</label>
            <input type="hidden" name="kelas_id" value="{{ $session->kelas_id }}">
            <select id="kelas_id" class="input" disabled>
                <option>{{ $session->kelas?->nama ?? 'Terbuka untuk semua peserta' }}</option>
            </select>
        @else
            @php
                $opsiKelas = $kelas->map(fn ($k) => [
                    'value' => (string) $k->id,
                    'label' => $k->nama,
                ])->prepend([
                    'value' => '',
                    'label' => 'Terbuka untuk semua peserta',
                    'search' => '',
                ])->all();
            @endphp
            <x-searchable-select
                id="kelas_id"
                name="kelas_id"
                label="Kelas"
                search-label="Cari kelas"
                placeholder="Nama kelas"
                :options="$opsiKelas"
                :selected="old('kelas_id', $session?->kelas_id ?? request('kelas_id'))"
                help="Sesi kelas hanya tampil untuk anggota kelas itu."
            />
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

    <div x-show="mode === 'acak'" x-cloak>
        <label for="durasi_total_menit" class="label">Durasi total sesi (menit)</label>
        <input
            id="durasi_total_menit"
            name="durasi_total_menit"
            type="number"
            min="1"
            max="600"
            value="{{ old('durasi_total_menit', $session?->durasi_total_detik ? intdiv($session->durasi_total_detik, 60) : '') }}"
            class="input"
            placeholder="100"
            x-bind:disabled="mode !== 'acak'"
            @error('durasi_total_menit') aria-invalid="true" @enderror
        >
        <p class="help mt-2">Kosongkan untuk otomatis. Sesi essay memakai 100 menit bila dikosongkan; sesi PG memakai jumlah durasi soal. Isi 1 sampai 600 menit.</p>
        @error('durasi_total_menit') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <fieldset class="rounded-2xl border border-outline bg-page p-4">
        <legend class="label">Pengawasan</legend>
        <div class="mt-3 grid gap-5 sm:grid-cols-2">
            <div>
                <label for="max_pelanggaran" class="label">Batas pelanggaran (opsional)</label>
                <input id="max_pelanggaran" name="max_pelanggaran" type="number" min="1" max="100" value="{{ $maxPelanggaran }}" class="input" @error('max_pelanggaran') aria-invalid="true" @enderror>
                <p class="help mt-2">Kosongkan untuk tanpa batas. Satu kali keluar layar penuh dihitung satu pelanggaran.</p>
                @error('max_pelanggaran') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="durasi_peringatan" class="label">Durasi peringatan (detik)</label>
                <input id="durasi_peringatan" name="durasi_peringatan" type="number" min="5" max="300" value="{{ $durasiPeringatan }}" class="input" required @error('durasi_peringatan') aria-invalid="true" @enderror>
                <p class="help mt-2">Waktu untuk kembali ke ujian. Saat batas pelanggaran sudah tercapai dan waktu habis, ujian dikumpulkan otomatis. Isi 5 sampai 300 detik.</p>
                @error('durasi_peringatan') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </fieldset>

    <div class="space-y-3">
        <label class="flex items-start gap-3 rounded-2xl border border-outline bg-page p-4">
            <input type="checkbox" name="acak_opsi" value="1" class="mt-1 h-5 w-5" @checked($acakOpsi)>
            <span>
                <span class="block text-base font-bold text-ink">Acak urutan opsi</span>
                <span class="block text-base text-ink-muted">Berlaku di kedua mode. Urutan opsi disimpan per peserta dan tidak berubah saat refresh.</span>
            </span>
        </label>

        <div class="rounded-2xl border border-outline bg-page p-4">
            <label for="pembahasan_mode" class="label">Pembahasan</label>
            <select id="pembahasan_mode" name="pembahasan_mode" class="input mt-2">
                <option value="tersembunyi" @selected($pembahasanMode === 'tersembunyi')>Sembunyikan</option>
                <option value="otomatis" @selected($pembahasanMode === 'otomatis')>Buka otomatis setelah semua peserta selesai</option>
                <option value="terbuka" @selected($pembahasanMode === 'terbuka')>Buka sekarang</option>
            </select>
            <p class="help mt-2">Bisa diubah dari ruang kendali saat sesi berjalan. Tidak pernah dikirim sebelum waktunya.</p>
            @error('pembahasan_mode') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-start gap-3 rounded-2xl border border-outline bg-page p-4">
            <input type="checkbox" name="leaderboard_live" value="1" class="mt-1 h-5 w-5" @checked($leaderboard)>
            <span>
                <span class="block text-base font-bold text-ink">Tampilkan leaderboard live</span>
                <span class="block text-base text-ink-muted">Di layar proyektor, Mode Acak hanya menampilkan peserta yang sudah selesai.</span>
            </span>
        </label>
    </div>
</div>
