<x-layouts.admin judul="Organisasi" keterangan="Buat workspace baru dan serahkan pengelolaannya kepada owner pertama.">
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
                                        <span class="font-semibold {{ $organization->is_active ? 'text-leaf-deep' : 'text-coral-deep' }}">
                                            {{ $organization->is_active ? 'Aktif' : 'Ditangguhkan' }}
                                        </span>
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
                    <label for="owner_password" class="label">Password sementara <span class="font-normal text-ink-subtle">(opsional)</span></label>
                    <input id="owner_password" name="owner_password" type="password" class="input" minlength="8" autocomplete="new-password">
                    <p class="help mt-1">Kosongkan agar sistem membuat password acak.</p>
                </div>
                <button type="submit" class="btn btn-primary w-full">Buat organisasi</button>
            </form>
        </section>
    </div>
</x-layouts.admin>
