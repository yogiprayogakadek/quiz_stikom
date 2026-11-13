<x-layouts.admin judul="Organisasi" keterangan="Pantau workspace, antrean permintaan, dan status layanan platform.">
    <section class="mb-6 rounded-2xl border border-outline bg-page p-5 sm:p-6" aria-labelledby="ringkasan-platform-heading">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0 flex-1">
                <h2 id="ringkasan-platform-heading" class="text-xl">Ringkasan platform</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-ink-muted">
                    Gambaran cepat workspace dan antrean yang perlu ditangani master.
                </p>

                <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-4">
                    <div>
                        <dt class="flex items-center gap-2 text-xs font-bold text-ink-subtle">
                            <x-icon name="briefcase" :size="15" class="text-ink-subtle" />
                            Workspace
                        </dt>
                        <dd class="mt-1.5 font-display text-2xl font-extrabold tabular-nums text-ink">{{ number_format($workspaceSummary['total'], 0, ',', '.') }}</dd>
                        <p class="mt-0.5 text-xs font-semibold text-ink-muted">{{ $workspaceSummary['active'] }} aktif, {{ $workspaceSummary['suspended'] }} ditangguhkan</p>
                    </div>
                    <div>
                        <dt class="flex items-center gap-2 text-xs font-bold text-ink-subtle">
                            <x-icon name="users" :size="15" class="text-ink-subtle" />
                            Peserta
                        </dt>
                        <dd class="mt-1.5 font-display text-2xl font-extrabold tabular-nums text-ink">{{ number_format($workspaceSummary['participants'], 0, ',', '.') }}</dd>
                        <p class="mt-0.5 text-xs text-ink-muted">anggota aktif</p>
                    </div>
                    <div>
                        <dt class="flex items-center gap-2 text-xs font-bold text-ink-subtle">
                            <x-icon name="shield-check" :size="15" class="text-ink-subtle" />
                            Admin
                        </dt>
                        <dd class="mt-1.5 font-display text-2xl font-extrabold tabular-nums text-ink">{{ number_format($workspaceSummary['admins'], 0, ',', '.') }}</dd>
                        <p class="mt-0.5 text-xs text-ink-muted">owner dan admin aktif</p>
                    </div>
                    <div>
                        <dt class="flex items-center gap-2 text-xs font-bold text-ink-subtle">
                            <x-icon name="kelas" :size="15" class="text-ink-subtle" />
                            Kelas
                        </dt>
                        <dd class="mt-1.5 font-display text-2xl font-extrabold tabular-nums text-ink">{{ number_format($workspaceSummary['classes'], 0, ',', '.') }}</dd>
                        <p class="mt-0.5 text-xs text-ink-muted">kelas dibuat</p>
                    </div>
                </dl>
            </div>

            <div class="w-full shrink-0 rounded-xl border border-outline bg-surface p-4 xl:w-80">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-extrabold text-ink">Perlu perhatian</h3>
                    @if ($attentionCount > 0)
                        <span class="rounded-full bg-sun-soft px-2 py-0.5 text-[11px] font-extrabold tabular-nums text-sun-deep">{{ $attentionCount }}</span>
                    @endif
                </div>
                <ul class="mt-3 space-y-2.5 text-sm">
                    @if ($pendingWorkspaceRequestCount > 0)
                        <li class="flex items-start gap-2">
                            <x-icon name="clock" :size="16" class="mt-0.5 shrink-0 text-sun-deep" />
                            <a href="{{ route('master.owners.index') }}" class="font-bold text-ink underline-offset-2 hover:text-brand-deep hover:underline">{{ number_format($pendingWorkspaceRequestCount, 0, ',', '.') }} permintaan workspace menunggu</a>
                        </li>
                    @endif
                    @if ($workspaceSummary['suspended'] > 0)
                        <li class="flex items-start gap-2">
                            <x-icon name="alert" :size="16" class="mt-0.5 shrink-0 text-coral-deep" />
                            <a href="#organization-list-heading" class="font-bold text-ink underline-offset-2 hover:text-brand-deep hover:underline">{{ number_format($workspaceSummary['suspended'], 0, ',', '.') }} workspace ditangguhkan</a>
                        </li>
                    @endif
                    @if ($failedJobCount > 0)
                        <li class="flex items-start gap-2">
                            <x-icon name="server" :size="16" class="mt-0.5 shrink-0 text-coral-deep" />
                            <a href="{{ route('master.system.index') }}" class="font-bold text-ink underline-offset-2 hover:text-brand-deep hover:underline">{{ number_format($failedJobCount, 0, ',', '.') }} job gagal</a>
                        </li>
                    @endif
                    @if ($platformSettings->maintenance_enabled)
                        <li class="flex items-start gap-2">
                            <x-icon name="alert" :size="16" class="mt-0.5 shrink-0 text-sun-deep" />
                            <a href="#system-status-heading" class="font-bold text-ink underline-offset-2 hover:text-brand-deep hover:underline">Maintenance sedang aktif</a>
                        </li>
                    @endif
                    @if ($attentionCount === 0)
                        <li class="flex items-start gap-2 text-ink-muted">
                            <x-icon name="check" :size="16" class="mt-0.5 shrink-0 text-leaf-deep" />
                            <span>Tidak ada antrean yang perlu ditangani.</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <section class="card min-w-0" aria-labelledby="organization-list-heading">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-outline pb-4">
                <div>
                    <h2 id="organization-list-heading" class="text-2xl">Daftar workspace</h2>
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
                <div class="mt-4">
                    <x-data-table :per-page="10" search-placeholder="Cari workspace, owner, atau status">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[42rem] text-left text-sm">
                                <thead class="text-xs text-ink-subtle">
                                    <tr>
                                        <th class="px-3 py-2 font-bold">Organisasi</th>
                                        <th class="px-3 py-2 font-bold">Owner</th>
                                        <th class="px-3 py-2 font-bold">Admin</th>
                                        <th class="px-3 py-2 font-bold">Peserta</th>
                                        <th class="px-3 py-2 font-bold">Kelas</th>
                                        <th class="px-3 py-2 font-bold">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline">
                                    @foreach ($organizations as $organization)
                                        <tr
                                            data-item
                                            data-item-key="{{ $organization->id }}"
                                            data-search="{{ $organization->name }} {{ $organization->slug }} {{ $organization->owners->pluck('nama')->join(' ') }} {{ $organization->is_active ? 'aktif' : 'ditangguhkan' }}"
                                        >
                                    <td class="px-3 py-3">
                                        <p class="font-bold text-ink">{{ $organization->name }}</p>
                                        <a href="{{ route('organizations.login', $organization) }}" class="text-xs font-semibold text-brand-deep hover:underline" target="_blank" rel="noopener">
                                            /o/{{ $organization->slug }}/login
                                        </a>
                                        <div class="mt-1 flex flex-wrap gap-3">
                                            <a href="{{ route('master.organizations.name.edit', $organization) }}" class="text-xs font-semibold text-ink-muted underline-offset-2 hover:text-brand-deep hover:underline">Nama</a>
                                            <a href="{{ route('master.organizations.limits.edit', $organization) }}" class="text-xs font-semibold text-ink-muted underline-offset-2 hover:text-brand-deep hover:underline">Kuota</a>
                                            <a href="{{ route('master.organizations.export', $organization) }}" class="text-xs font-semibold text-ink-muted underline-offset-2 hover:text-brand-deep hover:underline">Export</a>
                                            <a href="{{ route('master.organizations.delete', $organization) }}" class="text-xs font-semibold text-coral-deep underline-offset-2 hover:underline">Hapus</a>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        @forelse ($organization->owners as $owner)
                                            <p class="font-semibold text-ink">{{ $owner->nama }}</p>
                                            <p class="text-xs text-ink-subtle">{{ '@'.$owner->username }}</p>
                                        @empty
                                            <p class="font-semibold text-coral-deep">Belum ada owner</p>
                                        @endforelse
                                        <a href="{{ route('master.organizations.owner.edit', $organization) }}" class="mt-1 inline-block text-xs font-semibold text-ink-muted underline-offset-2 hover:text-brand-deep hover:underline">Pindahkan</a>
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
                    </x-data-table>
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
                <div class="border-t border-outline pt-4" x-data="{ ownerMode: '{{ old('owner_user_id') ? 'existing' : 'new' }}', ownerSearch: '' }">
                    <p class="text-sm font-bold text-ink">Owner pertama</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                        <label class="flex cursor-pointer items-start gap-2 rounded-xl border border-outline px-3 py-2 text-sm">
                            <input type="radio" name="owner_mode" value="new" x-model="ownerMode" class="mt-0.5">
                            <span>
                                <span class="block font-semibold text-ink">Buat owner baru</span>
                                <span class="block text-xs text-ink-muted">Akun admin baru langsung menjadi owner.</span>
                            </span>
                        </label>
                        <label @class([
                            'flex cursor-pointer items-start gap-2 rounded-xl border border-outline px-3 py-2 text-sm',
                            'opacity-50' => $ownerCandidates->isEmpty(),
                        ])>
                            <input type="radio" name="owner_mode" value="existing" x-model="ownerMode" @if ($ownerCandidates->isEmpty()) disabled @endif class="mt-0.5">
                            <span>
                                <span class="block font-semibold text-ink">Pilih owner yang sudah ada</span>
                                <span class="block text-xs text-ink-muted">Gunakan akun admin yang sudah terdaftar.</span>
                            </span>
                        </label>
                    </div>

                    <div x-show="ownerMode === 'existing'" x-cloak class="mt-4">
                        <label for="owner_search" class="label">Cari owner</label>
                        <input id="owner_search" type="search" class="input" x-model="ownerSearch" placeholder="Nama, username, atau email" autocomplete="off">
                        <label for="owner_user_id" class="label mt-3">Owner</label>
                        <select id="owner_user_id" name="owner_user_id" class="input" :disabled="ownerMode !== 'existing'">
                            <option value="" x-show="ownerSearch === ''">Pilih owner</option>
                            @foreach ($ownerCandidates as $candidate)
                                <option
                                    value="{{ $candidate->id }}"
                                    data-search="{{ strtolower($candidate->nama.' '.$candidate->username.' '.($candidate->email ?? '')) }}"
                                    x-show="ownerSearch === '' || $el.dataset.search.includes(ownerSearch.toLowerCase())"
                                    @selected((string) old('owner_user_id') === (string) $candidate->id)
                                >
                                    {{ $candidate->nama }} ({{ '@'.$candidate->username }}) · {{ $candidate->owned_organizations_count }} workspace
                                </option>
                            @endforeach
                        </select>
                        <p class="help mt-1">Owner terpilih langsung menjadi owner workspace ini. Password akun tidak berubah.</p>
                        @error('owner_user_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div x-show="ownerMode === 'new'" x-cloak class="mt-4 space-y-4">
                        <div>
                            <label for="owner_name" class="label">Nama owner</label>
                            <input id="owner_name" name="owner_name" class="input" required maxlength="120" value="{{ old('owner_name') }}" :disabled="ownerMode !== 'new'">
                        </div>
                        <div>
                            <label for="owner_username" class="label">Username owner</label>
                            <input id="owner_username" name="owner_username" class="input" required maxlength="60" value="{{ old('owner_username') }}" :disabled="ownerMode !== 'new'">
                        </div>
                        <div>
                            <label for="owner_email" class="label">Email owner</label>
                            <input id="owner_email" name="owner_email" type="email" class="input" required maxlength="255" autocomplete="email" placeholder="nama@contoh.com" value="{{ old('owner_email') }}" :disabled="ownerMode !== 'new'">
                            <p class="help mt-1">Dipakai untuk verifikasi akun dan reset password.</p>
                            @error('owner_email') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="owner_password" class="label">Password sementara <span class="font-normal text-ink-subtle">(opsional)</span></label>
                            <input id="owner_password" name="owner_password" type="password" class="input" minlength="8" autocomplete="new-password" :disabled="ownerMode !== 'new'">
                            <p class="help mt-1">Kosongkan agar sistem membuat password acak.</p>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-full">Buat organisasi</button>
            </form>
        </section>
    </div>

    <section @class([
        'mt-6 rounded-2xl border p-5 sm:p-6',
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
</x-layouts.admin>
