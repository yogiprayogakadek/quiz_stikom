@php
    $ruteSimpan = isset($quiz) ? route('admin.quizzes.questions.store', $quiz) : route('admin.quizzes.index');
    $ruteKembali = isset($quiz) ? route('admin.quizzes.questions.index', $quiz) : route('admin.quizzes.index');
@endphp

<x-layouts.admin :judul="isset($quiz) ? 'Tambah soal kuis: ' . $quiz->judul : 'Tambah soal'" keterangan="Tulis pertanyaan dan tentukan tipe soal. Pilihan ganda dinilai otomatis, essay dinilai manual.">
    <x-slot:aksi>
        <a href="{{ $ruteKembali }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

    <form method="POST" action="{{ $ruteSimpan }}" class="card max-w-3xl">
        @csrf
        @include('admin.questions._form', ['question' => null, 'quiz' => $quiz ?? null])

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Simpan soal</button>
            <a href="{{ $ruteKembali }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.admin>
