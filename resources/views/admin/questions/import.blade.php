@php
    $ruteTemplate = isset($quiz) ? route('admin.quizzes.questions.template', $quiz) : route('admin.questions.template');
    $ruteStore = isset($quiz) ? route('admin.quizzes.questions.import.store', $quiz) : route('admin.quizzes.index');
    $ruteKembali = isset($quiz) ? route('admin.quizzes.questions.index', $quiz) : route('admin.quizzes.index');
    $tabAwal = $errors->has('file') ? 'csv' : (isset($quiz) ? 'tempel' : 'csv');
@endphp

<x-layouts.admin :judul="isset($quiz) ? 'Impor & Tempel Soal: ' . $quiz->judul : 'Impor Soal'" keterangan="Tempel naskah soal langsung dari dokumen Word/Docs atau unggah berkas CSV.">
    <x-slot:aksi>
        <div class="flex flex-wrap items-center gap-2">
            @if (isset($quiz))
                <a href="{{ route('admin.quizzes.questions.bank', $quiz) }}" class="btn btn-secondary min-h-11">
                    <x-icon name="copy" :size="18" />
                    <span>Ambil dari Kuis Lain</span>
                </a>
            @endif
            <a href="{{ $ruteTemplate }}" class="btn btn-secondary min-h-11">
                <x-icon name="download" :size="20" />
                <span>Unduh template CSV</span>
            </a>
            <a href="{{ $ruteKembali }}" class="btn btn-ghost min-h-11">
                <x-icon name="arrow-left" :size="20" />
                <span>Kembali</span>
            </a>
        </div>
    </x-slot:aksi>

    <div
        x-data="{
            tab: {{ Illuminate\Support\Js::from($tabAwal) }},
            naskah: {{ Illuminate\Support\Js::from(old('raw_text', '')) }},
            memuatPratinjau: false,
            errorPratinjau: '',
            pratinjauValid: [],
            pratinjauGagal: [],
            sudahPratinjau: false,
            async cekPratinjau() {
                if (!this.naskah.trim()) {
                    this.errorPratinjau = 'Tempel minimal satu soal terlebih dahulu.';
                    this.sudahPratinjau = false;
                    return;
                }
                this.memuatPratinjau = true;
                this.errorPratinjau = '';
                try {
                    const res = await fetch('{{ isset($quiz) ? route('admin.quizzes.questions.quick-paste.preview', $quiz) : '' }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                        },
                        body: JSON.stringify({ raw_text: this.naskah })
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.errorPratinjau = data.message || 'Gagal memeriksa format soal.';
                        return;
                    }
                    this.pratinjauValid = Array.isArray(data.valid) ? data.valid : [];
                    this.pratinjauGagal = Array.isArray(data.errors) ? data.errors : [];
                    this.sudahPratinjau = true;
                } catch (e) {
                    this.errorPratinjau = 'Koneksi terputus saat memuat pratinjau. Anda tetap bisa menekan Simpan Soal.';
                } finally {
                    this.memuatPratinjau = false;
                }
            }
        }"
        class="space-y-6"
    >
        @if (isset($quiz))
            <div role="tablist" aria-label="Metode tambah banyak soal" class="inline-flex flex-wrap gap-2 rounded-2xl border border-outline bg-surface p-1.5">
                <button
                    type="button"
                    role="tab"
                    id="tab-btn-tempel"
                    aria-controls="panel-tempel"
                    :aria-selected="tab === 'tempel'"
                    @click="tab = 'tempel'"
                    class="min-h-11 rounded-xl px-4 py-2 text-sm font-bold transition"
                    :class="tab === 'tempel' ? 'bg-brand-deep text-white shadow-2xs' : 'text-ink-muted hover:text-ink'"
                >
                    Tempel Cepat Naskah Soal
                </button>
                <button
                    type="button"
                    role="tab"
                    id="tab-btn-csv"
                    aria-controls="panel-csv"
                    :aria-selected="tab === 'csv'"
                    @click="tab = 'csv'"
                    class="min-h-11 rounded-xl px-4 py-2 text-sm font-bold transition"
                    :class="tab === 'csv' ? 'bg-brand-deep text-white shadow-2xs' : 'text-ink-muted hover:text-ink'"
                >
                    Unggah File CSV
                </button>
            </div>
        @endif

        @if (isset($quiz))
            <div id="panel-tempel" role="tabpanel" aria-labelledby="tab-btn-tempel" x-show="tab === 'tempel'" class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                <section class="card">
                    <h2 class="text-2xl">Tempel langsung dari Word atau Google Docs</h2>
                    <p class="mt-2 text-base text-ink-muted">
                        Pisahkan antarsoal dengan satu baris kosong. Tandai pilihan benar memakai tanda bintang <span class="font-mono font-bold text-ink">*</span> di depan huruf opsi atau tambahkan baris <span class="font-mono font-bold text-ink">Kunci: B</span>.
                    </p>

                    <form method="POST" action="{{ route('admin.quizzes.questions.quick-paste.store', $quiz) }}" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label for="raw_text" class="label">Naskah soal</label>
                            <textarea
                                id="raw_text"
                                name="raw_text"
                                rows="12"
                                x-model="naskah"
                                class="input font-mono text-sm leading-relaxed"
                                required
                                placeholder="1. Protokol pengirim halaman web terenkripsi adalah?&#10;A. HTTP&#10;*B. HTTPS&#10;C. FTP&#10;D. Telnet&#10;&#10;2. [Essay | Bobot: 10] Jelaskan fungsi subnet mask pada jaringan IPv4.&#10;Kunci: Memisahkan porsi network ID dan host ID pada alamat IP."
                                @error('raw_text') aria-invalid="true" @enderror
                            >{{ old('raw_text') }}</textarea>
                            @error('raw_text') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <label x-show="pratinjauGagal.length > 0" x-cloak class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-outline bg-surface px-3.5 py-2.5 text-sm text-ink">
                            <input type="checkbox" name="abaikan_gagal" value="1" class="h-4 w-4">
                            <span>Tetap simpan soal yang valid dan lewati blok yang bermasalah</span>
                        </label>

                        <div class="flex flex-wrap items-center gap-3 pt-1">
                            <button type="button" @click="cekPratinjau()" :disabled="memuatPratinjau" class="btn btn-secondary min-h-11">
                                <span x-text="memuatPratinjau ? 'Memeriksa naskah...' : 'Pratinjau hasil baca'">Pratinjau hasil baca</span>
                            </button>
                            <button type="submit" class="btn btn-primary min-h-11">
                                Simpan soal ke kuis
                            </button>
                        </div>
                    </form>

                    <div x-show="errorPratinjau" x-cloak role="alert" class="mt-4 rounded-xl border border-coral-deep bg-coral-soft p-3.5 text-sm font-semibold text-ink" x-text="errorPratinjau"></div>

                    <div x-show="sudahPratinjau" x-cloak class="mt-6 space-y-4 border-t border-outline pt-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-lg font-bold text-ink">Hasil pratinjau</h3>
                            <div class="flex flex-wrap gap-2">
                                <span class="pill pill-leaf" x-text="pratinjauValid.length + ' siap disimpan'"></span>
                                <template x-if="pratinjauGagal.length > 0">
                                    <span class="pill pill-sun" x-text="pratinjauGagal.length + ' perlu diperbaiki'"></span>
                                </template>
                            </div>
                        </div>

                        <template x-if="pratinjauValid.length === 0 && pratinjauGagal.length === 0">
                            <p class="rounded-xl bg-surface p-4 text-sm text-ink-muted">Belum ada soal yang terbaca. Pastikan setiap soal dipisahkan baris kosong.</p>
                        </template>

                        <template x-if="pratinjauGagal.length > 0">
                            <ul class="space-y-2">
                                <template x-for="err in pratinjauGagal" :key="'err-' + err.nomor">
                                    <li class="rounded-xl border border-coral-deep bg-coral-soft/70 p-3 text-sm text-ink">
                                        <p class="font-bold">Blok #<span x-text="err.nomor"></span>: <span x-text="err.alasan"></span></p>
                                        <p class="mt-1 font-mono text-xs text-ink-muted" x-text="err.potongan"></p>
                                    </li>
                                </template>
                            </ul>
                        </template>

                        <template x-if="pratinjauValid.length > 0">
                            <ol class="max-h-96 space-y-2.5 overflow-y-auto pr-1">
                                <template x-for="soal in pratinjauValid" :key="'ok-' + soal.nomor">
                                    <li class="rounded-xl border border-outline bg-page p-3.5 text-sm">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="pill pill-brand text-xs" x-text="'#' + soal.nomor"></span>
                                            <span class="pill pill-neutral text-xs" x-text="soal.tipe.replace('_', ' ')"></span>
                                            <span class="pill pill-neutral text-xs" x-text="'Bobot ' + soal.bobot"></span>
                                        </div>
                                        <p class="mt-2 font-bold text-ink" x-text="soal.teks"></p>
                                        <template x-if="soal.opsi && soal.opsi.length > 0">
                                            <ul class="mt-2 space-y-1 text-xs text-ink-muted">
                                                <template x-for="(op, idx) in soal.opsi" :key="idx">
                                                    <li :class="op.is_correct ? 'font-bold text-leaf-deep' : ''">
                                                        <span x-text="String.fromCharCode(65 + idx) + '. ' + op.teks"></span>
                                                        <span x-show="op.is_correct"> (Kunci)</span>
                                                    </li>
                                                </template>
                                            </ul>
                                        </template>
                                    </li>
                                </template>
                            </ol>
                        </template>
                    </div>
                </section>

                <section class="card space-y-4">
                    <h2 class="text-2xl">Contoh format penulisan</h2>
                    <p class="text-base text-ink-muted">Salin pola di bawah ini bila ingin menyusun cepat di aplikasi pengolah kata:</p>

                    <div>
                        <p class="text-sm font-bold text-ink">1. Pilihan Ganda</p>
                        <pre class="mt-1.5 overflow-x-auto rounded-xl border border-outline bg-page p-3 font-mono text-xs leading-relaxed text-ink">1. Port standar layanan SSH adalah?
*A. 22
B. 23
C. 80
D. 443
Pembahasan: Port 22 digunakan oleh SSH, sedangkan 23 oleh Telnet.</pre>
                    </div>

                    <div>
                        <p class="text-sm font-bold text-ink">2. Essay dengan Bobot</p>
                        <pre class="mt-1.5 overflow-x-auto rounded-xl border border-outline bg-page p-3 font-mono text-xs leading-relaxed text-ink">2. [Essay | Bobot: 15] Mengapa protokol HTTPS lebih aman dibanding HTTP pada jaringan publik?
Kunci: HTTPS mengenkripsi komunikasi menggunakan TLS sehingga isi paket tidak mudah disadap.</pre>
                    </div>

                    <div>
                        <p class="text-sm font-bold text-ink">3. Pilihan Majemuk &amp; Isian Singkat</p>
                        <pre class="mt-1.5 overflow-x-auto rounded-xl border border-outline bg-page p-3 font-mono text-xs leading-relaxed text-ink">3. [Pilihan Majemuk | Parsial | Bobot: 4] Manakah alamat IP privat kelas C dan A?
*A. 192.168.1.10
*B. 10.0.0.5
C. 8.8.8.8
D. 1.1.1.1

4. [Isian Singkat | Bobot: 2] Tulis kepanjangan dari DNS!
Kunci: Domain Name System | domain name system</pre>
                    </div>
                </section>
            </div>
        @endif

        <div id="panel-csv" role="tabpanel" aria-labelledby="tab-btn-csv" x-show="tab === 'csv'" @if (isset($quiz)) x-cloak @endif class="grid gap-6 lg:grid-cols-[1fr_1.1fr]">
            <section class="card">
                <h2 class="text-2xl">Unggah file CSV</h2>
                <p class="mt-2 text-base text-ink-muted">
                    Sistem mengenali pemisah koma (<span class="font-mono text-ink">,</span>) maupun titik koma (<span class="font-mono text-ink">;</span>) dari Microsoft Excel Indonesia secara otomatis.
                </p>

                <x-import-progress>
                    <form method="POST" action="{{ $ruteStore }}" enctype="multipart/form-data" class="mt-5 space-y-4" @submit="handleSubmit($event)">
                        @csrf
                        <div>
                            <label for="file" class="label">File CSV Soal</label>
                            <input id="file" name="file" type="file" accept=".csv,text/csv" class="input py-2" required @change="handleFileSelect($event)" @error('file') aria-invalid="true" @enderror>
                            @error('file') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        @if (!isset($quiz))
                            <div>
                                <label for="quiz_id" class="label">Pilih Kuis Tujuan</label>
                                <select id="quiz_id" name="quiz_id" class="input" required>
                                    <option value="">Pilih kuis tujuan</option>
                                    @foreach ($quizzes as $itemKuis)
                                        <option value="{{ $itemKuis->id }}" @selected(old('quiz_id') == $itemKuis->id)>{{ $itemKuis->judul }}</option>
                                    @endforeach
                                </select>
                                @error('quiz_id') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div class="pt-2">
                            <button type="submit" class="btn btn-primary min-h-11" :disabled="uploading">
                                <x-icon name="upload" :size="20" />
                                <span x-text="uploading ? 'Mengimpor...' : 'Impor sekarang'">Impor sekarang</span>
                            </button>
                        </div>
                    </form>
                </x-import-progress>

                <div class="mt-6 rounded-2xl bg-surface p-4 text-base text-ink-muted">
                    <p class="font-bold text-ink">Panduan kolom CSV</p>
                    <ul class="mt-2 list-disc space-y-1.5 pl-5">
                        <li><strong class="text-ink">teks</strong>: Kalimat pertanyaan atau soal kuis (wajib).</li>
                        <li><strong class="text-ink">tipe</strong>: <span class="font-mono text-ink">pilihan_ganda</span> atau <span class="font-mono text-ink">essay</span>. Default pilihan ganda jika dikosongkan.</li>
                        <li><strong class="text-ink">bobot</strong>: Poin benar pilihan ganda atau nilai maksimal essay. Default 1.</li>
                        <li><strong class="text-ink">opsi_a &amp; opsi_b</strong>: Pilihan jawaban minimal 2 opsi untuk pilihan ganda. Opsi C sampai F opsional.</li>
                        <li><strong class="text-ink">jawaban_benar</strong>: Tulis huruf kunci seperti <span class="font-mono font-bold text-ink">A</span>, <span class="font-mono font-bold text-ink">B</span>, <span class="font-mono font-bold text-ink">C</span>, atau <span class="font-mono font-bold text-ink">D</span>.</li>
                        <li><strong class="text-ink">kunci_essay</strong>: Panduan penilaian essay (opsional).</li>
                        <li><strong class="text-ink">durasi_detik</strong>: Waktu pengerjaan dalam detik (5 sampai 300 detik). Default 30 detik.</li>
                        <li><strong class="text-ink">pembahasan</strong>: Penjelasan jawaban setelah sesi selesai (opsional).</li>
                    </ul>
                </div>
            </section>

            <section class="card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-2xl">Format &amp; Contoh Isi</h2>
                    <a href="{{ $ruteTemplate }}" class="text-sm font-bold text-brand-deep hover:underline">
                        Unduh file contoh (.csv)
                    </a>
                </div>
                <p class="mt-2 text-base text-ink-muted">Baris pertama wajib berisi header kolom berikut:</p>
                <div class="mt-3 overflow-x-auto rounded-xl border border-outline bg-page p-3 font-mono text-xs">
                    teks,tipe,bobot,opsi_a,opsi_b,opsi_c,opsi_d,opsi_e,jawaban_benar,durasi_detik,pembahasan,kunci_essay
                </div>

                <p class="mt-4 text-base font-bold text-ink">Contoh 1: Pilihan Ganda (4 Opsi)</p>
                <div class="mt-2 overflow-x-auto rounded-xl border border-outline bg-page p-3 font-mono text-xs">
                    "Ibu kota negara Indonesia?","pilihan_ganda",1,"Jakarta","Bandung","Surabaya","Medan","",A,30,"Jakarta ibu kota negara.",
                </div>

                <p class="mt-4 text-base font-bold text-ink">Contoh 2: Soal Essay (Uraian)</p>
                <div class="mt-2 overflow-x-auto rounded-xl border border-outline bg-page p-3 font-mono text-xs">
                    "Jelaskan perbedaan HTTP dan HTTPS.","essay",10,,,,,,,60,"Penjelasan singkat HTTP dan HTTPS.","HTTP tanpa enkripsi, HTTPS terenkripsi TLS."
                </div>
            </section>
        </div>
    </div>
</x-layouts.admin>
