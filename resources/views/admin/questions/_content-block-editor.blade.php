<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="label">{{ $label }}</p>
            <p class="help">Tambahkan teks berformat, gambar, tabel, atau kode setelah teks utama.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn btn-secondary min-h-11 px-3" x-on:click="tambahBlok({{ $blocksExpression }}, 'text')" x-bind:disabled="{{ $blocksExpression }}.length >= 20">Teks</button>
            <button type="button" class="btn btn-secondary min-h-11 px-3" x-on:click="tambahBlok({{ $blocksExpression }}, 'image')" x-bind:disabled="{{ $blocksExpression }}.length >= 20">Gambar</button>
            <button type="button" class="btn btn-secondary min-h-11 px-3" x-on:click="tambahBlok({{ $blocksExpression }}, 'table')" x-bind:disabled="{{ $blocksExpression }}.length >= 20">Tabel</button>
            <button type="button" class="btn btn-secondary min-h-11 px-3" x-on:click="tambahBlok({{ $blocksExpression }}, 'code')" x-bind:disabled="{{ $blocksExpression }}.length >= 20">Kode</button>
        </div>
    </div>

    <template x-for="(block, blockIndex) in {{ $blocksExpression }}" :key="block.client_key">
        <div class="space-y-3 border-t border-outline pt-4">
            <input type="hidden" x-bind:name="{{ $nameExpression }} + '[type]'" x-bind:value="block.type">
            <input x-show="block.media_id" type="hidden" x-bind:name="{{ $nameExpression }} + '[media_id]'" x-bind:value="block.media_id || ''">

            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-bold text-ink" x-text="({ text: 'Teks tambahan', image: 'Gambar soal', table: 'Tabel soal', code: 'Kode program' })[block.type]"></p>
                <div class="flex gap-1">
                    <button type="button" class="btn btn-ghost min-h-11 min-w-11 px-2" x-on:click="geserBlok({{ $blocksExpression }}, blockIndex, -1)" x-bind:disabled="blockIndex === 0" aria-label="Geser blok ke atas">Naik</button>
                    <button type="button" class="btn btn-ghost min-h-11 min-w-11 px-2" x-on:click="geserBlok({{ $blocksExpression }}, blockIndex, 1)" x-bind:disabled="blockIndex === {{ $blocksExpression }}.length - 1" aria-label="Geser blok ke bawah">Turun</button>
                    <button type="button" class="btn btn-ghost min-h-11 px-3 text-coral-deep" x-on:click="{{ $blocksExpression }}.splice(blockIndex, 1)">Hapus</button>
                </div>
            </div>

            <template x-if="block.type === 'text'">
                <div>
                    <label class="label" x-bind:for="'block-text-' + block.client_key">Teks berformat</label>
                    <textarea class="input min-h-32" maxlength="20000" x-bind:id="'block-text-' + block.client_key" x-bind:name="{{ $nameExpression }} + '[text]'" x-model="block.text" placeholder="Gunakan **tebal**, *miring*, daftar, atau `kode singkat`."></textarea>
                </div>
            </template>

            <template x-if="block.type === 'image'">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label" x-bind:for="'block-image-' + block.client_key">File gambar</label>
                        <input class="input" type="file" accept="image/jpeg,image/png,image/webp" x-bind:id="'block-image-' + block.client_key" x-bind:name="{{ $nameExpression }} + '[image]'" x-bind:required="!block.media_id">
                        <p class="help mt-1">JPEG, PNG, atau WebP. Maksimal 4 MB.</p>
                    </div>
                    <img x-show="block.media_url" x-bind:src="block.media_url" x-bind:alt="block.alt || ''" class="max-h-48 w-auto rounded-xl object-contain">
                    <div x-show="block.media_url" class="self-center text-sm text-ink-muted">Gambar saat ini dipertahankan jika tidak memilih file baru.</div>
                    <div>
                        <label class="label" x-bind:for="'block-alt-' + block.client_key">Teks alternatif</label>
                        <input class="input" maxlength="300" x-bind:id="'block-alt-' + block.client_key" x-bind:name="{{ $nameExpression }} + '[alt]'" x-model="block.alt" required>
                    </div>
                    <div>
                        <label class="label" x-bind:for="'block-caption-' + block.client_key">Caption (opsional)</label>
                        <input class="input" maxlength="500" x-bind:id="'block-caption-' + block.client_key" x-bind:name="{{ $nameExpression }} + '[caption]'" x-model="block.caption">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" x-bind:for="'block-ai-' + block.client_key">Konteks gambar untuk AI</label>
                        <textarea class="input" rows="2" maxlength="2000" x-bind:id="'block-ai-' + block.client_key" x-bind:name="{{ $nameExpression }} + '[ai_context]'" x-model="block.ai_context"></textarea>
                        <p class="help mt-1">Tidak terlihat oleh peserta. Wajib diisi sebelum meminta saran penilaian AI.</p>
                    </div>
                </div>
            </template>

            <template x-if="block.type === 'code'">
                <div class="grid gap-3 sm:grid-cols-[10rem_minmax(0,1fr)]">
                    <div>
                        <label class="label" x-bind:for="'block-language-' + block.client_key">Bahasa</label>
                        <input class="input" maxlength="50" x-bind:id="'block-language-' + block.client_key" x-bind:name="{{ $nameExpression }} + '[language]'" x-model="block.language" placeholder="python">
                    </div>
                    <div>
                        <label class="label" x-bind:for="'block-code-caption-' + block.client_key">Caption (opsional)</label>
                        <input class="input" maxlength="500" x-bind:id="'block-code-caption-' + block.client_key" x-bind:name="{{ $nameExpression }} + '[caption]'" x-model="block.caption">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" x-bind:for="'block-code-' + block.client_key">Kode</label>
                        <textarea class="input min-h-48 font-mono" spellcheck="false" maxlength="20000" x-bind:id="'block-code-' + block.client_key" x-bind:name="{{ $nameExpression }} + '[code]'" x-model="block.code" required></textarea>
                    </div>
                </div>
            </template>

            <template x-if="block.type === 'table'">
                <div class="space-y-3">
                    <div>
                        <label class="label" x-bind:for="'block-table-caption-' + block.client_key">Caption tabel (opsional)</label>
                        <input class="input" maxlength="500" x-bind:id="'block-table-caption-' + block.client_key" x-bind:name="{{ $nameExpression }} + '[caption]'" x-model="block.caption">
                    </div>
                    <div class="overflow-x-auto" tabindex="0">
                        <table class="min-w-full border-collapse text-sm">
                            <thead><tr>
                                <template x-for="(header, columnIndex) in block.headers" :key="columnIndex">
                                    <th class="min-w-40 border border-outline p-2 align-top">
                                        <label class="sr-only" x-bind:for="'header-' + block.client_key + '-' + columnIndex">Judul kolom</label>
                                        <input class="input" maxlength="500" x-bind:id="'header-' + block.client_key + '-' + columnIndex" x-bind:name="{{ $nameExpression }} + '[headers][' + columnIndex + ']'" x-model="block.headers[columnIndex]">
                                        <button type="button" class="mt-1 text-sm font-bold text-coral-deep underline" x-on:click="hapusKolom(block, columnIndex)" x-bind:disabled="block.headers.length <= 1">Hapus kolom</button>
                                    </th>
                                </template>
                            </tr></thead>
                            <tbody>
                                <template x-for="(row, rowIndex) in block.rows" :key="rowIndex">
                                    <tr>
                                        <template x-for="(cell, columnIndex) in row" :key="columnIndex">
                                            <td class="border border-outline p-2"><input class="input min-w-40" maxlength="1000" x-bind:name="{{ $nameExpression }} + '[rows][' + rowIndex + '][' + columnIndex + ']'" x-model="block.rows[rowIndex][columnIndex]" x-bind:aria-label="'Baris ' + (rowIndex + 1) + ', kolom ' + (columnIndex + 1)"></td>
                                        </template>
                                        <td class="border border-outline p-2"><button type="button" class="btn btn-ghost min-h-11 text-coral-deep" x-on:click="hapusBaris(block, rowIndex)" x-bind:disabled="block.rows.length <= 1">Hapus baris</button></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-secondary" x-on:click="tambahKolom(block)" x-bind:disabled="block.headers.length >= 10">Tambah kolom</button>
                        <button type="button" class="btn btn-secondary" x-on:click="tambahBaris(block)" x-bind:disabled="block.rows.length >= 20">Tambah baris</button>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <p x-show="{{ $blocksExpression }}.length === 0" class="text-sm text-ink-muted">Belum ada konten tambahan.</p>
</div>
