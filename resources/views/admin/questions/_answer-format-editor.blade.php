<div class="space-y-3" x-show="{{ $visibleExpression ?? 'true' }}">
    <div>
        <label class="label" x-bind:for="{{ $idExpression }}">Format jawaban peserta</label>
        <select
            class="input"
            x-bind:id="{{ $idExpression }}"
            x-bind:name="{{ $nameExpression }} ? {{ $nameExpression }} + '[answer_format]' : 'answer_format'"
            x-model="{{ $formatExpression }}"
            x-on:change="{{ $changeExpression }}"
            x-bind:disabled="{{ $disabledExpression ?? 'false' }}"
        >
            <option value="rich_text">Teks berformat</option>
            <option value="code">Kode program</option>
            <option value="table">Tabel jawaban</option>
        </select>
        <p class="help mt-1">Kode hanya ditulis dan dinilai; program tidak dijalankan.</p>
    </div>

    <template x-if="{{ $formatExpression }} === 'code'">
        <div>
            <label class="label" x-bind:for="{{ $idExpression }} + '-language'">Bahasa pemrograman</label>
            <input class="input" maxlength="50" x-bind:id="{{ $idExpression }} + '-language'" x-bind:name="{{ $nameExpression }} ? {{ $nameExpression }} + '[answer_config][language]' : 'answer_config[language]'" x-model="{{ $configExpression }}.language" placeholder="python" x-bind:disabled="{{ $disabledExpression ?? 'false' }}">
        </div>
    </template>

    <template x-if="{{ $formatExpression }} === 'table'">
        <div class="space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="label">Struktur tabel jawaban</p>
                    <p class="help mt-1">Edit judul, nama kolom, dan nama baris langsung pada tabel. Isi sel dengan kisi-kisi singkat yang akan terlihat sebagai petunjuk, bukan jawaban peserta.</p>
                    <p class="help mt-1">
                        <span x-text="{{ $configExpression }}.columns.length"></span>/10 kolom ·
                        <span x-text="{{ $configExpression }}.rows.length"></span>/20 baris
                    </p>
                </div>
                <div class="grid w-full grid-cols-2 gap-2 sm:w-auto">
                    <button type="button" class="btn btn-secondary min-h-11 whitespace-nowrap px-2 sm:px-3" x-on:click="tambahKolomJawaban({{ $configExpression }})" x-bind:disabled="{{ $disabledExpression ?? 'false' }} || {{ $configExpression }}.columns.length >= 10">Tambah kolom</button>
                    <button type="button" class="btn btn-secondary min-h-11 whitespace-nowrap px-2 sm:px-3" x-on:click="tambahBarisJawaban({{ $configExpression }})" x-bind:disabled="{{ $disabledExpression ?? 'false' }} || {{ $configExpression }}.rows.length >= 20">Tambah baris</button>
                </div>
            </div>

            <div class="max-h-[28rem] max-w-full overflow-auto overscroll-contain rounded-xl border border-outline" tabindex="0" aria-label="Pratinjau struktur tabel jawaban">
                <table class="answer-table min-w-[42rem]">
                    <thead>
                        <tr>
                            <th scope="col" class="sticky left-0 top-0 z-30 min-w-52">
                                <label class="sr-only" x-bind:for="{{ $idExpression }} + '-row-header'">Judul kolom baris</label>
                                <input
                                    class="input min-w-40 bg-page"
                                    maxlength="100"
                                    x-bind:id="{{ $idExpression }} + '-row-header'"
                                    x-bind:name="{{ $nameExpression }} ? {{ $nameExpression }} + '[answer_config][row_header]' : 'answer_config[row_header]'"
                                    x-model="{{ $configExpression }}.row_header"
                                    x-bind:disabled="{{ $disabledExpression ?? 'false' }}"
                                    placeholder="Judul baris"
                                >
                            </th>
                            <template x-for="(column, columnIndex) in {{ $configExpression }}.columns" :key="columnIndex">
                                <th scope="col" class="sticky top-0 z-20 min-w-56">
                                    <div class="flex items-center gap-2">
                                        <label class="sr-only" x-bind:for="{{ $idExpression }} + '-column-' + columnIndex">Nama kolom <span x-text="columnIndex + 1"></span></label>
                                        <input
                                            class="input min-w-40 bg-page"
                                            maxlength="100"
                                            x-bind:id="{{ $idExpression }} + '-column-' + columnIndex"
                                            x-bind:name="({{ $nameExpression }} ? {{ $nameExpression }} + '[answer_config][columns][' : 'answer_config[columns][') + columnIndex + ']'"
                                            x-model="{{ $configExpression }}.columns[columnIndex]"
                                            x-bind:disabled="{{ $disabledExpression ?? 'false' }}"
                                            placeholder="Nama kolom"
                                        >
                                        <button
                                            type="button"
                                            class="btn btn-ghost min-h-11 min-w-11 shrink-0 !p-2 text-coral-deep"
                                            x-on:click="hapusKolomJawaban({{ $configExpression }}, columnIndex)"
                                            x-bind:disabled="{{ $disabledExpression ?? 'false' }} || {{ $configExpression }}.columns.length <= 1"
                                            x-bind:aria-label="'Hapus kolom ' + (column || columnIndex + 1)"
                                        >
                                            <x-icon name="x" :size="18" />
                                        </button>
                                    </div>
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, rowIndex) in {{ $configExpression }}.rows" :key="rowIndex">
                            <tr>
                                <th scope="row" class="sticky left-0 z-10 min-w-56">
                                    <div class="flex items-center gap-2">
                                        <label class="sr-only" x-bind:for="{{ $idExpression }} + '-row-' + rowIndex">Nama baris <span x-text="rowIndex + 1"></span></label>
                                        <input
                                            class="input min-w-40 bg-page"
                                            maxlength="100"
                                            x-bind:id="{{ $idExpression }} + '-row-' + rowIndex"
                                            x-bind:name="({{ $nameExpression }} ? {{ $nameExpression }} + '[answer_config][rows][' : 'answer_config[rows][') + rowIndex + ']'"
                                            x-model="{{ $configExpression }}.rows[rowIndex]"
                                            x-bind:disabled="{{ $disabledExpression ?? 'false' }}"
                                            placeholder="Nama baris"
                                        >
                                        <button
                                            type="button"
                                            class="btn btn-ghost min-h-11 min-w-11 shrink-0 !p-2 text-coral-deep"
                                            x-on:click="hapusBarisJawaban({{ $configExpression }}, rowIndex)"
                                            x-bind:disabled="{{ $disabledExpression ?? 'false' }} || {{ $configExpression }}.rows.length <= 1"
                                            x-bind:aria-label="'Hapus baris ' + (row || rowIndex + 1)"
                                        >
                                            <x-icon name="x" :size="18" />
                                        </button>
                                    </div>
                                </th>
                                <template x-for="(column, columnIndex) in {{ $configExpression }}.columns" :key="columnIndex">
                                    <td>
                                        <input
                                            class="input min-w-40"
                                            maxlength="500"
                                            x-bind:name="({{ $nameExpression }} ? {{ $nameExpression }} + '[answer_config][cell_hints][' : 'answer_config[cell_hints][') + rowIndex + '][' + columnIndex + ']'"
                                            x-model="{{ $configExpression }}.cell_hints[rowIndex][columnIndex]"
                                            x-bind:disabled="{{ $disabledExpression ?? 'false' }}"
                                            placeholder="Kisi-kisi peserta"
                                            x-bind:aria-label="(row || 'Baris ' + (rowIndex + 1)) + ', ' + (column || 'Kolom ' + (columnIndex + 1))"
                                        >
                                    </td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>
</div>
