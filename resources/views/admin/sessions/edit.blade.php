<x-layouts.admin :judul="'Pengaturan '.$session->nama" :keterangan="$session->isDraft() ? 'Semua pengaturan masih bisa diubah sebelum lobby dibuka.' : 'Kuis dan mode terkunci. Pengaturan lain masih bisa diubah.'">
    <x-slot:aksi>
        <a href="{{ route('admin.sessions.show', $session) }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Ruang kendali
        </a>
    </x-slot:aksi>

    <form method="POST" action="{{ route('admin.sessions.update', $session) }}" class="card max-w-3xl">
        @csrf
        @method('PUT')
        @include('admin.sessions._form', ['session' => $session])

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Simpan perubahan</button>
            <a href="{{ route('admin.sessions.show', $session) }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.admin>
