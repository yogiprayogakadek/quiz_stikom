<x-layouts.guest judul="Daftarkan Organisasi">
    @php
        $selectedType = old('type', 'education');
        $selectedTimezone = old('timezone', config('app.timezone'));
        $hasOldTimezone = old('timezone') !== null;
        $types = [
            'education' => [
                'label' => 'Pendidikan',
                'copy' => 'Kelas, kampus, atau lembaga belajar.',
                'icon' => 'kelas',
            ],
            'company' => [
                'label' => 'Perusahaan',
                'copy' => 'Tim kerja dan organisasi usaha.',
                'icon' => 'briefcase',
            ],
            'community' => [
                'label' => 'Komunitas',
                'copy' => 'Ruang bersama untuk anggota.',
                'icon' => 'users',
            ],
            'personal' => [
                'label' => 'Personal',
                'copy' => 'Workspace pribadi untuk satu pemilik.',
                'icon' => 'user',
            ],
        ];
        $commonTimezones = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];
        $timezoneGroups = collect($timezones)
            ->reject(fn ($timezone) => in_array($timezone, $commonTimezones, true))
            ->groupBy(fn ($timezone) => str_contains($timezone, '/') ? explode('/', $timezone)[0] : 'Lainnya')
            ->sortKeys();
        $timezoneGroups = collect([
            'Indonesia' => collect($commonTimezones)->filter(
                fn ($timezone) => in_array($timezone, $timezones, true),
            ),
        ])->merge($timezoneGroups);
        $errorLabels = [
            'name' => 'Nama organisasi',
            'slug' => 'Alamat login',
            'type' => 'Jenis workspace',
            'timezone' => 'Zona waktu',
            'owner_name' => 'Nama lengkap',
            'owner_username' => 'Username',
            'owner_email' => 'Email',
            'owner_password' => 'Password',
            'owner_password_confirmation' => 'Ulangi password',
            'terms' => 'Persetujuan',
        ];
    @endphp

    <div class="reg-shell" data-registration-root>
        <a class="reg-skip-link" href="#registration-form">Lewati ke formulir</a>

        <header class="reg-nav">
            <div class="reg-nav-pill">
                <a href="{{ route('home') }}" class="landing-brand" aria-label="{{ $appSettings->site_name }}, beranda">
                    <span class="landing-brand-mark">
                        <img
                            src="{{ $appSettings->logoUrl() }}"
                            alt=""
                            width="40"
                            height="40"
                        >
                    </span>
                    <span class="min-w-0">
                        <span class="landing-brand-name">{{ $appSettings->site_name }}</span>
                        <span class="landing-brand-tagline">{{ $appSettings->site_tagline }}</span>
                    </span>
                </a>

                <div class="reg-nav-actions">
                    <a href="{{ route('home') }}" class="reg-nav-link reg-nav-back" aria-label="Kembali ke beranda">
                        <x-icon name="arrow-left" :size="15" />
                        <span class="reg-nav-back-label">Beranda</span>
                    </a>
                    <a href="{{ route('home') }}#cari" class="reg-nav-link">
                        <span class="reg-nav-workspace-label">Masuk workspace</span>
                        <span class="reg-nav-workspace-short">Masuk</span>
                    </a>
                    <a href="{{ route('master.login') }}" class="reg-nav-link reg-nav-master">Masuk master</a>
                </div>
            </div>
        </header>

        <main class="reg-main">
            <div class="reg-workbench">
                <aside class="reg-plate-panel" aria-labelledby="plate-heading">
                    <div class="reg-plate-head">
                        <p class="reg-eyebrow" id="plate-heading">Meja Terbit</p>
                        <p class="reg-plate-head-note">Pratinjau workspace<br>belum tersimpan</p>
                    </div>

                    <section class="reg-plate" data-plate data-ready="false" aria-label="Pratinjau plat organisasi">
                        <div class="reg-plate-rivets" aria-hidden="true">
                            <span></span><span></span><span></span><span></span>
                        </div>

                        <div class="reg-plate-top">
                            <span class="reg-plate-code">PLAT 01 / {{ date('Y') }}</span>
                            <span class="reg-plate-status" data-plate-status role="status" aria-live="polite">Draf</span>
                        </div>

                        <p class="reg-plate-name" data-plate-name>Workspace baru</p>
                        <p class="reg-plate-path">
                            <span aria-hidden="true">/o/</span><span class="reg-plate-slug" data-plate-slug>workspace</span><span aria-hidden="true">/login</span>
                        </p>

                        <dl class="reg-plate-meta">
                            <div>
                                <dt>Jenis</dt>
                                <dd data-plate-type>{{ $types[$selectedType]['label'] ?? 'Pendidikan' }}</dd>
                            </div>
                            <div>
                                <dt>Zona</dt>
                                <dd><span data-plate-timezone>{{ $selectedTimezone }}</span></dd>
                            </div>
                            <div>
                                <dt>Jam lokal</dt>
                                <dd>
                                    <span data-plate-clock>--:--</span>
                                    <span data-plate-zone>Waktu setempat</span>
                                </dd>
                            </div>
                        </dl>

                        <div class="reg-plate-ready">
                            <div class="reg-plate-ready-head">
                                <span>Kesiapan terbit</span>
                                <strong data-plate-ready-label>0 / 5</strong>
                            </div>
                            <div class="reg-plate-ready-track" data-plate-track aria-hidden="true">
                                <span></span><span></span><span></span><span></span><span></span>
                            </div>
                        </div>

                        <div class="reg-plate-serial">
                            <span>PRESS 01 / {{ strtoupper($appSettings->site_name) }}</span>
                            <span class="reg-plate-bars" aria-hidden="true"></span>
                        </div>
                    </section>

                    <div class="reg-press" aria-hidden="true">
                        <div class="reg-press-stage">
                            @include('partials.registration.workspace-press')
                        </div>
                        <div class="reg-press-caption">
                            <span>Tekanan <strong>01</strong></span>
                            <span>Status / 5 lampu</span>
                        </div>
                    </div>
                </aside>

                <section class="reg-form-panel" aria-labelledby="daftar-title">
                    <header class="reg-form-head">
                        <p class="reg-eyebrow">Pendaftaran organisasi</p>
                        <h1 class="reg-title" id="daftar-title">Daftarkan organisasi</h1>
                        <p class="reg-lede">
                            Buat ruang kerja untuk kelas, komunitas, atau tim. Pemilik workspace menerima email verifikasi sebelum masuk.
                        </p>
                    </header>

                    <nav class="reg-chapters" aria-label="Bab formulir">
                        <a class="reg-chapter-link" href="#bab-1"><span>01</span> Identitas workspace</a>
                        <a class="reg-chapter-link" href="#bab-2"><span>02</span> Pemegang kunci</a>
                        <a class="reg-chapter-link" href="#bab-3"><span>03</span> Persetujuan</a>
                    </nav>

                    @if ($errors->any())
                        <div class="reg-error-summary" role="alert" tabindex="-1" data-error-summary>
                            <strong>Ada {{ $errors->count() }} hal yang perlu diperiksa.</strong>
                            <ul>
                                @foreach ($errors->keys() as $field)
                                    <li>
                                        <a href="#{{ $field }}">
                                            {{ $errorLabels[$field] ?? $field }}: {{ $errors->first($field) }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register.organization.store') }}" class="reg-form" id="registration-form" tabindex="-1" data-registration-form>
                        @csrf

                        <div class="hidden" aria-hidden="true">
                            <label for="website">Website</label>
                            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                        </div>

                        <section class="reg-chapter" id="bab-1" aria-labelledby="bab-1-title">
                            <div class="reg-chapter-head">
                                <span class="reg-chapter-index" aria-hidden="true">01</span>
                                <div>
                                    <h2 class="reg-chapter-title" id="bab-1-title">Identitas workspace</h2>
                                    <p class="reg-chapter-copy">
                                        Tentukan nama, alamat login, jenis, dan zona waktu. Plat di samping mengikuti isian ini.
                                    </p>
                                </div>
                            </div>

                            <div class="reg-grid">
                                <div class="reg-field reg-field-full">
                                    <label for="name" class="reg-label">
                                        Nama organisasi
                                        <span class="reg-label-note">Wajib</span>
                                    </label>
                                    <input
                                        id="name"
                                        name="name"
                                        class="reg-input"
                                        maxlength="120"
                                        value="{{ old('name') }}"
                                        required
                                        autocomplete="organization"
                                        placeholder="Contoh: Kelas XI IPA 2"
                                        data-live-name
                                        @error('name') aria-invalid="true" aria-describedby="name_help name_error" @else aria-describedby="name_help" @enderror
                                    >
                                    <p id="name_help" class="reg-help">Nama yang tampil di halaman login dan laporan.</p>
                                    @error('name') <p id="name_error" class="reg-error">{{ $message }}</p> @enderror
                                </div>

                                <div class="reg-field reg-field-full">
                                    <label for="slug" class="reg-label">
                                        Alamat login
                                        <span class="reg-label-note">Huruf, angka, - atau _</span>
                                    </label>
                                    <div class="reg-slug-shell">
                                        <span class="reg-slug-prefix" aria-hidden="true">/o/</span>
                                        <input
                                            id="slug"
                                            name="slug"
                                            class="reg-slug-input"
                                            maxlength="80"
                                            value="{{ old('slug') }}"
                                            required
                                            pattern="[A-Za-z0-9_-]+"
                                            autocomplete="off"
                                            placeholder="workspace-baru"
                                            data-live-slug
                                            @error('slug') aria-invalid="true" aria-describedby="slug_help slug_error" @else aria-describedby="slug_help" @enderror
                                        >
                                        <span class="reg-slug-suffix" aria-hidden="true">/login</span>
                                    </div>
                                    <p id="slug_help" class="reg-help"><span data-slug-note>Alamat unik untuk halaman login. Bisa diubah kapan saja.</span></p>
                                    <p class="reg-slug-preview">Tautan login: <span data-slug-inline>/o/workspace/login</span></p>
                                    @error('slug') <p id="slug_error" class="reg-error">{{ $message }}</p> @enderror
                                </div>

                                <fieldset class="reg-field reg-field-full" id="type" tabindex="-1" @error('type') aria-invalid="true" aria-describedby="type_error" @enderror>
                                    <legend class="reg-label">Jenis workspace</legend>
                                    <div class="reg-type-grid">
                                        @foreach ($types as $value => $type)
                                            <label class="reg-type">
                                                <input
                                                    type="radio"
                                                    name="type"
                                                    value="{{ $value }}"
                                                    @checked($selectedType === $value)
                                                    required
                                                >
                                                <span class="reg-type-body">
                                                    <span class="reg-type-icon">
                                                        <x-icon :name="$type['icon']" :size="17" />
                                                    </span>
                                                    <span>
                                                        <span class="reg-type-title">{{ $type['label'] }}</span>
                                                        <span class="reg-type-copy">{{ $type['copy'] }}</span>
                                                    </span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('type') <p id="type_error" class="reg-error">{{ $message }}</p> @enderror
                                </fieldset>

                                <div class="reg-field reg-field-full">
                                    <label for="timezone" class="reg-label">
                                        Zona waktu
                                        <span class="reg-label-note">Waktu lokal</span>
                                    </label>
                                    <div class="reg-timezone-row">
                                        <select
                                            id="timezone"
                                            name="timezone"
                                            class="reg-select"
                                            required
                                            data-live-timezone
                                            data-timezone-defaulted="{{ $hasOldTimezone ? 'false' : 'true' }}"
                                            @error('timezone') aria-invalid="true" aria-describedby="timezone_help timezone_error" @else aria-describedby="timezone_help" @enderror
                                        >
                                            @foreach ($timezoneGroups as $region => $zones)
                                                <optgroup label="{{ $region }}">
                                                    @foreach ($zones as $timezone)
                                                        <option value="{{ $timezone }}" @selected($selectedTimezone === $timezone)>{{ $timezone }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                        <div class="reg-clock">
                                            <span class="reg-clock-icon" aria-hidden="true">
                                                <x-icon name="clock" :size="16" />
                                            </span>
                                            <span>
                                                <span class="reg-clock-time" data-timezone-clock>--:--</span>
                                                <span class="reg-clock-meta">
                                                    <span data-timezone-zone>Waktu setempat</span> · <span data-timezone-date>Hari ini</span>
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                    <p id="timezone_help" class="reg-help">Dipakai untuk jadwal sesi dan cap waktu laporan.</p>
                                    @error('timezone') <p id="timezone_error" class="reg-error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </section>

                        <section class="reg-chapter" id="bab-2" aria-labelledby="bab-2-title">
                            <div class="reg-chapter-head">
                                <span class="reg-chapter-index" aria-hidden="true">02</span>
                                <div>
                                    <h2 class="reg-chapter-title" id="bab-2-title">Pemegang kunci</h2>
                                    <p class="reg-chapter-copy">
                                        Akun ini menjadi owner workspace dan dapat menambah admin lain.
                                    </p>
                                </div>
                            </div>

                            <div class="reg-grid">
                                <div class="reg-field reg-field-full">
                                    <label for="owner_name" class="reg-label">
                                        Nama lengkap
                                        <span class="reg-label-note">Wajib</span>
                                    </label>
                                    <input
                                        id="owner_name"
                                        name="owner_name"
                                        class="reg-input"
                                        maxlength="120"
                                        value="{{ old('owner_name') }}"
                                        required
                                        autocomplete="name"
                                        placeholder="Nama pemilik workspace"
                                        data-live-owner-name
                                        @error('owner_name') aria-invalid="true" aria-describedby="owner_name_help owner_name_error" @else aria-describedby="owner_name_help" @enderror
                                    >
                                    <p id="owner_name_help" class="reg-help">Nama yang tampil pada catatan keanggotaan.</p>
                                    @error('owner_name') <p id="owner_name_error" class="reg-error">{{ $message }}</p> @enderror
                                </div>

                                <div class="reg-field">
                                    <label for="owner_username" class="reg-label">Username</label>
                                    <input
                                        id="owner_username"
                                        name="owner_username"
                                        class="reg-input"
                                        maxlength="60"
                                        value="{{ old('owner_username') }}"
                                        required
                                        pattern="[A-Za-z0-9_-]+"
                                        autocomplete="username"
                                        placeholder="owner_workspace"
                                        data-live-username
                                        @error('owner_username') aria-invalid="true" aria-describedby="owner_username_help owner_username_error" @else aria-describedby="owner_username_help" @enderror
                                    >
                                    <p id="owner_username_help" class="reg-help">Huruf, angka, tanda hubung, atau garis bawah.</p>
                                    @error('owner_username') <p id="owner_username_error" class="reg-error">{{ $message }}</p> @enderror
                                </div>

                                <div class="reg-field">
                                    <label for="owner_email" class="reg-label">Email</label>
                                    <input
                                        id="owner_email"
                                        name="owner_email"
                                        type="email"
                                        class="reg-input"
                                        maxlength="255"
                                        value="{{ old('owner_email') }}"
                                        required
                                        autocomplete="email"
                                        placeholder="nama@contoh.test"
                                        data-live-email
                                        @error('owner_email') aria-invalid="true" aria-describedby="owner_email_help owner_email_error" @else aria-describedby="owner_email_help" @enderror
                                    >
                                    <p id="owner_email_help" class="reg-help">Email verifikasi dikirim ke alamat ini.</p>
                                    @error('owner_email') <p id="owner_email_error" class="reg-error">{{ $message }}</p> @enderror
                                </div>

                                <div class="reg-field">
                                    <label for="owner_password" class="reg-label">
                                        Password
                                        <span class="reg-label-note">Minimal 8 karakter</span>
                                    </label>
                                    <div class="reg-input-action">
                                        <input
                                            id="owner_password"
                                            name="owner_password"
                                            type="password"
                                            class="reg-input"
                                            required
                                            autocomplete="new-password"
                                            placeholder="Buat password"
                                            data-live-password
                                            @error('owner_password') aria-invalid="true" aria-describedby="owner_password_help owner_password_error" @else aria-describedby="owner_password_help" @enderror
                                        >
                                        <button
                                            type="button"
                                            class="reg-reveal"
                                            aria-controls="owner_password"
                                            aria-pressed="false"
                                            aria-label="Tampilkan password"
                                            data-password-toggle="owner_password"
                                        >
                                            <span data-eye><x-icon name="eye" :size="17" /></span>
                                            <span data-eye-off><x-icon name="eye-off" :size="17" /></span>
                                        </button>
                                    </div>
                                    <p id="owner_password_help" class="reg-help">Minimal 8 karakter. Gunakan kombinasi yang sulit ditebak.</p>
                                    @error('owner_password') <p id="owner_password_error" class="reg-error">{{ $message }}</p> @enderror
                                </div>

                                <div class="reg-field">
                                    <label for="owner_password_confirmation" class="reg-label">Ulangi password</label>
                                    <div class="reg-input-action">
                                        <input
                                            id="owner_password_confirmation"
                                            name="owner_password_confirmation"
                                            type="password"
                                            class="reg-input"
                                            required
                                            autocomplete="new-password"
                                            placeholder="Ketik ulang password"
                                            data-live-password-confirmation
                                            @error('owner_password_confirmation') aria-invalid="true" aria-describedby="owner_password_confirmation_help owner_password_confirmation_error" @else aria-describedby="owner_password_confirmation_help" @enderror
                                        >
                                        <button
                                            type="button"
                                            class="reg-reveal"
                                            aria-controls="owner_password_confirmation"
                                            aria-pressed="false"
                                            aria-label="Tampilkan ulangi password"
                                            data-password-toggle="owner_password_confirmation"
                                        >
                                            <span data-eye><x-icon name="eye" :size="17" /></span>
                                            <span data-eye-off><x-icon name="eye-off" :size="17" /></span>
                                        </button>
                                    </div>
                                    <p id="owner_password_confirmation_help" class="reg-help" data-password-match aria-live="polite">Ketik ulang password yang sama.</p>
                                    @error('owner_password_confirmation') <p id="owner_password_confirmation_error" class="reg-error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </section>

                        <section class="reg-chapter" id="bab-3" aria-labelledby="bab-3-title">
                            <div class="reg-chapter-head">
                                <span class="reg-chapter-index" aria-hidden="true">03</span>
                                <div>
                                    <h2 class="reg-chapter-title" id="bab-3-title">Persetujuan</h2>
                                    <p class="reg-chapter-copy">
                                        Periksa kembali isian, lalu setujui ketentuan sebelum workspace dibuat.
                                    </p>
                                </div>
                            </div>

                            <div class="reg-grid">
                                <div class="reg-field reg-field-full">
                                    <label class="reg-consent">
                                        <input
                                            type="checkbox"
                                            id="terms"
                                            name="terms"
                                            value="1"
                                            required
                                            @checked(old('terms'))
                                            @error('terms') aria-invalid="true" aria-describedby="terms_error" @enderror
                                        >
                                        <span class="reg-consent-box" aria-hidden="true">
                                            <x-icon name="check" :size="14" />
                                        </span>
                                        <span class="reg-consent-copy">
                                            Saya menyetujui
                                            <a href="{{ route('terms.show') }}">Syarat Penggunaan</a>
                                            dan
                                            <a href="{{ route('privacy.show') }}">Kebijakan Privasi</a>.
                                        </span>
                                    </label>
                                    @error('terms') <p id="terms_error" class="reg-error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </section>

                        <div class="reg-submit">
                            <button type="submit" class="reg-submit-btn">
                                <x-icon name="check" :size="18" />
                                Buat workspace
                            </button>
                            <p class="reg-submit-note">
                                Email verifikasi dikirim setelah workspace dibuat. Tautan verifikasi berlaku terbatas.
                            </p>
                        </div>
                    </form>
                </section>
            </div>
        </main>

        <footer class="reg-footer">
            <div class="reg-footer-inner">
                <p class="reg-footer-meta">&copy; {{ date('Y') }} {{ $appSettings->site_name }} · Meja Terbit</p>
                <nav class="reg-footer-links" aria-label="Tautan footer">
                    <a href="{{ route('terms.show') }}">Syarat</a>
                    <a href="{{ route('privacy.show') }}">Privasi</a>
                    <a href="{{ route('master.login') }}">Masuk master</a>
                </nav>
            </div>
        </footer>
    </div>

    @push('scripts')
        @vite('resources/js/registration.js')
    @endpush
</x-layouts.guest>
