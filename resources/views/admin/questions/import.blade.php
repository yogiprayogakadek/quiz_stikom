<x-layouts.admin judul="Impor soal" keterangan="Tambahkan banyak soal kuis sekaligus ke bank soal dari file CSV.">
    <x-slot:aksi>
        <a href="{{ route('admin.questions.template') }}" class="btn btn-secondary">
            <x-icon name="download" :size="20" />
            Unduh template CSV
        </a>
        <a href="{{ route('admin.questions.index') }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="20" />
            Kembali
        </a>
    </x-slot:aksi>

    <div class="grid gap-6 lg:grid-cols-[1fr_1.1fr]">
        <section class="card">
            <h2 class="text-2xl">Unggah file CSV</h2>
            <p class="mt-2 text-base text-ink-muted">
                File harus berformat CSV dengan kolom minimal <span class="font-mono text-base text-ink">teks,opsi_a,opsi_b,jawaban_benar</span>.
            </p>

            <form method="POST" action="{{ route('admin.questions.import.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="file" class="label">File CSV Soal</label>
                    <input id="file" name="file" type="file" accept=".csv,text/csv" class="input py-2" required @error('file') aria-invalid="true" @enderror>
                    @error('file') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="quiz_id" class="label">Masukkan langsung ke kuis (opsional)</label>
                    <select id="quiz_id" name="quiz_id" class="input">
                        <option value="">-- Hanya simpan ke Bank Soal (tanpa kuis) --</option>
                        @foreach ($quizzes as $quiz)
                            <option value="{{ $quiz->id }}" @selected(old('quiz_id') == $quiz->id)>{{ $quiz->judul }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-sm text-ink-muted">Jika dipilih, seluruh soal yang berhasil diimpor otomatis ditautkan ke kuis ini.</p>
                    @error('quiz_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="pt-2">
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="upload" :size="20" />
                        Impor sekarang
                    </button>
                </div>
            </form>

            <div class="mt-6 rounded-2xl bg-surface p-4 text-base text-ink-muted">
                <p class="font-bold text-ink">Panduan Pengisian CSV</p>
                <ul class="mt-2 list-disc space-y-1.5 pl-5">
                    <li><strong class="text-ink">teks</strong>: Kalimat pertanyaan atau soal kuis (wajib).</li>
                    <li><strong class="text-ink">opsi_a &amp; opsi_b</strong>: Pilihan jawaban minimal 2 opsi (wajib). Opsi C, D, E bersifat opsional.</li>
                    <li><strong class="text-ink">jawaban_benar</strong>: Kunci jawaban benar. Tulis huruf pilihan: <span class="font-mono font-bold text-ink">A</span>, <span class="font-mono font-bold text-ink">B</span>, <span class="font-mono font-bold text-ink">C</span>, <span class="font-mono font-bold text-ink">D</span>, atau <span class="font-mono font-bold text-ink">E</span>.</li>
                    <li><strong class="text-ink">durasi_detik</strong>: Waktu pengerjaan dalam detik (5 - 300 detik). Default 30 detik jika dikosongkan.</li>
                    <li><strong class="text-ink">pembahasan</strong>: Catatan atau penjelasan jawaban (opsional).</li>
                </ul>
            </div>
        </section>

        <section class="card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-2xl">Format &amp; Contoh Isi</h2>
                <a href="{{ route('admin.questions.template') }}" class="text-sm font-bold text-brand-deep hover:underline">
                    Unduh file contoh (.csv)
                </a>
            </div>
            <p class="mt-2 text-base text-ink-muted">Baris pertama wajib berisi header kolom berikut ini:</p>

            <div class="mt-4 overflow-x-auto rounded-2xl border border-outline">
                <table class="table text-sm">
                    <thead>
                        <tr>
                            <th scope="col">teks</th>
                            <th scope="col">opsi_a</th>
                            <th scope="col">opsi_b</th>
                            <th scope="col">opsi_c</th>
                            <th scope="col">opsi_d</th>
                            <th scope="col">kunci</th>
                            <th scope="col">durasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Ibu kota negara Indonesia saat ini adalah?</td>
                            <td>Jakarta</td>
                            <td>Bandung</td>
                            <td>Surabaya</td>
                            <td>Medan</td>
                            <td class="font-mono font-bold text-leaf-deep">A</td>
                            <td class="font-mono">30</td>
                        </tr>
                        <tr>
                            <td>Berapakah hasil dari 15 dikalikan 4?</td>
                            <td>45</td>
                            <td>50</td>
                            <td>60</td>
                            <td>65</td>
                            <td class="font-mono font-bold text-leaf-deep">C</td>
                            <td class="font-mono">20</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-5 space-y-2 text-sm text-ink-muted">
                <p>
                    <strong class="text-ink">Tips:</strong> Jika teks pertanyaan atau pilihan mengandung tanda koma, pastikan teks diapit dengan tanda petik ganda (contoh: <span class="font-mono">"Berapa hasil 1,5 + 2,5?"</span>).
                </p>
                <p>
                    Jika mengedit di Microsoft Excel, pilih menu <strong>Save As &rarr; CSV UTF-8 (Comma delimited) (*.csv)</strong> agar format karakter tetap terjaga.
                </p>
            </div>
        </section>
    </div>
</x-layouts.admin>
