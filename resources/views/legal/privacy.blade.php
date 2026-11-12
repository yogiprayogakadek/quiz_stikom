<x-layouts.guest judul="Kebijakan Privasi">
    <div class="mx-auto min-h-screen w-full max-w-3xl px-5 py-10 sm:px-8 sm:py-16">
        <div class="mb-6 flex items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="text-sm font-bold text-brand-deep underline-offset-2 hover:underline">Kembali ke halaman utama</a>
            <a href="{{ route('terms.show') }}" class="text-sm font-bold text-brand-deep underline-offset-2 hover:underline">Syarat Penggunaan</a>
        </div>

        <article class="card space-y-6 p-6 sm:p-9">
            <header>
                <p class="text-sm font-bold text-brand-deep">Dokumen legal</p>
                <h1 class="mt-2 font-display text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">Kebijakan Privasi</h1>
                <p class="mt-2 text-sm text-ink-muted">Ringkasan data yang diproses platform dan cara mengelolanya.</p>
            </header>

            <div class="rounded-xl border border-sun-deep/30 bg-sun-soft p-4 text-sm text-sun-deep" role="note">
                <p class="font-bold">Draf, belum ditinjau secara hukum.</p>
                <p class="mt-1">Teks ini adalah kerangka operasional. Ganti dengan dokumen final sebelum dipakai sebagai dasar hukum.</p>
            </div>

            <section class="space-y-2">
                <h2 class="text-xl text-ink">1. Data yang dikumpulkan</h2>
                <p class="text-base leading-relaxed text-ink-muted">Nama, username, alamat email, peran, kelas, jawaban kuis, nilai, log kejadian sesi, dan catatan audit. Password disimpan dalam bentuk hash. Kunci API dan password SMTP disimpan terenkripsi.</p>
            </section>

            <section class="space-y-2">
                <h2 class="text-xl text-ink">2. Tujuan pemrosesan</h2>
                <p class="text-base leading-relaxed text-ink-muted">Data dipakai untuk menjalankan akun, menyelenggarakan kuis, menghitung nilai, menampilkan laporan, menjaga integritas sesi, dan membantu peninjauan oleh admin workspace.</p>
            </section>

            <section class="space-y-2">
                <h2 class="text-xl text-ink">3. Akses dan pembagian</h2>
                <p class="text-base leading-relaxed text-ink-muted">Anggota workspace hanya melihat data sesuai perannya. Admin platform dapat mengakses data operasional untuk dukungan, pemeliharaan, dan pemulihan. Data tidak dijual.</p>
            </section>

            <section class="space-y-2">
                <h2 class="text-xl text-ink">4. Penyedia AI pihak ketiga</h2>
                <p class="text-base leading-relaxed text-ink-muted">Jika workspace mengaktifkan fitur AI, potongan soal, rubrik, jawaban, atau prompt gambar dikirim ke penyedia yang dikonfigurasi workspace. Penyedia tersebut memproses data menurut kebijakannya sendiri.</p>
            </section>

            <section class="space-y-2">
                <h2 class="text-xl text-ink">5. Penyimpanan dan retensi</h2>
                <p class="text-base leading-relaxed text-ink-muted">Data disimpan selama workspace aktif dan selama diperlukan untuk operasional, audit, atau kewajiban yang berlaku. Penghapusan workspace menghapus data tenant, tetapi tidak menghapus akun pengguna yang mungkin tergabung di workspace lain.</p>
            </section>

            <section class="space-y-2">
                <h2 class="text-xl text-ink">6. Hak pengguna</h2>
                <p class="text-base leading-relaxed text-ink-muted">Pengguna dapat meminta akses, koreksi, atau penghapusan data melalui admin workspace. Permintaan yang memerlukan tindakan platform dapat dikirim ke kontak di bawah.</p>
            </section>

            <section class="space-y-2">
                <h2 class="text-xl text-ink">7. Kontak</h2>
                <p class="text-base leading-relaxed text-ink-muted">Pertanyaan privasi dapat dikirim ke <a href="mailto:{{ $contactEmail }}" class="font-bold text-brand-deep underline-offset-2 hover:underline">{{ $contactEmail }}</a>.</p>
            </section>
        </article>
    </div>
</x-layouts.guest>
