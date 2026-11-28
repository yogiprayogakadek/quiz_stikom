@php
    $opsiAwal = old('opsi', $question?->options->map(fn ($o) => ['teks' => $o->teks])->all() ?? [['teks' => ''], ['teks' => '']]);
    $benarAwal = (int) old('correct', $question?->options->search(fn ($o) => $o->is_correct) ?: 0);
    $tipeAwal = old('tipe', $question?->tipe ?? 'pilihan_ganda');
    $bobotAwal = (int) old('bobot', $question?->bobot ?? ($tipeAwal === 'essay' ? 10 : 1));
    $rubrikAwal = array_values(old('rubrik_essay', $question?->rubrik_essay ?? []));
    $petunjukAwal = old('petunjuk', implode("\n", (array) ($question?->petunjuk_json ?? [])));
    $blokAwal = collect(old('content_blocks', $question?->content_blocks_json ?? []))
        ->map(function ($blok) {
            $blok = is_array($blok) ? $blok : [];
            $blok['client_key'] = $blok['client_key'] ?? (string) \Illuminate\Support\Str::uuid();
            if (($blok['type'] ?? null) === 'image' && ! empty($blok['media_id'])) {
                $blok['media_url'] = route('question-media.show', $blok['media_id']);
            }
            return $blok;
        })->values()->all();
    $formatJawabanAwal = old('answer_format', $question?->answer_format ?? 'rich_text');
    $konfigurasiJawabanAwal = \App\Support\QuestionContent::normalizeAnswerConfig(
        $formatJawabanAwal,
        old('answer_config', $question?->answer_config_json ?? []),
    );
    $subSoalAwal = collect(old('sub_soal', $question?->subSoalTerstruktur() ?? []))
        ->map(function ($bagian, $index) {
            $bagian = is_array($bagian) ? $bagian : [];
            $petunjuk = $bagian['petunjuk'] ?? [];
            $formatJawaban = $bagian['answer_format'] ?? 'rich_text';

            return [
                'key' => ($bagian['legacy'] ?? false) ? 'legacy-'.$index : ($bagian['key'] ?? 'legacy-'.$index),
                'parent_key' => $bagian['parent_key'] ?? null,
                'label' => $bagian['label'] ?? '',
                'teks' => $bagian['teks'] ?? '',
                'petunjuk' => is_array($petunjuk) ? implode("\n", $petunjuk) : (string) $petunjuk,
                'content_blocks' => collect($bagian['content_blocks'] ?? [])->map(function ($blok) {
                    $blok['client_key'] = $blok['client_key'] ?? (string) \Illuminate\Support\Str::uuid();
                    if (($blok['type'] ?? null) === 'image' && ! empty($blok['media_id'])) {
                        $blok['media_url'] = route('question-media.show', $blok['media_id']);
                    }
                    return $blok;
                })->values()->all(),
                'answer_format' => $formatJawaban,
                'answer_config' => \App\Support\QuestionContent::normalizeAnswerConfig($formatJawaban, $bagian['answer_config'] ?? []),
            ];
        })
        ->values()
        ->all();
@endphp

<div
    x-data="{
        tipe: {{ Illuminate\Support\Js::from($tipeAwal) }},
        bobot: {{ $bobotAwal }},
        opsi: {{ Illuminate\Support\Js::from(array_values($opsiAwal)) }},
        rubrik: {{ Illuminate\Support\Js::from($rubrikAwal) }},
        petunjuk: {{ Illuminate\Support\Js::from($petunjukAwal) }},
        contentBlocks: {{ Illuminate\Support\Js::from($blokAwal) }},
        answerFormat: {{ Illuminate\Support\Js::from($formatJawabanAwal) }},
        answerConfig: {{ Illuminate\Support\Js::from($konfigurasiJawabanAwal) }},
        subSoal: {{ Illuminate\Support\Js::from($subSoalAwal) }},
        correct: {{ $benarAwal }},
        tambah() {
            if (this.opsi.length < 6) {
                this.opsi.push({ teks: '' });
            }
        },
        hapus(index) {
            if (this.opsi.length > 2) {
                this.opsi.splice(index, 1);
                if (this.correct >= this.opsi.length) {
                    this.correct = 0;
                } else if (this.correct > index) {
                    this.correct--;
                }
            }
        },
        tambahKriteria() {
            if (this.rubrik.length < 10) {
                this.rubrik.push({ label: '', points: 1 });
            }
        },
        hapusKriteria(index) {
            this.rubrik.splice(index, 1);
        },
        keyBaru() {
            return globalThis.crypto?.randomUUID?.() ?? ('part_' + Date.now() + '_' + Math.random().toString(36).slice(2));
        },
        anak(parentKey) {
            return this.subSoal.filter((bagian) => (bagian.parent_key ?? null) === parentKey);
        },
        labelBaru(parentKey) {
            const terpakai = new Set(this.anak(parentKey).map((bagian) => String(bagian.label).toLowerCase()));
            if (parentKey === null) {
                let kode = 65;
                while (terpakai.has(String.fromCharCode(kode).toLowerCase())) kode++;
                return String.fromCharCode(kode);
            }
            let nomor = 1;
            while (terpakai.has(String(nomor))) nomor++;
            return String(nomor);
        },
        turunanDari(bagian, parentKey) {
            let key = bagian.parent_key;
            const dilihat = new Set();
            while (key && !dilihat.has(key)) {
                if (key === parentKey) return true;
                dilihat.add(key);
                key = this.subSoal.find((item) => item.key === key)?.parent_key;
            }
            return false;
        },
        kodeBagian(bagian) {
            const label = [];
            let saatIni = bagian;
            const dilihat = new Set();
            while (saatIni && !dilihat.has(saatIni.key)) {
                label.unshift(saatIni.label);
                dilihat.add(saatIni.key);
                saatIni = this.subSoal.find((item) => item.key === saatIni.parent_key);
            }
            return label.join('.');
        },
        punyaAnak(key) {
            return this.subSoal.some((bagian) => bagian.parent_key === key);
        },
        blokBaru(type) {
            const client_key = this.keyBaru();
            if (type === 'image') return { type, client_key, media_id: null, alt: '', caption: '', ai_context: '' };
            if (type === 'table') return { type, client_key, caption: '', headers: ['Kolom 1', 'Kolom 2'], rows: [['', '']] };
            if (type === 'code') return { type, client_key, language: 'text', caption: '', code: '' };
            return { type: 'text', client_key, text: '' };
        },
        tambahBlok(target, type) {
            if (target.length < 20) target.push(this.blokBaru(type));
        },
        geserBlok(target, index, arah) {
            const tujuan = index + arah;
            if (tujuan < 0 || tujuan >= target.length) return;
            [target[index], target[tujuan]] = [target[tujuan], target[index]];
        },
        tambahKolom(block) {
            if (block.headers.length >= 10) return;
            block.headers.push('Kolom ' + (block.headers.length + 1));
            block.rows.forEach((row) => row.push(''));
        },
        hapusKolom(block, index) {
            if (block.headers.length <= 1) return;
            block.headers.splice(index, 1);
            block.rows.forEach((row) => row.splice(index, 1));
        },
        tambahBaris(block) {
            if (block.rows.length < 20) block.rows.push(Array(block.headers.length).fill(''));
        },
        hapusBaris(block, index) {
            if (block.rows.length > 1) block.rows.splice(index, 1);
        },
        pastikanConfig(target, format) {
            if (format === 'code') return { language: target?.language || 'text' };
            if (format === 'table') {
                const columns = target?.columns?.length ? target.columns : ['Kolom 1', 'Kolom 2'];
                const rows = target?.rows?.length ? target.rows : ['Baris 1'];
                return {
                    row_header: target?.row_header || 'Baris',
                    columns,
                    rows,
                    cell_hints: rows.map((row, rowIndex) => columns.map((column, columnIndex) => target?.cell_hints?.[rowIndex]?.[columnIndex] || ''))
                };
            }
            return {};
        },
        ubahFormatUtama() {
            this.answerConfig = this.pastikanConfig(this.answerConfig, this.answerFormat);
        },
        ubahFormatBagian(bagian) {
            bagian.answer_config = this.pastikanConfig(bagian.answer_config, bagian.answer_format);
        },
        tambahKolomJawaban(config) {
            if (config.columns.length >= 10) return;
            config.columns.push('Kolom ' + (config.columns.length + 1));
            config.cell_hints.forEach((row) => row.push(''));
        },
        tambahBarisJawaban(config) {
            if (config.rows.length >= 20) return;
            config.rows.push('Baris ' + (config.rows.length + 1));
            config.cell_hints.push(Array(config.columns.length).fill(''));
        },
        hapusKolomJawaban(config, index) {
            if (config.columns.length <= 1) return;
            config.columns.splice(index, 1);
            config.cell_hints.forEach((row) => row.splice(index, 1));
        },
        hapusBarisJawaban(config, index) {
            if (config.rows.length <= 1) return;
            config.rows.splice(index, 1);
            config.cell_hints.splice(index, 1);
        },
        tambahSubSoal(parentKey = null) {
            if (this.subSoal.length >= 50 || this.anak(parentKey).length >= 10) return;
            const bagian = { key: this.keyBaru(), parent_key: parentKey, label: this.labelBaru(parentKey), teks: '', petunjuk: '', content_blocks: [], answer_format: 'rich_text', answer_config: {} };
            if (parentKey === null) {
                this.subSoal.push(bagian);
                return;
            }
            let posisi = this.subSoal.findIndex((item) => item.key === parentKey) + 1;
            while (posisi < this.subSoal.length && this.turunanDari(this.subSoal[posisi], parentKey)) posisi++;
            this.subSoal.splice(posisi, 0, bagian);
        },
        hapusSubSoal(index) {
            const key = this.subSoal[index].key;
            const jumlah = this.subSoal.filter((bagian) => bagian.key === key || this.turunanDari(bagian, key)).length;
            if (jumlah > 1 && !window.confirm('Hapus bagian ini beserta ' + (jumlah - 1) + ' turunannya?')) return;
            this.subSoal = this.subSoal.filter((bagian) => bagian.key !== key && !this.turunanDari(bagian, key));
        }
    }"
    class="min-w-0 space-y-6"
>
    <div>
        <label for="teks" class="label">Teks soal</label>
        <textarea id="teks" name="teks" rows="4" class="input" required @error('teks') aria-invalid="true" @enderror>{{ old('teks', $question?->teks) }}</textarea>
        @error('teks') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <section class="border-t border-outline pt-5" aria-label="Konten tambahan soal">
        @include('admin.questions._content-block-editor', [
            'label' => 'Konten tambahan (opsional)',
            'blocksExpression' => 'contentBlocks',
            'nameExpression' => "'content_blocks[' + blockIndex + ']'",
        ])
        @error('content_blocks') <p class="field-error">{{ $message }}</p> @enderror
        @error('content_blocks.*') <p class="field-error">{{ $message }}</p> @enderror
    </section>

    <fieldset>
        <legend class="label">Tipe soal</legend>
        <div class="grid gap-3 sm:grid-cols-2">
            <label
                class="flex cursor-pointer items-center gap-3 rounded-xl border-2 p-3"
                x-bind:class="tipe === 'pilihan_ganda' ? 'border-brand bg-brand-soft' : 'border-outline-strong bg-page'"
            >
                <input type="radio" name="tipe" value="pilihan_ganda" x-model="tipe" class="h-5 w-5">
                <span>
                    <span class="block font-bold text-ink">Pilihan ganda</span>
                    <span class="block text-sm text-ink-muted">Dinilai otomatis dari kunci jawaban.</span>
                </span>
            </label>
            <label
                class="flex cursor-pointer items-center gap-3 rounded-xl border-2 p-3"
                x-bind:class="tipe === 'essay' ? 'border-brand bg-brand-soft' : 'border-outline-strong bg-page'"
            >
                <input type="radio" name="tipe" value="essay" x-model="tipe" class="h-5 w-5">
                <span>
                    <span class="block font-bold text-ink">Essay</span>
                    <span class="block text-sm text-ink-muted">Dijawab dengan teks dan dinilai manual admin.</span>
                </span>
            </label>
        </div>
        <p class="help mt-2">Essay hanya bisa dipakai di Mode Acak. Mode Serempak akan menolak soal essay saat dimulai.</p>
        @error('tipe') <p class="field-error">{{ $message }}</p> @enderror
    </fieldset>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <div x-show="tipe === 'pilihan_ganda'" x-cloak>
                <label for="durasi_detik" class="label">Durasi soal (detik)</label>
                <input id="durasi_detik" name="durasi_detik" type="number" min="5" max="300" value="{{ old('durasi_detik', $question?->durasi_detik ?? 30) }}" class="input" x-bind:required="tipe === 'pilihan_ganda'" x-bind:disabled="tipe !== 'pilihan_ganda'" @error('durasi_detik') aria-invalid="true" @enderror>
                <p class="help mt-2">5 sampai 300 detik. Timer per soal di Mode Serempak; di Mode Acak menjadi penentu batas total bila durasi sesi kosong.</p>
                @error('durasi_detik') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div x-show="tipe === 'essay'" x-cloak>
                <p class="label">Durasi soal</p>
                <p class="help">Essay memakai durasi total sesi Mode Acak, bukan durasi per soal.</p>
            </div>
        </div>

        <div>
            <label for="bobot" class="label">Bobot / nilai maksimal</label>
            <input id="bobot" name="bobot" type="number" min="1" max="1000" value="{{ $bobotAwal }}" class="input" required x-model.number="bobot" x-on:change="if (tipe === 'essay' && bobot === 1) { bobot = 10 }" @error('bobot') aria-invalid="true" @enderror>
            <p class="help mt-2">Poin jika benar untuk pilihan ganda, atau nilai maksimal untuk essay. Mode Serempak tetap memakai poin kecepatan.</p>
            @error('bobot') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label for="pembahasan" class="label">Pembahasan (opsional)</label>
        <textarea id="pembahasan" name="pembahasan" rows="3" class="input" @error('pembahasan') aria-invalid="true" @enderror>{{ old('pembahasan', $question?->pembahasan) }}</textarea>
        <p class="help mt-2">Tampil di akhir attempt hanya bila sesi mengaktifkan pembahasan.</p>
        @error('pembahasan') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <fieldset x-show="tipe === 'pilihan_ganda'" x-cloak>
        <legend class="label">Opsi jawaban</legend>
        <p class="help mb-3">Isi 2 sampai 6 opsi. Pilih satu jawaban benar dengan tombol radio di kiri.</p>

        <div class="space-y-3">
            <template x-for="(item, index) in opsi" :key="index">
                <div class="flex items-center gap-3">
                    <label class="flex h-12 w-12 flex-none items-center justify-center rounded-xl border-2 border-outline-strong bg-page">
                        <input
                            type="radio"
                            name="correct"
                            x-bind:value="index"
                            x-model.number="correct"
                            x-bind:disabled="tipe !== 'pilihan_ganda'"
                            class="h-5 w-5"
                            x-bind:aria-label="'Tandai opsi ' + (index + 1) + ' sebagai jawaban benar'"
                        >
                    </label>

                    <div class="flex-1">
                        <label class="sr-only" x-bind:for="'opsi-' + index">Teks opsi <span x-text="index + 1"></span></label>
                        <input
                            type="text"
                            x-bind:id="'opsi-' + index"
                            x-bind:name="'opsi[' + index + '][teks]'"
                            x-model="item.teks"
                            class="input"
                            x-bind:required="tipe === 'pilihan_ganda'"
                            x-bind:disabled="tipe !== 'pilihan_ganda'"
                            maxlength="500"
                            placeholder="Tulis teks opsi"
                        >
                    </div>

                    <button
                        type="button"
                        class="btn btn-ghost px-3"
                        x-on:click="hapus(index)"
                        x-bind:disabled="tipe !== 'pilihan_ganda' || opsi.length <= 2"
                        x-bind:aria-label="'Hapus opsi ' + (index + 1)"
                    >
                        <x-icon name="x" :size="20" />
                    </button>
                </div>
            </template>
        </div>

        <button type="button" class="btn btn-secondary mt-3" x-on:click="tambah()" x-bind:disabled="tipe !== 'pilihan_ganda' || opsi.length >= 6">
            <x-icon name="plus" :size="20" />
            Tambah opsi
        </button>

        @error('opsi') <p class="field-error">{{ $message }}</p> @enderror
        @error('opsi.*') <p class="field-error">{{ $message }}</p> @enderror
        @error('correct') <p class="field-error">{{ $message }}</p> @enderror
    </fieldset>

    <div x-show="tipe === 'essay'" x-cloak class="min-w-0 space-y-5">
        <details class="rounded-2xl border border-outline bg-surface p-4">
            <summary class="cursor-pointer font-bold text-ink">Pola soal subnetting</summary>
            <div class="mt-3 grid gap-3 text-sm leading-relaxed text-ink-muted sm:grid-cols-3">
                <div>
                    <p class="font-bold text-ink">Subnet tunggal</p>
                    <p class="mt-1">Gunakan bagian rich text untuk proses perhitungan, lalu tabel vertikal untuk network, broadcast, dan rentang host.</p>
                </div>
                <div>
                    <p class="font-bold text-ink">Enumerasi FLSM</p>
                    <p class="mt-1">Pisahkan perhitungan awal dan tabel jawaban yang memuat setiap blok subnet secara berurutan.</p>
                </div>
                <div>
                    <p class="font-bold text-ink">Perencanaan VLSM</p>
                    <p class="mt-1">Minta alasan urutan alokasi dalam rich text dan gunakan tabel untuk alokasi tiap bagian. Nilai tetap memakai rubrik manual atau saran AI.</p>
                </div>
            </div>
        </details>

        <section x-show="subSoal.length === 0" class="min-w-0 border-b border-outline pb-5">
            @include('admin.questions._answer-format-editor', [
                'formatExpression' => 'answerFormat',
                'configExpression' => 'answerConfig',
                'nameExpression' => "''",
                'idExpression' => "'answer-format'",
                'changeExpression' => 'ubahFormatUtama()',
                'disabledExpression' => "tipe !== 'essay' || subSoal.length > 0",
            ])
            @error('answer_format') <p class="field-error">{{ $message }}</p> @enderror
            @error('answer_config') <p class="field-error">{{ $message }}</p> @enderror
        </section>
        <div>
        <label for="kunci_essay" class="label">Jawaban acuan (opsional)</label>
        <textarea id="kunci_essay" name="kunci_essay" rows="4" class="input" maxlength="5000" x-bind:disabled="tipe !== 'essay'" @error('kunci_essay') aria-invalid="true" @enderror>{{ old('kunci_essay', $question?->kunci_essay) }}</textarea>
        <p class="help mt-2">Contoh jawaban yang benar. Tidak ditampilkan ke peserta kecuali pembahasan sesi diaktifkan.</p>
        @error('kunci_essay') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="petunjuk" class="label">Poin yang harus dijawab (opsional)</label>
            <textarea id="petunjuk" name="petunjuk" rows="4" class="input" maxlength="5000" x-model="petunjuk" x-bind:disabled="tipe !== 'essay'" @error('petunjuk') aria-invalid="true" @enderror></textarea>
            <p class="help mt-2">Satu poin per baris. Tampil sebagai checklist untuk peserta.</p>
            @error('petunjuk') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <fieldset class="border-t border-outline pt-5" aria-labelledby="sub-soal-label">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p id="sub-soal-label" class="label">Sub-soal (opsional)</p>
                    <p class="help">Bagian dapat memiliki turunan. Peserta mendapat satu kotak jawaban untuk setiap bagian terakhir.</p>
                </div>
                <button type="button" class="btn btn-secondary" x-on:click="tambahSubSoal()" x-bind:disabled="tipe !== 'essay' || subSoal.length >= 50 || anak(null).length >= 10">
                    <x-icon name="plus" :size="18" />
                    Tambah sub-soal
                </button>
            </div>

            <div class="mt-3 min-w-0 space-y-3">
                <template x-for="(bagian, index) in subSoal" :key="bagian.key">
                    <div class="min-w-0 max-w-full space-y-3 rounded-xl border border-outline p-4">
                        <input type="hidden" x-bind:name="'sub_soal[' + index + '][key]'" x-bind:value="bagian.key">
                        <input type="hidden" x-bind:name="'sub_soal[' + index + '][parent_key]'" x-bind:value="bagian.parent_key ?? ''">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline pb-3">
                            <div>
                                <p class="font-bold text-ink">Bagian <span x-text="kodeBagian(bagian)"></span></p>
                                <p class="text-sm text-ink-muted" x-text="punyaAnak(bagian.key) ? 'Kelompok soal; jawaban diisi pada turunannya.' : 'Bagian terakhir; peserta mengisi satu jawaban.'"></p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn btn-secondary min-h-11 px-3" x-on:click="tambahSubSoal(bagian.key)" x-bind:disabled="tipe !== 'essay' || subSoal.length >= 50 || anak(bagian.key).length >= 10">
                                    <x-icon name="plus" :size="18" />
                                    Tambah turunan
                                </button>
                                <button type="button" class="btn btn-ghost min-h-11 min-w-11 px-3 text-coral-deep" x-on:click="hapusSubSoal(index)" x-bind:disabled="tipe !== 'essay'" x-bind:aria-label="'Hapus bagian ' + kodeBagian(bagian)">
                                    <x-icon name="x" :size="18" />
                                </button>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-[6rem_minmax(0,1fr)] sm:items-start">
                            <div>
                                <label class="label" x-bind:for="'sub-label-' + index">Label</label>
                                <input type="text" x-bind:id="'sub-label-' + index" x-bind:name="'sub_soal[' + index + '][label]'" x-model="bagian.label" class="input" maxlength="10" placeholder="A" x-bind:required="tipe === 'essay'" x-bind:disabled="tipe !== 'essay'">
                            </div>
                            <div>
                                <label class="label" x-bind:for="'sub-teks-' + index">Teks sub-soal</label>
                                <textarea x-bind:id="'sub-teks-' + index" x-bind:name="'sub_soal[' + index + '][teks]'" x-model="bagian.teks" rows="3" class="input" maxlength="2000" x-bind:required="tipe === 'essay'" x-bind:disabled="tipe !== 'essay'"></textarea>
                            </div>
                        </div>
                        <div>
                            <label class="label" x-bind:for="'sub-petunjuk-' + index">Poin yang harus dijawab (opsional)</label>
                            <textarea x-bind:id="'sub-petunjuk-' + index" x-bind:name="'sub_soal[' + index + '][petunjuk]'" x-model="bagian.petunjuk" rows="3" class="input" maxlength="5000" x-bind:disabled="tipe !== 'essay'"></textarea>
                            <p class="help mt-1">Satu poin per baris.</p>
                        </div>
                        <div class="min-w-0 border-t border-outline pt-4">
                            @include('admin.questions._content-block-editor', [
                                'label' => 'Konten tambahan bagian (opsional)',
                                'blocksExpression' => 'bagian.content_blocks',
                                'nameExpression' => "'sub_soal[' + index + '][content_blocks][' + blockIndex + ']'",
                            ])
                        </div>
                        <div class="min-w-0 border-t border-outline pt-4">
                            @include('admin.questions._answer-format-editor', [
                                'formatExpression' => 'bagian.answer_format',
                                'configExpression' => 'bagian.answer_config',
                                'nameExpression' => "'sub_soal[' + index + ']'",
                                'idExpression' => "'sub-answer-format-' + index",
                                'changeExpression' => 'ubahFormatBagian(bagian)',
                                'disabledExpression' => "tipe !== 'essay' || punyaAnak(bagian.key)",
                            ])
                        </div>
                    </div>
                </template>
                <p x-show="subSoal.length === 0" class="text-sm text-ink-muted">Belum ada sub-soal. Soal essay biasa tetap memakai satu kotak jawaban.</p>
            </div>
            @error('sub_soal') <p class="field-error">{{ $message }}</p> @enderror
            @error('sub_soal.*.key') <p class="field-error">{{ $message }}</p> @enderror
            @error('sub_soal.*.parent_key') <p class="field-error">{{ $message }}</p> @enderror
            @error('sub_soal.*.label') <p class="field-error">{{ $message }}</p> @enderror
            @error('sub_soal.*.teks') <p class="field-error">{{ $message }}</p> @enderror
            @error('sub_soal.*.petunjuk') <p class="field-error">{{ $message }}</p> @enderror
            @error('sub_soal.*.content_blocks*') <p class="field-error">{{ $message }}</p> @enderror
            @error('sub_soal.*.answer_format') <p class="field-error">{{ $message }}</p> @enderror
            @error('sub_soal.*.answer_config*') <p class="field-error">{{ $message }}</p> @enderror
        </fieldset>

        <fieldset class="border-t border-outline pt-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <legend class="label">Kriteria penilaian</legend>
                    <p class="help">Opsional. Total poin kriteria harus sama dengan nilai maksimal essay.</p>
                </div>
                <button type="button" class="btn btn-secondary" x-on:click="tambahKriteria()" x-bind:disabled="rubrik.length >= 10">
                    <x-icon name="plus" :size="18" />
                    Tambah kriteria
                </button>
            </div>

            <div class="mt-3 divide-y divide-outline border-y border-outline">
                <template x-for="(criterion, index) in rubrik" :key="index">
                    <div class="grid gap-3 py-3 sm:grid-cols-[minmax(0,1fr)_8rem_auto] sm:items-end">
                        <div>
                            <label class="label" x-bind:for="'rubrik-label-' + index">Kriteria <span x-text="index + 1"></span></label>
                            <input type="text" x-bind:id="'rubrik-label-' + index" x-bind:name="'rubrik_essay[' + index + '][label]'" x-model="criterion.label" class="input" maxlength="200" x-bind:required="tipe === 'essay'" x-bind:disabled="tipe !== 'essay'">
                        </div>
                        <div>
                            <label class="label" x-bind:for="'rubrik-points-' + index">Poin maksimal</label>
                            <input type="number" x-bind:id="'rubrik-points-' + index" x-bind:name="'rubrik_essay[' + index + '][points]'" x-model.number="criterion.points" class="input" min="1" max="1000" x-bind:required="tipe === 'essay'" x-bind:disabled="tipe !== 'essay'">
                        </div>
                        <button type="button" class="btn btn-ghost min-h-11 px-3 text-coral-deep" x-on:click="hapusKriteria(index)" x-bind:aria-label="'Hapus kriteria ' + (index + 1)">
                            <x-icon name="x" :size="18" />
                        </button>
                    </div>
                </template>
                <p x-show="rubrik.length === 0" class="py-4 text-sm text-ink-muted">Belum ada kriteria. Admin tetap dapat memberi nilai total secara manual.</p>
            </div>
            <p x-show="rubrik.length > 0" class="mt-2 text-sm font-semibold" x-bind:class="rubrik.reduce((total, item) => total + (Number(item.points) || 0), 0) === Number(bobot) ? 'text-leaf-deep' : 'text-coral-deep'">
                Total kriteria: <span x-text="rubrik.reduce((total, item) => total + (Number(item.points) || 0), 0)"></span> / <span x-text="bobot"></span> poin
            </p>
            @error('rubrik_essay') <p class="field-error">{{ $message }}</p> @enderror
            @error('rubrik_essay.*') <p class="field-error">{{ $message }}</p> @enderror
        </fieldset>
    </div>
</div>
