<x-layouts.admin judul="Edit soal" keterangan="Perubahan hanya berlaku untuk sesi yang belum dibuka.">
    <x-slot:aksi>
        <a href="{{ route('admin.questions.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

    <form method="POST" action="{{ route('admin.questions.update', $question) }}" class="card max-w-3xl">
        @csrf
        @method('PUT')
        @include('admin.questions._form', ['question' => $question])

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Simpan perubahan</button>
            <a href="{{ route('admin.questions.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.admin>
