<x-layouts.guest judul="Kuis kelas untuk banyak organisasi">
    <div class="flex min-h-screen flex-col bg-surface">
        <header class="border-b border-outline bg-page">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-5 py-4 sm:px-8">
                <a href="{{ route('home') }}" class="flex min-h-11 min-w-0 items-center gap-3">
                    <img
                        src="{{ $appSettings->logoUrl() }}"
                        alt="Logo {{ $appSettings->site_name }}"
                        width="40"
                        height="40"
                        class="h-10 w-10 flex-none rounded-xl border border-outline bg-page object-contain p-1"
                    >
                    <span class="min-w-0">
                        <span class="block truncate font-display text-lg font-bold leading-tight text-ink">{{ $appSettings->site_name }}</span>
                        <span class="hidden truncate text-xs text-ink-subtle sm:block">{{ $appSettings->site_tagline }}</span>
                    </span>
                </a>

                <nav class="flex flex-none items-center gap-2" aria-label="Navigasi utama">
                    <a href="#cari" class="hidden min-h-11 items-center px-3 text-sm font-bold text-ink-muted hover:text-ink sm:inline-flex">
                        Cari organisasi
                    </a>
                    <a href="{{ route('register.organization') }}" class="btn btn-secondary min-h-11 px-4 py-2 text-sm">
                        Daftarkan organisasi
                    </a>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            @if (session('sukses') || session('peringatan') || $errors->any())
                <div class="mx-auto w-full max-w-6xl px-5 pt-5 sm:px-8">
                    @if (session('sukses'))
                        <div class="flex items-start gap-3 rounded-xl border border-leaf-deep/20 bg-leaf-soft p-4 text-sm text-leaf-deep" role="status">
                            <x-icon name="check" :size="18" class="mt-0.5 flex-none" />
                            <div>{{ session('sukses') }}</div>
                        </div>
                    @endif
                    @if (session('peringatan'))
                        <div class="mt-3 flex items-start gap-3 rounded-xl border border-sun-deep/20 bg-sun-soft p-4 text-sm text-sun-deep" role="alert">
                            <x-icon name="alert" :size="18" class="mt-0.5 flex-none" />
                            <div>{{ session('peringatan') }}</div>
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="mt-3 rounded-xl border border-coral-deep/20 bg-coral-soft p-4 text-sm text-coral-deep" role="alert">
                            <ul class="list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $pesan)
                                    <li>{{ $pesan }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif

            <section class="bg-page">
                <div class="mx-auto grid w-full max-w-6xl gap-10 px-5 py-10 sm:px-8 sm:py-14 lg:grid-cols-12 lg:gap-14 lg:py-20">
                    <div class="lg:col-span-7">
                        <h1 class="max-w-2xl font-display text-4xl font-extrabold leading-[1.05] tracking-tight text-ink sm:text-5xl">
                            Kuis kelas yang berjalan di ruang organisasi masing-masing.
                        </h1>
                        <p class="mt-5 max-w-xl text-lg text-ink-muted">
                            Satu instalasi melayani banyak organisasi. Peserta masuk lewat organisasinya, dan setiap organisasi mengelola kelas, kuis, soal, serta laporan tanpa bercampur dengan organisasi lain.
                        </p>

                        <dl class="mt-8 grid gap-x-8 gap-y-4 border-t border-outline pt-6 sm:grid-cols-3">
                            <div>
                                <dt class="text-sm font-bold text-ink">Peserta</dt>
                                <dd class="mt-1 text-sm text-ink-muted">Akun dapat menjadi anggota lebih dari satu organisasi, dengan satu organisasi aktif pada satu waktu.</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-bold text-ink">Sesi realtime</dt>
                                <dd class="mt-1 text-sm text-ink-muted">Admin membuka lobi, memulai sesi, dan memantau peserta serta kejadian selama kuis berjalan.</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-bold text-ink">Penilaian</dt>
                                <dd class="mt-1 text-sm text-ink-muted">Soal pilihan ganda dinilai otomatis. Essay dapat memakai saran AI, dengan nilai akhir ditetapkan manusia.</dd>
                            </div>
                        </dl>
                    </div>

                    <div id="cari" class="lg:col-span-5">
                        <div class="rounded-2xl border border-outline bg-page p-5 shadow-lift sm:p-6">
                            <h2 class="font-display text-2xl font-extrabold tracking-tight text-ink">Masuk ke organisasi</h2>
                            <p class="mt-2 text-sm text-ink-muted">
                                Ketik nama atau alamat organisasi. Login hanya tersedia di halaman organisasi yang terdaftar pada akunmu.
                            </p>

                            <form method="GET" action="{{ route('home') }}#cari" class="mt-5" role="search">
                                <label for="q" class="label">Nama atau alamat organisasi</label>
                                <div class="mt-1.5 flex flex-col gap-2 sm:flex-row">
                                    <div class="relative min-w-0 flex-1">
                                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-ink-faint">
                                            <x-icon name="search" :size="18" />
                                        </span>
                                        <input
                                            id="q"
                                            name="q"
                                            type="search"
                                            value="{{ $q }}"
                                            maxlength="80"
                                            autocomplete="off"
                                            placeholder="Contoh: stikom atau nama kampus"
                                            aria-describedby="q-help"
                                            class="input w-full pl-10"
                                        >
                                    </div>
                                    <button type="submit" class="btn btn-primary min-h-11 px-5 py-2 text-sm">Cari organisasi</button>
                                </div>
                                <p id="q-help" class="help mt-2">Hanya organisasi aktif yang muncul. Tautan login juga bisa diminta langsung ke admin organisasi.</p>
                            </form>

                            @if ($q !== '')
                                <div class="mt-5 border-t border-outline pt-4" aria-live="polite">
                                    @if ($organizations->isEmpty())
                                        <p class="text-sm font-bold text-ink">Tidak ada organisasi aktif yang cocok dengan "{{ $q }}".</p>
                                        <p class="mt-1 text-sm text-ink-muted">Periksa ejaan nama atau alamatnya. Kamu juga bisa meminta tautan login ke admin organisasi.</p>
                                    @else
                                        <p class="text-sm font-bold text-ink">{{ $organizations->count() }} organisasi ditemukan</p>
                                        <ul class="mt-1 divide-y divide-outline">
                                            @foreach ($organizations as $organization)
                                                <li class="flex items-center justify-between gap-3 py-3">
                                                    <div class="min-w-0">
                                                        <p class="truncate font-display font-bold text-ink">{{ $organization->name }}</p>
                                                        <p class="truncate text-sm text-ink-subtle">/o/{{ $organization->slug }}/login</p>
                                                    </div>
                                                    <a href="{{ route('organizations.login', $organization) }}" class="btn btn-secondary min-h-11 flex-none px-4 py-2 text-sm">
                                                        Masuk
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto w-full max-w-6xl px-5 py-10 sm:px-8 sm:py-14">
                <div class="grid gap-10 lg:grid-cols-12 lg:gap-14">
                    <div class="lg:col-span-4">
                        <h2 class="font-display text-3xl font-extrabold tracking-tight text-ink">Yang dikelola tiap organisasi</h2>
                        <p class="mt-3 text-base text-ink-muted">
                            Batas organisasi berlaku pada data dan akses, bukan hanya pada tampilan. Admin organisasi tidak melihat data organisasi lain.
                        </p>
                    </div>

                    <div class="grid gap-x-10 gap-y-8 sm:grid-cols-2 lg:col-span-8">
                        <div class="border-t border-outline pt-4 sm:col-span-2">
                            <h3 class="font-display text-xl font-bold text-ink">Kelas dan peserta</h3>
                            <p class="mt-2 text-base text-ink-muted">Buat kelas, atur cover, tambahkan peserta satu per satu atau lewat impor. Peserta dapat masuk kelas sendiri memakai PIN bila kebijakan organisasi mengizinkannya.</p>
                        </div>
                        <div class="border-t border-outline pt-4">
                            <h3 class="font-display text-xl font-bold text-ink">Kuis dan bank soal</h3>
                            <p class="mt-2 text-base text-ink-muted">Susun soal pilihan ganda dan essay, impor dari CSV, atau buat soal dengan provider AI yang diatur organisasi. Soal tersimpan di bank soal organisasi.</p>
                        </div>
                        <div class="border-t border-outline pt-4">
                            <h3 class="font-display text-xl font-bold text-ink">Sesi dan pemantauan</h3>
                            <p class="mt-2 text-base text-ink-muted">Jalankan sesi dengan lobi, pilih peserta, lalu pantau jawaban dan kejadian seperti keluar layar penuh atau berpindah tab.</p>
                        </div>
                        <div class="border-t border-outline pt-4">
                            <h3 class="font-display text-xl font-bold text-ink">Nilai dan laporan</h3>
                            <p class="mt-2 text-base text-ink-muted">Tinjau jawaban essay, tetapkan nilai akhir, lalu unduh laporan sesi atau berita acara untuk arsip kelas.</p>
                        </div>
                        <div class="border-t border-outline pt-4">
                            <h3 class="font-display text-xl font-bold text-ink">Identitas organisasi</h3>
                            <p class="mt-2 text-base text-ink-muted">Nama, logo, warna utama, judul login, dan deskripsi singkat diatur per organisasi oleh pengelolanya.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-y border-outline bg-page">
                <div class="mx-auto grid w-full max-w-6xl gap-10 px-5 py-10 sm:px-8 sm:py-14 lg:grid-cols-2 lg:gap-14">
                    <div>
                        <h2 class="font-display text-3xl font-extrabold tracking-tight text-ink">Dari kelas sampai nilai</h2>
                        <ol class="mt-6 space-y-5">
                            <li class="flex gap-4">
                                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-outline-strong font-display text-sm font-bold text-ink-muted" aria-hidden="true">1</span>
                                <p class="text-base text-ink-muted"><span class="font-bold text-ink">Admin menyiapkan kelas dan kuis.</span> Peserta ditambahkan ke kelas, soal disusun, dan sesi dijadwalkan.</p>
                            </li>
                            <li class="flex gap-4">
                                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-outline-strong font-display text-sm font-bold text-ink-muted" aria-hidden="true">2</span>
                                <p class="text-base text-ink-muted"><span class="font-bold text-ink">Peserta masuk lewat organisasinya.</span> Gunakan pencarian di halaman ini atau tautan login yang dibagikan admin.</p>
                            </li>
                            <li class="flex gap-4">
                                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-outline-strong font-display text-sm font-bold text-ink-muted" aria-hidden="true">3</span>
                                <p class="text-base text-ink-muted"><span class="font-bold text-ink">Sesi berjalan dan dipantau.</span> Admin melihat peserta yang bergabung dan kejadian selama kuis.</p>
                            </li>
                            <li class="flex gap-4">
                                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-outline-strong font-display text-sm font-bold text-ink-muted" aria-hidden="true">4</span>
                                <p class="text-base text-ink-muted"><span class="font-bold text-ink">Nilai dan laporan keluar di workspace.</span> Hasil sesi dapat diunduh tanpa berpindah ke organisasi lain.</p>
                            </li>
                        </ol>
                    </div>

                    <aside class="self-start rounded-2xl border border-outline bg-surface-sunken p-6">
                        <h2 class="font-display text-2xl font-extrabold tracking-tight text-ink">Punya organisasi sendiri?</h2>
                        <p class="mt-3 text-base text-ink-muted">
                            Pendaftaran organisasi membuka workspace baru beserta akun owner. Owner kemudian mengundang admin, mendaftarkan peserta, dan mengatur identitas workspace.
                        </p>
                        <p class="mt-3 text-base text-ink-muted">
                            Pendaftaran publik dibuka bertahap. Bila formulir sedang tidak tersedia, hubungi pengelola platform.
                        </p>
                        <a href="{{ route('register.organization') }}" class="btn btn-primary mt-5 w-full sm:w-auto">Daftarkan organisasi</a>
                    </aside>
                </div>
            </section>
        </main>

        <footer class="mt-auto">
            <div class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-5 py-6 text-sm text-ink-muted sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <p>&copy; {{ date('Y') }} {{ $appSettings->site_name }}</p>
                <nav class="flex flex-wrap items-center gap-x-5 gap-y-2" aria-label="Tautan footer">
                    <a href="{{ route('terms.show') }}" class="inline-flex min-h-11 items-center font-semibold hover:text-ink">Syarat</a>
                    <a href="{{ route('privacy.show') }}" class="inline-flex min-h-11 items-center font-semibold hover:text-ink">Privasi</a>
                    <a href="{{ route('master.login') }}" class="inline-flex min-h-11 items-center font-semibold hover:text-ink">Masuk master platform</a>
                </nav>
            </div>
        </footer>
    </div>
</x-layouts.guest>