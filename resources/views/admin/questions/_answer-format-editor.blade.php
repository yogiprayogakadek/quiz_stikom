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
            <div>
                <div class="flex items-center justify-between gap-2">
                    <p class="label">Kolom jawaban</p>
                    <button type="button" class="btn btn-secondary min-h-11 px-3" x-on:click="tambahKolomJawaban({{ $configExpression }})" x-bind:disabled="{{ $configExpression }}.columns.length >= 10">Tambah kolom</button>
                </div>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <template x-for="(column, columnIndex) in {{ $configExpression }}.columns" :key="columnIndex">
                        <div class="flex gap-2">
                            <input class="input" maxlength="100" x-bind:name="({{ $nameExpression }} ? {{ $nameExpression }} + '[answer_config][columns][' : 'answer_config[columns][') + columnIndex + ']'" x-model="{{ $configExpression }}.columns[columnIndex]" x-bind:aria-label="'Nama kolom ' + (columnIndex + 1)" x-bind:disabled="{{ $disabledExpression ?? 'false' }}">
                            <button type="button" class="btn btn-ghost min-h-11 px-3 text-coral-deep" x-on:click="{{ $configExpression }}.columns.splice(columnIndex, 1)" x-bind:disabled="{{ $configExpression }}.columns.length <= 1">Hapus</button>
                        </div>
                    </template>
                </div>
            </div>
            <div>
                <div class="flex items-center justify-between gap-2">
                    <p class="label">Baris jawaban</p>
                    <button type="button" class="btn btn-secondary min-h-11 px-3" x-on:click="tambahBarisJawaban({{ $configExpression }})" x-bind:disabled="{{ $configExpression }}.rows.length >= 20">Tambah baris</button>
                </div>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <template x-for="(row, rowIndex) in {{ $configExpression }}.rows" :key="rowIndex">
                        <div class="flex gap-2">
                            <input class="input" maxlength="100" x-bind:name="({{ $nameExpression }} ? {{ $nameExpression }} + '[answer_config][rows][' : 'answer_config[rows][') + rowIndex + ']'" x-model="{{ $configExpression }}.rows[rowIndex]" x-bind:aria-label="'Nama baris ' + (rowIndex + 1)" x-bind:disabled="{{ $disabledExpression ?? 'false' }}">
                            <button type="button" class="btn btn-ghost min-h-11 px-3 text-coral-deep" x-on:click="{{ $configExpression }}.rows.splice(rowIndex, 1)" x-bind:disabled="{{ $configExpression }}.rows.length <= 1">Hapus</button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
