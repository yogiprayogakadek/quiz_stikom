@php
    $ruteTemplate = isset($quiz) ? route('admin.quizzes.questions.template', $quiz) : route('admin.questions.template');
    $ruteStore = isset($quiz) ? route('admin.quizzes.questions.import.store', $quiz) : route('admin.quizzes.index');
    $ruteKembali = isset($quiz) ? route('admin.quizzes.questions.index', $quiz) : route('admin.quizzes.index');
@endphp

<x-layouts.admin :judul="isset($quiz) ? 'Impor Soal: ' . $quiz->judul : 'Impor Soal'" keterangan="Tambahkan banyak soal kuis sekaligus dari file CSV.">
    <x-slot:aksi>
        <a href="{{ $ruteTemplate }}" class="btn btn-secondary">
            <x-icon name="download" :size="20" />
            Unduh template CSV
        </a>
        <a href="{{ $ruteKembali }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

    <div class="grid gap-6 lg:grid-cols-[1fr_1.1fr]">
        <section class="card">
            <h2 class="text-2xl">Unggah file CSV</h2>
            <p class="mt-2 text-base text-ink-muted">
                File CSV wajib punya kolom <span class="font-mono text-base text-ink">teks</span>. Soal pilihan ganda juga butuh <span class="font-mono text-base text-ink">opsi_a,opsi_b,jawaban_benar</span>.
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
                                <option value="">-- Pilih Kuis --</option>
                                @foreach ($quizzes as $itemKuis)
                                    <option value="{{ $itemKuis->id }}" @selected(old('quiz_id') == $itemKuis->id)>{{ $itemKuis->judul }}</option>
                                @endforeach
                            </select>
                            @error('quiz_id') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div class="pt-2">
                        <button type="submit" class="btn btn-primary" :disabled="uploading">
                            <x-icon name="upload" :size="20" />
                            <span x-text="uploading ? 'Mengimpor...' : 'Impor sekarang'">Impor sekarang</span>
                        </button>
                    </div>
                </form>
            </x-import-progress>

            <div class="mt-6 rounded-2xl bg-surface p-4 text-base text-ink-muted">
                <p class="font-bold text-ink">Panduan Pengisian CSV</p>
                <ul class="mt-2 list-disc space-y-1.5 pl-5">
                    <li><strong class="text-ink">teks</strong>: Kalimat pertanyaan atau soal kuis (wajib).</li>
                    <li><strong class="text-ink">tipe</strong>: <span class="font-mono text-ink">pilihan_ganda</span> atau <span class="font-mono text-ink">essay</span>. Default pilihan ganda jika dikosongkan.</li>
                    <li><strong class="text-ink">bobot</strong>: Poin untuk pilihan ganda atau nilai maksimal essay. Default 1.</li>
                    <li><strong class="text-ink">opsi_a &amp; opsi_b</strong>: Pilihan jawaban minimal 2 opsi (wajib untuk pilihan ganda). Opsi C, D, E bersifat opsional.</li>
                    <li><strong class="text-ink">jawaban_benar</strong>: Kunci jawaban benar untuk pilihan ganda. Tulis huruf pilihan: <span class="font-mono font-bold text-ink">A</span>, <span class="font-mono font-bold text-ink">B</span>, <span class="font-mono font-bold text-ink">C</span>, <span class="font-mono font-bold text-ink">D</span>, atau <span class="font-mono font-bold text-ink">E</span>.</li>
                    <li><strong class="text-ink">kunci_essay</strong>: Panduan penilaian essay (opsional, tidak wajib).</li>
                    <li><strong class="text-ink">durasi_detik</strong>: Waktu pengerjaan dalam detik (5 - 300 detik). Default 30 detik jika dikosongkan.</li>
                    <li><strong class="text-ink">pembahasan</strong>: Catatan atau penjelasan jawaban (opsional).</li>
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
            <p class="mt-2 text-base text-ink-muted">Baris pertama wajib berisi header kolom berikut ini:</p>
            <div class="mt-3 overflow-x-auto rounded-xl border border-outline bg-page p-3 font-mono text-xs">
                teks,tipe,bobot,opsi_a,opsi_b,opsi_c,opsi_d,opsi_e,jawaban_benar,durasi_detik,pembahasan,kunci_essay
            </div>

            <p class="mt-4 text-base font-bold text-ink">Contoh 1: Pilihan Ganda (4 Opsi)</p>
            <div class="mt-2 overflow-x-auto rounded-xl border border-outline bg-page p-3 font-mono text-xs">
                "Ibu kota negara Indonesia?","pilihan_ganda",1,"Jakarta","Bandung","Surabaya","Medan","",A,30,"Jakarta ibukota negara.",
            </div>

            <p class="mt-4 text-base font-bold text-ink">Contoh 2: Soal Essay (Uraian)</p>
            <div class="mt-2 overflow-x-auto rounded-xl border border-outline bg-page p-3 font-mono text-xs">
                "Jelaskan perbedaan HTTP dan HTTPS.","essay",10,,,,,,,60,"Penjelasan singkat HTTP dan HTTPS.","HTTP data plain teks, HTTPS data terenkripsi TLS."
            </div>
        </section>
    </div>
</x-layouts.admin>
