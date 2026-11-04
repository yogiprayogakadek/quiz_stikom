@php
    $terpilihAwal = old('question_ids', $quiz?->questions->pluck('id')->map(fn ($id) => (string) $id)->all() ?? []);
@endphp

<div
    x-data="{
        terpilih: {{ Illuminate\Support\Js::from(array_map('strval', $terpilihAwal)) }},
        pilihSemua() {
            this.terpilih = {{ Illuminate\Support\Js::from($questions->pluck('id')->map(fn ($id) => (string) $id)->all()) }};
        },
        kosongkan() {
            this.terpilih = [];
        }
    }"
    class="space-y-6"
>
    <div>
        <label for="judul" class="label">Judul kuis</label>
        <input id="judul" name="judul" type="text" value="{{ old('judul', $quiz?->judul) }}" class="input" required @error('judul') aria-invalid="true" @enderror>
        @error('judul') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="jumlah_soal" class="label">Jumlah soal dipakai (opsional)</label>
        <input id="jumlah_soal" name="jumlah_soal" type="number" min="1" value="{{ old('jumlah_soal', $quiz?->jumlah_soal) }}" class="input" @error('jumlah_soal') aria-invalid="true" @enderror>
        <p class="help mt-2">Kosongkan untuk memakai semua soal di pool. Saat sesi dimulai, server memilih secara acak sebanyak angka ini.</p>
        @error('jumlah_soal') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <fieldset>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <legend class="label">Pool soal</legend>
                <p class="help">Pilih soal yang boleh dipakai kuis ini. Minimal satu.</p>
            </div>
            <div class="flex gap-2">
                <button type="button" class="btn btn-ghost" x-on:click="pilihSemua()">Pilih semua</button>
                <button type="button" class="btn btn-ghost" x-on:click="kosongkan()">Kosongkan</button>
            </div>
        </div>

        @if ($questions->isEmpty())
            <x-empty-state
                class="mt-4"
                judul="Belum ada soal"
                keterangan="Tulis soal di bank soal dulu, lalu kembali ke halaman ini."
            >
                <a href="{{ route('admin.questions.create') }}" class="btn btn-primary mt-2">Tambah soal</a>
            </x-empty-state>
        @else
            <div class="mt-4 max-h-[28rem] space-y-2 overflow-y-auto rounded-2xl border border-outline bg-page p-3">
                @foreach ($questions as $soal)
                    @php $idSoal = (string) $soal->id; @endphp
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-transparent p-3 hover:bg-surface has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                        <input
                            type="checkbox"
                            name="question_ids[]"
                            value="{{ $soal->id }}"
                            x-model="terpilih"
                            class="mt-1 h-5 w-5 flex-none rounded border-outline-strong text-brand"
                        >
                        <span class="min-w-0">
                            <span class="block text-base font-bold text-ink">{{ \Illuminate\Support\Str::limit($soal->teks, 160) }}</span>
                            <span class="mt-1 flex flex-wrap gap-2 text-sm text-ink-muted">
                                <span class="pill pill-neutral">{{ $soal->options_count }} opsi</span>
                                <span class="pill pill-brand">{{ $soal->durasi_detik }} detik</span>
                                @if ($soal->isLocked())
                                    <span class="pill pill-sun">Terkunci di sesi</span>
                                @endif
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        @endif

        @error('question_ids') <p class="field-error">{{ $message }}</p> @enderror
    </fieldset>
</div>
