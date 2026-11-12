<x-layouts.admin judul="Owner" keterangan="Akun owner dan workspace yang mereka kelola.">
    @if ($pendingRequests->isNotEmpty())
        <section class="card mb-6" aria-labelledby="workspace-request-queue-heading">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-outline pb-4">
                <div>
                    <h2 id="workspace-request-queue-heading" class="text-2xl">Permintaan workspace</h2>
                    <p class="mt-1 text-sm text-ink-muted">Permintaan dari owner yang menunggu keputusan master.</p>
                </div>
                <span class="text-sm font-bold text-ink-muted">{{ $pendingRequests->count() }} permintaan</span>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="text-xs text-ink-subtle">
                        <tr>
                            <th class="px-3 py-2 font-bold">Workspace</th>
                            <th class="px-3 py-2 font-bold">Pemohon</th>
                            <th class="px-3 py-2 font-bold">Diajukan</th>
                            <th class="px-3 py-2 font-bold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline">
                        @foreach ($pendingRequests as $pendingRequest)
                            <tr>
                                <td class="px-3 py-3 align-top">
                                    <p class="font-bold text-ink">{{ $pendingRequest->name }}</p>
                                    <p class="text-xs text-ink-subtle">{{ ucfirst($pendingRequest->type) }} · {{ $pendingRequest->timezone }}</p>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <p class="font-semibold text-ink">{{ $pendingRequest->requester?->nama ?? 'Akun tidak tersedia' }}</p>
                                    <p class="text-xs text-ink-subtle">{{ $pendingRequest->requester ? '@'.$pendingRequest->requester->username : '—' }}</p>
                                </td>
                                <td class="px-3 py-3 align-top text-ink-muted">{{ $pendingRequest->created_at->format('d M Y H:i') }}</td>
                                <td class="px-3 py-3 text-right align-top">
                                    <a href="{{ route('master.workspace-requests.approve', $pendingRequest) }}" class="btn btn-secondary !px-3 !py-1.5 text-xs">Tinjau</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="card" aria-labelledby="owner-list-heading">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-outline pb-4">
            <div>
                <h2 id="owner-list-heading" class="text-2xl">Daftar owner</h2>
                <p class="mt-1 text-sm text-ink-muted">Owner adalah akun admin yang dapat mengelola satu atau lebih workspace.</p>
            </div>
            <span class="text-sm font-bold text-ink-muted">{{ $owners->count() }} owner</span>
        </div>

        @if ($owners->isEmpty())
            <div class="py-12 text-center">
                <p class="font-bold text-ink">Belum ada akun admin.</p>
                <p class="mt-1 text-sm text-ink-muted">Buat workspace untuk menambahkan owner pertama.</p>
            </div>
        @else
            <div class="mt-4">
                <x-data-table :per-page="10" search-placeholder="Cari nama, username, email, atau workspace">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[48rem] text-left text-sm">
                            <thead class="text-xs text-ink-subtle">
                                <tr>
                                    <th class="px-3 py-2 font-bold">Owner</th>
                                    <th class="px-3 py-2 font-bold">Workspace</th>
                                    <th class="px-3 py-2 font-bold">Jumlah</th>
                                    <th class="px-3 py-2 text-right font-bold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline">
                                @foreach ($owners as $owner)
                                    @php
                                        $alasanTidakBisaDihapus = $owner->orphanDeletionBlocker();
                                    @endphp
                                    <tr
                                        data-item
                                        data-item-key="{{ $owner->id }}"
                                        data-search="{{ $owner->nama }} {{ $owner->username }} {{ $owner->email }} {{ $owner->ownedOrganizations->pluck('name')->join(' ') }}"
                                    >
                                        <td class="px-3 py-3 align-top">
                                            <p class="font-bold text-ink">{{ $owner->nama }}</p>
                                            <p class="text-xs text-ink-subtle">{{ '@'.$owner->username }}</p>
                                            @if ($owner->email)
                                                <p class="mt-1 text-xs text-ink-muted">{{ $owner->email }}</p>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="flex flex-wrap gap-2">
                                                @forelse ($owner->ownedOrganizations as $organization)
                                                    <div class="rounded-xl border border-outline bg-page px-3 py-2">
                                                        <p class="font-semibold text-ink">{{ $organization->name }}</p>
                                                        <p class="text-xs text-ink-subtle">/o/{{ $organization->slug }}</p>
                                                        <a href="{{ route('master.organizations.owner.edit', $organization) }}" class="mt-1 inline-block text-xs font-semibold text-brand-deep underline-offset-2 hover:underline">Pindahkan</a>
                                                    </div>
                                                @empty
                                                    <span class="text-sm text-ink-subtle">Belum punya workspace</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 align-top text-ink-muted">{{ $owner->owned_organizations_count }}</td>
                                        <td class="px-3 py-3 text-right align-top">
                                            @if ($alasanTidakBisaDihapus === null)
                                                <form
                                                    method="POST"
                                                    action="{{ route('master.owners.destroy', $owner) }}"
                                                    onsubmit="return confirm('Hapus akun owner ini? Tindakan ini tidak bisa dibatalkan.')"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-ghost !px-3 !py-1.5 text-xs text-coral-deep" aria-label="Hapus akun {{ $owner->nama }}">
                                                        Hapus akun
                                                    </button>
                                                </form>
                                            @elseif ($alasanTidakBisaDihapus === 'member')
                                                <span class="text-xs text-ink-subtle">Masih anggota workspace</span>
                                            @elseif ($alasanTidakBisaDihapus === 'pending')
                                                <span class="text-xs text-ink-subtle">Permintaan pending</span>
                                            @endif
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
</x-layouts.admin>
