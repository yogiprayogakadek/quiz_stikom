@props(['format' => 'rich_text', 'config' => [], 'id', 'label' => 'Jawaban Anda'])

<p id="{{ $id }}-label" class="label">{{ $label }}</p>

@if ($format === 'table')
    <div class="overflow-x-auto" tabindex="0">
        <table class="answer-table" aria-labelledby="{{ $id }}-label">
            <thead>
                <tr>
                    <th scope="col">{{ $config['row_header'] ?? 'Baris' }}</th>
                    @foreach (($config['columns'] ?? []) as $column)
                        <th scope="col">{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach (($config['rows'] ?? []) as $rowIndex => $row)
                    <tr>
                        <th scope="row">{{ $row }}</th>
                        @foreach (($config['columns'] ?? []) as $columnIndex => $column)
                            <td><input class="input min-w-40" disabled placeholder="{{ $config['cell_hints'][$rowIndex][$columnIndex] ?? '' }}" aria-label="{{ $row }}, {{ $column }}"></td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    @if ($format === 'rich_text')
        <div class="mb-2 flex flex-wrap gap-2" aria-hidden="true">
            <span class="btn btn-secondary min-h-11 px-3"><strong>Tebal</strong></span>
            <span class="btn btn-secondary min-h-11 px-3"><em>Miring</em></span>
            <span class="btn btn-secondary min-h-11 px-3">Daftar</span>
        </div>
    @endif
    <textarea id="{{ $id }}"
        aria-labelledby="{{ $id }}-label"
        @class(['input min-h-32 resize-none', 'font-mono' => $format === 'code'])
        disabled
        spellcheck="{{ $format === 'code' ? 'false' : 'true' }}"
        placeholder="{{ $format === 'code' ? 'Kode peserta ditulis di sini' : 'Jawaban peserta ditulis di sini' }}"
    ></textarea>
@endif
