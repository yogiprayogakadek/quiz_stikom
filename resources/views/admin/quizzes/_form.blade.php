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
