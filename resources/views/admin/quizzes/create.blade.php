<x-layouts.admin judul="Buat kuis" keterangan="Pilih soal yang masuk ke pool kuis ini.">
    <x-slot:aksi>
        <a href="{{ route('admin.quizzes.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

    <form method="POST" action="{{ route('admin.quizzes.store') }}" class="card max-w-3xl">
        @csrf
        @include('admin.quizzes._form', ['quiz' => null])

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Simpan kuis</button>
            <a href="{{ route('admin.quizzes.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.admin>
