<x-layouts.admin :judul="'Nama '.$organization->name" keterangan="Ubah nama tampilan workspace. Slug dan tautan login tidak berubah.">
    <x-slot:aksi>
        <a href="{{ route('master.organizations.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Daftar organisasi
        </a>
    </x-slot:aksi>

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('master.organizations.name.update', $organization) }}" class="card space-y-5" data-progress-message="Menyimpan nama workspace...">
            @csrf
            @method('PUT')

            <div>
                <h2 class="text-xl">Nama workspace</h2>
                <p class="mt-1 text-sm text-ink-muted">Nama ini dipakai di pengalih workspace, laporan, dan halaman milik organisasi.</p>
            </div>

            <div>
                <label for="name" class="label">Nama workspace</label>
                <input id="name" name="name" class="input" required maxlength="120" value="{{ old('name', $organization->name) }}" @error('name') aria-invalid="true" aria-describedby="name_error" @enderror>
                <p class="help mt-1">Alamat login tetap <span class="font-mono">/o/{{ $organization->slug }}/login</span>.</p>
                @error('name') <p id="name_error" class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end border-t border-outline pt-5">
                <button type="submit" class="btn btn-primary">
                    <x-icon name="check" :size="18" />
                    Simpan nama
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
