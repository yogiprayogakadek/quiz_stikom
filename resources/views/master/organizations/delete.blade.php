<x-layouts.admin :judul="'Hapus '.$organization->name" keterangan="Tindakan permanen. Export data sebelum melanjutkan.">
    <x-slot:aksi>
        <a href="{{ route('master.organizations.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Daftar organisasi
        </a>
    </x-slot:aksi>

    <div class="max-w-3xl space-y-6">
        <section class="rounded-2xl border border-coral-deep/30 bg-coral-soft p-5 sm:p-6" aria-labelledby="hapus-heading">
            <h2 id="hapus-heading" class="text-xl text-coral-deep">Organisasi akan dihapus permanen</h2>
            <p class="mt-2 text-base leading-relaxed text-ink-muted">
                Workspace <span class="font-bold text-ink">{{ $organization->name }}</span> ({{ $organization->slug }}) beserta data tenant di bawah ini akan dihapus. Tindakan ini tidak bisa dibatalkan.
            </p>

            <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-coral-deep/20 bg-page p-4">
                    <dt class="text-sm text-ink-muted">Kelas</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold">{{ $counts['classes'] }}</dd>
                </div>
                <div class="rounded-xl border border-coral-deep/20 bg-page p-4">
                    <dt class="text-sm text-ink-muted">Kuis</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold">{{ $counts['quizzes'] }}</dd>
                </div>
                <div class="rounded-xl border border-coral-deep/20 bg-page p-4">
                    <dt class="text-sm text-ink-muted">Sesi</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold">{{ $counts['sessions'] }}</dd>
                </div>
                <div class="rounded-xl border border-coral-deep/20 bg-page p-4">
                    <dt class="text-sm text-ink-muted">Attempt</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold">{{ $counts['attempts'] }}</dd>
                </div>
                <div class="rounded-xl border border-coral-deep/20 bg-page p-4 sm:col-span-2">
                    <dt class="text-sm text-ink-muted">Anggota workspace</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold">{{ $counts['members'] }}</dd>
                </div>
            </dl>
        </section>

        <section class="card" aria-labelledby="tetap-heading">
            <h2 id="tetap-heading" class="text-xl">Yang tetap ada</h2>
            <ul class="mt-3 space-y-2 text-base leading-relaxed text-ink-muted">
                <li>Akun pengguna tidak dihapus. Mereka bisa tetap masuk ke workspace lain atau dibuatkan workspace baru.</li>
                <li>Audit log platform untuk tindakan hapus ini disimpan tanpa organization_id.</li>
                <li>File logo dan cover workspace dihapus dari penyimpanan publik.</li>
            </ul>
        </section>

        <section class="card">
            <h2 class="text-xl">Konfirmasi</h2>
            <p class="mt-1 text-sm text-ink-muted">
                Ketik <span class="font-mono font-bold text-ink">{{ $organization->slug }}</span> untuk mengaktifkan tombol hapus.
            </p>

            <form method="POST" action="{{ route('master.organizations.destroy', $organization) }}" class="mt-4 space-y-4" data-progress-message="Menghapus organisasi...">
                @csrf
                @method('DELETE')

                <div>
                    <label for="konfirmasi" class="label">Slug organisasi</label>
                    <input id="konfirmasi" name="konfirmasi" class="input" required autocomplete="off" placeholder="{{ $organization->slug }}" @error('konfirmasi') aria-invalid="true" aria-describedby="konfirmasi_error" @enderror>
                    @error('konfirmasi') <p id="konfirmasi_error" class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-3 border-t border-outline pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('master.organizations.export', $organization) }}" class="btn btn-secondary">
                        <x-icon name="download" :size="18" />
                        Export JSON dulu
                    </a>
                    <button type="submit" class="btn btn-soft-danger">
                        <x-icon name="x" :size="18" />
                        Hapus organisasi
                    </button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.admin>
