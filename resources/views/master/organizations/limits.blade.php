<x-layouts.admin :judul="'Kuota '.$organization->name" keterangan="Batas pemakaian workspace. Kosong memakai nilai platform, nol memblokir fitur.">
    <x-slot:aksi>
        <a href="{{ route('master.organizations.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Daftar organisasi
        </a>
    </x-slot:aksi>

    <div class="max-w-4xl space-y-6">
        <section class="card" aria-labelledby="pemakaian-kuota">
            <h2 id="pemakaian-kuota" class="text-xl">Pemakaian saat ini</h2>
            <p class="mt-1 text-sm text-ink-muted">Nilai batas di bawah sudah memakai default platform jika kolom organisasi kosong.</p>

            <dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-outline p-4">
                    <dt class="text-sm text-ink-muted">Admin</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold">{{ $summary['admin_used'] }} <span class="text-base font-medium text-ink-muted">/ {{ $summary['admin_limit'] }}</span></dd>
                </div>
                <div class="rounded-xl border border-outline p-4">
                    <dt class="text-sm text-ink-muted">Peserta</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold">{{ $summary['participant_used'] }} <span class="text-base font-medium text-ink-muted">/ {{ $summary['participant_limit'] }}</span></dd>
                </div>
                <div class="rounded-xl border border-outline p-4">
                    <dt class="text-sm text-ink-muted">Kelas</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold">{{ $summary['class_used'] }} <span class="text-base font-medium text-ink-muted">/ {{ $summary['class_limit'] }}</span></dd>
                </div>
                <div class="rounded-xl border border-outline p-4">
                    <dt class="text-sm text-ink-muted">AI bulan ini</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold">{{ number_format($summary['ai_used'], 0, ',', '.') }} <span class="text-base font-medium text-ink-muted">/ {{ number_format($summary['ai_limit'], 0, ',', '.') }}</span></dd>
                </div>
                <div class="rounded-xl border border-outline p-4">
                    <dt class="text-sm text-ink-muted">Penyimpanan</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold">{{ number_format($summary['storage_used'] / 1024 / 1024, 1, ',', '.') }} <span class="text-base font-medium text-ink-muted">/ {{ number_format($summary['storage_limit'] / 1024 / 1024, 0, ',', '.') }} MB</span></dd>
                </div>
            </dl>
        </section>

        <form method="POST" action="{{ route('master.organizations.limits.update', $organization) }}" class="card space-y-5" data-progress-message="Menyimpan kuota workspace...">
            @csrf
            @method('PUT')

            <div>
                <h2 class="text-xl">Batas workspace</h2>
                <p class="mt-1 text-sm text-ink-muted">Isi kosong untuk memakai default platform. Isi 0 untuk memblokir fitur.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="admin_limit" class="label">Batas admin</label>
                    <input id="admin_limit" name="admin_limit" type="number" min="0" max="65535" class="input" value="{{ old('admin_limit', $organization->admin_limit) }}" placeholder="{{ config('platform.admin_limit') }}" @error('admin_limit') aria-invalid="true" aria-describedby="admin_limit_error" @enderror>
                    <p class="help mt-1">Default platform: {{ config('platform.admin_limit') }}. Termasuk owner.</p>
                    @error('admin_limit') <p id="admin_limit_error" class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="participant_limit" class="label">Batas peserta</label>
                    <input id="participant_limit" name="participant_limit" type="number" min="0" max="1000000" class="input" value="{{ old('participant_limit', $organization->participant_limit) }}" placeholder="{{ config('platform.participant_limit') }}" @error('participant_limit') aria-invalid="true" aria-describedby="participant_limit_error" @enderror>
                    <p class="help mt-1">Default platform: {{ number_format(config('platform.participant_limit'), 0, ',', '.') }}.</p>
                    @error('participant_limit') <p id="participant_limit_error" class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="class_limit" class="label">Batas kelas</label>
                    <input id="class_limit" name="class_limit" type="number" min="0" max="65535" class="input" value="{{ old('class_limit', $organization->class_limit) }}" placeholder="{{ config('platform.class_limit') }}" @error('class_limit') aria-invalid="true" aria-describedby="class_limit_error" @enderror>
                    <p class="help mt-1">Default platform: {{ config('platform.class_limit') }}.</p>
                    @error('class_limit') <p id="class_limit_error" class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="ai_monthly_requests" class="label">Batas AI per bulan</label>
                    <input id="ai_monthly_requests" name="ai_monthly_requests" type="number" min="0" max="100000000" class="input" value="{{ old('ai_monthly_requests', $organization->ai_monthly_requests) }}" placeholder="{{ config('platform.ai_monthly_requests') }}" @error('ai_monthly_requests') aria-invalid="true" aria-describedby="ai_monthly_requests_error" @enderror>
                    <p class="help mt-1">Default platform: {{ number_format(config('platform.ai_monthly_requests'), 0, ',', '.') }} request.</p>
                    @error('ai_monthly_requests') <p id="ai_monthly_requests_error" class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="storage_mb" class="label">Batas penyimpanan (MB)</label>
                    <input id="storage_mb" name="storage_mb" type="number" min="0" max="1048576" class="input" value="{{ old('storage_mb', $organization->storage_mb) }}" placeholder="{{ config('platform.storage_mb') }}" @error('storage_mb') aria-invalid="true" aria-describedby="storage_mb_error" @enderror>
                    <p class="help mt-1">Default platform: {{ number_format(config('platform.storage_mb'), 0, ',', '.') }} MB.</p>
                    @error('storage_mb') <p id="storage_mb_error" class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end border-t border-outline pt-5">
                <button type="submit" class="btn btn-primary">
                    <x-icon name="check" :size="18" />
                    Simpan kuota
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
