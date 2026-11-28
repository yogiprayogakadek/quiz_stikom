<div class="exam-sheet">
    @if ($meta['varian'] === 'pengajar')
        <div class="teacher-banner">
            ARSIP PENGAJAR DAN KUNCI JAWABAN (RAHASIA, TIDAK UNTUK DIBAGIKAN KEPADA PESERTA)
        </div>
    @endif

    <table class="kop-table" role="presentation">
        <tr>
            @if ($logoDataUri)
                <td class="kop-logo-cell">
                    <img src="{{ $logoDataUri }}" alt="Logo {{ $institutionName }}" width="64" height="64" fetchpriority="high" class="kop-logo">
                </td>
            @endif
            <td class="kop-title-cell">
                <div class="kop-org">{{ mb_strtoupper($institutionName) }}</div>
                @if ($institutionTagline !== '')
                    <div class="kop-tagline">{{ $institutionTagline }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="exam-heading">
        <h2 class="exam-main-title">{{ mb_strtoupper($meta['judul_ujian']) }}</h2>
        @if ($meta['subjudul'] !== '')
            <div class="exam-subtitle">{{ mb_strtoupper($meta['subjudul']) }}</div>
        @endif
    </div>

    <table class="meta-table" role="presentation">
        <tr>
            <td class="meta-col">
                <table class="meta-inner" role="presentation">
                    <tr>
                        <td class="meta-lbl">Mata Kuliah / Kuis</td>
                        <td class="meta-sep">:</td>
                        <td class="meta-val">{{ $meta['mata_kuliah'] }}</td>
                    </tr>
                    <tr>
                        <td class="meta-lbl">Dosen / Pengajar</td>
                        <td class="meta-sep">:</td>
                        <td class="meta-val">{{ $meta['dosen'] }}</td>
                    </tr>
                    <tr>
                        <td class="meta-lbl">Kelas</td>
                        <td class="meta-sep">:</td>
                        <td class="meta-val">{{ $meta['kelas'] }}</td>
                    </tr>
                </table>
            </td>
            <td class="meta-col">
                <table class="meta-inner" role="presentation">
                    <tr>
                        <td class="meta-lbl">Waktu</td>
                        <td class="meta-sep">:</td>
                        <td class="meta-val">{{ $meta['waktu_menit'] }} Menit</td>
                    </tr>
                    @if ($meta['sifat_ujian'] !== '')
                        <tr>
                            <td class="meta-lbl">Sifat Ujian</td>
                            <td class="meta-sep">:</td>
                            <td class="meta-val">{{ $meta['sifat_ujian'] }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="meta-lbl">{{ $meta['program_studi'] !== '' ? 'Program Studi' : 'Total Bobot' }}</td>
                        <td class="meta-sep">:</td>
                        <td class="meta-val">
                            @if ($meta['program_studi'] !== '')
                                {{ $meta['program_studi'] }}
                            @else
                                {{ $totalBobot }} Poin ({{ $rawQuestionCount }} Soal)
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($meta['kode_cpmk'] !== '')
        <div class="cpmk-section">
            <div class="cpmk-caption">
                Tabel pengukuran Capaian Pembelajaran Mata Kuliah (CPMK) dengan total bobot {{ $totalBobot }} poin:
            </div>
            <table class="cpmk-table">
                <thead>
                    <tr>
                        <th style="width: 24%;">Kode CPMK</th>
                        <th style="width: 48%;">Bentuk Soal Ujian</th>
                        <th style="width: 28%;">Metode Ujian</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>&#10003; {{ $meta['kode_cpmk'] }}</td>
                        <td>
                            @if ($pgCount > 0)
                                <div>&#10003; Pilihan Ganda ({{ $pgCount }} butir)</div>
                            @endif
                            @if ($essayCount > 0)
                                <div>&#10003; Soal Uraian / Studi Kasus ({{ $essayCount }} butir)</div>
                            @endif
                        </td>
                        <td>&#10003; Ujian Tertulis / Terkomputerisasi</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    @if ($meta['kolom_identitas'] && $meta['varian'] === 'peserta')
        <table class="student-box">
            <tr>
                <td style="width: 55%;"><strong>Nama Peserta:</strong> ........................................................</td>
                <td style="width: 45%;"><strong>NIM / Username:</strong> ........................................</td>
            </tr>
            <tr>
                <td><strong>Kelas / Ruang:</strong> ........................................................</td>
                <td><strong>Tanda Tangan:</strong> ...........................................</td>
            </tr>
        </table>
    @endif

    <div class="questions-heading">
        Soal Ujian @if ($meta['kode_cpmk'] !== '') [Pemenuhan {{ $meta['kode_cpmk'] }}] @endif
    </div>

    <div class="question-list">
        @foreach ($questions as $soal)
            <div class="question-item">
                <table class="q-layout" role="presentation">
                    <tr>
                        <td class="q-num">{{ $soal['nomor'] }}.</td>
                        <td class="q-body">
                            <div class="q-stem">
                                {!! nl2br(e($soal['teks'])) !!}
                                <span class="q-points">({{ $soal['bobot'] }} Poin)</span>
                            </div>

                            @foreach ($soal['blocks'] as $block)
                                @if ($block['type'] === 'text')
                                    <div class="q-rich">{!! $block['html'] !!}</div>
                                @elseif ($block['type'] === 'code')
                                    <div class="q-code-wrap">
                                        @if ($block['caption'] !== '')
                                            <div class="q-block-caption">{{ $block['caption'] }}</div>
                                        @endif
                                        <pre class="q-code"><code>{{ $block['code'] }}</code></pre>
                                    </div>
                                @elseif ($block['type'] === 'table')
                                    <div class="q-table-wrap">
                                        @if ($block['caption'] !== '')
                                            <div class="q-block-caption">{{ $block['caption'] }}</div>
                                        @endif
                                        <table class="q-data-table">
                                            @if (! empty($block['headers']))
                                                <thead>
                                                    <tr>
                                                        @foreach ($block['headers'] as $header)
                                                            <th>{{ $header }}</th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                            @endif
                                            <tbody>
                                                @foreach ($block['rows'] as $row)
                                                    <tr>
                                                        @foreach ($row as $cell)
                                                            <td>{{ $cell }}</td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @elseif ($block['type'] === 'image' && ! empty($block['data_uri']))
                                    <div class="q-image-wrap">
                                        <img src="{{ $block['data_uri'] }}" alt="{{ $block['alt'] ?: 'Gambar soal '.$soal['nomor'] }}" width="420" height="240" class="q-image">
                                        @if ($block['caption'] !== '')
                                            <div class="q-image-caption">{{ $block['caption'] }}</div>
                                        @endif
                                    </div>
                                @endif
                            @endforeach

                            @if ($soal['tipe'] === 'pilihan_ganda' && ! empty($soal['options']))
                                <table class="q-options" role="presentation">
                                    @foreach ($soal['options'] as $opt)
                                        <tr>
                                            <td class="opt-letter">{{ $opt['huruf'] }}.</td>
                                            <td class="opt-text">
                                                {{ $opt['teks'] }}
                                                @if ($meta['varian'] === 'pengajar' && $opt['is_correct'])
                                                    <span class="key-badge">&#10003; KUNCI JAWABAN</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @if (! empty($soal['sub_soal']))
                                <div class="sub-questions">
                                    @foreach ($soal['sub_soal'] as $bagian)
                                        <div class="sub-item" style="margin-left: {{ max(0, ((int) ($bagian['depth'] ?? 0)) * 16) }}px;">
                                            <div class="sub-stem">
                                                <strong>{{ $bagian['code'] }}.</strong>
                                                {!! nl2br(e($bagian['teks'])) !!}
                                            </div>
                                            @foreach ($bagian['blocks'] as $subBlock)
                                                @if ($subBlock['type'] === 'text')
                                                    <div class="q-rich">{!! $subBlock['html'] !!}</div>
                                                @elseif ($subBlock['type'] === 'code')
                                                    <div class="q-code-wrap">
                                                        @if ($subBlock['caption'] !== '')
                                                            <div class="q-block-caption">{{ $subBlock['caption'] }}</div>
                                                        @endif
                                                        <pre class="q-code"><code>{{ $subBlock['code'] }}</code></pre>
                                                    </div>
                                                @elseif ($subBlock['type'] === 'table')
                                                    <div class="q-table-wrap">
                                                        @if ($subBlock['caption'] !== '')
                                                            <div class="q-block-caption">{{ $subBlock['caption'] }}</div>
                                                        @endif
                                                        <table class="q-data-table">
                                                            @if (! empty($subBlock['headers']))
                                                                <thead>
                                                                    <tr>
                                                                        @foreach ($subBlock['headers'] as $header)
                                                                            <th>{{ $header }}</th>
                                                                        @endforeach
                                                                    </tr>
                                                                </thead>
                                                            @endif
                                                            <tbody>
                                                                @foreach ($subBlock['rows'] as $row)
                                                                    <tr>
                                                                        @foreach ($row as $cell)
                                                                            <td>{{ $cell }}</td>
                                                                        @endforeach
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @elseif ($subBlock['type'] === 'image' && ! empty($subBlock['data_uri']))
                                                    <div class="q-image-wrap">
                                                        <img src="{{ $subBlock['data_uri'] }}" alt="{{ $subBlock['alt'] ?: 'Gambar sub-soal '.$bagian['code'] }}" width="380" height="220" class="q-image">
                                                        @if ($subBlock['caption'] !== '')
                                                            <div class="q-image-caption">{{ $subBlock['caption'] }}</div>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endforeach

                                            @if ($bagian['is_leaf'] && $bagian['answer_format'] === 'table' && ! empty($bagian['answer_config']['columns']))
                                                <div class="q-table-wrap">
                                                    <div class="q-block-caption">Lembar Jawab Tabel Bagian {{ $bagian['code'] }}:</div>
                                                    <table class="q-data-table">
                                                        <thead>
                                                            <tr>
                                                                <th>{{ $bagian['answer_config']['row_header'] ?? 'Baris' }}</th>
                                                                @foreach ($bagian['answer_config']['columns'] as $col)
                                                                    <th>{{ $col }}</th>
                                                                @endforeach
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($bagian['answer_config']['rows'] ?? [] as $rIdx => $rowName)
                                                                <tr>
                                                                    <td><strong>{{ $rowName }}</strong></td>
                                                                    @foreach ($bagian['answer_config']['columns'] as $cIdx => $col)
                                                                        <td style="height: 24px; color: #475569; font-size: 9pt;">
                                                                            {{ $bagian['answer_config']['cell_hints'][$rIdx][$cIdx] ?? '' }}
                                                                        </td>
                                                                    @endforeach
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @elseif ($soal['tipe'] === 'essay' && $soal['answer_format'] === 'table' && ! empty($soal['answer_config']['columns']))
                                <div class="q-table-wrap">
                                    <div class="q-block-caption">Tabel Isian Jawaban:</div>
                                    <table class="q-data-table">
                                        <thead>
                                            <tr>
                                                <th>{{ $soal['answer_config']['row_header'] ?? 'Baris' }}</th>
                                                @foreach ($soal['answer_config']['columns'] as $col)
                                                    <th>{{ $col }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($soal['answer_config']['rows'] ?? [] as $rIdx => $rowName)
                                                <tr>
                                                    <td><strong>{{ $rowName }}</strong></td>
                                                    @foreach ($soal['answer_config']['columns'] as $cIdx => $col)
                                                        <td style="height: 24px; color: #475569; font-size: 9pt;">
                                                            {{ $soal['answer_config']['cell_hints'][$rIdx][$cIdx] ?? '' }}
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            @if ($meta['varian'] === 'pengajar' && ($soal['kunci_essay'] !== '' || $soal['pembahasan'] !== '' || ! empty($soal['rubrik_essay'])))
                                <div class="teacher-notes">
                                    @if ($soal['kunci_essay'] !== '')
                                        <div class="teacher-note-row">
                                            <strong>Kunci / Jawaban Acuan Essay:</strong>
                                            <div>{!! nl2br(e($soal['kunci_essay'])) !!}</div>
                                        </div>
                                    @endif
                                    @if ($soal['pembahasan'] !== '')
                                        <div class="teacher-note-row">
                                            <strong>Pembahasan:</strong>
                                            <div>{!! nl2br(e($soal['pembahasan'])) !!}</div>
                                        </div>
                                    @endif
                                    @if (! empty($soal['rubrik_essay']))
                                        <div class="teacher-note-row">
                                            <strong>Rubrik Penilaian:</strong>
                                            <table class="q-data-table" style="margin-top: 4px;">
                                                <thead>
                                                    <tr>
                                                        <th>Kriteria</th>
                                                        <th style="width: 80px; text-align: right;">Poin Maks</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($soal['rubrik_essay'] as $kriteria)
                                                        <tr>
                                                            <td>
                                                                <strong>{{ $kriteria['kriteria'] ?? $kriteria['nama'] ?? 'Kriteria' }}</strong>
                                                                @if (! empty($kriteria['deskripsi']))
                                                                    <div style="font-size: 8.5pt; color: #334155;">{{ $kriteria['deskripsi'] }}</div>
                                                                @endif
                                                            </td>
                                                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace;">
                                                                {{ $kriteria['poin'] ?? $kriteria['skor'] ?? $kriteria['bobot'] ?? 0 }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    </div>

    <div class="exam-footer">
        @if ($meta['varian'] === 'pengajar')
            - Akhir Dokumen Arsip Pengajar -
        @else
            - Selamat Mengerjakan -
        @endif
    </div>
</div>
