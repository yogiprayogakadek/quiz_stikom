<x-layouts.admin judul="Tautkan peserta" keterangan="Hubungkan akun peserta yang sudah terdaftar ke workspace lain, atau verifikasi akun yang sudah ada.">
    <div class="grid max-w-5xl gap-6 lg:grid-cols-2">
        <section class="card" aria-labelledby="tautkan-peserta-heading">
            <h2 id="tautkan-peserta-heading" class="text-2xl">Tautkan akun ke workspace</h2>
            <p class="mt-2 text-base text-ink-muted">
                Satu NIM memakai satu akun global. Tautkan akun yang sudah ada tanpa membuat akun baru atau mengubah nama, email, dan password.
            </p>

            @if ($organizations->isEmpty())
                <x-alert jenis="warning" class="mt-5">
                    Belum ada workspace aktif. Buat workspace terlebih dahulu sebelum menautkan peserta.
                </x-alert>
            @else
                <form method="POST" action="{{ route('master.participants.link') }}" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label for="organization_id" class="label">Workspace tujuan</label>
                        <select id="organization_id" name="organization_id" class="input" required @error('organization_id') aria-invalid="true" @enderror>
                            <option value="">Pilih workspace</option>
                            @foreach ($organizations as $organization)
                                <option value="{{ $organization->id }}" @selected((string) old('organization_id') === (string) $organization->id)>
                                    {{ $organization->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('organization_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="link_username" class="label">NIM</label>
                        <input id="link_username" name="username" type="text" value="{{ old('username') }}" class="input" required autocomplete="off" autocapitalize="none" spellcheck="false" @error('username') aria-invalid="true" @enderror>
                        @error('username') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="link_nama" class="label">Nama pada akun</label>
                        <input id="link_nama" name="nama" type="text" value="{{ old('nama') }}" class="input" required autocomplete="off" @error('nama') aria-invalid="true" @enderror>
                        <p class="help mt-2">Isi nama yang terdaftar pada akun. Nama harus sama kecuali Anda mencentang konfirmasi di bawah.</p>
                        @error('nama') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-start gap-3 rounded-xl border border-outline p-3 text-sm text-ink-muted">
                        <input type="checkbox" name="allow_name_mismatch" value="1" class="mt-0.5" @checked(old('allow_name_mismatch'))>
                        <span>Saya sudah memastikan ini akun yang benar meskipun nama pada akun berbeda.</span>
                    </label>

                    <button type="submit" class="btn btn-primary w-full sm:w-auto">Tautkan akun</button>
                </form>
            @endif
        </section>

        <section class="card" aria-labelledby="verifikasi-peserta-heading">
            <h2 id="verifikasi-peserta-heading" class="text-2xl">Verifikasi akun</h2>
            <p class="mt-2 text-base text-ink-muted">
                Tandai email akun peserta sudah valid. Verifikasi berlaku untuk seluruh workspace yang memakai akun tersebut.
            </p>

            <form method="POST" action="{{ route('master.participants.verify') }}" class="mt-5 space-y-4">
                @csrf

                <div>
                    <label for="verify_username" class="label">NIM</label>
                    <input id="verify_username" name="username" type="text" value="{{ old('username') }}" class="input" required autocomplete="off" autocapitalize="none" spellcheck="false" @error('username', 'verify') aria-invalid="true" @enderror>
                    @error('username', 'verify') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn btn-secondary w-full sm:w-auto">Tandai terverifikasi</button>
            </form>
        </section>
    </div>
</x-layouts.admin>
