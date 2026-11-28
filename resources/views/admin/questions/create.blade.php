@php
    $ruteSimpan = isset($quiz) ? route('admin.quizzes.questions.store', $quiz) : route('admin.quizzes.index');
    $ruteKembali = isset($quiz) ? route('admin.quizzes.questions.index', $quiz) : route('admin.quizzes.index');
@endphp

<x-layouts.admin :judul="isset($quiz) ? 'Tambah soal kuis: ' . $quiz->judul : 'Tambah soal'" keterangan="Pilih tipe soal, tulis pertanyaan utama, lalu tentukan kunci jawaban. Bagian lampiran dan rubrik tersedia di menu lipat bila diperlukan.">
    <x-slot:aksi>
        <div class="flex flex-wrap items-center gap-2">
            @if (isset($quiz))
                <a href="{{ route('admin.quizzes.questions.import', $quiz) }}" class="btn btn-secondary min-h-11">
                    <x-icon name="upload" :size="18" />
                    <span>Tempel Cepat / CSV</span>
                </a>
                <a href="{{ route('admin.quizzes.questions.bank', $quiz) }}" class="btn btn-secondary min-h-11">
                    <x-icon name="copy" :size="18" />
                    <span>Ambil dari Kuis Lain</span>
                </a>
            @endif
            <a href="{{ $ruteKembali }}" class="btn btn-ghost min-h-11">
                <x-icon name="arrow-left" :size="20" />
                <span>Kembali</span>
            </a>
        </div>
    </x-slot:aksi>

    <form method="POST" action="{{ $ruteSimpan }}" enctype="multipart/form-data" class="card w-full min-w-0 max-w-3xl">
        @csrf
        @include('admin.questions._form', ['question' => null, 'quiz' => $quiz ?? null])

        <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-outline pt-5">
            <button type="submit" name="after_save" value="index" class="btn btn-primary min-h-11">Simpan soal</button>
            @if (isset($quiz))
                <button type="submit" name="after_save" value="create_another" class="btn btn-secondary min-h-11">
                    Simpan &amp; tambah soal lagi
                </button>
            @endif
            <a href="{{ $ruteKembali }}" class="btn btn-ghost min-h-11">Batal</a>
        </div>
    </form>
</x-layouts.admin>
