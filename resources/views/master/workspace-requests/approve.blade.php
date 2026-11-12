<x-layouts.admin judul="Tinjau permintaan" keterangan="Setujui atau tolak permintaan workspace dari owner.">
    <div class="mx-auto max-w-3xl">
        <section class="card" aria-labelledby="review-request-heading">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-outline pb-4">
                <div>
                    <h2 id="review-request-heading" class="text-2xl">Tinjau permintaan workspace</h2>
                    <p class="mt-1 text-sm text-ink-muted">Permintaan diajukan {{ $workspaceRequest->created_at->format('d M Y H:i') }}.</p>
                </div>
                <a href="{{ route('master.owners.index') }}" class="btn btn-ghost">Kembali</a>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-outline bg-page p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-ink-subtle">Workspace</p>
                    <p class="mt-2 font-bold text-ink">{{ $workspaceRequest->name }}</p>
                    <p class="text-xs text-ink-muted">{{ ucfirst($workspaceRequest->type) }} · {{ $workspaceRequest->timezone }}</p>
                </div>
                <div class="rounded-xl border border-outline bg-page p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-ink-subtle">Pemohon</p>
                    @if ($requester)
                        <p class="mt-2 font-bold text-ink">{{ $requester->nama }}</p>
                        <p class="text-xs text-ink-muted">{{ '@'.$requester->username }}{{ $requester->email ? ' · '.$requester->email : '' }}</p>
                    @else
                        <p class="mt-2 font-semibold text-coral-deep">Akun pemohon tidak aktif.</p>
                    @endif
                </div>
            </div>

            @if ($workspaceRequest->note)
                <div class="mt-3 rounded-xl border border-outline bg-page p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-ink-subtle">Catatan pemohon</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-ink-muted">{{ $workspaceRequest->note }}</p>
                </div>
            @endif

            <div class="mt-6 border-t border-outline pt-5">
                <h3 class="text-lg">Setujui permintaan</h3>
                <p class="mt-1 text-sm text-ink-muted">Workspace dibuat dan pemohon langsung menjadi owner-nya.</p>

                <form method="POST" action="{{ route('master.workspace-requests.approve.update', $workspaceRequest) }}" class="mt-4 space-y-4" data-progress-message="Menyetujui permintaan...">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="slug" class="label">Slug workspace</label>
                        <input id="slug" name="slug" class="input" required maxlength="80" pattern="[A-Za-z0-9_-]+" value="{{ old('slug', $suggestedSlug) }}">
                        <p class="help mt-1">Dipakai pada alamat login workspace. Boleh diubah sebelum disetujui.</p>
                        @error('slug') <p class="field-error">{{ $message }}</p> @enderror
                        @error('status') <p class="field-error">{{ $message }}</p> @enderror
                        @error('requester') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-full sm:w-auto" @disabled(! $requester)>Setujui dan buat workspace</button>
                </form>
            </div>

            <div class="mt-6 border-t border-outline pt-5">
                <h3 class="text-lg">Tolak permintaan</h3>
                <p class="mt-1 text-sm text-ink-muted">Pemohon akan melihat alasan penolakan di halaman Workspace saya.</p>

                <form method="POST" action="{{ route('master.workspace-requests.reject', $workspaceRequest) }}" class="mt-4 space-y-4" onsubmit="return confirm('Tolak permintaan workspace ini?')" data-progress-message="Menolak permintaan...">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="rejection_reason" class="label">Alasan penolakan</label>
                        <textarea id="rejection_reason" name="rejection_reason" class="input min-h-24 resize-y" required maxlength="1000">{{ old('rejection_reason') }}</textarea>
                        @error('rejection_reason') <p class="field-error">{{ $message }}</p> @enderror
                        @error('status') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="btn btn-soft-danger w-full sm:w-auto">Tolak permintaan</button>
                </form>
            </div>
        </section>
    </div>
</x-layouts.admin>
