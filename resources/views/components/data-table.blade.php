@props([
    'perPage' => 10,
    'searchPlaceholder' => 'Cari data...',
    'perPageOptions' => [5, 10, 25, 50],
    'emptyMessage' => 'Tidak ada data yang sesuai dengan pencarian.',
    'showSearch' => true,
    'showPerPage' => true,
])

<div x-data="tabelData({ perPage: {{ $perPage }} })" class="space-y-4">
    {{-- Data Table Toolbar: Live Filter & Rows per page --}}
    @if ($showSearch || $showPerPage || isset($toolbarAksi))
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            @if ($showSearch)
                <div class="relative flex-1 max-w-sm">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <x-icon name="search" :size="18" />
                    </div>
                    <input
                        x-model="cari"
                        type="search"
                        placeholder="{{ $searchPlaceholder }}"
                        class="input pl-10 pr-9 !min-h-[42px] !py-2 text-sm"
                        aria-label="{{ $searchPlaceholder }}"
                    >
                    <button
                        type="button"
                        x-show="cari !== ''"
                        @click="cari = ''"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
                        title="Bersihkan pencarian"
                    >
                        <x-icon name="x" :size="16" />
                    </button>
                </div>
            @endif

            <div class="flex items-center gap-3 sm:ml-auto">
                @isset($toolbarAksi)
                    {{ $toolbarAksi }}
                @endisset

                @if ($showPerPage)
                    <div class="flex items-center gap-2 text-sm text-slate-500 shrink-0">
                        <label for="per_page_select_{{ Str::random(6) }}" class="sr-only">Baris per halaman</label>
                        <span>Baris:</span>
                        <select
                            x-model.number="perPage"
                            class="input !w-auto !min-h-[40px] !py-1 !px-2.5 text-sm font-semibold rounded-xl bg-white border-slate-200"
                        >
                            @foreach ($perPageOptions as $option)
                                <option :value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Main Content / Table Slot --}}
    <div class="relative">
        {{ $slot }}

        {{-- Filter Empty Notice --}}
        <div
            x-show="totalFiltered === 0 && items.length > 0"
            x-cloak
            class="rounded-2xl border border-dashed border-slate-200 p-8 text-center bg-slate-50/50 mt-3"
        >
            <p class="font-bold text-slate-700">{{ $emptyMessage }}</p>
            <p class="mt-1 text-sm text-slate-400">Coba ubah atau bersihkan kata kunci pencarian.</p>
            <button
                type="button"
                @click="cari = ''"
                class="btn btn-secondary mt-3 text-xs"
            >
                Bersihkan filter
            </button>
        </div>
    </div>

    {{-- Realtime Pagination Footer --}}
    <div
        class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 text-sm text-slate-500"
        x-show="items.length > 0"
    >
        <p>
            Menampilkan
            <span class="font-bold text-slate-800" x-text="dari"></span>
            sampai
            <span class="font-bold text-slate-800" x-text="sampai"></span>
            dari
            <span class="font-bold text-slate-800" x-text="totalFiltered"></span>
            data
            <span x-show="cari !== '' && totalFiltered !== items.length" class="text-xs text-slate-400">
                (disaring dari <span x-text="items.length"></span> total)
            </span>
        </p>

        <div class="flex items-center gap-1.5" x-show="totalHalaman > 1">
            <button
                type="button"
                @click="halamanSebelumnya()"
                :disabled="halaman === 1"
                class="btn btn-secondary !px-3 !py-1.5 text-xs disabled:opacity-40 disabled:pointer-events-none"
            >
                Sebelumnya
            </button>

            <template x-for="p in daftarHalaman" :key="p">
                <button
                    type="button"
                    @click="keHalaman(p)"
                    :class="halaman === p ? 'btn-primary shadow-xs' : 'btn-secondary'"
                    class="btn !px-3 !py-1.5 text-xs font-bold"
                >
                    <span x-text="p"></span>
                </button>
            </template>

            <button
                type="button"
                @click="halamanBerikutnya()"
                :disabled="halaman === totalHalaman"
                class="btn btn-secondary !px-3 !py-1.5 text-xs disabled:opacity-40 disabled:pointer-events-none"
            >
                Selanjutnya
            </button>
        </div>
    </div>
</div>
