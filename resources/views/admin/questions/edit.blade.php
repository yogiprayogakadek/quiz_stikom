@php
    $kuis = $quiz ?? $question->quiz;
    $ruteUpdate = $kuis ? route('admin.quizzes.questions.update', [$kuis, $question]) : route('admin.quizzes.index');
    $ruteKembali = $kuis ? route('admin.quizzes.questions.index', $kuis) : route('admin.quizzes.index');
@endphp

<x-layouts.admin judul="Edit soal" keterangan="Perubahan hanya berlaku untuk sesi yang belum dibuka.">
    <x-slot:aksi>
        <a href="{{ $ruteKembali }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

    <form method="POST" action="{{ $ruteUpdate }}" class="card max-w-3xl">
        @csrf
        @method('PUT')
        @include('admin.questions._form', ['question' => $question, 'quiz' => $kuis])

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Simpan perubahan</button>
            <a href="{{ $ruteKembali }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.admin>
