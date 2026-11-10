<x-layouts.admin judul="Tim admin" :keterangan="'Kelola admin untuk workspace '.$organization->name.'.'">
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="card" aria-labelledby="admin-list-heading">
            <div class="border-b border-outline pb-4">
                <h2 id="admin-list-heading" class="text-2xl">Admin workspace</h2>
                <p class="mt-1 text-sm text-ink-muted">Owner dapat menambah admin. Semua admin di daftar ini mengelola data workspace yang sama.</p>
            </div>
            <div class="mt-4 divide-y divide-outline">
                @foreach ($admins as $admin)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-4">
                        <div class="min-w-0">
                            <p class="truncate font-bold text-ink">{{ $admin->nama }}</p>
                            <p class="text-sm text-ink-muted">{{ $admin->username }} · {{ $admin->pivot->role === 'owner' ? 'Owner' : 'Admin' }}</p>
                        </div>
                        @if ($admin->pivot->role === 'admin')
                            <form method="POST" action="{{ route('admin.team.destroy', $admin) }}" onsubmit="return confirm('Cabut akses admin ini dari workspace?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-secondary text-coral-deep">Cabut akses</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card h-fit" aria-labelledby="add-admin-heading">
            <h2 id="add-admin-heading" class="text-xl">Tambah admin</h2>
            <p class="mt-1 text-sm text-ink-muted">Password sementara hanya ditampilkan sekali.</p>
            <form method="POST" action="{{ route('admin.team.store') }}" class="mt-5 space-y-4" data-progress-message="Menambahkan admin...">
                @csrf
                <div>
                    <label for="nama" class="label">Nama</label>
                    <input id="nama" name="nama" class="input" required maxlength="120" value="{{ old('nama') }}">
                </div>
                <div>
                    <label for="username" class="label">Username</label>
                    <input id="username" name="username" class="input" required maxlength="60" value="{{ old('username') }}">
                </div>
                <div>
                    <label for="password" class="label">Password sementara <span class="font-normal text-ink-subtle">(opsional)</span></label>
                    <input id="password" name="password" type="password" class="input" minlength="8" autocomplete="new-password">
                    <p class="help mt-1">Kosongkan agar sistem membuat password acak.</p>
                </div>
                <button type="submit" class="btn btn-primary w-full">Tambah admin</button>
            </form>
        </section>
    </div>
</x-layouts.admin>
