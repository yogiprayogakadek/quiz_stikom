@php
    $daftarPeserta = $pesertaAktif->map(fn ($attempt) => [
        'id' => $attempt->id,
        'nama' => $attempt->user?->nama ?? '(peserta dihapus)',
        'username' => $attempt->user?->username ?? '-',
    ])->values();
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
    x-data="{ target: '{{ old('target', 'semua') }}', terpilih: @js(array_map('intval', old('attempt_ids', []))), cari: '', teks: @js(old('teks', '')), daftar: @js($daftarPeserta) }"
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
                x-model="teks"
                @error('teks') aria-invalid="true" @enderror
            >{{ old('teks') }}</textarea>
            <div class="mt-1 flex items-start justify-between gap-3">
                <p class="help">Maksimal 500 karakter.</p>
                <p class="text-xs font-bold text-ink-muted" x-text="teks.length + ' / 500'"></p>
            </div>
            @error('teks') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <fieldset class="mt-5">
            <legend class="label">Penerima</legend>
            <div class="space-y-2">
                <label class="block rounded-xl border-2 border-outline p-4 has-[:checked]:border-brand">
                    <span class="flex items-start gap-3">
                        <input type="radio" name="target" value="semua" x-model="target" class="mt-1 h-5 w-5" @checked(old('target', 'semua') === 'semua')>
                        <span><span class="block font-bold">Semua peserta yang sedang mengerjakan</span><span class="mt-1 block text-sm text-ink-muted">{{ $daftarPeserta->count() }} peserta aktif.</span></span>
                    </span>
                </label>
                <label class="block rounded-xl border-2 border-outline p-4 has-[:checked]:border-brand">
                    <span class="flex items-start gap-3">
                        <input type="radio" name="target" value="terpilih" x-model="target" class="mt-1 h-5 w-5" @checked(old('target') === 'terpilih')>
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
                    <template x-for="item in daftar.filter((peserta) => peserta.nama.toLowerCase().includes(cari.toLowerCase()) || peserta.username.toLowerCase().includes(cari.toLowerCase()))" :key="item.id">
                        <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border p-3 transition" :class="terpilih.includes(item.id) ? 'border-brand bg-brand-soft' : 'border-outline bg-surface hover:border-outline-strong'">
                            <input type="checkbox" name="attempt_ids[]" x-model="terpilih" :value="item.id" class="h-5 w-5 shrink-0 rounded border-outline text-brand focus:ring-brand">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-bold" x-text="item.nama"></span>
                                <span class="block truncate text-xs text-ink-muted" x-text="item.username"></span>
                            </span>
                        </label>
                    </template>
                    <p x-show="daftar.filter((peserta) => peserta.nama.toLowerCase().includes(cari.toLowerCase()) || peserta.username.toLowerCase().includes(cari.toLowerCase())).length === 0" class="py-6 text-center text-sm text-ink-muted sm:col-span-2">Tidak ada peserta yang cocok.</p>
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
