<x-layouts.admin judul="Buat sesi" keterangan="Sesi menghubungkan kuis, mode, dan peserta. Status awal selalu draft.">
    <x-slot:aksi>
        <a href="{{ route('admin.sessions.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

    @if ($quizzes->isEmpty())
        <x-empty-state
            judul="Belum ada kuis"
            keterangan="Buat kuis dari bank soal dulu, lalu kembali ke halaman ini untuk membuat sesi."
        >
            <a href="{{ route('admin.quizzes.create') }}" class="btn btn-primary mt-2">Buat kuis</a>
        </x-empty-state>
    @else
        <form method="POST" action="{{ route('admin.sessions.store') }}" class="card max-w-3xl">
            @csrf
            @include('admin.sessions._form', ['session' => null])

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary">Simpan sesi</button>
                <a href="{{ route('admin.sessions.index') }}" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    @endif
</x-layouts.admin>
