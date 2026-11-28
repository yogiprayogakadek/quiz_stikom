@php
    $isPercobaan = (bool) old('is_percobaan', $quiz?->is_percobaan ?? false);
    $alatKuis = old('exam_tools', $quiz?->examTools() ?? []);
@endphp

<div
    class="space-y-6"
    x-data="{ trial: @js($isPercobaan), tools: @js(array_values((array) $alatKuis)) }"
>
    <div>
        <label for="kelas_id" class="label">Kelas</label>
        <select id="kelas_id" name="kelas_id" class="input" required @error('kelas_id') aria-invalid="true" @enderror>
            <option value="">Pilih kelas</option>
            @foreach ($kelasList as $itemKelas)
                <option value="{{ $itemKelas->id }}" @selected((int) old('kelas_id', $quiz?->kelas_id) === $itemKelas->id)>
                    {{ $itemKelas->nama }}
                </option>
            @endforeach
        </select>
        <p class="help mt-1">Kuis akan terhubung ke kelas ini dan hanya bisa diakses peserta yang telah enroll.</p>
        @error('kelas_id') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="judul" class="label">Judul kuis</label>
        <input id="judul" name="judul" type="text" value="{{ old('judul', $quiz?->judul) }}" class="input" placeholder="Contoh: Kuis Pemrograman Dasar Pertemuan 3" required @error('judul') aria-invalid="true" @enderror>
        @error('judul') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <input type="hidden" name="composition_present" value="1">

    <fieldset class="rounded-2xl border border-outline bg-page p-4 space-y-4">
        <legend class="label">Komposisi & urutan soal</legend>
        <p class="help">Atur kuota soal yang ditarik dari bank soal kuis ini saat sesi dimulai. Isi kuota per tipe bila ujian menggabungkan soal objektif dan essay.</p>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="jumlah_soal" class="label">Total soal dipakai (opsional)</label>
                <input id="jumlah_soal" name="jumlah_soal" type="number" min="1" value="{{ old('jumlah_soal', $quiz?->jumlah_soal) }}" class="input" placeholder="Semua" @error('jumlah_soal') aria-invalid="true" @enderror>
                <p class="help mt-1">Dipakai jika kuota per tipe di samping dikosongkan.</p>
                @error('jumlah_soal') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="jumlah_soal_pg" class="label">Kuota soal objektif (opsional)</label>
                <input id="jumlah_soal_pg" name="jumlah_soal_pg" type="number" min="0" max="500" value="{{ old('jumlah_soal_pg', $quiz?->jumlah_soal_pg) }}" class="input" placeholder="Semua objektif" @error('jumlah_soal_pg') aria-invalid="true" @enderror>
                <p class="help mt-1">Jumlah pilihan ganda/isian yang ditarik per peserta.</p>
                @error('jumlah_soal_pg') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="jumlah_soal_essay" class="label">Kuota soal essay (opsional)</label>
                <input id="jumlah_soal_essay" name="jumlah_soal_essay" type="number" min="0" max="500" value="{{ old('jumlah_soal_essay', $quiz?->jumlah_soal_essay) }}" class="input" placeholder="Semua essay" @error('jumlah_soal_essay') aria-invalid="true" @enderror>
                <p class="help mt-1">Jumlah soal essay yang ditarik per peserta.</p>
                @error('jumlah_soal_essay') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        @php
            $acakSoalKuis = (bool) old('acak_soal', $quiz?->acak_soal ?? true);
            $kelompokTipeKuis = (bool) old('kelompokkan_tipe_soal', $quiz?->kelompokkan_tipe_soal ?? false);
        @endphp
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex min-h-14 cursor-pointer items-start gap-3 rounded-xl border border-outline bg-surface p-3.5 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                <input type="checkbox" name="acak_soal" value="1" class="mt-0.5 h-5 w-5 shrink-0" @checked($acakSoalKuis)>
                <span>
                    <span class="block text-sm font-bold text-ink">Acak urutan nomor soal</span>
                    <span class="mt-0.5 block text-xs leading-relaxed text-ink-muted">Matikan bila soal harus tampil berurutan sesuai nomor urutan tetap.</span>
                </span>
            </label>

            <label class="flex min-h-14 cursor-pointer items-start gap-3 rounded-xl border border-outline bg-surface p-3.5 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                <input type="checkbox" name="kelompokkan_tipe_soal" value="1" class="mt-0.5 h-5 w-5 shrink-0" @checked($kelompokTipeKuis)>
                <span>
                    <span class="block text-sm font-bold text-ink">Kelompokkan objektif dulu, essay di akhir</span>
                    <span class="mt-0.5 block text-xs leading-relaxed text-ink-muted">Peserta mengerjakan seluruh soal objektif sebelum masuk ke soal uraian.</span>
                </span>
            </label>
        </div>
    </fieldset>

    <div>
        @php
            $acakOpsiKuis = (bool) old('acak_opsi', $quiz?->acak_opsi ?? true);
        @endphp
        <label class="flex items-start gap-3.5 cursor-pointer rounded-2xl border border-slate-200/90 bg-white p-4 shadow-2xs hover:bg-slate-50 transition">
            <input type="checkbox" name="acak_opsi" value="1" class="mt-1 h-5 w-5 rounded-md border-slate-300 text-blue-600 focus:ring-blue-500" @checked($acakOpsiKuis)>
            <div class="min-w-0">
                <span class="text-sm font-bold text-slate-900 block">Acak Urutan Pilihan Jawaban (A, B, C, D)</span>
                <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">
                    Setiap peserta yang duduk bersebelahan akan menerima letak opsi jawaban dalam urutan berbeda untuk mencegah contekan.
                </p>
            </div>
        </label>
    </div>

    <div>
        @php
            $autoGradeEssay = (bool) old('auto_grade_essay', $quiz?->auto_grade_essay ?? false);
        @endphp
        <label class="flex items-start gap-3.5 cursor-pointer rounded-2xl border border-slate-200/90 bg-white p-4 shadow-2xs hover:bg-slate-50 transition">
            <input type="checkbox" name="auto_grade_essay" value="1" class="mt-1 h-5 w-5 rounded-md border-slate-300 text-blue-600 focus:ring-blue-500" @checked($autoGradeEssay)>
            <div class="min-w-0">
                <span class="text-sm font-bold text-slate-900 block">Nilai essay otomatis dengan AI</span>
                <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">
                    Hanya untuk kuis berisiko rendah di Mode Acak. Nilai AI langsung dipakai dan tetap bisa diubah admin.
                </p>
            </div>
        </label>
    </div>

    <div>
        <label class="flex items-start gap-3.5 cursor-pointer rounded-2xl border border-slate-200/90 bg-white p-4 shadow-2xs hover:bg-slate-50 transition">
            <input type="checkbox" name="is_percobaan" value="1" x-model="trial" x-on:change="if (!trial) tools = tools.filter(tool => tool !== 'subnet_calculator')" class="mt-1 h-5 w-5 rounded-md border-slate-300 text-blue-600 focus:ring-blue-500">
            <div class="min-w-0">
                <span class="text-sm font-bold text-slate-900 block">Kuis percobaan sistem</span>
                <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">
                    Skor kuis ini tetap disimpan dan tampil di laporan, tetapi tidak masuk leaderboard dan tidak menambah total skor peserta.
                </p>
            </div>
        </label>
    </div>

    <fieldset class="rounded-2xl border border-outline bg-page p-4">
        <legend class="label">Alat bantu peserta</legend>
        <p class="help mt-1">Pilihan ini menjadi default untuk sesi baru. Sesi yang sudah dibuat tidak ikut berubah.</p>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <label class="flex min-h-24 items-start gap-3 rounded-xl border border-outline p-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                <input type="checkbox" name="exam_tools[]" value="calculator" x-model="tools" class="mt-0.5 h-5 w-5 shrink-0">
                <span>
                    <span class="block font-bold text-ink">Kalkulator standar</span>
                    <span class="mt-1 block text-sm leading-relaxed text-ink-muted">Operasi dasar, kurung, persen, akar kuadrat, dan pangkat dua. Aman untuk ujian nyata.</span>
                </span>
            </label>

            <label class="flex min-h-24 items-start gap-3 rounded-xl border border-outline p-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft" x-bind:class="!trial && 'cursor-not-allowed opacity-60'">
                <input type="checkbox" name="exam_tools[]" value="subnet_calculator" x-model="tools" x-bind:disabled="!trial" class="mt-0.5 h-5 w-5 shrink-0">
                <span>
                    <span class="block font-bold text-ink">Kalkulator subnet IPv4</span>
                    <span class="mt-1 block text-sm leading-relaxed text-ink-muted" x-text="trial ? 'Menghitung network, broadcast, host, mask, dan ukuran blok.' : 'Hanya dapat dipakai pada kuis simulasi.'"></span>
                </span>
            </label>
        </div>
        @error('exam_tools') <p class="field-error mt-2">{{ $message }}</p> @enderror
        @error('exam_tools.*') <p class="field-error mt-2">{{ $message }}</p> @enderror
    </fieldset>

    <div>
        <div class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-2xs">
            <div class="min-w-0">
                <span class="text-sm font-bold text-slate-900 block">Remidi</span>
                <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">
                    Setelah sesi kuis selesai, admin bisa membuat sesi remidi dari halaman sesi atau laporan. Soal remidi boleh memakai soal lama atau kuis soal baru.
                </p>
            </div>

            <div class="mt-4 max-w-xs">
                <label for="remidi_nilai_min" class="label">Nilai minimum lulus (opsional)</label>
                <input id="remidi_nilai_min" name="remidi_nilai_min" type="number" min="0" max="100" class="input" value="{{ old('remidi_nilai_min', $quiz?->remidi_nilai_min) }}">
                <p class="help mt-1">Peserta di bawah angka ini ditandai sebagai calon remidi. Kosongkan untuk menampilkan semua peserta.</p>
                @error('remidi_nilai_min') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    @if (isset($quiz))
        <div class="rounded-2xl border border-outline bg-surface p-4">
            <p class="text-sm font-bold text-ink">Kelola soal kuis</p>
            <p class="text-sm text-ink-muted mt-1">Kuis ini saat ini memiliki {{ $quiz->questions()->count() }} soal. Anda dapat menambah atau mengedit soal langsung di menu kelola soal.</p>
            <div class="mt-3">
                <a href="{{ route('admin.quizzes.questions.index', $quiz) }}" class="btn btn-secondary">
                    <x-icon name="pencil" :size="18" />
                    Kelola {{ $quiz->questions()->count() }} soal kuis
                </a>
            </div>
        </div>
    @else
        <div class="rounded-2xl border border-outline bg-surface p-4">
            <p class="text-sm text-ink-muted">Setelah menyimpan kuis ini, Anda akan langsung diarahkan untuk membuat soal-soal khusus untuk kuis ini.</p>
        </div>
    @endif
</div>
