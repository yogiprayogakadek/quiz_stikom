@php($organization = $organization ?? null)

<x-layouts.guest :judul="'Syarat Penggunaan'.($organization ? ' - '.$organization->name : '')">
    <div class="mx-auto min-h-screen w-full max-w-3xl px-5 py-10 sm:px-8 sm:py-16">
        <div class="mb-6 flex items-center justify-between gap-4">
            <a href="{{ $organization ? route('organizations.login', $organization) : route('home') }}" class="text-sm font-bold text-brand-deep underline-offset-2 hover:underline">
                {{ $organization ? 'Kembali ke login organisasi' : 'Kembali ke halaman utama' }}
            </a>
            <a href="{{ $organization ? route('organizations.privacy', $organization) : route('privacy.show') }}" class="text-sm font-bold text-brand-deep underline-offset-2 hover:underline">Kebijakan Privasi</a>
        </div>

        <article class="card space-y-6 p-6 sm:p-9">
            <header>
                <p class="text-sm font-bold text-brand-deep">Dokumen legal</p>
                <h1 class="mt-2 font-display text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">Syarat Penggunaan</h1>
                <p class="mt-2 text-sm text-ink-muted">{{ $organization ? 'Berlaku untuk workspace '.$organization->name.'.' : 'Berlaku untuk akun dan workspace yang dibuat di platform ini.' }}</p>
            </header>

            @if (filled($customContent ?? null))
                <div class="rounded-xl border border-outline bg-surface-sunken p-4 text-sm text-ink-muted" role="note">
                    Dokumen ini diisi oleh pengelola workspace. Hubungi admin workspace untuk pertanyaan tentang ketentuan ini.
                </div>
                <x-legal-content :content="$customContent" />
            @else
            <div class="rounded-xl border border-sun-deep/30 bg-sun-soft p-4 text-sm text-sun-deep" role="note">
                <p class="font-bold">Draf, belum ditinjau secara hukum.</p>
                <p class="mt-1">Teks ini adalah kerangka operasional. Ganti dengan dokumen final sebelum dipakai sebagai dasar hukum.</p>
            </div>

            <section class="space-y-2">
                <h2 class="text-xl text-ink">1. Ruang lingkup layanan</h2>
                <p class="text-base leading-relaxed text-ink-muted">Platform menyediakan pembuatan kuis, pelaksanaan sesi, penilaian, dan laporan. Fitur AI bersifat bantuan dan hasilnya tetap perlu diperiksa pengguna yang berwenang.</p>
            </section>

            <section class="space-y-2">
                <h2 class="text-xl text-ink">2. Akun dan keamanan</h2>
                <p class="text-base leading-relaxed text-ink-muted">Pemilik workspace bertanggung jawab atas undangan anggota, pembagian peran, dan kerahasiaan kredensial. Laporkan akses yang tidak sah melalui kontak di bawah.</p>
            </section>

            <section class="space-y-2">
                <h2 class="text-xl text-ink">3. Penggunaan yang dilarang</h2>
                <p class="text-base leading-relaxed text-ink-muted">Jangan mengunggah konten melanggar hukum, mencoba mengakses data workspace lain, mengganggu layanan, atau memakai platform untuk menipu peserta dan pihak lain.</p>
            </section>

            <section class="space-y-2">
                <h2 class="text-xl text-ink">4. Integritas kuis</h2>
                <p class="text-base leading-relaxed text-ink-muted">Pemilik workspace menentukan aturan sesi dan menindaklanjuti laporan pelanggaran. Log kejadian adalah sinyal bantu, bukan bukti tunggal, dan tidak mendiskualifikasi peserta secara otomatis.</p>
            </section>

            <section class="space-y-2">
                <h2 class="text-xl text-ink">5. Bantuan AI</h2>
                <p class="text-base leading-relaxed text-ink-muted">Saran soal dan nilai AI dapat salah. Pengguna wajib meninjau hasil sebelum dipakai. Data yang dikirim ke penyedia AI mengikuti pengaturan workspace dan kebijakan penyedia tersebut.</p>
            </section>

            <section class="space-y-2">
                <h2 class="text-xl text-ink">6. Penangguhan dan perubahan</h2>
                <p class="text-base leading-relaxed text-ink-muted">Akses dapat ditangguhkan untuk pelanggaran, penyalahgunaan, atau alasan keamanan. Ketentuan dapat diperbarui dan versi baru berlaku setelah dipublikasikan di halaman ini.</p>
            </section>

            <section class="space-y-2">
                <h2 class="text-xl text-ink">7. Kontak</h2>
                <p class="text-base leading-relaxed text-ink-muted">Pertanyaan tentang ketentuan ini dapat dikirim ke <a href="mailto:{{ $contactEmail }}" class="font-bold text-brand-deep underline-offset-2 hover:underline">{{ $contactEmail }}</a>.</p>
            </section>
            @endif
        </article>
    </div>
</x-layouts.guest>
