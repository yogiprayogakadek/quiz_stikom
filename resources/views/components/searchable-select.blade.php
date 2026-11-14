@props([
    'id',
    'name' => null,
    'label',
    'searchLabel' => null,
    'placeholder' => 'Ketik untuk menyaring',
    'options' => [],
    'selected' => null,
    'required' => false,
    'help' => null,
])

@php
    $nama = $name ?? $id;
@endphp

<div
    x-data="{
        query: '',
        cocok: {{ count($options) }},
        saring() {
            const kata = this.query.trim().toLowerCase();
            this.cocok = 0;
            this.$refs.daftar.querySelectorAll('option[data-search]').forEach((opsi) => {
                const terpilih = opsi.value !== '' && opsi.selected;
                const tampil = terpilih || kata === '' || opsi.dataset.search.includes(kata);
                opsi.hidden = !tampil;
                if (tampil) this.cocok++;
            });
        }
    }"
    x-init="saring()"
>
    <label for="{{ $id }}-cari" class="label">{{ $searchLabel ?? 'Cari '.$label }}</label>
    <input
        id="{{ $id }}-cari"
        type="search"
        class="input"
        x-model="query"
        x-on:input="saring()"
        x-on:keydown.escape="query = ''; saring()"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        aria-controls="{{ $id }}"
    >

    <label for="{{ $id }}" class="label mt-3">{{ $label }}</label>
    <select
        id="{{ $id }}"
        name="{{ $nama }}"
        class="input"
        x-ref="daftar"
        @required($required)
        @if ($help) aria-describedby="{{ $id }}-help" @endif
        @if (isset($errors) && $errors->has($nama)) aria-invalid="true" @endif
    >
        @foreach ($options as $opsi)
            <option
                value="{{ $opsi['value'] }}"
                data-search="{{ strtolower($opsi['search'] ?? $opsi['label']) }}"
                @selected((string) $selected === (string) $opsi['value'])
                @disabled($opsi['disabled'] ?? false)
            >{{ $opsi['label'] }}</option>
        @endforeach
    </select>

    <p class="help mt-2" x-show="cocok === 0" x-cloak aria-live="polite">Tidak ada pilihan yang cocok dengan pencarian.</p>

    @if ($help)
        <p id="{{ $id }}-help" class="help mt-2">{{ $help }}</p>
    @endif
</div>
