<x-layouts.admin judul="Pengaturan" keterangan="Sesuaikan identitas publik, layanan AI, dan cover kelas tanpa mengubah kode.">
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="max-w-5xl space-y-8" data-progress-message="Menyimpan pengaturan publik...">
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
                    <input id="site_name" name="site_name" class="input" required maxlength="80" value="{{ old('site_name', $settings->site_name) }}">
                    @error('site_name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="organization_name" class="label">Nama organisasi <span class="font-normal text-ink-subtle">(opsional)</span></label>
                    <input id="organization_name" name="organization_name" class="input" maxlength="120" value="{{ old('organization_name', $settings->organization_name) }}">
                    @error('organization_name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="site_tagline" class="label">Deskripsi singkat</label>
                    <input id="site_tagline" name="site_tagline" class="input" required maxlength="160" value="{{ old('site_tagline', $settings->site_tagline) }}">
                    @error('site_tagline') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="primary_color" class="label">Warna utama</label>
                    <div class="flex items-center gap-3">
                        <input id="primary_color_picker" type="color" aria-label="Pilih warna utama" class="h-12 w-16 cursor-pointer rounded-xl border border-outline bg-page p-1" value="{{ old('primary_color', $settings->primary_color) }}" oninput="document.getElementById('primary_color').value = this.value">
                        <input id="primary_color" name="primary_color" class="input font-mono" required pattern="#[0-9A-Fa-f]{6}" value="{{ old('primary_color', $settings->primary_color) }}" oninput="document.getElementById('primary_color_picker').value = this.value">
                    </div>
                    @error('primary_color') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="logo" class="label">Logo</label>
                    <div class="flex items-center gap-3">
                        <img src="{{ $settings->logoUrl() }}" alt="Logo saat ini" class="h-12 w-12 rounded-xl border border-outline bg-page object-contain p-1">
                        <input id="logo" name="logo" type="file" class="input" accept=".png,.jpg,.jpeg,.webp,.svg">
                    </div>
                    <p class="help mt-1">PNG, JPG, WebP, atau SVG. Maksimal 2 MB.</p>
                    @error('logo') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="login_title" class="label">Judul halaman login</label>
                    <input id="login_title" name="login_title" class="input" required maxlength="100" value="{{ old('login_title', $settings->login_title) }}">
                    @error('login_title') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="login_description" class="label">Petunjuk login</label>
                    <textarea id="login_description" name="login_description" class="input min-h-24" required maxlength="300">{{ old('login_description', $settings->login_description) }}</textarea>
                    @error('login_description') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <fieldset class="sm:col-span-2">
                    <legend class="label">Layout halaman login</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-outline p-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                            <input type="radio" name="login_layout" value="split" @checked(old('login_layout', $settings->login_layout) === 'split')>
                            <span><strong class="block text-ink">Split</strong><span class="text-sm text-ink-muted">Ilustrasi di samping formulir pada layar lebar.</span></span>
                        </label>
                        <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-outline p-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                            <input type="radio" name="login_layout" value="centered" @checked(old('login_layout', $settings->login_layout) === 'centered')>
                            <span><strong class="block text-ink">Terpusat</strong><span class="text-sm text-ink-muted">Formulir ringkas tanpa panel ilustrasi.</span></span>
                        </label>
                    </div>
                    @error('login_layout') <p class="field-error">{{ $message }}</p> @enderror
                </fieldset>
            </div>
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

        <div class="sticky bottom-4 flex justify-end rounded-2xl border border-outline bg-page/95 p-3 shadow-modal backdrop-blur-sm">
            <button type="submit" class="btn btn-primary">Simpan pengaturan</button>
        </div>
    </form>
</x-layouts.admin>
