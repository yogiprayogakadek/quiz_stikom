<div class="space-y-6">
    <div>
        <label for="kelas_id" class="label">Kelas</label>
        <select id="kelas_id" name="kelas_id" class="input" required @error('kelas_id') aria-invalid="true" @enderror>
            <option value="">-- Pilih Kelas --</option>
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

    <div>
        <label for="jumlah_soal" class="label">Jumlah soal dipakai (opsional)</label>
        <input id="jumlah_soal" name="jumlah_soal" type="number" min="1" value="{{ old('jumlah_soal', $quiz?->jumlah_soal) }}" class="input" @error('jumlah_soal') aria-invalid="true" @enderror>
        <p class="help mt-2">Kosongkan jika ingin memakai semua soal kuis ini. Jika diisi (misal: 10), sistem akan memilih secara acak sebanyak angka tersebut saat sesi dibuka.</p>
        @error('jumlah_soal') <p class="field-error">{{ $message }}</p> @enderror
    </div>

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
        @php
            $isPercobaan = (bool) old('is_percobaan', $quiz?->is_percobaan ?? false);
        @endphp
        <label class="flex items-start gap-3.5 cursor-pointer rounded-2xl border border-slate-200/90 bg-white p-4 shadow-2xs hover:bg-slate-50 transition">
            <input type="checkbox" name="is_percobaan" value="1" class="mt-1 h-5 w-5 rounded-md border-slate-300 text-blue-600 focus:ring-blue-500" @checked($isPercobaan)>
            <div class="min-w-0">
                <span class="text-sm font-bold text-slate-900 block">Kuis percobaan sistem</span>
                <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">
                    Skor kuis ini tetap disimpan dan tampil di laporan, tetapi tidak masuk leaderboard dan tidak menambah total skor peserta.
                </p>
            </div>
        </label>
    </div>

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
