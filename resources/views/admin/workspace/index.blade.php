<x-layouts.admin judul="Workspace saya" keterangan="Kelola workspace yang Anda miliki dan ajukan workspace baru.">
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <section class="card min-w-0" aria-labelledby="owned-workspaces-heading">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-outline pb-4">
                <div>
                    <h2 id="owned-workspaces-heading" class="text-2xl">Workspace yang Anda miliki</h2>
                    <p class="mt-1 text-sm text-ink-muted">Pilih workspace untuk membuka dashboard admin-nya.</p>
                </div>
                <span class="text-sm font-bold text-ink-muted">{{ $ownedOrganizations->count() }} workspace</span>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($ownedOrganizations as $organization)
                    <div class="flex flex-col justify-between gap-3 rounded-xl border border-outline bg-page p-4">
                        <div>
                            <p class="font-bold text-ink">{{ $organization->name }}</p>
                            <p class="text-xs text-ink-subtle">/o/{{ $organization->slug }}</p>
                            <p class="mt-2 text-xs text-ink-muted">{{ ucfirst($organization->type) }} · {{ $organization->timezone }}</p>
                        </div>
                        @if ($currentOrganization && $organization->is($currentOrganization))
                            <span class="inline-flex rounded-full bg-leaf-soft px-2.5 py-1 text-xs font-bold text-leaf-deep">Sedang aktif</span>
                        @else
                            <form method="POST" action="{{ route('organizations.switch') }}">
                                @csrf
                                <input type="hidden" name="organization_id" value="{{ $organization->id }}">
                                <button type="submit" class="btn btn-secondary w-full">Buka workspace</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card h-fit" aria-labelledby="request-workspace-heading">
            <h2 id="request-workspace-heading" class="text-xl">Ajukan workspace baru</h2>
            <p class="mt-1 text-sm text-ink-muted">Permintaan akan ditinjau master sebelum workspace dibuat.</p>

            <form method="POST" action="{{ route('admin.workspace.requests.store') }}" class="mt-5 space-y-4" data-progress-message="Mengirim permintaan...">
                @csrf
                <div>
                    <label for="name" class="label">Nama workspace</label>
                    <input id="name" name="name" class="input" required maxlength="120" value="{{ old('name') }}">
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                    <div>
                        <label for="type" class="label">Jenis</label>
                        <select id="type" name="type" class="input" required>
                            @foreach (['education' => 'Pendidikan', 'company' => 'Perusahaan', 'community' => 'Komunitas', 'personal' => 'Pribadi'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $currentOrganization?->type ?? 'education') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="timezone" class="label">Zona waktu</label>
                        <input id="timezone" name="timezone" class="input" required value="{{ old('timezone', $currentOrganization?->timezone ?? 'Asia/Singapore') }}">
                        @error('timezone') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label for="note" class="label">Catatan <span class="font-normal text-ink-subtle">(opsional)</span></label>
                    <textarea id="note" name="note" class="input min-h-24 resize-y" maxlength="1000" placeholder="Ceritakan kebutuhan workspace ini.">{{ old('note') }}</textarea>
                    @error('note') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn-primary w-full">Kirim permintaan</button>
            </form>
        </section>
    </div>

    <section class="card mt-6" aria-labelledby="workspace-request-history-heading">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-outline pb-4">
            <div>
                <h2 id="workspace-request-history-heading" class="text-xl">Riwayat permintaan</h2>
                <p class="mt-1 text-sm text-ink-muted">Status permintaan workspace yang Anda ajukan.</p>
            </div>
        </div>

        @if ($requests->isEmpty())
            <div class="py-10 text-center">
                <p class="font-bold text-ink">Belum ada permintaan.</p>
                <p class="mt-1 text-sm text-ink-muted">Gunakan formulir di atas untuk mengajukan workspace baru.</p>
            </div>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="text-xs text-ink-subtle">
                        <tr>
                            <th class="px-3 py-2 font-bold">Workspace</th>
                            <th class="px-3 py-2 font-bold">Status</th>
                            <th class="px-3 py-2 font-bold">Diajukan</th>
                            <th class="px-3 py-2 font-bold">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline">
                        @foreach ($requests as $workspaceRequest)
                            <tr>
                                <td class="px-3 py-3 align-top">
                                    <p class="font-bold text-ink">{{ $workspaceRequest->name }}</p>
                                    <p class="text-xs text-ink-subtle">{{ ucfirst($workspaceRequest->type) }} · {{ $workspaceRequest->timezone }}</p>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    @switch($workspaceRequest->status)
                                        @case(\App\Models\WorkspaceRequest::STATUS_APPROVED)
                                            <span class="inline-flex rounded-full bg-leaf-soft px-2.5 py-1 text-xs font-bold text-leaf-deep">Disetujui</span>
                                            @break
                                        @case(\App\Models\WorkspaceRequest::STATUS_REJECTED)
                                            <span class="inline-flex rounded-full bg-coral-soft px-2.5 py-1 text-xs font-bold text-coral-deep">Ditolak</span>
                                            @break
                                        @default
                                            <span class="inline-flex rounded-full bg-sun-soft px-2.5 py-1 text-xs font-bold text-sun-deep">Menunggu</span>
                                    @endswitch
                                </td>
                                <td class="px-3 py-3 align-top text-ink-muted">{{ $workspaceRequest->created_at->format('d M Y H:i') }}</td>
                                <td class="px-3 py-3 align-top text-ink-muted">
                                    @if ($workspaceRequest->status === \App\Models\WorkspaceRequest::STATUS_REJECTED && $workspaceRequest->rejection_reason)
                                        <p class="text-coral-deep">{{ $workspaceRequest->rejection_reason }}</p>
                                    @elseif ($workspaceRequest->note)
                                        {{ $workspaceRequest->note }}
                                    @else
                                        <span class="text-ink-subtle">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.admin>
