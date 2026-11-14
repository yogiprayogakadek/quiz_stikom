@props([
    'id',
    'name' => null,
    'label',
    'placeholder' => null,
    'searchPlaceholder' => null,
    'options' => [],
    'selected' => null,
    'required' => false,
    'help' => null,
])

@php
    $nama = $name ?? $id;
    $pilihan = collect($options);
    $terpilih = $pilihan->first(fn ($opsi) => (string) $opsi['value'] === (string) $selected);
    $labelTerpilih = $terpilih['label'] ?? null;
    $teksPlaceholder = $placeholder ?? 'Pilih '.strtolower($label);
    $teksCari = $searchPlaceholder ?? 'Cari '.strtolower($label);
    $adaError = isset($errors) && $errors->has($nama);
@endphp

<div
    class="relative"
    x-data="{
        terbuka: false,
        query: '',
        aktif: -1,
        cocok: {{ count($options) }},
        nilai: @js((string) ($selected ?? '')),
        label: @js($labelTerpilih ?? ''),
        galat: '',
        wajib: {{ $required ? 'true' : 'false' }},
        pesanWajib: @js('Pilih '.strtolower($label).' dulu.'),
        init() {
            if (! this.wajib) return;

            this.$el.closest('form')?.addEventListener('submit', (event) => {
                if (this.nilai !== '') return;

                event.preventDefault();
                this.galat = this.pesanWajib;
                this.buka();
            });
        },
        buka() {
            if (this.terbuka) return;

            this.terbuka = true;
            this.query = '';

            this.$nextTick(() => {
                this.saring();
                this.$refs.pencarian?.focus();
            });
        },
        tutup(kembalikanFokus = false) {
            if (! this.terbuka) return;

            this.terbuka = false;
            this.query = '';
            this.saring();
            this.aktif = -1;

            if (kembalikanFokus) this.$nextTick(() => this.$refs.pemicu?.focus());
        },
        daftarTampil() {
            return [...this.$refs.daftar.querySelectorAll('[role=option]')]
                .filter((opsi) => ! opsi.hidden && opsi.getAttribute('aria-disabled') !== 'true');
        },
        opsi(indeks) {
            return [...this.$refs.daftar.querySelectorAll('[role=option]')]
                .find((opsi) => Number(opsi.dataset.indeks) === indeks);
        },
        saring() {
            const kata = this.query.trim().toLowerCase();
            let pertama = -1;

            this.cocok = 0;

            this.$refs.daftar.querySelectorAll('[role=option]').forEach((opsi) => {
                const tampil = kata === '' || opsi.dataset.search.includes(kata);

                opsi.hidden = ! tampil;

                if (! tampil) return;

                this.cocok++;

                if (pertama === -1 && opsi.getAttribute('aria-disabled') !== 'true') {
                    pertama = Number(opsi.dataset.indeks);
                }
            });

            const terpilih = [...this.$refs.daftar.querySelectorAll('[role=option][aria-selected=true]')]
                .find((opsi) => ! opsi.hidden && opsi.getAttribute('aria-disabled') !== 'true');

            this.aktif = kata === '' && terpilih ? Number(terpilih.dataset.indeks) : pertama;
        },
        geser(arah) {
            const daftar = this.daftarTampil();

            if (daftar.length === 0) return;

            const posisi = daftar.findIndex((opsi) => Number(opsi.dataset.indeks) === this.aktif);
            const berikut = posisi === -1
                ? (arah > 0 ? 0 : daftar.length - 1)
                : (posisi + arah + daftar.length) % daftar.length;

            this.aktif = Number(daftar[berikut].dataset.indeks);
            this.$nextTick(() => this.opsi(this.aktif)?.scrollIntoView({ block: 'nearest' }));
        },
        keUjung(arah) {
            const daftar = this.daftarTampil();

            if (daftar.length === 0) return;

            this.aktif = Number((arah > 0 ? daftar[0] : daftar[daftar.length - 1]).dataset.indeks);
        },
        pilihAktif() {
            if (this.aktif >= 0) this.pilih(this.aktif);
        },
        pilih(indeks) {
            const opsi = this.opsi(indeks);

            if (! opsi || opsi.getAttribute('aria-disabled') === 'true') return;

            this.nilai = opsi.dataset.nilai;
            this.label = opsi.dataset.label;
            this.galat = '';
            this.tutup(true);
        }
    }"
    x-on:click.outside="tutup()"
>
    <label id="{{ $id }}-label" for="{{ $id }}" class="label">{{ $label }}</label>

    <input
        type="hidden"
        name="{{ $nama }}"
        value="{{ (string) ($selected ?? '') }}"
        x-ref="nilai"
        x-bind:value="nilai"
    >

    <button
        type="button"
        id="{{ $id }}"
        x-ref="pemicu"
        class="input flex w-full items-center justify-between gap-3 text-left"
        aria-haspopup="listbox"
        aria-expanded="false"
        x-bind:aria-expanded="terbuka ? 'true' : 'false'"
        aria-controls="{{ $id }}-daftar"
        @if ($required) aria-required="true" @endif
        @if ($adaError) aria-invalid="true" @endif
        x-bind:aria-invalid="(galat || {{ $adaError ? 'true' : 'false' }}) ? 'true' : 'false'"
        @if ($help) aria-describedby="{{ $id }}-help" @endif
        x-on:click="terbuka ? tutup(true) : buka()"
        x-on:keydown.down.prevent="buka()"
        x-on:keydown.up.prevent="buka()"
        x-on:keydown.enter.prevent="buka()"
        x-on:keydown.space.prevent="buka()"
    >
        <span class="truncate" x-text="label || @js($teksPlaceholder)">{{ $labelTerpilih ?? $teksPlaceholder }}</span>
        <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="h-5 w-5 shrink-0 text-ink-subtle transition-transform"
            x-bind:class="terbuka ? 'rotate-180' : ''"
            aria-hidden="true"
        >
            <path d="m6 9 6 6 6-6" />
        </svg>
    </button>

    <div
        id="{{ $id }}-panel"
        x-show="terbuka"
        x-cloak
        x-transition.opacity.duration.120ms
        class="absolute left-0 right-0 z-50 mt-2 overflow-hidden rounded-2xl border border-outline bg-page shadow-lift"
    >
        <div class="border-b border-outline p-2">
            <label for="{{ $id }}-pencarian" class="sr-only">{{ $teksCari }}</label>
            <input
                id="{{ $id }}-pencarian"
                type="search"
                x-ref="pencarian"
                x-bind:value="query"
                x-on:input="query = $event.target.value; saring()"
                x-on:keydown.down.prevent="geser(1)"
                x-on:keydown.up.prevent="geser(-1)"
                x-on:keydown.home.prevent="keUjung(1)"
                x-on:keydown.end.prevent="keUjung(-1)"
                x-on:keydown.enter.prevent="pilihAktif()"
                x-on:keydown.escape.prevent="tutup(true)"
                x-on:keydown.tab="tutup()"
                class="input placeholder:text-ink-subtle"
                placeholder="{{ $teksCari }}"
                autocomplete="off"
                spellcheck="false"
                role="combobox"
                aria-autocomplete="list"
                aria-expanded="true"
                aria-controls="{{ $id }}-daftar"
                x-bind:aria-activedescendant="aktif >= 0 ? '{{ $id }}-opsi-' + aktif : null"
            >
        </div>

        <ul
            id="{{ $id }}-daftar"
            x-ref="daftar"
            role="listbox"
            aria-labelledby="{{ $id }}-label"
            class="max-h-64 overflow-y-auto p-1.5"
        >
            @foreach ($options as $indeks => $opsi)
                @php
                    $nonaktif = (bool) ($opsi['disabled'] ?? false);
                @endphp
                <li
                    id="{{ $id }}-opsi-{{ $indeks }}"
                    role="option"
                    data-indeks="{{ $indeks }}"
                    data-nilai="{{ $opsi['value'] }}"
                    data-label="{{ $opsi['label'] }}"
                    data-search="{{ mb_strtolower($opsi['search'] ?? $opsi['label']) }}"
                    aria-selected="{{ (string) $selected === (string) $opsi['value'] ? 'true' : 'false' }}"
                    x-bind:aria-selected="nilai === @js((string) $opsi['value']) ? 'true' : 'false'"
                    @if ($nonaktif) aria-disabled="true" @endif
                    x-bind:class="{
                        'bg-brand-soft text-brand-deep': aktif === {{ $indeks }},
                        'font-bold': nilai === @js((string) $opsi['value'])
                    }"
                    class="flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-base {{ $nonaktif ? 'cursor-not-allowed text-ink-subtle' : 'cursor-pointer text-ink hover:bg-surface' }}"
                    @if (! $nonaktif) x-on:click="pilih({{ $indeks }})" @endif
                >
                    <span class="truncate">{{ $opsi['label'] }}</span>
                </li>
            @endforeach
        </ul>

        <p x-show="cocok === 0" x-cloak class="help border-t border-outline px-3 py-4 text-center" aria-live="polite">
            Tidak ada pilihan yang cocok.
        </p>
    </div>

    @if ($help)
        <p id="{{ $id }}-help" class="help mt-2">{{ $help }}</p>
    @endif

    <p class="field-error mt-2" x-show="galat" x-cloak x-text="galat"></p>
</div>
