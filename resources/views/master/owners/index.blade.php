<x-layouts.admin judul="Owner" keterangan="Akun owner dan workspace yang mereka kelola.">
    <section class="card" aria-labelledby="owner-list-heading">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-outline pb-4">
            <div>
                <h2 id="owner-list-heading" class="text-2xl">Daftar owner</h2>
                <p class="mt-1 text-sm text-ink-muted">Owner adalah akun admin yang memiliki setidaknya satu workspace.</p>
            </div>
            <span class="text-sm font-bold text-ink-muted">{{ $owners->total() }} owner</span>
        </div>

        <form method="GET" action="{{ route('master.owners.index') }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
            <label class="sr-only" for="q">Cari owner</label>
            <input id="q" name="q" class="input sm:max-w-md" value="{{ $search }}" placeholder="Cari nama, username, atau email">
            <div class="flex gap-2">
                <button type="submit" class="btn btn-secondary">Cari</button>
                @if ($search !== '')
                    <a href="{{ route('master.owners.index') }}" class="btn btn-ghost">Reset</a>
                @endif
            </div>
        </form>

        @if ($owners->isEmpty())
            <div class="py-12 text-center">
                <p class="font-bold text-ink">Belum ada owner.</p>
                <p class="mt-1 text-sm text-ink-muted">
                    {{ $search !== '' ? 'Tidak ada owner yang cocok dengan pencarian.' : 'Buat workspace untuk menambahkan owner pertama.' }}
                </p>
            </div>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[44rem] text-left text-sm">
                    <thead class="text-xs text-ink-subtle">
                        <tr>
                            <th class="px-3 py-2 font-bold">Owner</th>
                            <th class="px-3 py-2 font-bold">Workspace</th>
                            <th class="px-3 py-2 font-bold">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline">
                        @foreach ($owners as $owner)
                            <tr>
                                <td class="px-3 py-3 align-top">
                                    <p class="font-bold text-ink">{{ $owner->nama }}</p>
                                    <p class="text-xs text-ink-subtle">{{ '@'.$owner->username }}</p>
                                    @if ($owner->email)
                                        <p class="mt-1 text-xs text-ink-muted">{{ $owner->email }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($owner->ownedOrganizations as $organization)
                                            <div class="rounded-xl border border-outline bg-page px-3 py-2">
                                                <p class="font-semibold text-ink">{{ $organization->name }}</p>
                                                <p class="text-xs text-ink-subtle">/o/{{ $organization->slug }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top text-ink-muted">{{ $owner->owned_organizations_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">{{ $owners->links() }}</div>
        @endif
    </section>
</x-layouts.admin>
