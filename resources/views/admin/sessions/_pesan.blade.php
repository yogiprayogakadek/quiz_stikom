@php
    $targetAwal = old('target', 'semua');
    $terpilihAwal = array_map('intval', (array) old('attempt_ids', []));
    $teksAwal = old('teks', '');
@endphp

<div class="mt-4 rounded-2xl border border-outline bg-page p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-lg font-bold text-ink">Pesan ke peserta</p>
            <p class="mt-1 text-sm text-ink-muted">Tampil sebagai notifikasi di pojok kanan atas layar peserta yang sedang mengerjakan.</p>
        </div>
        <button
            type="button"
            class="btn btn-secondary min-h-11"
            x-on:click="document.getElementById('dialog-pesan').showModal()"
        >Kirim pesan</button>
    </div>
</div>

<dialog
    id="dialog-pesan"
    class="m-auto max-h-[calc(100dvh-2rem)] w-[min(92vw,38rem)] overflow-y-auto rounded-2xl border border-outline bg-surface p-0 text-ink shadow-xl backdrop:bg-ink/40"
    aria-labelledby="judul-dialog-pesan"
    x-data="{ target: '{{ $targetAwal }}', terpilih: @json($terpilihAwal), cari: '', jumlahTeks: {{ mb_strlen($teksAwal) }} }"
    @if ($errors->has('teks') || $errors->has('attempt_ids'))
        x-init="$el.showModal()"
    @endif
>
    <form
        method="POST"
        action="{{ route('admin.sessions.pesan', $session) }}"
        class="p-5 sm:p-6"
        data-progress-message="Mengirim pesan ke peserta..."
    >
        @csrf
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h3 id="judul-dialog-pesan" class="text-xl font-bold">Kirim pesan ke peserta</h3>
                <p class="mt-2 text-sm text-ink-muted">Pesan tersimpan dan tetap muncul sampai peserta menutupnya.</p>
            </div>
            <button type="button" class="btn btn-ghost min-h-11 px-3" aria-label="Tutup dialog"
                x-on:click="document.getElementById('dialog-pesan').close()">Tutup</button>
        </div>

        <div class="mt-5">
            <label for="pesan-teks" class="label">Isi pesan</label>
            <textarea
                id="pesan-teks"
                name="teks"
                rows="3"
                maxlength="500"
                required
                autofocus
                class="input"
                x-on:input="jumlahTeks = $event.target.value.length"
                @error('teks') aria-invalid="true" @enderror
            >{{ $teksAwal }}</textarea>
            <div class="mt-1 flex items-start justify-between gap-3">
                <p class="help">Maksimal 500 karakter.</p>
                <p class="text-xs font-bold text-ink-muted" x-text="jumlahTeks + ' / 500'"></p>
            </div>
            @error('teks') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <fieldset class="mt-5">
            <legend class="label">Penerima</legend>
            <div class="space-y-2">
                <label class="block rounded-xl border-2 border-outline p-4 has-[:checked]:border-brand">
                    <span class="flex items-start gap-3">
                        <input type="radio" name="target" value="semua" x-model="target" class="mt-1 h-5 w-5" @checked($targetAwal === 'semua')>
                        <span><span class="block font-bold">Semua peserta yang sedang mengerjakan</span><span class="mt-1 block text-sm text-ink-muted">{{ $pesertaAktif->count() }} peserta aktif.</span></span>
                    </span>
                </label>
                <label class="block rounded-xl border-2 border-outline p-4 has-[:checked]:border-brand">
                    <span class="flex items-start gap-3">
                        <input type="radio" name="target" value="terpilih" x-model="target" class="mt-1 h-5 w-5" @checked($targetAwal === 'terpilih')>
                        <span><span class="block font-bold">Peserta terpilih</span><span class="mt-1 block text-sm text-ink-muted">Kirim hanya ke peserta yang dicentang.</span></span>
                    </span>
                </label>
            </div>
        </fieldset>

        <div x-show="target === 'terpilih'" x-cloak class="mt-4">
            <label for="pesan-cari" class="label">Cari peserta</label>
            <input
                id="pesan-cari"
                type="search"
                class="input"
                placeholder="Nama atau username"
                autocomplete="off"
                x-model="cari"
            >

            <fieldset class="mt-4">
                <legend class="text-sm font-bold text-ink">
                    <span x-text="terpilih.length"></span> peserta dipilih
                </legend>
                <div class="mt-2 grid max-h-72 gap-2 overflow-y-auto rounded-2xl border border-outline bg-page p-2 sm:grid-cols-2">
                    @foreach ($pesertaAktif as $attempt)
                        <label
                            class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-outline bg-surface p-3 transition hover:border-outline-strong has-[:checked]:border-brand has-[:checked]:bg-brand-soft"
                            data-cari="{{ \Illuminate\Support\Str::lower(($attempt->user?->nama ?? '').' '.($attempt->user?->username ?? '')) }}"
                            x-show="cari === '' || $el.dataset.cari.includes(cari.toLowerCase())"
                        >
                            <input type="checkbox" name="attempt_ids[]" value="{{ $attempt->id }}" x-model="terpilih" class="h-5 w-5 shrink-0 rounded border-outline text-brand focus:ring-brand">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-bold">{{ $attempt->user?->nama ?? '(peserta dihapus)' }}</span>
                                <span class="block truncate text-xs text-ink-muted">{{ $attempt->user?->username ?? '-' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @error('attempt_ids') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" class="btn btn-secondary" x-on:click="document.getElementById('dialog-pesan').close()">Batal</button>
            <button type="submit" class="btn btn-primary" x-bind:disabled="target === 'terpilih' && terpilih.length === 0">Kirim pesan</button>
        </div>
    </form>
</dialog>
