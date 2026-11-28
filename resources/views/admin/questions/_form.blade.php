@php
    $defaultOpsiBaru = [
        ['teks' => ''],
        ['teks' => ''],
        ['teks' => ''],
        ['teks' => ''],
    ];
    $opsiDariSoal = ($question !== null && $question->tipe !== 'isian_singkat' && $question->options->isNotEmpty())
        ? $question->options->map(fn ($o) => ['teks' => $o->teks])->all()
        : $defaultOpsiBaru;
    $opsiAwal = old('opsi', $opsiDariSoal);
    $benarAwal = (int) old('correct', $question?->options->search(fn ($o) => $o->is_correct) ?: 0);
    $indeksBenarSoal = ($question !== null && $question->tipe === 'pilihan_majemuk')
        ? $question->options->values()->filter(fn ($o) => $o->is_correct)->keys()->map(fn ($k) => (int) $k)->values()->all()
        : [0];
    $benarMajemukAwal = array_values(array_map('intval', (array) old('correct_indices', $indeksBenarSoal ?: [0])));
    $scoringModeAwal = old('scoring_mode', $question?->answer_config_json['scoring_mode'] ?? 'all_or_nothing');
    $acceptedAnswersAwal = old(
        'accepted_answers',
        ($question !== null && $question->tipe === 'isian_singkat')
            ? $question->options->where('is_correct', true)->pluck('teks')->implode("\n")
            : ''
    );
    $caseSensitiveAwal = (bool) old('case_sensitive', $question?->answer_config_json['case_sensitive'] ?? false);
    $tipeQuery = request()->query('tipe');
    $tipeDefault = in_array($tipeQuery, ['pilihan_ganda', 'essay', 'pilihan_majemuk', 'isian_singkat'], true) ? $tipeQuery : 'pilihan_ganda';
    $tipeAwal = old('tipe', $question?->tipe ?? $tipeDefault);
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

    $bukaLampiran = $blokAwal !== [] || $errors->has('content_blocks') || $errors->has('content_blocks.*');
    $bukaPembahasan = filled(old('pembahasan', $question?->pembahasan)) || $errors->has('pembahasan');
    $bukaLanjutanEssay = filled($petunjukAwal)
        || $subSoalAwal !== []
        || $rubrikAwal !== []
        || $formatJawabanAwal !== 'rich_text'
        || $errors->has('petunjuk')
        || $errors->has('sub_soal')
        || $errors->has('sub_soal.*')
        || $errors->has('rubrik_essay')
        || $errors->has('rubrik_essay.*')
        || $errors->has('answer_format')
        || $errors->has('answer_config');
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
        correctIndices: {{ Illuminate\Support\Js::from($benarMajemukAwal) }},
        scoringMode: {{ Illuminate\Support\Js::from($scoringModeAwal) }},
        hurufOpsi(index) {
            return String.fromCharCode(65 + index);
        },
        toggleCorrectIndex(index) {
            const pos = this.correctIndices.indexOf(index);
            if (pos >= 0) {
                this.correctIndices.splice(pos, 1);
            } else {
                this.correctIndices.push(index);
            }
        },
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
                this.correctIndices = this.correctIndices
                    .filter((i) => i !== index)
                    .map((i) => (i > index ? i - 1 : i));
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
    <fieldset>
        <legend class="label">Tipe soal</legend>
        <div class="grid gap-3 sm:grid-cols-2">
            <label
                class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border-2 p-3 transition"
                x-bind:class="tipe === 'pilihan_ganda' ? 'border-brand bg-brand-soft' : 'border-outline-strong bg-page'"
            >
                <input type="radio" name="tipe" value="pilihan_ganda" x-model="tipe" class="h-5 w-5 shrink-0">
                <span>
                    <span class="block font-bold text-ink">Pilihan ganda</span>
                    <span class="block text-sm text-ink-muted">Satu jawaban benar, dinilai otomatis.</span>
                </span>
            </label>
            <label
                class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border-2 p-3 transition"
                x-bind:class="tipe === 'pilihan_majemuk' ? 'border-brand bg-brand-soft' : 'border-outline-strong bg-page'"
            >
                <input type="radio" name="tipe" value="pilihan_majemuk" x-model="tipe" class="h-5 w-5 shrink-0">
                <span>
                    <span class="block font-bold text-ink">Pilihan majemuk</span>
                    <span class="block text-sm text-ink-muted">Lebih dari satu opsi benar, semua-atau-nol atau parsial.</span>
                </span>
            </label>
            <label
                class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border-2 p-3 transition"
                x-bind:class="tipe === 'isian_singkat' ? 'border-brand bg-brand-soft' : 'border-outline-strong bg-page'"
            >
                <input type="radio" name="tipe" value="isian_singkat" x-model="tipe" class="h-5 w-5 shrink-0">
                <span>
                    <span class="block font-bold text-ink">Isian singkat</span>
                    <span class="block text-sm text-ink-muted">Jawaban singkat 1 baris, cocok otomatis dengan daftar kunci.</span>
                </span>
            </label>
            <label
                class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border-2 p-3 transition"
                x-bind:class="tipe === 'essay' ? 'border-brand bg-brand-soft' : 'border-outline-strong bg-page'"
            >
                <input type="radio" name="tipe" value="essay" x-model="tipe" x-on:change="if (bobot === 1) { bobot = 10 }" class="h-5 w-5 shrink-0">
                <span>
                    <span class="block font-bold text-ink">Essay</span>
                    <span class="block text-sm text-ink-muted">Uraian atau tabel, dinilai pengajar atau saran AI.</span>
                </span>
            </label>
        </div>
        <p class="help mt-2">Pilihan ganda, pilihan majemuk, dan isian singkat didukung di Mode Acak maupun Mode Serempak. Essay khusus Mode Acak.</p>
        @error('tipe') <p class="field-error">{{ $message }}</p> @enderror
    </fieldset>

    <div>
        <label for="teks" class="label">Teks pertanyaan</label>
        <textarea id="teks" name="teks" rows="4" class="input" required placeholder="Tulis kalimat soal di sini..." @error('teks') aria-invalid="true" @enderror>{{ old('teks', $question?->teks) }}</textarea>
        @error('teks') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <fieldset x-show="tipe === 'pilihan_ganda' || tipe === 'pilihan_majemuk'" x-cloak>
        <legend class="label">Opsi jawaban</legend>
        <p class="help mb-3" x-text="tipe === 'pilihan_majemuk' ? 'Isi 2 sampai 6 opsi. Centang satu atau beberapa huruf di sebelah kiri sebagai kunci jawaban benar.' : 'Isi 2 sampai 6 opsi. Pilih satu kunci jawaban benar pada tombol huruf di sebelah kiri.'"></p>

        <div class="space-y-3">
            <template x-for="(item, index) in opsi" :key="index">
                <div class="flex items-center gap-3">
                    <template x-if="tipe === 'pilihan_ganda'">
                        <label
                            class="flex h-12 min-w-14 flex-none cursor-pointer items-center justify-center gap-1.5 rounded-xl border-2 px-2.5 transition"
                            x-bind:class="Number(correct) === index ? 'border-brand bg-brand-soft text-brand-deep font-bold' : 'border-outline-strong bg-page text-ink'"
                        >
                            <input
                                type="radio"
                                name="correct"
                                x-bind:value="index"
                                x-model.number="correct"
                                class="h-4 w-4"
                                x-bind:aria-label="'Tandai opsi ' + hurufOpsi(index) + ' sebagai jawaban benar'"
                            >
                            <span class="font-mono text-sm font-bold" x-text="hurufOpsi(index)"></span>
                        </label>
                    </template>

                    <template x-if="tipe === 'pilihan_majemuk'">
                        <label
                            class="flex h-12 min-w-14 flex-none cursor-pointer items-center justify-center gap-1.5 rounded-xl border-2 px-2.5 transition"
                            x-bind:class="correctIndices.includes(index) ? 'border-brand bg-brand-soft text-brand-deep font-bold' : 'border-outline-strong bg-page text-ink'"
                        >
                            <input
                                type="checkbox"
                                name="correct_indices[]"
                                x-bind:value="index"
                                x-bind:checked="correctIndices.includes(index)"
                                x-on:change="toggleCorrectIndex(index)"
                                class="h-4 w-4 rounded"
                                x-bind:aria-label="'Centang opsi ' + hurufOpsi(index) + ' sebagai salah satu jawaban benar'"
                            >
                            <span class="font-mono text-sm font-bold" x-text="hurufOpsi(index)"></span>
                        </label>
                    </template>

                    <div class="flex-1">
                        <label class="sr-only" x-bind:for="'opsi-' + index">Teks opsi <span x-text="hurufOpsi(index)"></span></label>
                        <input
                            type="text"
                            x-bind:id="'opsi-' + index"
                            x-bind:name="'opsi[' + index + '][teks]'"
                            x-model="item.teks"
                            class="input"
                            x-bind:required="tipe === 'pilihan_ganda' || tipe === 'pilihan_majemuk'"
                            x-bind:disabled="tipe !== 'pilihan_ganda' && tipe !== 'pilihan_majemuk'"
                            maxlength="500"
                            x-bind:placeholder="'Tulis pilihan ' + hurufOpsi(index)"
                        >
                    </div>

                    <button
                        type="button"
                        class="btn btn-ghost min-h-11 min-w-11 px-3"
                        x-on:click="hapus(index)"
                        x-bind:disabled="(tipe !== 'pilihan_ganda' && tipe !== 'pilihan_majemuk') || opsi.length <= 2"
                        x-bind:aria-label="'Hapus opsi ' + hurufOpsi(index)"
                    >
                        <x-icon name="x" :size="20" />
                    </button>
                </div>
            </template>
        </div>

        <button type="button" class="btn btn-secondary mt-3 min-h-11" x-on:click="tambah()" x-bind:disabled="(tipe !== 'pilihan_ganda' && tipe !== 'pilihan_majemuk') || opsi.length >= 6">
            <x-icon name="plus" :size="20" />
            <span>Tambah opsi</span>
        </button>

        <div x-show="tipe === 'pilihan_majemuk'" x-cloak class="mt-4 rounded-xl border border-outline bg-page p-4">
            <p class="label">Aturan penilaian pilihan majemuk</p>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                <label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border border-outline bg-surface p-3 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                    <input type="radio" name="scoring_mode" value="all_or_nothing" x-model="scoringMode" x-bind:disabled="tipe !== 'pilihan_majemuk'" class="mt-0.5 h-4 w-4 shrink-0">
                    <span>
                        <span class="block text-sm font-bold text-ink">Semua benar atau nol</span>
                        <span class="block text-xs text-ink-muted">Poin penuh hanya diberikan bila seluruh kunci dipilih tanpa opsi salah.</span>
                    </span>
                </label>
                <label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border border-outline bg-surface p-3 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                    <input type="radio" name="scoring_mode" value="partial" x-model="scoringMode" x-bind:disabled="tipe !== 'pilihan_majemuk'" class="mt-0.5 h-4 w-4 shrink-0">
                    <span>
                        <span class="block text-sm font-bold text-ink">Parsial proporsional</span>
                        <span class="block text-xs text-ink-muted">Menghitung (opsi benar dipilih dikurangi opsi salah dipilih) dibagi jumlah kunci.</span>
                    </span>
                </label>
            </div>
        </div>

        @error('opsi') <p class="field-error">{{ $message }}</p> @enderror
        @error('opsi.*') <p class="field-error">{{ $message }}</p> @enderror
        @error('correct') <p class="field-error">{{ $message }}</p> @enderror
        @error('correct_indices') <p class="field-error">{{ $message }}</p> @enderror
    </fieldset>

    <div x-show="tipe === 'isian_singkat'" x-cloak class="min-w-0 space-y-4">
        <div>
            <label for="accepted_answers" class="label">Daftar jawaban singkat yang diterima</label>
            <textarea
                id="accepted_answers"
                name="accepted_answers"
                rows="4"
                class="input font-mono text-sm"
                maxlength="2000"
                x-bind:required="tipe === 'isian_singkat'"
                x-bind:disabled="tipe !== 'isian_singkat'"
                placeholder="255.255.255.0&#10;/24"
                @error('accepted_answers') aria-invalid="true" @enderror
            >{{ $acceptedAnswersAwal }}</textarea>
            <p class="help mt-2">Tulis satu variasi jawaban yang benar per baris (maksimal 15 baris). Spasi berlebih dirapikan otomatis saat peserta menjawab.</p>
            @error('accepted_answers') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-outline bg-page px-4 py-3 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
            <input type="checkbox" name="case_sensitive" value="1" x-bind:disabled="tipe !== 'isian_singkat'" class="h-4 w-4 rounded" @checked($caseSensitiveAwal)>
            <span class="text-sm font-semibold text-ink">Peka huruf besar dan kecil (case-sensitive)</span>
        </label>
    </div>

    <div x-show="tipe === 'essay'" x-cloak class="min-w-0 space-y-5">
        <div>
            <label for="kunci_essay" class="label">Jawaban acuan (opsional)</label>
            <textarea id="kunci_essay" name="kunci_essay" rows="4" class="input" maxlength="5000" x-bind:disabled="tipe !== 'essay'" placeholder="Tulis poin jawaban ideal sebagai acuan koreksi pengajar atau AI..." @error('kunci_essay') aria-invalid="true" @enderror>{{ old('kunci_essay', $question?->kunci_essay) }}</textarea>
            <p class="help mt-2">Tidak ditampilkan ke peserta kecuali pembahasan sesi dibuka.</p>
            @error('kunci_essay') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-3">
        <div>
            <label for="bobot" class="label">Bobot / nilai maksimal</label>
            <input id="bobot" name="bobot" type="number" min="1" max="1000" value="{{ $bobotAwal }}" class="input" required x-model.number="bobot" x-on:change="if (tipe === 'essay' && bobot === 1) { bobot = 10 }" @error('bobot') aria-invalid="true" @enderror>
            <p class="help mt-2">Poin jika benar untuk pilihan ganda, atau nilai maksimal untuk essay.</p>
            @error('bobot') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <div x-show="tipe !== 'essay'" x-cloak>
                <label for="durasi_detik" class="label">Durasi soal (detik)</label>
                <input id="durasi_detik" name="durasi_detik" type="number" min="5" max="300" value="{{ old('durasi_detik', $question?->durasi_detik ?? 30) }}" class="input" x-bind:required="tipe !== 'essay'" x-bind:disabled="tipe === 'essay'" @error('durasi_detik') aria-invalid="true" @enderror>
                <p class="help mt-2">5 sampai 300 detik. Dipakai pada timer per soal atau Mode Serempak.</p>
                @error('durasi_detik') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div x-show="tipe === 'essay'" x-cloak>
                <p class="label">Durasi soal</p>
                <p class="help">Essay mengikuti durasi total sesi pada Mode Acak.</p>
            </div>
        </div>

        <div>
            <label for="urutan_tetap" class="label">Urutan tetap (opsional)</label>
            <input id="urutan_tetap" name="urutan_tetap" type="number" min="1" max="1000" value="{{ old('urutan_tetap', $question?->urutan_tetap) }}" class="input" placeholder="Otomatis" @error('urutan_tetap') aria-invalid="true" @enderror>
            <p class="help mt-2">Dipakai saat acak urutan soal pada pengaturan kuis dimatikan.</p>
            @error('urutan_tetap') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <details class="rounded-2xl border border-outline bg-surface p-4" @if ($bukaLampiran) open @endif>
        <summary class="flex min-h-11 cursor-pointer items-center justify-between gap-3 font-bold text-ink">
            <span>Lampiran soal: gambar, tabel, atau blok kode (opsional)</span>
            <span class="pill pill-neutral text-xs" x-text="contentBlocks.length > 0 ? contentBlocks.length + ' blok' : 'Kosong'"></span>
        </summary>
        <div class="mt-4 border-t border-outline pt-4">
            @include('admin.questions._content-block-editor', [
                'label' => 'Konten tambahan (opsional)',
                'blocksExpression' => 'contentBlocks',
                'nameExpression' => "'content_blocks[' + blockIndex + ']'",
            ])
            @error('content_blocks') <p class="field-error">{{ $message }}</p> @enderror
            @error('content_blocks.*') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </details>

    <details class="rounded-2xl border border-outline bg-surface p-4" @if ($bukaPembahasan) open @endif>
        <summary class="flex min-h-11 cursor-pointer items-center justify-between gap-3 font-bold text-ink">
            <span>Pembahasan soal (opsional)</span>
            <span class="text-xs font-semibold text-ink-muted">Ditampilkan bila sesi membuka pembahasan</span>
        </summary>
        <div class="mt-4 border-t border-outline pt-4">
            <label for="pembahasan" class="label">Penjelasan jawaban</label>
            <textarea id="pembahasan" name="pembahasan" rows="3" class="input" @error('pembahasan') aria-invalid="true" @enderror>{{ old('pembahasan', $question?->pembahasan) }}</textarea>
            @error('pembahasan') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </details>

    <details x-show="tipe === 'essay'" x-cloak class="rounded-2xl border border-outline bg-surface p-4" @if ($bukaLanjutanEssay) open @endif>
        <summary class="flex min-h-11 cursor-pointer items-center justify-between gap-3 font-bold text-ink">
            <span>Pengaturan lanjutan essay: format jawaban, sub-soal, dan rubrik (opsional)</span>
            <span class="pill pill-neutral text-xs" x-text="(subSoal.length + rubrik.length) > 0 ? (subSoal.length + ' sub, ' + rubrik.length + ' rubrik') : 'Opsional'"></span>
        </summary>

        <div class="mt-4 min-w-0 space-y-5 border-t border-outline pt-4">
            <details class="rounded-xl border border-outline bg-page p-3">
                <summary class="min-h-11 cursor-pointer font-bold text-ink flex items-center">Pola soal subnetting</summary>
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
                <label for="petunjuk" class="label">Poin yang harus dijawab (opsional)</label>
                <textarea id="petunjuk" name="petunjuk" rows="3" class="input" maxlength="5000" x-model="petunjuk" x-bind:disabled="tipe !== 'essay'" @error('petunjuk') aria-invalid="true" @enderror></textarea>
                <p class="help mt-2">Satu poin per baris. Tampil sebagai daftar periksa untuk peserta.</p>
                @error('petunjuk') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <fieldset class="border-t border-outline pt-5" aria-labelledby="sub-soal-label">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p id="sub-soal-label" class="label">Sub-soal (opsional)</p>
                        <p class="help">Bagian dapat memiliki turunan. Peserta mendapat satu kotak jawaban untuk setiap bagian terakhir.</p>
                    </div>
                    <button type="button" class="btn btn-secondary min-h-11" x-on:click="tambahSubSoal()" x-bind:disabled="tipe !== 'essay' || subSoal.length >= 50 || anak(null).length >= 10">
                        <x-icon name="plus" :size="18" />
                        <span>Tambah sub-soal</span>
                    </button>
                </div>

                <div class="mt-3 min-w-0 space-y-3">
                    <template x-for="(bagian, index) in subSoal" :key="bagian.key">
                        <div class="min-w-0 max-w-full space-y-3 rounded-xl border border-outline bg-page p-4">
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
                                        <span>Tambah turunan</span>
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
                    <p x-show="subSoal.length === 0" class="text-sm text-ink-muted">Belum ada sub-soal. Soal essay biasa memakai satu kotak jawaban.</p>
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
                    <button type="button" class="btn btn-secondary min-h-11" x-on:click="tambahKriteria()" x-bind:disabled="rubrik.length >= 10">
                        <x-icon name="plus" :size="18" />
                        <span>Tambah kriteria</span>
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
                            <button type="button" class="btn btn-ghost min-h-11 min-w-11 px-3 text-coral-deep" x-on:click="hapusKriteria(index)" x-bind:aria-label="'Hapus kriteria ' + (index + 1)">
                                <x-icon name="x" :size="18" />
                            </button>
                        </div>
                    </template>
                    <p x-show="rubrik.length === 0" class="py-4 text-sm text-ink-muted">Belum ada kriteria. Pengajar tetap dapat memberi nilai total secara manual.</p>
                </div>
                <p x-show="rubrik.length > 0" class="mt-2 text-sm font-semibold" x-bind:class="rubrik.reduce((total, item) => total + (Number(item.points) || 0), 0) === Number(bobot) ? 'text-leaf-deep' : 'text-coral-deep'">
                    Total kriteria: <span x-text="rubrik.reduce((total, item) => total + (Number(item.points) || 0), 0)"></span> / <span x-text="bobot"></span> poin
                </p>
                @error('rubrik_essay') <p class="field-error">{{ $message }}</p> @enderror
                @error('rubrik_essay.*') <p class="field-error">{{ $message }}</p> @enderror
            </fieldset>
        </div>
    </details>
</div>
