@php
    $queryParams = [
        'varian' => $meta['varian'],
        'judul_ujian' => $meta['judul_ujian'],
        'subjudul' => $meta['subjudul'],
        'mata_kuliah' => $meta['mata_kuliah'],
        'dosen' => $meta['dosen'],
        'program_studi' => $meta['program_studi'],
        'waktu_menit' => $meta['waktu_menit'],
        'sifat_ujian' => $meta['sifat_ujian'],
        'kode_cpmk' => $meta['kode_cpmk'],
        'kolom_identitas' => $meta['kolom_identitas'] ? '1' : '0',
    ];
    $downloadUrl = route('admin.quizzes.questions.pdf.download', [$quiz, ...$queryParams]);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lembar Soal PDF: {{ $quiz->judul }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .preview-shell {
            max-width: 1240px;
            margin: 0 auto;
            padding: 1.25rem;
        }
        .a4-paper {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.07);
            padding: 1.5rem;
        }
        @media (min-width: 768px) {
            .preview-shell {
                padding: 2rem;
            }
            .a4-paper {
                padding: 2.5rem 3rem;
            }
        }
        .exam-sheet {
            font-family: 'Atkinson Hyperlegible', system-ui, -apple-system, sans-serif;
            color: #0f172a;
            font-size: 10.5pt;
            line-height: 1.55;
        }
        .teacher-banner {
            background-color: #fef3c7;
            border: 1.5px solid #b45309;
            color: #78350f;
            font-weight: 700;
            font-size: 9pt;
            text-align: center;
            padding: 6px 10px;
            margin-bottom: 14px;
            border-radius: 6px;
        }
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 3px double #0f172a;
            margin-bottom: 14px;
        }
        .kop-table td {
            padding-bottom: 10px;
            vertical-align: middle;
        }
        .kop-logo-cell {
            width: 76px;
            padding-right: 12px;
        }
        .kop-logo {
            width: 64px;
            height: 64px;
            object-fit: contain;
        }
        .kop-org {
            font-size: 14.5pt;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
            letter-spacing: 0.02em;
        }
        .kop-tagline {
            font-size: 9pt;
            color: #334155;
            margin-top: 3px;
        }
        .exam-heading {
            text-align: center;
            margin: 12px 0 14px 0;
        }
        .exam-main-title {
            font-size: 13pt;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            line-height: 1.3;
        }
        .exam-subtitle {
            font-size: 11pt;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2px;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1.5px solid #0f172a;
            margin-bottom: 12px;
        }
        .meta-col {
            width: 50%;
            vertical-align: top;
            padding-bottom: 8px;
        }
        @media (max-width: 640px) {
            .meta-col {
                display: block;
                width: 100%;
            }
        }
        .meta-inner {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
        }
        .meta-inner td {
            padding: 2px 0;
            vertical-align: top;
        }
        .meta-lbl {
            width: 128px;
            font-weight: 700;
            color: #0f172a;
        }
        .meta-sep {
            width: 10px;
            font-weight: 700;
        }
        .meta-val {
            font-weight: 700;
            color: #0f172a;
        }
        .cpmk-section {
            margin: 10px 0 14px 0;
            overflow-x: auto;
        }
        .cpmk-caption {
            font-size: 9.5pt;
            font-weight: 700;
            margin-bottom: 5px;
            color: #0f172a;
        }
        .cpmk-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
        }
        .cpmk-table th, .cpmk-table td {
            border: 1px solid #0f172a;
            padding: 6px 8px;
            vertical-align: top;
            text-align: left;
        }
        .cpmk-table th {
            background-color: #f1f5f9;
            font-weight: 700;
            text-align: center;
        }
        .student-box {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #334155;
            margin: 10px 0 14px 0;
            font-size: 9.5pt;
            background-color: #f8fafc;
        }
        .student-box td {
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
        }
        .questions-heading {
            font-size: 10.5pt;
            font-weight: 800;
            color: #0f172a;
            margin: 12px 0 8px 0;
        }
        .question-item {
            margin-bottom: 14px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .q-layout {
            width: 100%;
            border-collapse: collapse;
        }
        .q-num {
            width: 28px;
            vertical-align: top;
            font-weight: 700;
            padding-top: 1px;
        }
        .q-body {
            vertical-align: top;
        }
        .q-stem {
            text-align: justify;
            color: #0f172a;
        }
        .q-points {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            font-size: 9.5pt;
            white-space: nowrap;
        }
        .q-rich {
            margin-top: 6px;
        }
        .q-code-wrap, .q-table-wrap, .q-image-wrap {
            margin-top: 8px;
            overflow-x: auto;
        }
        .q-block-caption {
            font-size: 9pt;
            font-weight: 700;
            color: #334155;
            margin-bottom: 3px;
        }
        .q-code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 8.5pt;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            border-radius: 6px;
            white-space: pre-wrap;
            word-wrap: break-word;
            margin: 0;
        }
        .q-data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        .q-data-table th, .q-data-table td {
            border: 1px solid #475569;
            padding: 5px 8px;
            text-align: left;
            vertical-align: top;
        }
        .q-data-table th {
            background-color: #f1f5f9;
            font-weight: 700;
        }
        .q-image {
            max-width: 100%;
            height: auto;
            max-height: 260px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
        }
        .q-image-caption {
            font-size: 8.5pt;
            color: #334155;
            margin-top: 3px;
            font-style: italic;
        }
        .q-options {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .q-options td {
            padding: 2px 0;
            vertical-align: top;
        }
        .opt-letter {
            width: 24px;
            font-weight: 700;
        }
        .key-badge {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 7px;
            font-size: 8pt;
            font-weight: 700;
            color: #14532d;
            background-color: #dcfce7;
            border: 1px solid #16a34a;
            border-radius: 4px;
        }
        .sub-questions {
            margin-top: 6px;
        }
        .sub-item {
            margin-top: 6px;
        }
        .sub-stem {
            text-align: justify;
        }
        .teacher-notes {
            margin-top: 8px;
            padding: 9px 12px;
            background-color: #f8fafc;
            border: 1px solid #64748b;
            border-radius: 6px;
            font-size: 9pt;
        }
        .teacher-note-row + .teacher-note-row {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px dashed #cbd5e1;
        }
        .exam-footer {
            margin-top: 24px;
            text-align: center;
            font-style: italic;
            font-size: 10pt;
            color: #0f172a;
        }
        @media print {
            body {
                background: #ffffff !important;
            }
            .no-print {
                display: none !important;
            }
            .preview-shell {
                padding: 0 !important;
                max-width: 100% !important;
            }
            .a4-paper {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
            @page {
                size: A4 portrait;
                margin: 1.5cm 1.7cm;
            }
        }
    </style>
</head>
<body class="bg-surface text-ink min-h-screen">
    <div class="preview-shell">
        <header class="no-print mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <nav aria-label="Navigasi lembar soal">
                    <a href="{{ route('admin.quizzes.questions.index', $quiz) }}" class="btn btn-secondary min-h-11">
                        <x-icon name="arrow-left" :size="18" />
                        <span>Kembali ke Soal</span>
                    </a>
                </nav>
                <div>
                    <h1 class="font-display text-xl font-bold text-ink">Pratinjau &amp; Ekspor Lembar Soal PDF</h1>
                    <p class="text-sm text-ink-muted">{{ $rawQuestionCount }} soal ({{ $pgCount }} PG, {{ $essayCount }} essay) · Total {{ $totalBobot }} poin</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($questions->isNotEmpty())
                    <button type="button" onclick="window.print()" class="btn btn-secondary min-h-11">
                        <x-icon name="printer" :size="18" />
                        <span>Cetak Kertas</span>
                    </button>
                    <a href="{{ $downloadUrl }}" class="btn btn-primary min-h-11">
                        <x-icon name="download" :size="18" />
                        <span>Unduh File PDF</span>
                    </a>
                @else
                    <button type="button" disabled class="btn btn-primary min-h-11 cursor-not-allowed opacity-50">
                        <x-icon name="download" :size="18" />
                        <span>Unduh File PDF</span>
                    </button>
                @endif
            </div>
        </header>

        @if ($errors->any())
            <div role="alert" class="no-print mb-6 rounded-2xl border border-coral bg-coral-soft px-4 py-3 text-sm text-coral-deep">
                <p class="font-bold">Pengaturan naskah belum valid:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $pesanError)
                        <li>{{ $pesanError }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <main class="grid gap-6 lg:grid-cols-[340px_minmax(0,1fr)] items-start">
            <section aria-label="Pengaturan kepala naskah soal" class="no-print card p-5">
                <h2 class="font-display text-lg font-bold text-ink">Pengaturan Naskah</h2>
                <p class="mt-1 text-sm text-ink-muted">Sesuaikan identitas ujian lalu perbarui pratinjau sebelum mengunduh PDF.</p>

                <form
                    method="GET"
                    action="{{ route('admin.quizzes.questions.pdf.preview', $quiz) }}"
                    x-data="{ memuat: false }"
                    x-on:submit="memuat = true"
                    class="mt-4 space-y-4"
                >
                    <div>
                        <label for="varian" class="label text-sm">Jenis dokumen</label>
                        <select id="varian" name="varian" class="input mt-1">
                            <option value="peserta" @selected($meta['varian'] === 'peserta')>Naskah Soal Peserta (tanpa kunci)</option>
                            <option value="pengajar" @selected($meta['varian'] === 'pengajar')>Arsip Pengajar &amp; Kunci Jawaban</option>
                        </select>
                    </div>

                    <div>
                        <label for="judul_ujian" class="label text-sm">Judul naskah ujian</label>
                        <input id="judul_ujian" name="judul_ujian" type="text" value="{{ $meta['judul_ujian'] }}" placeholder="Contoh: UJIAN TENGAH SEMESTER (UTS)" class="input mt-1">
                    </div>

                    <div>
                        <label for="subjudul" class="label text-sm">Subjudul / tahun akademik (opsional)</label>
                        <input id="subjudul" name="subjudul" type="text" value="{{ $meta['subjudul'] }}" placeholder="Contoh: SEMESTER GANJIL 2026/2027" class="input mt-1">
                    </div>

                    <div>
                        <label for="mata_kuliah" class="label text-sm">Mata kuliah / subjek</label>
                        <input id="mata_kuliah" name="mata_kuliah" type="text" value="{{ $meta['mata_kuliah'] }}" class="input mt-1">
                    </div>

                    <div>
                        <label for="dosen" class="label text-sm">Nama dosen / pengajar</label>
                        <input id="dosen" name="dosen" type="text" value="{{ $meta['dosen'] }}" class="input mt-1">
                    </div>

                    <div>
                        <label for="program_studi" class="label text-sm">Program studi / jurusan (opsional)</label>
                        <input id="program_studi" name="program_studi" type="text" value="{{ $meta['program_studi'] }}" placeholder="Contoh: SISTEM INFORMASI" class="input mt-1">
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                        <div>
                            <label for="waktu_menit" class="label text-sm">Waktu ujian (menit)</label>
                            <input id="waktu_menit" name="waktu_menit" type="number" min="1" max="600" value="{{ $meta['waktu_menit'] }}" class="input mt-1">
                        </div>

                        <div>
                            <label for="sifat_ujian" class="label text-sm">Sifat ujian</label>
                            <input id="sifat_ujian" name="sifat_ujian" type="text" value="{{ $meta['sifat_ujian'] }}" placeholder="Contoh: CLOSE BOOK" class="input mt-1">
                        </div>
                    </div>

                    <div>
                        <label for="kode_cpmk" class="label text-sm">Kode CPMK (opsional)</label>
                        <input id="kode_cpmk" name="kode_cpmk" type="text" value="{{ $meta['kode_cpmk'] }}" placeholder="Contoh: CPMK04-01" class="input mt-1">
                        <p class="help mt-1">Jika diisi, tabel pengukuran CPMK ditampilkan pada kepala naskah.</p>
                    </div>

                    <label class="flex items-start gap-3 rounded-xl border border-outline bg-page p-3 cursor-pointer">
                        <input type="hidden" name="kolom_identitas" value="0">
                        <input type="checkbox" name="kolom_identitas" value="1" class="mt-0.5 h-5 w-5" @checked($meta['kolom_identitas'])>
                        <span class="text-sm text-ink">
                            <span class="block font-bold">Sertakan kotak identitas peserta</span>
                            <span class="text-ink-muted">Menampilkan kolom Nama, NIM, Kelas, dan Tanda Tangan untuk ujian tulis kertas.</span>
                        </span>
                    </label>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <button
                            type="submit"
                            x-bind:disabled="memuat"
                            x-bind:aria-busy="memuat"
                            class="btn btn-primary min-h-11 w-full justify-center disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span x-text="memuat ? 'Memperbarui pratinjau...' : 'Perbarui Pratinjau'">Perbarui Pratinjau</span>
                        </button>
                        <a href="{{ route('admin.quizzes.questions.pdf.preview', $quiz) }}" class="btn btn-ghost min-h-11 w-full justify-center">
                            Kembalikan ke Bawaan
                        </a>
                    </div>
                </form>
            </section>

            <section aria-label="Pratinjau lembar ujian A4">
                @if ($questions->isEmpty())
                    <x-empty-state
                        judul="Belum ada soal untuk dicetak"
                        keterangan="Tambahkan soal pilihan ganda atau essay terlebih dahulu pada kuis ini sebelum mengunduh naskah PDF."
                    >
                        <div class="mt-4 flex flex-wrap justify-center gap-3">
                            <a href="{{ route('admin.quizzes.questions.create', $quiz) }}" class="btn btn-primary min-h-11">Tambah soal pertama</a>
                            <a href="{{ route('admin.quizzes.questions.ai.form', $quiz) }}" class="btn btn-secondary min-h-11">Generate soal via AI</a>
                        </div>
                    </x-empty-state>
                @else
                    <div class="a4-paper">
                        @include('admin.questions._pdf-document')
                    </div>
                @endif
            </section>
        </main>

        <footer class="no-print mt-8 flex flex-wrap items-center justify-between gap-2 border-t border-outline pt-4 text-sm text-ink-muted">
            <span>{{ $institutionName }} · Ekspor Lembar Ujian Standar Akademik A4</span>
            <span>Format cetak: A4 Portrait</span>
        </footer>
    </div>
</body>
</html>
