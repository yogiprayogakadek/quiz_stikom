@php
    $sheetStyles = <<<'CSS'
    .exam-sheet {
        font-family: 'Atkinson Hyperlegible', 'DejaVu Sans', sans-serif;
        color: #0f172a;
        font-size: 10.5pt;
        line-height: 1.52;
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
        border-radius: 4px;
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
    .kop-title-cell {
        text-align: left;
    }
    .kop-org {
        font-size: 14.5pt;
        font-weight: 700;
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
        font-size: 12.5pt;
        font-weight: 700;
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
    .meta-inner {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.5pt;
    }
    .meta-inner td {
        padding: 1.5px 0;
        vertical-align: top;
    }
    .meta-lbl {
        width: 122px;
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
        padding: 5px 8px;
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
        font-weight: 700;
        color: #0f172a;
        margin: 12px 0 8px 0;
    }
    .question-item {
        margin-bottom: 12px;
        page-break-inside: avoid;
        break-inside: avoid;
    }
    .q-layout {
        width: 100%;
        border-collapse: collapse;
    }
    .q-num {
        width: 26px;
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
        font-family: 'JetBrains Mono', 'DejaVu Sans Mono', monospace;
        font-weight: 700;
        font-size: 9.5pt;
        white-space: nowrap;
    }
    .q-rich {
        margin-top: 6px;
    }
    .q-code-wrap, .q-table-wrap, .q-image-wrap {
        margin-top: 8px;
    }
    .q-block-caption {
        font-size: 9pt;
        font-weight: 700;
        color: #334155;
        margin-bottom: 3px;
    }
    .q-code {
        font-family: 'JetBrains Mono', 'DejaVu Sans Mono', monospace;
        font-size: 8.5pt;
        background-color: #f8fafc;
        border: 1px solid #cbd5e1;
        padding: 8px 10px;
        border-radius: 4px;
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
        padding: 4px 7px;
        text-align: left;
        vertical-align: top;
    }
    .q-data-table th {
        background-color: #f1f5f9;
        font-weight: 700;
    }
    .q-image {
        max-width: 420px;
        max-height: 240px;
        width: auto;
        height: auto;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
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
        width: 22px;
        font-weight: 700;
    }
    .key-badge {
        display: inline-block;
        margin-left: 6px;
        padding: 1px 6px;
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
        margin-top: 5px;
    }
    .sub-stem {
        text-align: justify;
    }
    .teacher-notes {
        margin-top: 8px;
        padding: 8px 10px;
        background-color: #f8fafc;
        border: 1px solid #64748b;
        border-radius: 4px;
        font-size: 9pt;
    }
    .teacher-note-row + .teacher-note-row {
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px dashed #cbd5e1;
    }
    .exam-footer {
        margin-top: 22px;
        text-align: center;
        font-style: italic;
        font-size: 10pt;
        color: #0f172a;
    }
CSS;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['judul_ujian'] }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.6cm 1.8cm;
        }
        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #0f172a;
        }
        {!! $sheetStyles !!}
    </style>
</head>
<body>
    @include('admin.questions._pdf-document')
</body>
</html>
