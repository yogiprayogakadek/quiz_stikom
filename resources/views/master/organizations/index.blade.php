<x-layouts.admin judul="Organisasi" keterangan="Buat workspace baru dan serahkan pengelolaannya kepada owner pertama.">
    <section @class([
        'mb-6 rounded-2xl border p-5 sm:p-6',
        'border-sun-deep/30 bg-sun-soft' => $platformSettings->maintenance_enabled,
        'border-outline bg-page' => ! $platformSettings->maintenance_enabled,
    ]) aria-labelledby="system-status-heading">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-2xl">
                <div class="flex items-center gap-2">
                    <span @class([
                        'h-2.5 w-2.5 rounded-full',
                        'bg-sun-deep' => $platformSettings->maintenance_enabled,
                        'bg-leaf' => ! $platformSettings->maintenance_enabled,
                    ])></span>
                    <h2 id="system-status-heading" class="text-xl">
                        {{ $platformSettings->maintenance_enabled ? 'Maintenance aktif' : 'Sistem tersedia' }}
                    </h2>
                </div>
                <p class="mt-2 text-sm leading-6 text-ink-muted">
                    {{ $platformSettings->maintenance_enabled
                        ? 'Peserta dan admin organisasi sedang melihat halaman maintenance. Master tetap dapat masuk untuk membuka sistem kembali.'
                        : 'Peserta dan admin organisasi dapat mengakses layanan seperti biasa.' }}
                </p>
                @if ($platformSettings->maintenance_started_at)
                    <p class="mt-2 text-xs font-semibold text-ink-subtle">
                        Dimulai {{ $platformSettings->maintenance_started_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                    </p>
                @endif
            </div>

            <form method="POST" action="{{ route('master.maintenance.update') }}" class="w-full max-w-xl space-y-3" data-progress-message="Memperbarui status sistem...">
                @csrf
                @method('PUT')
                <input type="hidden" name="maintenance_enabled" value="{{ $platformSettings->maintenance_enabled ? 0 : 1 }}">
                <div>
                    <label for="maintenance_message" class="label">Pesan untuk pengguna</label>
                    <textarea id="maintenance_message" name="maintenance_message" class="input min-h-24 resize-y" maxlength="500">{{ old('maintenance_message', $platformSettings->maintenance_message) }}</textarea>
                    <p class="help mt-1">Pesan ini tampil selama layanan ditutup.</p>
                </div>
                <button type="submit" class="btn {{ $platformSettings->maintenance_enabled ? 'btn-primary' : 'btn-soft-danger' }} w-full sm:w-auto">
                    {{ $platformSettings->maintenance_enabled ? 'Buka kembali sistem' : 'Aktifkan maintenance' }}
                </button>
            </form>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <section class="card min-w-0" aria-labelledby="organization-list-heading">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-outline pb-4">
                <div>
                    <h2 id="organization-list-heading" class="text-2xl">Workspace aktif</h2>
                    <p class="mt-1 text-sm text-ink-muted">Setiap workspace memiliki admin, peserta, kelas, dan pengaturan sendiri.</p>
                </div>
                <span class="text-sm font-bold text-ink-muted">{{ $organizations->count() }} organisasi</span>
            </div>

            @if ($organizations->isEmpty())
                <div class="py-12 text-center">
                    <p class="font-bold text-ink">Belum ada organisasi.</p>
                    <p class="mt-1 text-sm text-ink-muted">Isi formulir untuk membuat workspace pertama.</p>
                </div>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[42rem] text-left text-sm">
                        <thead class="text-xs text-ink-subtle">
                            <tr>
                                <th class="px-3 py-2 font-bold">Organisasi</th>
                                <th class="px-3 py-2 font-bold">Admin</th>
                                <th class="px-3 py-2 font-bold">Peserta</th>
                                <th class="px-3 py-2 font-bold">Kelas</th>
                                <th class="px-3 py-2 font-bold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline">
                            @foreach ($organizations as $organization)
                                <tr>
                                    <td class="px-3 py-3">
                                        <p class="font-bold text-ink">{{ $organization->name }}</p>
                                        <a href="{{ route('organizations.login', $organization) }}" class="text-xs font-semibold text-brand-deep hover:underline" target="_blank" rel="noopener">
                                            /o/{{ $organization->slug }}/login
                                        </a>
                                    </td>
                                    <td class="px-3 py-3 text-ink-muted">{{ $organization->admin_count }}</td>
                                    <td class="px-3 py-3 text-ink-muted">{{ $organization->participant_count }}</td>
                                    <td class="px-3 py-3 text-ink-muted">{{ $organization->kelas_count }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="font-semibold {{ $organization->is_active ? 'text-leaf-deep' : 'text-coral-deep' }}">
                                                {{ $organization->is_active ? 'Aktif' : 'Ditangguhkan' }}
                                            </span>
                                            <form method="POST" action="{{ route('master.organizations.status.update', $organization) }}" onsubmit="return confirm('{{ $organization->is_active ? 'Tangguhkan organisasi ini? Semua admin dan peserta akan kehilangan akses.' : 'Aktifkan kembali organisasi ini?' }}')" data-progress-message="Memperbarui organisasi...">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="is_active" value="{{ $organization->is_active ? 0 : 1 }}">
                                                <button type="submit" class="btn {{ $organization->is_active ? 'btn-soft-danger' : 'btn-secondary' }} !px-3 !py-1.5 text-xs">
                                                    {{ $organization->is_active ? 'Tangguhkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card h-fit" aria-labelledby="create-organization-heading">
            <h2 id="create-organization-heading" class="text-xl">Organisasi baru</h2>
            <p class="mt-1 text-sm text-ink-muted">Owner wajib mengganti password sementara saat login pertama.</p>
            <form method="POST" action="{{ route('master.organizations.store') }}" class="mt-5 space-y-4" data-progress-message="Membuat organisasi...">
                @csrf
                <div>
                    <label for="name" class="label">Nama organisasi</label>
                    <input id="name" name="name" class="input" required maxlength="120" value="{{ old('name') }}">
                </div>
                <div>
                    <label for="slug" class="label">Slug workspace</label>
                    <input id="slug" name="slug" class="input" required maxlength="80" pattern="[A-Za-z0-9_-]+" value="{{ old('slug') }}" placeholder="sekolah-harapan">
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                    <div>
                        <label for="type" class="label">Jenis</label>
                        <select id="type" name="type" class="input" required>
                            <option value="education">Pendidikan</option>
                            <option value="company">Perusahaan</option>
                            <option value="community">Komunitas</option>
                            <option value="personal">Pribadi</option>
                        </select>
                    </div>
                    <div>
                        <label for="timezone" class="label">Zona waktu</label>
                        <input id="timezone" name="timezone" class="input" required value="{{ old('timezone', 'Asia/Singapore') }}">
                    </div>
                </div>
                <div class="border-t border-outline pt-4">
                    <p class="text-sm font-bold text-ink">Owner pertama</p>
                </div>
                <div>
                    <label for="owner_name" class="label">Nama owner</label>
                    <input id="owner_name" name="owner_name" class="input" required maxlength="120" value="{{ old('owner_name') }}">
                </div>
                <div>
                    <label for="owner_username" class="label">Username owner</label>
                    <input id="owner_username" name="owner_username" class="input" required maxlength="60" value="{{ old('owner_username') }}">
                </div>
                <div>
                    <label for="owner_email" class="label">Email owner</label>
                    <input id="owner_email" name="owner_email" type="email" class="input" required maxlength="255" autocomplete="email" placeholder="nama@contoh.com" value="{{ old('owner_email') }}">
                    <p class="help mt-1">Dipakai untuk verifikasi akun dan reset password.</p>
                    @error('owner_email') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="owner_password" class="label">Password sementara <span class="font-normal text-ink-subtle">(opsional)</span></label>
                    <input id="owner_password" name="owner_password" type="password" class="input" minlength="8" autocomplete="new-password">
                    <p class="help mt-1">Kosongkan agar sistem membuat password acak.</p>
                </div>
                <button type="submit" class="btn btn-primary w-full">Buat organisasi</button>
            </form>
        </section>
    </div>
</x-layouts.admin>
