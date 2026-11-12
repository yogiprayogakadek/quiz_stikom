@php($activeOrganization = app(\App\Support\OrganizationContext::class)->current())

<x-layouts.admin judul="Pengaturan" :keterangan="$activeOrganization ? 'Pengaturan ini berlaku untuk workspace '.$activeOrganization->name.'.' : 'Sesuaikan identitas platform, layanan AI, dan cover kelas.'">
    <form
        method="POST"
        action="{{ route('admin.settings.update') }}"
        enctype="multipart/form-data"
        class="max-w-5xl space-y-8"
        data-progress-message="Menyimpan pengaturan publik..."
        x-data="{
            siteName: @js(old('site_name', $settings->site_name)),
            siteTagline: @js(old('site_tagline', $settings->site_tagline)),
            primaryColor: @js(old('primary_color', $settings->primary_color)),
            loginTitle: @js(old('login_title', $settings->login_title)),
            loginDescription: @js(old('login_description', $settings->login_description)),
            layout: @js(old('login_layout', $settings->login_layout)),
            logoPreview: @js($settings->logoUrl()),
            logoObjectUrl: null,
            previewStyle() {
                return `--preview-soft: color-mix(in srgb, ${this.primaryColor} 15%, white); --preview-deep: color-mix(in srgb, ${this.primaryColor} 72%, #0f172a);`;
            },
            previewLogo(event) {
                const file = event.target.files?.[0];
                if (!file) return;
                if (this.logoObjectUrl) URL.revokeObjectURL(this.logoObjectUrl);
                this.logoObjectUrl = URL.createObjectURL(file);
                this.logoPreview = this.logoObjectUrl;
            }
        }"
    >
        @csrf
        @method('PUT')

        <section class="card" aria-labelledby="identitas-heading">
            <div class="border-b border-outline pb-4">
                <h2 id="identitas-heading" class="text-2xl">Identitas publik</h2>
                <p class="mt-1 text-base text-ink-muted">Nama, warna, dan logo dipakai di halaman login serta navigasi aplikasi.</p>
            </div>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="site_name" class="label">Nama aplikasi</label>
                    <input id="site_name" name="site_name" class="input" required maxlength="80" value="{{ old('site_name', $settings->site_name) }}" x-model="siteName">
                    <p class="help mt-1">Dipakai sebagai judul halaman dan nama platform di navigasi.</p>
                    @error('site_name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="site_tagline" class="label">Deskripsi singkat</label>
                    <input id="site_tagline" name="site_tagline" class="input" required maxlength="160" value="{{ old('site_tagline', $settings->site_tagline) }}" x-model="siteTagline">
                    @error('site_tagline') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="primary_color" class="label">Warna utama</label>
                    <div class="flex items-center gap-3">
                        <input id="primary_color_picker" type="color" aria-label="Pilih warna utama" class="h-12 w-16 cursor-pointer rounded-xl border border-outline bg-page p-1" value="{{ old('primary_color', $settings->primary_color) }}" :value="primaryColor" x-on:input="primaryColor = $event.target.value">
                        <input id="primary_color" name="primary_color" class="input font-mono" required pattern="#[0-9A-Fa-f]{6}" value="{{ old('primary_color', $settings->primary_color) }}" x-model="primaryColor">
                    </div>
                    @error('primary_color') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="logo" class="label">Logo</label>
                    <div class="flex items-center gap-3">
                        <img :src="logoPreview" alt="Logo saat ini" class="h-12 w-12 rounded-xl border border-outline bg-page object-contain p-1">
                        <input id="logo" name="logo" type="file" class="input" accept=".png,.jpg,.jpeg,.webp,.svg" x-on:change="previewLogo($event)">
                    </div>
                    <p class="help mt-1">PNG, JPG, WebP, atau SVG. Maksimal 2 MB.</p>
                    @error('logo') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="login_title" class="label">Judul halaman login</label>
                    <input id="login_title" name="login_title" class="input" required maxlength="100" value="{{ old('login_title', $settings->login_title) }}" x-model="loginTitle">
                    @error('login_title') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="login_description" class="label">Petunjuk login</label>
                    <textarea id="login_description" name="login_description" class="input min-h-24" required maxlength="300" x-model="loginDescription">{{ old('login_description', $settings->login_description) }}</textarea>
                    @error('login_description') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <fieldset class="sm:col-span-2">
                    <legend class="label">Layout halaman login</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-outline p-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                            <input type="radio" name="login_layout" value="split" @checked(old('login_layout', $settings->login_layout) === 'split') x-model="layout">
                            <span><strong class="block text-ink">Split</strong><span class="text-sm text-ink-muted">Ilustrasi di samping formulir pada layar lebar.</span></span>
                        </label>
                        <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-outline p-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                            <input type="radio" name="login_layout" value="centered" @checked(old('login_layout', $settings->login_layout) === 'centered') x-model="layout">
                            <span><strong class="block text-ink">Terpusat</strong><span class="text-sm text-ink-muted">Formulir ringkas tanpa panel ilustrasi.</span></span>
                        </label>
                    </div>
                    @error('login_layout') <p class="field-error">{{ $message }}</p> @enderror
                </fieldset>
            </div>
        </section>

        <section class="card" aria-labelledby="preview-heading">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-outline pb-4">
                <div>
                    <h2 id="preview-heading" class="text-2xl">Pratinjau branding</h2>
                    <p class="mt-1 text-base text-ink-muted">Pratinjau mengikuti isian di atas. Perubahan baru dipakai setelah kamu menekan tombol simpan.</p>
                </div>
                <span class="pill pill-neutral">Pratinjau</span>
            </div>

            <div class="mt-5 rounded-2xl border border-outline bg-surface-sunken p-3 sm:p-5" :style="previewStyle()" aria-hidden="true">
                <div class="overflow-hidden rounded-xl border border-outline bg-page shadow-lift">
                    <div :class="layout === 'split' ? 'grid md:grid-cols-2' : 'flex flex-col'">
                        <div
                            x-show="layout === 'split'"
                            x-cloak
                            class="relative hidden flex-col justify-between gap-6 overflow-hidden p-5 md:flex"
                            style="background: linear-gradient(145deg, var(--preview-soft), var(--color-surface), var(--preview-soft));"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-outline bg-page p-1.5">
                                    <img :src="logoPreview" alt="" class="h-full w-full object-contain">
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate font-display text-base font-bold text-ink" x-text="siteName"></p>
                                    <p class="truncate text-xs text-ink-subtle" x-text="siteTagline"></p>
                                </div>
                            </div>
                            <div class="rounded-xl border border-outline bg-page/70 p-3">
                                <div class="flex h-36 items-center justify-center rounded-lg" style="background: var(--preview-soft);">
                                    <span class="text-xs font-bold" style="color: var(--preview-deep);">Ilustrasi login</span>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-[11px] font-semibold text-ink-subtle">
                                <span>Mode layar penuh</span>
                                <span>Sinkron realtime</span>
                            </div>
                        </div>

                        <div
                            :class="layout === 'split' ? 'p-5 sm:p-6' : 'p-5 sm:p-8'"
                            :style="layout === 'centered' ? 'background: linear-gradient(145deg, var(--preview-soft), var(--color-surface), var(--preview-soft));' : ''"
                        >
                            <div class="mx-auto w-full max-w-sm">
                                <div class="mb-4 flex items-center justify-center gap-2" :class="layout === 'centered' ? '' : 'md:hidden'">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-outline bg-page p-1.5">
                                        <img :src="logoPreview" alt="" class="h-full w-full object-contain">
                                    </div>
                                    <span class="min-w-0 truncate font-display text-lg font-bold tracking-tight text-ink" x-text="siteName"></span>
                                </div>

                                <div class="rounded-xl border border-outline bg-page p-4 shadow-lift sm:p-5">
                                    <p class="font-display text-xl font-extrabold tracking-tight text-ink" x-text="loginTitle"></p>
                                    <p class="mt-1 text-xs leading-relaxed text-ink-muted" x-text="loginDescription"></p>

                                    <div class="mt-4 space-y-2.5">
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-ink-muted">Username</p>
                                            <div class="mt-1 h-9 rounded-lg border border-outline bg-page"></div>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-ink-muted">Password</p>
                                            <div class="mt-1 h-9 rounded-lg border border-outline bg-page"></div>
                                        </div>
                                        <div class="rounded-lg px-3 py-2 text-center text-xs font-bold text-white" :style="'background-color: ' + primaryColor">Masuk akun</div>
                                    </div>
                                </div>

                                <p class="mt-3 text-center text-[10px] text-ink-subtle">Kendala akun? Hubungi admin kelas.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="workspace-heading">
            <div class="border-b border-outline pb-4">
                <h2 id="workspace-heading" class="text-2xl">Workspace</h2>
                <p class="mt-1 text-base text-ink-muted">Alamat login, zona waktu, dan bahasa pesan sistem untuk workspace ini.</p>
            </div>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                @if ($activeOrganization)
                    @if ($isOwner)
                        <div class="sm:col-span-2">
                            <label for="organization_name" class="label">Nama organisasi</label>
                            <input id="organization_name" name="organization_name" class="input" required maxlength="120" value="{{ old('organization_name', $activeOrganization->name) }}">
                            <p class="help mt-1">Dipakai di pengalih workspace, laporan, dan halaman milik organisasi. Hanya owner yang dapat mengubahnya.</p>
                            @error('organization_name') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <label for="organization_slug" class="label">Slug alamat login</label>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold text-ink-muted">/o/</span>
                            <input id="organization_slug" name="organization_slug" class="input min-w-0 flex-1 font-mono" required maxlength="80" pattern="[A-Za-z0-9_-]+" value="{{ old('organization_slug', $activeOrganization->slug) }}">
                            <span class="text-sm font-semibold text-ink-muted">/login</span>
                        </div>
                        <p class="help mt-1">Mengubah slug membuat tautan login lama tidak berlaku.</p>
                        @error('organization_slug') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="organization_timezone" class="label">Zona waktu</label>
                        <input id="organization_timezone" name="organization_timezone" class="input" required list="timezone-options" value="{{ old('organization_timezone', $activeOrganization->timezone) }}">
                        <datalist id="timezone-options">
                            @foreach (['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'Asia/Singapore', 'UTC'] as $timezone)
                                <option value="{{ $timezone }}"></option>
                            @endforeach
                        </datalist>
                        <p class="help mt-1">Dipakai untuk tanggal dan jam di laporan.</p>
                        @error('organization_timezone') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label for="locale" class="label">Bahasa pesan sistem</label>
                    <select id="locale" name="locale" class="input" required>
                        <option value="id" @selected(old('locale', $settings->locale) === 'id')>Indonesia</option>
                        <option value="en" @selected(old('locale', $settings->locale) === 'en')>English</option>
                    </select>
                    <p class="help mt-1">Mengatur bahasa bawaan sistem dan nama bulan. Teks utama aplikasi tetap Bahasa Indonesia.</p>
                    @error('locale') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        @if ($activeOrganization && $isOwner)
            <section class="card" aria-labelledby="legal-heading">
                <div class="border-b border-outline pb-4">
                    <h2 id="legal-heading" class="text-2xl">Dokumen legal workspace</h2>
                    <p class="mt-1 text-base text-ink-muted">Teks ini tampil di halaman syarat dan privasi workspace. Kosongkan untuk memakai teks platform.</p>
                </div>
                <div class="mt-5 grid gap-5">
                    <div>
                        <label for="terms_content" class="label">Syarat penggunaan</label>
                        <textarea id="terms_content" name="terms_content" class="input min-h-40" maxlength="20000" placeholder="Kosongkan untuk memakai teks platform.">{{ old('terms_content', $settings->terms_content) }}</textarea>
                        <p class="help mt-1">Pisahkan paragraf dengan baris kosong. Awali judul bagian dengan <code>##</code>. HTML tidak dijalankan.</p>
                        <p class="mt-1 text-sm"><a href="{{ route('organizations.terms', $activeOrganization) }}" class="font-semibold text-brand-deep underline-offset-2 hover:underline" target="_blank" rel="noopener">Lihat halaman syarat</a></p>
                        @error('terms_content') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="privacy_content" class="label">Kebijakan privasi</label>
                        <textarea id="privacy_content" name="privacy_content" class="input min-h-40" maxlength="20000" placeholder="Kosongkan untuk memakai teks platform.">{{ old('privacy_content', $settings->privacy_content) }}</textarea>
                        <p class="help mt-1">Pisahkan paragraf dengan baris kosong. Awali judul bagian dengan <code>##</code>. HTML tidak dijalankan.</p>
                        <p class="mt-1 text-sm"><a href="{{ route('organizations.privacy', $activeOrganization) }}" class="font-semibold text-brand-deep underline-offset-2 hover:underline" target="_blank" rel="noopener">Lihat halaman privasi</a></p>
                        @error('privacy_content') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>
        @endif

        <section class="card" aria-labelledby="peserta-heading">
            <div class="border-b border-outline pb-4">
                <h2 id="peserta-heading" class="text-2xl">Kebijakan peserta</h2>
                <p class="mt-1 text-base text-ink-muted">Atur cara peserta masuk ke kelas pada workspace ini.</p>
            </div>
            <label class="mt-5 flex items-start gap-3 rounded-2xl border border-outline bg-page p-4">
                <input type="checkbox" name="allow_pin_enrollment" value="1" class="mt-1 h-5 w-5" @checked((bool) old('allow_pin_enrollment', $settings->allow_pin_enrollment))>
                <span>
                    <span class="block text-base font-bold text-ink">Izinkan peserta masuk kelas sendiri dengan PIN</span>
                    <span class="block text-base text-ink-muted">Jika dimatikan, admin harus menambahkan peserta ke kelas dari halaman kelas.</span>
                </span>
            </label>
        </section>

        <section class="card" aria-labelledby="ai-heading">
            <div class="border-b border-outline pb-4">
                <h2 id="ai-heading" class="text-2xl">Provider AI soal</h2>
                <p class="mt-1 text-base text-ink-muted">Gunakan 9Router atau endpoint lain yang kompatibel dengan OpenAI Chat Completions.</p>
            </div>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="ai_base_url" class="label">Base URL</label>
                    <input id="ai_base_url" name="ai_base_url" type="url" class="input" placeholder="https://api.example.com/v1" value="{{ old('ai_base_url', $settings->ai_base_url) }}">
                    @error('ai_base_url') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ai_api_key" class="label">API key</label>
                    <input id="ai_api_key" name="ai_api_key" type="password" class="input" autocomplete="new-password" placeholder="Kosongkan untuk mempertahankan key saat ini">
                    @error('ai_api_key') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ai_default_model" class="label">Model default</label>
                    <input id="ai_default_model" name="ai_default_model" class="input" placeholder="provider/model-name" value="{{ old('ai_default_model', $settings->ai_default_model) }}">
                    @error('ai_default_model') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ai_timeout" class="label">Batas waktu request</label>
                    <div class="relative">
                        <input id="ai_timeout" name="ai_timeout" type="number" class="input pr-16" min="10" max="300" value="{{ old('ai_timeout', $settings->ai_timeout) }}">
                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-ink-subtle">detik</span>
                    </div>
                    @error('ai_timeout') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="cover-heading">
            <div class="border-b border-outline pb-4">
                <h2 id="cover-heading" class="text-2xl">Cover kelas</h2>
                <p class="mt-1 text-base text-ink-muted">Mode lokal selalu tersedia dan menjadi fallback bila layanan gambar gagal.</p>
            </div>
            <fieldset class="mt-5">
                <legend class="label">Sumber cover baru</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-outline p-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                        <input type="radio" name="class_cover_mode" value="local" @checked(old('class_cover_mode', $settings->class_cover_mode) === 'local')>
                        <span><strong class="block text-ink">Ilustrasi lokal</strong><span class="text-sm text-ink-muted">Cepat, konsisten, tanpa biaya API.</span></span>
                    </label>
                    <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-outline p-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                        <input type="radio" name="class_cover_mode" value="ai" @checked(old('class_cover_mode', $settings->class_cover_mode) === 'ai')>
                        <span><strong class="block text-ink">Generate dengan AI</strong><span class="text-sm text-ink-muted">Dibuat di antrean setelah kelas disimpan.</span></span>
                    </label>
                </div>
            </fieldset>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="image_base_url" class="label">Base URL image API <span class="font-normal text-ink-subtle">(opsional)</span></label>
                    <input id="image_base_url" name="image_base_url" type="url" class="input" placeholder="https://api.example.com/v1" value="{{ old('image_base_url', $settings->image_base_url) }}">
                    <p class="help mt-1">Kosongkan untuk memakai Base URL provider AI soal.</p>
                    @error('image_base_url') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="image_api_key" class="label">API key gambar <span class="font-normal text-ink-subtle">(opsional)</span></label>
                    <input id="image_api_key" name="image_api_key" type="password" class="input" autocomplete="new-password" placeholder="Kosongkan untuk mempertahankan key saat ini">
                    <p class="help mt-1">Jika belum pernah diisi, sistem memakai API key provider soal.</p>
                    @error('image_api_key') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="image_model" class="label">Model gambar</label>
                    <input id="image_model" name="image_model" class="input" placeholder="gpt-image-1" value="{{ old('image_model', $settings->image_model) }}">
                    @error('image_model') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        @if ($usage)
            <section class="card" aria-labelledby="usage-heading">
                <div class="border-b border-outline pb-4">
                    <h2 id="usage-heading" class="text-2xl">Pemakaian dan kuota</h2>
                    <p class="mt-1 text-base text-ink-muted">Batas berlaku untuk workspace ini dan ditetapkan operator platform.</p>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-outline bg-page p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-base font-bold text-ink">AI bulan ini</p>
                            <p class="text-sm font-semibold text-ink-muted">{{ number_format($usage['ai_used'], 0, ',', '.') }} / {{ number_format($usage['ai_limit'], 0, ',', '.') }} request</p>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-surface-sunken" role="progressbar" aria-valuenow="{{ $usage['ai_percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Pemakaian kuota AI">
                            <div @class(['h-full rounded-full', 'bg-coral' => $usage['ai_percent'] >= 100, 'bg-brand' => $usage['ai_percent'] < 100]) style="width: {{ $usage['ai_percent'] }}%"></div>
                        </div>
                        <p class="mt-2 text-sm text-ink-muted">Sisa {{ number_format(max(0, $usage['ai_limit'] - $usage['ai_used']), 0, ',', '.') }} request. Generate soal, saran nilai essay, dan cover AI memakai kuota ini.</p>
                    </div>
                    <div class="rounded-2xl border border-outline bg-page p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-base font-bold text-ink">Penyimpanan</p>
                            <p class="text-sm font-semibold text-ink-muted">{{ number_format($usage['storage_used'] / 1048576, 1, ',', '.') }} / {{ number_format($usage['storage_limit'] / 1048576, 0, ',', '.') }} MB</p>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-surface-sunken" role="progressbar" aria-valuenow="{{ $usage['storage_percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Pemakaian kuota penyimpanan">
                            <div @class(['h-full rounded-full', 'bg-coral' => $usage['storage_percent'] >= 100, 'bg-brand' => $usage['storage_percent'] < 100]) style="width: {{ $usage['storage_percent'] }}%"></div>
                        </div>
                        <p class="mt-2 text-sm text-ink-muted">Sisa {{ number_format(max(0, $usage['storage_limit'] - $usage['storage_used']) / 1048576, 1, ',', '.') }} MB. Logo dan cover kelas dihitung; backup database tidak.</p>
                    </div>
                </div>
                @if ($usage['ai_percent'] >= 100 || $usage['storage_percent'] >= 100)
                    <p class="mt-4 rounded-xl border border-coral-deep/30 bg-coral-soft px-4 py-3 text-sm font-semibold text-coral-deep">Kuota habis. Hapus file lama atau hubungi operator platform sebelum memakai fitur AI atau mengunggah file baru.</p>
                @endif
            </section>
        @endif

        <div class="sticky bottom-4 flex justify-end rounded-2xl border border-outline bg-page/95 p-3 shadow-modal backdrop-blur-sm">
            <button type="submit" class="btn btn-primary">Simpan pengaturan</button>
        </div>
    </form>
</x-layouts.admin>
