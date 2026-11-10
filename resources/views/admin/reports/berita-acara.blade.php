<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Berita Acara - {{ $session->nama }}</title>
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            padding: 2rem;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .paper {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            padding: 2.5rem 3rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.08);
            border: 1px solid #e2e8f0;
        }

        .toolbar {
            max-width: 900px;
            margin: 0 auto 1.5rem auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 700;
            border-radius: 12px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
            border: 1px solid transparent;
        }

        .btn-primary {
            background-color: #1e6ffb;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #1557cf;
        }

        .btn-secondary {
            background-color: #ffffff;
            color: #334155;
            border-color: #cbd5e1;
        }
        .btn-secondary:hover {
            background-color: #f8fafc;
        }

        /* Kop Surat Header */
        .kop-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding-bottom: 1.25rem;
            border-bottom: 3px double #0f172a;
            margin-bottom: 1.5rem;
        }

        .kop-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .kop-text {
            flex: 1;
            text-align: center;
        }

        .kop-text h2 {
            font-size: 1.25rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #0f172a;
        }

        .kop-text h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1e6ffb;
            line-height: 1.2;
            margin: 0.15rem 0;
        }

        .kop-text p {
            font-size: 0.8rem;
            color: #475569;
        }

        /* Title */
        .doc-title {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .doc-title h3 {
            font-size: 1.2rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            color: #0f172a;
            text-decoration: underline;
        }

        .doc-title p {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        /* Metadata Grid */
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem 2rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .meta-row {
            display: flex;
            gap: 0.5rem;
        }

        .meta-label {
            width: 140px;
            color: #64748b;
            font-weight: 600;
            flex-shrink: 0;
        }

        .meta-value {
            font-weight: 700;
            color: #0f172a;
        }

        /* Summary Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .stat-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 0.5rem;
            background: #ffffff;
        }

        .stat-card .val {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
        }

        .stat-card .lbl {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
            letter-spacing: 0.03em;
            margin-top: 0.2rem;
        }

        /* Score Table */
        table.score-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
            font-size: 0.8125rem;
        }

        table.score-table th, table.score-table td {
            border: 1px solid #cbd5e1;
            padding: 0.5rem 0.75rem;
            text-align: left;
        }

        table.score-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.04em;
        }

        table.score-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }

        /* Signatures */
        .signatures {
            margin-top: 2.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            font-size: 0.875rem;
            page-break-inside: avoid;
        }

        .sig-box {
            text-align: center;
        }

        .sig-space {
            height: 70px;
        }

        .sig-line {
            display: inline-block;
            min-width: 200px;
            border-bottom: 1.5px solid #0f172a;
            font-weight: 700;
            padding-bottom: 0.2rem;
        }

        .sig-role {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                color: #000000 !important;
            }

            .toolbar {
                display: none !important;
            }

            .paper {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
            }

            @page {
                size: A4;
                margin: 1.5cm;
            }

            .stat-card {
                border-color: #94a3b8 !important;
            }

            table.score-table th, table.score-table td {
                border-color: #64748b !important;
            }
        }
    </style>
</head>
<body>
    {{-- Top Action Toolbar (Hidden when printing) --}}
    <div class="toolbar">
        <a href="{{ route('admin.reports.session', $session) }}" class="btn btn-secondary">
            &larr; Kembali ke Laporan
        </a>
        <div style="display: flex; gap: 0.75rem;">
            <button onclick="window.print()" class="btn btn-primary">
                Cetak / Simpan PDF
            </button>
        </div>
    </div>

    {{-- Printable Paper --}}
    <div class="paper">
        {{-- Kop Surat Resmi --}}
        <div class="kop-header">
            <img src="{{ asset('logo.png') }}" alt="Logo" class="kop-logo">
            <div class="kop-text">
                <h2>{{ mb_strtoupper($appSettings->site_name) }}</h2>
                <h1>BERITA ACARA & REKAP NILAI UJIAN</h1>
                <p>Sistem Asesmen Terkomputerisasi Jaringan Lokal Kampus</p>
            </div>
        </div>

        {{-- Judul Dokumen --}}
        <div class="doc-title">
            <h3>BERITA ACARA PELAKSANAAN UJIAN</h3>
            <p>Nomor Dokumen: BA/{{ now()->timezone($orgTimezone)->format('Y') }}/{{ str_pad($session->id, 5, '0', STR_PAD_LEFT) }}</p>
        </div>

        {{-- Rincian Sesi Ujian --}}
        <div class="meta-grid">
            <div class="meta-row">
                <span class="meta-label">Nama Sesi:</span>
                <span class="meta-value">{{ $session->nama }}</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Mata Ujian / Kuis:</span>
                <span class="meta-value">{{ $session->quiz->judul }}</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Kelas:</span>
                <span class="meta-value">{{ $session->kelas?->nama ?? 'Semua Kelas' }}</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Mode Ujian:</span>
                <span class="meta-value">Mode {{ ucfirst($session->mode) }} ({{ $session->acak_opsi ? 'Acak Pilihan' : 'Opsi Tetap' }})</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Waktu Buka:</span>
                <span class="meta-value">{{ $session->dibuka_at ? $session->dibuka_at->timezone($orgTimezone)->translatedFormat('d F Y, H:i') : '-' }}</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Waktu Tutup:</span>
                <span class="meta-value">{{ $session->ditutup_at ? $session->ditutup_at->timezone($orgTimezone)->translatedFormat('d F Y, H:i') : 'Selesai' }}</span>
            </div>
        </div>

        {{-- Statistik Ringkasan --}}
        <div class="stats-grid">
            <div class="stat-card">
                <div class="val">{{ $totalPeserta }}</div>
                <div class="lbl">Total Peserta</div>
            </div>
            <div class="stat-card">
                <div class="val">{{ $selesai }}</div>
                <div class="lbl">Hadir / Selesai</div>
            </div>
            <div class="stat-card">
                <div class="val">{{ $skorRataRata }}</div>
                <div class="lbl">Rata-Rata</div>
            </div>
            <div class="stat-card">
                <div class="val">{{ $skorTertinggi }}</div>
                <div class="lbl">Tertinggi</div>
            </div>
            <div class="stat-card">
                <div class="val">{{ $skorTerendah }}</div>
                <div class="lbl">Terendah</div>
            </div>
        </div>

        {{-- Tabel Rekap Peserta & Nilai --}}
        <table class="score-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 40px;">No</th>
                    <th>Nama Peserta</th>
                    <th style="width: 120px;">Username / NIM</th>
                    <th class="text-center" style="width: 70px;">Benar</th>
                    <th class="text-center" style="width: 80px;">Skor Akhir</th>
                    <th class="text-center" style="width: 85px;">Pelanggaran</th>
                    <th class="text-center" style="width: 100px;">Tanda Tangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attempts as $index => $att)
                    <tr>
                        <td class="text-center font-mono">{{ $index + 1 }}</td>
                        <td style="font-weight: 600;">{{ $att->user?->nama ?? '(Dihapus)' }}</td>
                        <td class="font-mono">{{ $att->user?->username ?? '-' }}</td>
                        <td class="text-center font-mono">{{ $att->jumlah_benar }}</td>
                        <td class="text-center font-mono" style="font-weight: 700; color: #0f172a;">
                            {{ $att->skor_total }}
                        </td>
                        <td class="text-center">
                            @if ($att->events_count > 0)
                                <span style="font-weight: 700; color: {{ $att->events_count >= 3 ? '#e11d48' : '#64748b' }};">
                                    {{ $att->events_count }} kali
                                </span>
                            @else
                                <span style="color: #94a3b8;">Nihil</span>
                            @endif
                        </td>
                        <td style="height: 32px;"></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 1.5rem; color: #64748b;">
                            Belum ada peserta yang mengikuti sesi ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Keterangan / Catatan Pengawas --}}
        <div style="font-size: 0.8125rem; color: #334155; margin-bottom: 1.5rem; line-height: 1.6;">
            <p><strong>Catatan Pelaksanaan:</strong></p>
            <p>Ujian dilaksanakan secara serempak/mandiri pada jaringan lokal. Seluruh aktivitas ujian diawasi oleh sistem proctoring otomatis dan pengawas ruang ujian. Demikian berita acara ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>
        </div>

        {{-- Kolom Tanda Tangan Pengawas --}}
        <div class="signatures">
            <div class="sig-box">
                <p>Pengawas Ruang Ujian,</p>
                <div class="sig-space"></div>
                <div class="sig-line">( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
                <div class="sig-role">NIP / Identitas Pengawas</div>
            </div>
            <div class="sig-box">
                <p>Mengetahui / Penanggung Jawab,</p>
                <div class="sig-space"></div>
                <div class="sig-line">{{ auth()->user()->nama }}</div>
                <div class="sig-role">Administrator / Dosen Penguji</div>
            </div>
        </div>
    </div>
</body>
</html>
