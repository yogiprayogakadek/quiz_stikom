@php
    $opsiAwal = old('opsi', $question?->options->map(fn ($o) => ['teks' => $o->teks])->all() ?? [['teks' => ''], ['teks' => '']]);
    $benarAwal = (int) old('correct', $question?->options->search(fn ($o) => $o->is_correct) ?: 0);
    $tipeAwal = old('tipe', $question?->tipe ?? 'pilihan_ganda');
    $bobotAwal = (int) old('bobot', $question?->bobot ?? ($tipeAwal === 'essay' ? 10 : 1));
@endphp

<div
    x-data="{
        tipe: {{ Illuminate\Support\Js::from($tipeAwal) }},
        opsi: {{ Illuminate\Support\Js::from(array_values($opsiAwal)) }},
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
            <input id="bobot" name="bobot" type="number" min="1" max="1000" value="{{ $bobotAwal }}" class="input" required x-on:change="if (tipe === 'essay' && $event.target.value === '1') { $event.target.value = 10 }" @error('bobot') aria-invalid="true" @enderror>
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

    <div x-show="tipe === 'essay'" x-cloak>
        <label for="kunci_essay" class="label">Kunci / panduan penilaian (opsional)</label>
        <textarea id="kunci_essay" name="kunci_essay" rows="4" class="input" maxlength="5000" x-bind:disabled="tipe !== 'essay'" @error('kunci_essay') aria-invalid="true" @enderror>{{ old('kunci_essay', $question?->kunci_essay) }}</textarea>
        <p class="help mt-2">Dipakai admin saat menilai. Tidak ditampilkan ke peserta kecuali pembahasan sesi diaktifkan.</p>
        @error('kunci_essay') <p class="field-error">{{ $message }}</p> @enderror
    </div>
</div>
