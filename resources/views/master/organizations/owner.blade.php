<x-layouts.admin judul="Pindahkan owner" keterangan="Alihkan kepemilikan workspace ke akun admin lain.">
    <div class="mx-auto max-w-3xl">
        <section class="card" aria-labelledby="transfer-owner-heading">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-outline pb-4">
                <div>
                    <h2 id="transfer-owner-heading" class="text-2xl">Pindahkan kepemilikan</h2>
                    <p class="mt-1 text-sm text-ink-muted">{{ $organization->name }} <span class="text-ink-subtle">/o/{{ $organization->slug }}</span></p>
                </div>
                <a href="{{ route('master.organizations.index') }}" class="btn btn-ghost">Kembali</a>
            </div>

            <div class="mt-5 rounded-xl border border-outline bg-page p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-ink-subtle">Owner saat ini</p>
                @forelse ($currentOwners as $owner)
                    <p class="mt-2 font-bold text-ink">{{ $owner->nama }}</p>
                    <p class="text-xs text-ink-subtle">{{ '@'.$owner->username }}{{ $owner->email ? ' · '.$owner->email : '' }}</p>
                @empty
                    <p class="mt-2 font-semibold text-coral-deep">Workspace ini belum memiliki owner aktif.</p>
                @endforelse
            </div>

            @if ($candidates->isEmpty())
                <div class="mt-5 rounded-xl border border-sun-deep/30 bg-sun-soft p-4 text-sm text-ink">
                    Tidak ada akun admin lain yang bisa dijadikan owner. Buat akun admin terlebih dahulu melalui menu Tim pada workspace tujuan.
                </div>
            @else
                <form method="POST" action="{{ route('master.organizations.owner.update', $organization) }}" class="mt-5 space-y-5" onsubmit="return confirm('Pindahkan kepemilikan workspace ini? Tindakan ini langsung mengubah akses owner.')" data-progress-message="Memindahkan kepemilikan...">
                    @csrf
                    @method('PUT')

                    <div x-data="{ ownerSearch: '' }">
                        <label for="new_owner_search" class="label">Cari owner</label>
                        <input id="new_owner_search" type="search" class="input" x-model="ownerSearch" placeholder="Nama, username, atau email" autocomplete="off">
                        <label for="new_owner_id" class="label mt-3">Owner baru</label>
                        <select id="new_owner_id" name="new_owner_id" class="input" required>
                            <option value="" x-show="ownerSearch === ''">Pilih akun admin</option>
                            @foreach ($candidates as $candidate)
                                <option
                                    value="{{ $candidate->id }}"
                                    data-search="{{ strtolower($candidate->nama.' '.$candidate->username.' '.($candidate->email ?? '')) }}"
                                    x-show="ownerSearch === '' || $el.dataset.search.includes(ownerSearch.toLowerCase())"
                                    @selected((string) old('new_owner_id') === (string) $candidate->id)
                                >
                                    {{ $candidate->nama }} ({{ '@'.$candidate->username }}) — {{ $candidate->owned_organizations_count }} workspace
                                </option>
                            @endforeach
                        </select>
                        <p class="help mt-1">Ketik untuk menyaring akun admin.</p>
                        @error('new_owner_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <fieldset>
                        <legend class="label">Owner lama</legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-start gap-2 rounded-xl border border-outline px-3 py-2 text-sm">
                                <input type="radio" name="previous_owner_action" value="keep_admin" class="mt-0.5" @checked(old('previous_owner_action', 'keep_admin') === 'keep_admin')>
                                <span>
                                    <span class="block font-semibold text-ink">Tetap sebagai admin</span>
                                    <span class="block text-xs text-ink-muted">Akses admin dipertahankan, status owner dicabut.</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-2 rounded-xl border border-outline px-3 py-2 text-sm">
                                <input type="radio" name="previous_owner_action" value="remove" class="mt-0.5" @checked(old('previous_owner_action') === 'remove')>
                                <span>
                                    <span class="block font-semibold text-ink">Keluarkan dari workspace</span>
                                    <span class="block text-xs text-ink-muted">Owner lama kehilangan seluruh akses ke workspace ini.</span>
                                </span>
                            </label>
                        </div>
                        @error('previous_owner_action') <p class="field-error">{{ $message }}</p> @enderror
                    </fieldset>

                    <div class="rounded-xl border border-coral-deep/20 bg-coral-soft/40 p-4 text-sm text-ink-muted">
                        Setelah disimpan, hanya owner baru yang berstatus owner aktif. Tindakan ini dicatat di audit log.
                    </div>

                    <button type="submit" class="btn btn-primary w-full sm:w-auto">Pindahkan kepemilikan</button>
                </form>
            @endif
        </section>
    </div>
</x-layouts.admin>
