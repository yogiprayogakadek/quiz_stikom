@props([
    'url',
    'inputs' => [],
    'modelInput',
    'title' => 'Tes koneksi',
    'button' => 'Tes koneksi',
])

<div
    {{ $attributes->merge(['class' => 'mt-5 rounded-2xl border border-outline bg-page p-4']) }}
    x-data="{
        url: @js($url),
        inputs: @js(array_values((array) $inputs)),
        modelInput: @js($modelInput),
        memuat: false,
        pesan: '',
        berhasil: false,
        probePesan: '',
        probeBerhasil: false,
        model: {},
        get jumlah() {
            return Object.values(this.model).reduce((total, daftar) => total + daftar.length, 0);
        },
        async uji() {
            this.memuat = true;
            this.pesan = '';
            this.berhasil = false;
            this.probePesan = '';
            this.probeBerhasil = false;
            this.model = {};
            try {
                const hasil = await window.kuisFetch(this.url, {
                    method: 'POST',
                    progress: false,
                    body: Object.fromEntries(this.inputs.map((id) => [id, document.getElementById(id)?.value ?? ''])),
                });
                const data = hasil.data ?? {};
                this.berhasil = data.endpoint?.ok === true;
                this.pesan = data.endpoint?.pesan ?? data.message ?? 'Tes koneksi gagal. Periksa kembali isian.';
                this.probeBerhasil = data.probe?.ok === true;
                this.probePesan = data.probe?.pesan ?? '';
                this.model = data.model ?? {};
            } catch (e) {
                this.berhasil = false;
                this.pesan = 'Tes koneksi gagal. Periksa koneksi server.';
            } finally {
                this.memuat = false;
            }
        },
        pilih(id) {
            document.getElementById(this.modelInput).value = id;
            this.probePesan = 'Model diisi: ' + id + '. Klik tombol tes lagi untuk menguji model ini.';
            this.probeBerhasil = false;
        },
    }"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-lg font-bold text-ink">{{ $title }}</p>
            <p class="mt-1 text-sm text-ink-muted">Memakai isian di atas. Perubahan baru dipakai setelah kamu menekan Simpan.</p>
        </div>
        <button
            type="button"
            class="btn btn-secondary min-h-11"
            x-on:click="uji()"
            x-bind:disabled="memuat"
        >
            <span x-show="!memuat">{{ $button }}</span>
            <span x-show="memuat" x-cloak>Menguji endpoint dan model...</span>
        </button>
    </div>
    <div class="mt-3 space-y-1" role="status" aria-live="polite">
        <p
            class="text-base"
            x-show="pesan"
            x-text="pesan"
            x-bind:class="berhasil ? 'text-brand-deep' : 'text-coral-deep'"
        ></p>
        <p
            class="text-sm"
            x-show="probePesan"
            x-text="probePesan"
            x-bind:class="probeBerhasil ? 'text-brand-deep' : 'text-coral-deep'"
        ></p>
    </div>
    <div x-show="Object.keys(model).length > 0" x-cloak class="mt-4">
        <p class="text-sm font-bold text-ink"><span x-text="jumlah"></span> model tersedia</p>
        <div class="mt-2 max-h-72 space-y-3 overflow-y-auto rounded-xl border border-outline bg-surface p-3">
            <template x-for="(daftar, provider) in model" :key="provider">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-ink-muted" x-text="provider"></p>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <template x-for="id in daftar" :key="id">
                            <button
                                type="button"
                                class="btn btn-ghost min-h-11 px-3 text-left font-mono text-xs"
                                x-on:click="pilih(id)"
                                x-bind:aria-label="'Pakai model ' + id"
                                x-text="id"
                            ></button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
        <p class="help mt-2">Klik model untuk mengisi kolom model.</p>
    </div>
</div>
