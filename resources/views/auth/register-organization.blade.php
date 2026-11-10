<x-layouts.guest judul="Daftarkan Organisasi">
    <div class="mx-auto flex min-h-screen w-full max-w-4xl flex-col justify-center px-5 py-10 sm:px-8 sm:py-16">
        <div class="mb-6 flex items-center justify-between gap-4">
            <a href="{{ route('login') }}" class="text-sm font-bold text-brand-deep underline-offset-2 hover:underline">Kembali ke login</a>
            <a href="{{ route('master.login') }}" class="text-sm font-bold text-brand-deep underline-offset-2 hover:underline">Login master</a>
        </div>

        <div class="card p-6 sm:p-9">
            <header>
                <p class="text-sm font-bold text-brand-deep">Workspace baru</p>
                <h1 class="mt-2 font-display text-3xl font-extrabold tracking-tight text-ink">Daftarkan organisasi</h1>
                <p class="mt-2 max-w-2xl text-base leading-relaxed text-ink-muted">
                    Buat ruang kerja untuk kelas, komunitas, atau tim. Pemilik workspace akan menerima email verifikasi sebelum masuk.
                </p>
            </header>

            <form method="POST" action="{{ route('register.organization.store') }}" class="mt-7 space-y-7">
                @csrf

                <div class="hidden" aria-hidden="true">
                    <label for="website">Website</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <fieldset class="space-y-4">
                    <legend class="text-lg font-bold text-ink">Organisasi</legend>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="name" class="label">Nama organisasi</label>
                            <input id="name" name="name" class="input" maxlength="120" value="{{ old('name') }}" required autocomplete="organization" @error('name') aria-invalid="true" aria-describedby="name_error" @enderror>
                            @error('name') <p id="name_error" class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="slug" class="label">Slug</label>
                            <input id="slug" name="slug" class="input" maxlength="80" value="{{ old('slug') }}" required pattern="[A-Za-z0-9_-]+" autocomplete="off" @error('slug') aria-invalid="true" aria-describedby="slug_error" @enderror>
                            <p class="help mt-1">Huruf, angka, tanda hubung, atau garis bawah. Dipakai di alamat login organisasi.</p>
                            @error('slug') <p id="slug_error" class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="type" class="label">Jenis</label>
                            <select id="type" name="type" class="input" required @error('type') aria-invalid="true" aria-describedby="type_error" @enderror>
                                <option value="education" @selected(old('type', 'education') === 'education')>Pendidikan</option>
                                <option value="company" @selected(old('type') === 'company')>Perusahaan</option>
                                <option value="community" @selected(old('type') === 'community')>Komunitas</option>
                                <option value="personal" @selected(old('type') === 'personal')>Personal</option>
                            </select>
                            @error('type') <p id="type_error" class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="timezone" class="label">Zona waktu</label>
                            <select id="timezone" name="timezone" class="input" required @error('timezone') aria-invalid="true" aria-describedby="timezone_error" @enderror>
                                @foreach ($timezones as $timezone)
                                    <option value="{{ $timezone }}" @selected(old('timezone', config('app.timezone')) === $timezone)>{{ $timezone }}</option>
                                @endforeach
                            </select>
                            @error('timezone') <p id="timezone_error" class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="space-y-4 border-t border-outline pt-6">
                    <legend class="text-lg font-bold text-ink">Akun pemilik</legend>
                    <p class="text-sm text-ink-muted">Akun ini menjadi owner workspace dan dapat menambah admin lain.</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="owner_name" class="label">Nama lengkap</label>
                            <input id="owner_name" name="owner_name" class="input" maxlength="120" value="{{ old('owner_name') }}" required autocomplete="name" @error('owner_name') aria-invalid="true" aria-describedby="owner_name_error" @enderror>
                            @error('owner_name') <p id="owner_name_error" class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="owner_username" class="label">Username</label>
                            <input id="owner_username" name="owner_username" class="input" maxlength="60" value="{{ old('owner_username') }}" required pattern="[A-Za-z0-9_-]+" autocomplete="username" @error('owner_username') aria-invalid="true" aria-describedby="owner_username_error" @enderror>
                            @error('owner_username') <p id="owner_username_error" class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="owner_email" class="label">Email</label>
                            <input id="owner_email" name="owner_email" type="email" class="input" maxlength="255" value="{{ old('owner_email') }}" required autocomplete="email" @error('owner_email') aria-invalid="true" aria-describedby="owner_email_error" @enderror>
                            @error('owner_email') <p id="owner_email_error" class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="owner_password" class="label">Password</label>
                            <input id="owner_password" name="owner_password" type="password" class="input" required autocomplete="new-password" @error('owner_password') aria-invalid="true" aria-describedby="owner_password_error" @enderror>
                            <p class="help mt-1">Minimal 8 karakter.</p>
                            @error('owner_password') <p id="owner_password_error" class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="owner_password_confirmation" class="label">Ulangi password</label>
                            <input id="owner_password_confirmation" name="owner_password_confirmation" type="password" class="input" required autocomplete="new-password">
                        </div>
                    </div>
                </fieldset>

                <div>
                    <label class="flex items-start gap-3 text-sm text-ink-muted">
                        <input type="checkbox" name="terms" value="1" class="mt-0.5 h-4 w-4 rounded border-outline text-brand focus:ring-brand-soft" required @checked(old('terms')) @error('terms') aria-invalid="true" aria-describedby="terms_error" @enderror>
                        <span>
                            Saya menyetujui
                            <a href="{{ route('terms.show') }}" class="font-bold text-brand-deep underline-offset-2 hover:underline">Syarat Penggunaan</a>
                            dan
                            <a href="{{ route('privacy.show') }}" class="font-bold text-brand-deep underline-offset-2 hover:underline">Kebijakan Privasi</a>.
                        </span>
                    </label>
                    @error('terms') <p id="terms_error" class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="border-t border-outline pt-6">
                    <button type="submit" class="btn btn-primary w-full">
                        <x-icon name="check" :size="18" />
                        Buat workspace
                    </button>
                    <p class="mt-3 text-center text-xs text-ink-subtle">Email verifikasi dikirim setelah workspace dibuat. Tautan berlaku terbatas.</p>
                </div>
            </form>
        </div>
    </div>
</x-layouts.guest>
