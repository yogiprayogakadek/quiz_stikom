<x-layouts.guest judul="Kuis kelas untuk banyak organisasi">
    @php
        $hasilAwal = $organizations->map(fn ($organization) => [
            'name' => $organization->name,
            'slug' => $organization->slug,
            'login_url' => route('organizations.login', $organization),
        ])->values();
        $stateAwal = $q === '' ? 'idle' : ($hasilAwal->isNotEmpty() ? 'results' : 'empty');
        $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
    @endphp

    <div class="landing-shell">
        <header class="landing-nav">
            <div class="landing-nav-pill">
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

                <nav class="landing-nav-links" aria-label="Navigasi utama">
                    <a href="#cari" class="landing-nav-link">Cari organisasi</a>
                    <a href="#fitur" class="landing-nav-link">Fitur</a>
                    <a href="#alur" class="landing-nav-link">Alur</a>
                    <a href="#organisasi" class="landing-nav-link">Untuk organisasi</a>
                </nav>

                <a href="{{ route('register.organization') }}" class="landing-nav-cta">
                    Daftarkan organisasi
                </a>
            </div>
        </header>

        <main>
            <section class="landing-hero">
                <div class="landing-hero-glow" aria-hidden="true"></div>

                <div class="landing-hero-inner">
                    <div class="landing-hero-copy">
                        <h1 class="landing-headline">
                            Kuis kelas.<br>
                            <span>Ruang sendiri.</span>
                        </h1>

                        <p class="landing-lede">
                            Satu instalasi, banyak organisasi. Peserta masuk lewat organisasinya, dan tiap workspace mengelola kelas, kuis, soal, serta laporan tanpa bercampur.
                        </p>

                        <div
                            id="cari"
                            class="landing-search"
                            x-data="{
                                q: @js($q),
                                results: {{ json_encode($hasilAwal, $jsonFlags) }},
                                state: @js($stateAwal),
                                active: 0,
                                timer: null,
                                sequence: 0,
                                init() {
                                    this.$watch('q', (value) => {
                                        clearTimeout(this.timer);
                                        if (value.trim() === '') {
                                            this.sequence++;
                                            this.results = [];
                                            this.active = 0;
                                            this.state = 'idle';
                                            this.notify();
                                            return;
                                        }
                                        this.timer = setTimeout(() => this.cari(), 260);
                                    });
                                    this.notify();
                                },
                                notify() {
                                    this.$dispatch('kuis:search', {
                                        state: this.state,
                                        results: this.results,
                                        active: this.active,
                                        query: this.q,
                                    });
                                },
                                setActive(index) {
                                    if (this.state !== 'results' || index === this.active) return;
                                    this.active = index;
                                    this.notify();
                                },
                                async cari() {
                                    clearTimeout(this.timer);
                                    const query = this.q.trim();
                                    if (query === '') {
                                        this.sequence++;
                                        this.results = [];
                                        this.active = 0;
                                        this.state = 'idle';
                                        this.notify();
                                        return;
                                    }
                                    const sequence = ++this.sequence;
                                    this.state = 'loading';
                                    this.active = 0;
                                    this.notify();
                                    try {
                                        const response = await fetch('{{ route('organizations.search') }}?q=' + encodeURIComponent(query), {
                                            headers: { Accept: 'application/json' },
                                        });
                                        if (!response.ok) throw new Error('Pencarian gagal');
                                        const data = await response.json();
                                        if (sequence !== this.sequence) return;
                                        this.results = Array.isArray(data.organizations) ? data.organizations : [];
                                        this.active = 0;
                                        this.state = this.results.length > 0 ? 'results' : 'empty';
                                        this.notify();
                                    } catch {
                                        if (sequence !== this.sequence) return;
                                        this.results = [];
                                        this.active = 0;
                                        this.state = 'error';
                                        this.notify();
                                    }
                                }
                            }"
                        >
                            <form
                                method="GET"
                                action="{{ route('home') }}#cari"
                                role="search"
                                @submit.prevent="cari()"
                            >
                                <label for="q" class="landing-search-label">Cari organisasi</label>
                                <div class="landing-search-pill">
                                    <span class="landing-search-icon" aria-hidden="true">
                                        <x-icon name="search" :size="20" />
                                    </span>
                                    <input
                                        id="q"
                                        name="q"
                                        type="search"
                                        x-model="q"
                                        value="{{ $q }}"
                                        maxlength="80"
                                        autocomplete="off"
                                        placeholder="Nama atau alamat"
                                        aria-describedby="q-help"
                                        class="landing-search-input"
                                    >
                                    <button
                                        type="submit"
                                        class="landing-search-button"
                                        :disabled="state === 'loading'"
                                        :aria-label="state === 'loading' ? 'Mencari organisasi' : 'Cari organisasi'"
                                    >
                                        <span x-show="state !== 'loading'" aria-hidden="true">
                                            <x-icon name="arrow-left" :size="20" class="rotate-180" />
                                        </span>
                                        <span x-show="state === 'loading'" class="landing-search-spinner" aria-hidden="true"></span>
                                    </button>
                                </div>
                                <p id="q-help" class="landing-search-help">
                                    Hanya organisasi aktif yang muncul. Tautan login juga bisa diminta langsung ke <strong>admin organisasi</strong>.
                                </p>
                            </form>

                            <script type="application/json" id="kuis-initial">{!! json_encode(['q' => $q, 'results' => $hasilAwal], $jsonFlags) !!}</script>

                            <div
                                class="landing-results"
                                x-show="state !== 'idle'"
                                x-cloak
                                aria-live="polite"
                                :aria-busy="state === 'loading'"
                            >
                                <div class="landing-results-loading" x-show="state === 'loading'">
                                    <span class="landing-results-dot" aria-hidden="true"></span>
                                    <span>Mencari organisasi...</span>
                                </div>

                                <div class="landing-results-empty" x-show="state === 'empty'">
                                    <strong>Tidak ada organisasi aktif yang cocok dengan "<span x-text="q"></span>".</strong>
                                    <p>Periksa ejaan nama atau alamatnya. Kamu juga bisa meminta tautan login ke admin organisasi.</p>
                                </div>

                                <div class="landing-results-error" x-show="state === 'error'">
                                    <strong>Pencarian gagal.</strong>
                                    <p>Periksa koneksi lalu coba lagi. Kamu juga bisa meminta tautan login ke admin organisasi.</p>
                                </div>

                                <div x-show="state === 'results'">
                                    <div class="landing-results-head">
                                        <span><span x-text="results.length"></span> organisasi ditemukan</span>
                                        <span>Pilih untuk masuk</span>
                                    </div>
                                    <ul class="landing-results-list">
                                        <template x-for="(organization, index) in results" :key="organization.slug">
                                            <li>
                                                <a
                                                    :href="organization.login_url"
                                                    class="landing-result"
                                                    :data-active="index === active"
                                                    @mouseenter="setActive(index)"
                                                    @focus="setActive(index)"
                                                >
                                                    <span class="min-w-0">
                                                        <span class="landing-result-name" x-text="organization.name"></span>
                                                        <span class="landing-result-path" x-text="'/o/' + organization.slug + '/login'"></span>
                                                    </span>
                                                    <span class="landing-result-go">
                                                        Masuk
                                                        <x-icon name="arrow-left" :size="15" class="landing-result-arrow rotate-180" />
                                                    </span>
                                                </a>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <dl class="landing-capabilities">
                            <div class="landing-capability">
                                <dt class="landing-capability-label">
                                    <x-icon name="users" :size="16" />
                                    Multi-organisasi
                                </dt>
                                <dd class="landing-capability-copy">Satu instalasi, batas data per workspace.</dd>
                            </div>
                            <div class="landing-capability">
                                <dt class="landing-capability-label">
                                    <x-icon name="wifi" :size="16" />
                                    Sesi realtime
                                </dt>
                                <dd class="landing-capability-copy">Lobi, sesi, dan pantauan langsung.</dd>
                            </div>
                            <div class="landing-capability">
                                <dt class="landing-capability-label">
                                    <x-icon name="shield-check" :size="16" />
                                    Nilai manusia
                                </dt>
                                <dd class="landing-capability-copy">AI menyarankan, manusia menetapkan.</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="landing-machine-bleed" aria-hidden="true">
                        <div class="landing-machine">
                            @include('partials.landing.quiz-machine')
                        </div>
                    </div>
                </div>
            </section>

            <section id="fitur" class="landing-section">
                <div class="mx-auto w-full max-w-[80rem] px-5 py-16 sm:px-8 sm:py-20">
                    <div class="grid gap-10 lg:grid-cols-12 lg:gap-16">
                        <div class="lg:sticky lg:top-28 lg:col-span-4 lg:self-start">
                            <h2 class="font-display text-3xl font-extrabold leading-[1.05] tracking-tight text-landing-ink sm:text-4xl">
                                Satu workspace,<br>batas yang jelas.
                            </h2>
                            <p class="mt-4 max-w-sm text-base leading-relaxed text-landing-muted">
                                Batas organisasi berlaku pada data dan akses, bukan hanya pada tampilan. Admin organisasi tidak melihat data organisasi lain.
                            </p>
                        </div>

                        <div class="lg:col-span-8">
                            <div class="landing-feature-list">
                                <article class="landing-feature-row">
                                    <div>
                                        <h3 class="landing-feature-title">Kelas dan peserta</h3>
                                        <p class="landing-feature-copy">Buat kelas, atur cover, lalu tambahkan peserta satu per satu atau lewat impor. Peserta dapat masuk kelas sendiri memakai PIN bila kebijakan organisasi mengizinkannya.</p>
                                    </div>
                                </article>
                                <article class="landing-feature-row">
                                    <div>
                                        <h3 class="landing-feature-title">Kuis dan bank soal</h3>
                                        <p class="landing-feature-copy">Susun soal pilihan ganda dan essay, impor dari CSV, atau buat soal dengan provider AI yang diatur organisasi. Soal tersimpan di bank soal organisasi.</p>
                                    </div>
                                </article>
                                <article class="landing-feature-row">
                                    <div>
                                        <h3 class="landing-feature-title">Sesi dan pemantauan</h3>
                                        <p class="landing-feature-copy">Buka lobi, pilih peserta, mulai sesi, lalu pantau jawaban dan kejadian seperti keluar layar penuh atau berpindah tab.</p>
                                    </div>
                                </article>
                                <article class="landing-feature-row">
                                    <div>
                                        <h3 class="landing-feature-title">Nilai dan laporan</h3>
                                        <p class="landing-feature-copy">Tinjau jawaban essay, tetapkan nilai akhir, lalu unduh laporan sesi atau berita acara untuk arsip kelas.</p>
                                    </div>
                                </article>
                                <article class="landing-feature-row">
                                    <div>
                                        <h3 class="landing-feature-title">Identitas organisasi</h3>
                                        <p class="landing-feature-copy">Nama, logo, warna utama, judul login, dan deskripsi singkat diatur per organisasi oleh pengelolanya.</p>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="alur" class="landing-section border-y border-landing-line bg-landing-panel">
                <div class="mx-auto w-full max-w-[80rem] px-5 py-16 sm:px-8 sm:py-20">
                    <div class="max-w-2xl">
                        <h2 class="font-display text-3xl font-extrabold leading-[1.05] tracking-tight text-landing-ink sm:text-4xl">
                            Dari kelas sampai nilai.
                        </h2>
                        <p class="mt-4 text-base leading-relaxed text-landing-muted">
                            Empat langkah yang sama untuk setiap organisasi, dengan data yang tetap terpisah.
                        </p>
                    </div>

                    <ol class="landing-flow mt-10">
                        <li class="landing-flow-step">
                            <span class="landing-flow-dot" aria-hidden="true">01</span>
                            <h3 class="landing-flow-title">Admin menyiapkan kelas dan kuis.</h3>
                            <p class="landing-flow-copy">Peserta ditambahkan ke kelas, soal disusun, dan sesi dijadwalkan.</p>
                        </li>
                        <li class="landing-flow-step">
                            <span class="landing-flow-dot" aria-hidden="true">02</span>
                            <h3 class="landing-flow-title">Peserta masuk lewat organisasinya.</h3>
                            <p class="landing-flow-copy">Gunakan pencarian di halaman ini atau tautan login yang dibagikan admin.</p>
                        </li>
                        <li class="landing-flow-step">
                            <span class="landing-flow-dot" aria-hidden="true">03</span>
                            <h3 class="landing-flow-title">Sesi berjalan dan dipantau.</h3>
                            <p class="landing-flow-copy">Admin melihat peserta yang bergabung dan kejadian selama kuis.</p>
                        </li>
                        <li class="landing-flow-step">
                            <span class="landing-flow-dot" aria-hidden="true">04</span>
                            <h3 class="landing-flow-title">Nilai dan laporan keluar di workspace.</h3>
                            <p class="landing-flow-copy">Hasil sesi dapat diunduh tanpa berpindah ke organisasi lain.</p>
                        </li>
                    </ol>
                </div>
            </section>

            <section id="organisasi" class="landing-section">
                <div class="mx-auto w-full max-w-[80rem] px-5 py-16 sm:px-8 sm:py-20">
                    <div class="landing-cta px-6 py-10 sm:p-12 lg:p-16">
                        <div class="grid gap-8 lg:grid-cols-[1.5fr_1fr] lg:items-end">
                            <div>
                                <h2 class="landing-cta-title">Punya organisasi sendiri?</h2>
                                <p class="landing-cta-copy">
                                    Pendaftaran organisasi membuka workspace baru beserta akun owner. Owner kemudian mengundang admin, mendaftarkan peserta, dan mengatur identitas workspace.
                                </p>
                            </div>
                            <div class="flex flex-col items-start gap-4 lg:items-end">
                                <a href="{{ route('register.organization') }}" class="landing-cta-button">
                                    Daftarkan organisasi
                                </a>
                                <a href="#cari" class="landing-cta-link">
                                    Cari organisasi yang sudah terdaftar
                                    <x-icon name="arrow-left" :size="15" class="rotate-180" />
                                </a>
                            </div>
                        </div>
                        <p class="landing-cta-note mt-8 border-t border-white/15 pt-5">
                            Pendaftaran publik dibuka bertahap. Bila formulir sedang tidak tersedia, hubungi pengelola platform.
                        </p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="landing-footer">
            <div class="mx-auto flex w-full max-w-[80rem] flex-col gap-5 px-5 py-8 sm:px-8 lg:flex-row lg:items-center lg:justify-between">
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

                <p class="landing-footer-meta">
                    &copy; {{ date('Y') }} {{ $appSettings->site_name }}
                    <span aria-hidden="true">·</span>
                    Dibuat oleh Yogi Prayoga
                </p>

                <nav class="flex flex-wrap items-center gap-x-5" aria-label="Tautan footer">
                    <a href="{{ route('terms.show') }}" class="landing-footer-link">Syarat</a>
                    <a href="{{ route('privacy.show') }}" class="landing-footer-link">Privasi</a>
                    <a href="{{ route('master.login') }}" class="landing-footer-link">Masuk master platform</a>
                </nav>
            </div>
        </footer>
    </div>

    @push('scripts')
        @vite('resources/js/landing-machine.js')
    @endpush
</x-layouts.guest>
