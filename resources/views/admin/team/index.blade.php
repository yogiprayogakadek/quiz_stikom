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
                    <label for="email" class="label">Email</label>
                    <input id="email" name="email" type="email" class="input" required maxlength="255" autocomplete="email" placeholder="nama@contoh.com" value="{{ old('email') }}">
                    <p class="help mt-1">Dipakai untuk verifikasi akun dan reset password.</p>
                    @error('email') <p class="field-error">{{ $message }}</p> @enderror
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

    <section class="mt-6 card" aria-labelledby="access-audit-heading">
        <div class="border-b border-outline pb-4">
            <h2 id="access-audit-heading" class="text-xl">Riwayat akses</h2>
            <p class="mt-1 text-sm text-ink-muted">Perubahan admin dan peserta terbaru di workspace ini.</p>
        </div>

        @if ($auditLogs->isEmpty())
            <p class="py-6 text-sm text-ink-muted">Belum ada perubahan akses yang tercatat.</p>
        @else
            <ol class="divide-y divide-outline">
                @foreach ($auditLogs as $log)
                    @php
                        $labels = [
                            'admin_added' => 'Admin ditambahkan',
                            'admin_revoked' => 'Akses admin dicabut',
                            'participant_added' => 'Peserta ditambahkan',
                            'participant_removed' => 'Peserta dikeluarkan',
                        ];
                    @endphp
                    <li class="grid gap-1 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:gap-4">
                        <div class="min-w-0">
                            <p class="font-bold text-ink">{{ $labels[$log->action] ?? $log->action }}</p>
                            <p class="mt-1 truncate text-sm text-ink-muted">
                                {{ $log->metadata['name'] ?? $log->metadata['username'] ?? 'Akun' }}
                                @if (! empty($log->metadata['username']))
                                    · {{ $log->metadata['username'] }}
                                @endif
                            </p>
                        </div>
                        <p class="text-xs text-ink-subtle sm:text-right">
                            {{ $log->actor?->nama ?? 'Sistem' }}<br>
                            <time datetime="{{ $log->created_at->timezone($orgTimezone)->toIso8601String() }}">{{ $log->created_at->timezone($orgTimezone)->format('d M Y, H:i') }}</time>
                        </p>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</x-layouts.admin>
