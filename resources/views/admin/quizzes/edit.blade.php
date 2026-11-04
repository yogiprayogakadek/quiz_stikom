<x-layouts.admin :judul="'Edit '.$quiz->judul" keterangan="Perubahan pool berlaku untuk sesi baru.">
    <x-slot:aksi>
        <a href="{{ route('admin.quizzes.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

    <form method="POST" action="{{ route('admin.quizzes.update', $quiz) }}" class="card max-w-3xl">
        @csrf
        @method('PUT')
        @include('admin.quizzes._form', ['quiz' => $quiz])

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Simpan perubahan</button>
            <a href="{{ route('admin.quizzes.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.admin>
