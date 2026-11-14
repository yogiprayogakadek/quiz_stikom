@php
    $opsiAwal = old('opsi', $question?->options->map(fn ($o) => ['teks' => $o->teks])->all() ?? [['teks' => ''], ['teks' => '']]);
    $benarAwal = (int) old('correct', $question?->options->search(fn ($o) => $o->is_correct) ?: 0);
    $tipeAwal = old('tipe', $question?->tipe ?? 'pilihan_ganda');
    $bobotAwal = (int) old('bobot', $question?->bobot ?? ($tipeAwal === 'essay' ? 10 : 1));
    $rubrikAwal = array_values(old('rubrik_essay', $question?->rubrik_essay ?? []));
    $petunjukAwal = old('petunjuk', implode("\n", (array) ($question?->petunjuk_json ?? [])));
    $subSoalAwal = collect(old('sub_soal', $question?->sub_soal_json ?? []))
        ->map(function ($bagian) {
            $bagian = is_array($bagian) ? $bagian : [];
            $petunjuk = $bagian['petunjuk'] ?? [];

            return [
                'label' => $bagian['label'] ?? '',
                'teks' => $bagian['teks'] ?? '',
                'petunjuk' => is_array($petunjuk) ? implode("\n", $petunjuk) : (string) $petunjuk,
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
        tambahSubSoal() {
            if (this.subSoal.length < 10) {
                let kode = 65;
                while (this.subSoal.some((bagian) => bagian.label === String.fromCharCode(kode))) {
                    kode++;
                }
                this.subSoal.push({ label: String.fromCharCode(kode), teks: '', petunjuk: '' });
            }
        },
        hapusSubSoal(index) {
            this.subSoal.splice(index, 1);
        }
    }"
    class="space-y-6"
>
    <div>
        <label for="teks" class="label">Teks soal</label>
        <textarea id="teks" name="teks" rows="4" class="input" required @error('teks') aria-invalid="true" @enderror>{{ old('teks', $question?->teks) }}</textarea>
        @error('teks') <p class="field-error">{{ $message }}</p> @enderror
    </div>

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
            <label for="durasi_detik" class="label">Durasi soal (detik)</label>
            <input id="durasi_detik" name="durasi_detik" type="number" min="5" max="300" value="{{ old('durasi_detik', $question?->durasi_detik ?? 30) }}" class="input" required @error('durasi_detik') aria-invalid="true" @enderror>
            <p class="help mt-2">5 sampai 300 detik. Dipakai sebagai timer di Mode Serempak dan penentu batas total di Mode Acak.</p>
            @error('durasi_detik') <p class="field-error">{{ $message }}</p> @enderror
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

    <div x-show="tipe === 'essay'" x-cloak class="space-y-5">
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
                    <p class="help">Isi kalau soal punya bagian A, B, C. Peserta mendapat satu kotak jawaban per sub-soal.</p>
                </div>
                <button type="button" class="btn btn-secondary" x-on:click="tambahSubSoal()" x-bind:disabled="tipe !== 'essay' || subSoal.length >= 10">
                    <x-icon name="plus" :size="18" />
                    Tambah sub-soal
                </button>
            </div>

            <div class="mt-3 space-y-3">
                <template x-for="(bagian, index) in subSoal" :key="index">
                    <div class="space-y-3 rounded-xl border border-outline p-4">
                        <div class="grid gap-3 sm:grid-cols-[6rem_minmax(0,1fr)_auto] sm:items-start">
                            <div>
                                <label class="label" x-bind:for="'sub-label-' + index">Label</label>
                                <input type="text" x-bind:id="'sub-label-' + index" x-bind:name="'sub_soal[' + index + '][label]'" x-model="bagian.label" class="input" maxlength="10" placeholder="A" x-bind:required="tipe === 'essay'" x-bind:disabled="tipe !== 'essay'">
                            </div>
                            <div>
                                <label class="label" x-bind:for="'sub-teks-' + index">Teks sub-soal</label>
                                <textarea x-bind:id="'sub-teks-' + index" x-bind:name="'sub_soal[' + index + '][teks]'" x-model="bagian.teks" rows="3" class="input" maxlength="2000" x-bind:required="tipe === 'essay'" x-bind:disabled="tipe !== 'essay'"></textarea>
                            </div>
                            <button type="button" class="btn btn-ghost min-h-11 min-w-11 justify-self-start px-3 text-coral-deep" x-on:click="hapusSubSoal(index)" x-bind:disabled="tipe !== 'essay'" x-bind:aria-label="'Hapus sub-soal ' + (index + 1)">
                                <x-icon name="x" :size="18" />
                            </button>
                        </div>
                        <div>
                            <label class="label" x-bind:for="'sub-petunjuk-' + index">Poin yang harus dijawab (opsional)</label>
                            <textarea x-bind:id="'sub-petunjuk-' + index" x-bind:name="'sub_soal[' + index + '][petunjuk]'" x-model="bagian.petunjuk" rows="3" class="input" maxlength="5000" x-bind:disabled="tipe !== 'essay'"></textarea>
                            <p class="help mt-1">Satu poin per baris.</p>
                        </div>
                    </div>
                </template>
                <p x-show="subSoal.length === 0" class="text-sm text-ink-muted">Belum ada sub-soal. Soal essay biasa tetap memakai satu kotak jawaban.</p>
            </div>
            @error('sub_soal') <p class="field-error">{{ $message }}</p> @enderror
            @error('sub_soal.*.label') <p class="field-error">{{ $message }}</p> @enderror
            @error('sub_soal.*.teks') <p class="field-error">{{ $message }}</p> @enderror
            @error('sub_soal.*.petunjuk') <p class="field-error">{{ $message }}</p> @enderror
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
