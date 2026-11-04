<x-layouts.admin judul="Tambah soal" keterangan="Tulis soal dan tandai satu jawaban benar.">
    <x-slot:aksi>
        <a href="{{ route('admin.questions.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

    <form method="POST" action="{{ route('admin.questions.store') }}" class="card max-w-3xl">
        @csrf
        @include('admin.questions._form', ['question' => null])

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Simpan soal</button>
            <a href="{{ route('admin.questions.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.admin>
