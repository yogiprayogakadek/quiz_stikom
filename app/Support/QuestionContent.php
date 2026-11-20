<?php

namespace App\Support;

use App\Models\Answer;
use App\Models\Question;
use App\Models\QuestionMedia;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class QuestionContent
{
    public const FORMATS = ['rich_text', 'code', 'table'];

    public static function normalizeBlocks(array $blocks): array
    {
        return collect($blocks)
            ->filter(fn ($block) => is_array($block))
            ->map(function (array $block) {
                $type = in_array($block['type'] ?? null, ['text', 'image', 'table', 'code'], true)
                    ? $block['type']
                    : 'text';

                return match ($type) {
                    'image' => [
                        'type' => 'image',
                        'media_id' => isset($block['media_id']) ? (int) $block['media_id'] : null,
                        'alt' => trim((string) ($block['alt'] ?? '')),
                        'caption' => trim((string) ($block['caption'] ?? '')),
                        'ai_context' => trim((string) ($block['ai_context'] ?? '')),
                        ...(($block['image'] ?? null) ? ['image' => $block['image']] : []),
                    ],
                    'table' => [
                        'type' => 'table',
                        'caption' => trim((string) ($block['caption'] ?? '')),
                        'headers' => array_values(array_map(
                            fn ($value) => trim((string) $value),
                            array_slice((array) ($block['headers'] ?? []), 0, 10),
                        )),
                        'rows' => array_values(array_map(
                            fn ($row) => array_values(array_map(fn ($value) => trim((string) $value), array_slice((array) $row, 0, 10))),
                            array_slice((array) ($block['rows'] ?? []), 0, 20),
                        )),
                    ],
                    'code' => [
                        'type' => 'code',
                        'language' => trim((string) ($block['language'] ?? 'text')) ?: 'text',
                        'caption' => trim((string) ($block['caption'] ?? '')),
                        'code' => rtrim((string) ($block['code'] ?? '')),
                    ],
                    default => [
                        'type' => 'text',
                        'text' => trim((string) ($block['text'] ?? '')),
                    ],
                };
            })
            ->filter(function (array $block) {
                return match ($block['type']) {
                    'image' => ($block['media_id'] ?? null) || ($block['image'] ?? null),
                    'table' => $block['headers'] !== [] || $block['rows'] !== [],
                    'code' => $block['code'] !== '',
                    default => $block['text'] !== '',
                };
            })
            ->take(20)
            ->values()
            ->all();
    }

    public static function normalizeAnswerConfig(string $format, array $config): array
    {
        if ($format === 'code') {
            return ['language' => trim((string) ($config['language'] ?? 'text')) ?: 'text'];
        }

        if ($format === 'table') {
            $columns = array_values(array_map(
                fn ($value) => trim((string) $value),
                array_slice((array) ($config['columns'] ?? []), 0, 10),
            ));
            $rows = array_values(array_map(
                fn ($value) => trim((string) $value),
                array_slice((array) ($config['rows'] ?? []), 0, 20),
            ));
            $hints = (array) ($config['cell_hints'] ?? []);

            return [
                'row_header' => trim((string) ($config['row_header'] ?? '')) ?: 'Baris',
                'columns' => $columns,
                'rows' => $rows,
                'cell_hints' => collect($rows)->keys()->map(fn ($row) => collect($columns)->keys()
                    ->map(fn ($column) => trim((string) ($hints[$row][$column] ?? '')))
                    ->all())->all(),
            ];
        }

        return [];
    }

    public static function renderBlocks(array $blocks, bool $signedMedia = false): string
    {
        $blocks = self::normalizeBlocks($blocks);
        $mediaIds = collect($blocks)->where('type', 'image')->pluck('media_id')->filter()->all();
        $media = $mediaIds === []
            ? collect()
            : QuestionMedia::query()->whereIn('id', $mediaIds)->get()->keyBy('id');

        return collect($blocks)->map(function (array $block) use ($media, $signedMedia) {
            if ($block['type'] === 'text') {
                return '<div class="question-rich-text">'.self::safeMarkdown($block['text']).'</div>';
            }

            if ($block['type'] === 'code') {
                $caption = $block['caption'] !== '' ? '<figcaption class="mb-2 text-sm font-bold text-ink">'.e($block['caption']).'</figcaption>' : '';

                return '<figure class="question-code">'.$caption.'<div class="mb-2 flex justify-end"><button type="button" class="btn btn-secondary min-h-11 px-3" data-copy-code>Salin kode</button></div><pre tabindex="0"><code data-language="'.e($block['language']).'">'.e($block['code']).'</code></pre></figure>';
            }

            if ($block['type'] === 'table') {
                return self::renderTable($block['headers'], $block['rows'], $block['caption']);
            }

            $asset = $media->get($block['media_id']);
            if (! $asset) {
                return '';
            }

            $url = $signedMedia
                ? URL::temporarySignedRoute('question-media.show', now()->addHours(12), ['media' => $asset->id])
                : route('question-media.show', $asset);
            $caption = $block['caption'] !== '' ? '<figcaption class="mt-2 text-sm text-ink-muted">'.e($block['caption']).'</figcaption>' : '';

            return '<figure class="question-image"><img src="'.e($url).'" alt="'.e($block['alt']).'" loading="lazy">'.$caption.'</figure>';
        })->implode('');
    }

    public static function forGrading(array $blocks): string
    {
        return collect(self::normalizeBlocks($blocks))->map(function (array $block) {
            return match ($block['type']) {
                'text' => $block['text'],
                'code' => ($block['caption'] !== '' ? $block['caption']."\n" : '')."```{$block['language']}\n{$block['code']}\n```",
                'table' => self::tableToMarkdown($block['headers'], $block['rows'], $block['caption']),
                'image' => implode("\n", array_filter([
                    '[Gambar: '.$block['alt'].']',
                    $block['caption'],
                    $block['ai_context'] !== '' ? 'Konteks gambar untuk penilaian: '.$block['ai_context'] : '',
                ])),
                default => '',
            };
        })->filter()->implode("\n\n");
    }

    public static function definition(Question $question, ?string $partKey = null): array
    {
        if ($partKey === null || $partKey === '') {
            $format = in_array($question->answer_format, self::FORMATS, true) ? $question->answer_format : 'rich_text';

            return ['format' => $format, 'config' => self::normalizeAnswerConfig($format, $question->answer_config_json ?? [])];
        }

        $part = collect($question->subSoalJawaban())->firstWhere('key', $partKey);
        $format = in_array($part['answer_format'] ?? null, self::FORMATS, true) ? $part['answer_format'] : 'rich_text';

        return ['format' => $format, 'config' => self::normalizeAnswerConfig($format, (array) ($part['answer_config'] ?? []))];
    }

    public static function normalizeAnswer(string $format, array $config, ?string $text, ?array $table): array
    {
        if ($format !== 'table') {
            $value = trim((string) $text);

            return [
                'data' => ['format' => $format, 'value' => $value],
                'text' => $value,
                'empty' => $value === '',
                'complete' => $value !== '',
            ];
        }

        $rows = count($config['rows'] ?? []);
        $columns = count($config['columns'] ?? []);
        $cells = [];
        $totalLength = 0;

        for ($row = 0; $row < $rows; $row++) {
            $cells[$row] = [];
            for ($column = 0; $column < $columns; $column++) {
                $value = trim((string) ($table['cells'][$row][$column] ?? ''));
                $cells[$row][$column] = $value;
                $totalLength += mb_strlen($value);
            }
        }

        $flat = collect($cells)->flatten();
        $empty = $flat->every(fn ($value) => $value === '');
        $complete = ! $empty && $flat->every(fn ($value) => $value !== '');

        return [
            'data' => ['format' => 'table', 'value' => ['cells' => $cells]],
            'text' => $empty ? '' : self::tableToMarkdown([$config['row_header'] ?? 'Baris', ...($config['columns'] ?? [])], self::answerRows($config, $cells)),
            'empty' => $empty,
            'complete' => $complete,
            'total_length' => $totalLength,
        ];
    }

    public static function answerIsComplete(array $stored, string $format, array $config): bool
    {
        $value = $stored['value'] ?? null;
        if ($format !== 'table') {
            return trim((string) $value) !== '';
        }

        return self::normalizeAnswer('table', $config, null, is_array($value) ? $value : [])['complete'];
    }

    public static function renderAnswer(string $format, array $config, mixed $stored, ?string $fallback = null): string
    {
        $value = is_array($stored) && array_key_exists('value', $stored) ? $stored['value'] : $fallback;

        if ($format === 'table') {
            $cells = is_array($value) ? (array) ($value['cells'] ?? []) : [];

            return self::renderTable(
                [$config['row_header'] ?? 'Baris', ...(array) ($config['columns'] ?? [])],
                self::answerRows($config, $cells),
            );
        }

        if ($format === 'code') {
            return '<div class="question-code"><pre tabindex="0"><code data-language="'.e($config['language'] ?? 'text').'">'.e((string) $value).'</code></pre></div>';
        }

        return '<div class="question-rich-text">'.self::safeMarkdown((string) $value).'</div>';
    }

    public static function answerForGrading(Question $question, Answer $answer): string
    {
        $parts = $question->subSoalJawaban();
        $data = $answer->jawaban_data_json ?? [];
        $fallbackParts = $answer->jawaban_parts_json ?? [];

        if ($parts !== []) {
            return collect($parts)->map(function (array $part) use ($data, $fallbackParts) {
                $format = $part['answer_format'] ?? 'rich_text';
                $config = $part['answer_config'] ?? [];
                $text = self::answerForGradingValue(
                    $format,
                    $config,
                    $data[$part['key']] ?? null,
                    $fallbackParts[$part['key']] ?? null,
                );

                return $part['code'].'. '.$text;
            })->implode("\n\n");
        }

        $definition = self::definition($question);

        return self::answerForGradingValue(
            $definition['format'],
            $definition['config'],
            $data['__main'] ?? null,
            $answer->jawaban_teks,
        );
    }

    public static function safeMarkdown(string $markdown): string
    {
        $markdown = str_replace(['<', '>'], ['&lt;', '&gt;'], $markdown);
        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $html = preg_replace('/<img\b[^>]*>/i', '', $html) ?? '';

        return strip_tags($html, '<p><br><strong><em><ul><ol><li><code><pre><blockquote>');
    }

    private static function renderTable(array $headers, array $rows, string $caption = ''): string
    {
        $head = $headers === [] ? '' : '<thead><tr>'.collect($headers)->map(fn ($value) => '<th scope="col">'.e($value).'</th>')->implode('').'</tr></thead>';
        $body = '<tbody>'.collect($rows)->map(fn ($row) => '<tr>'.collect($row)->map(fn ($value) => '<td>'.e($value).'</td>')->implode('').'</tr>')->implode('').'</tbody>';
        $captionHtml = $caption !== '' ? '<caption>'.e($caption).'</caption>' : '';

        return '<div class="question-table" tabindex="0"><table>'.$captionHtml.$head.$body.'</table></div>';
    }

    private static function tableToMarkdown(array $headers, array $rows, string $caption = ''): string
    {
        $columns = max(count($headers), collect($rows)->map(fn ($row) => count((array) $row))->max() ?? 0);
        if ($columns === 0) {
            return $caption;
        }

        $headers = array_pad($headers, $columns, '');
        $lines = [
            '| '.implode(' | ', array_map([self::class, 'escapeTableCell'], $headers)).' |',
            '| '.implode(' | ', array_fill(0, $columns, '---')).' |',
        ];
        foreach ($rows as $row) {
            $row = array_pad(array_slice((array) $row, 0, $columns), $columns, '');
            $lines[] = '| '.implode(' | ', array_map([self::class, 'escapeTableCell'], $row)).' |';
        }

        return ($caption !== '' ? $caption."\n" : '').implode("\n", $lines);
    }

    private static function answerRows(array $config, array $cells): array
    {
        return collect($cells)->map(function ($row, $index) use ($config) {
            $label = (string) (($config['rows'] ?? [])[$index] ?? '');

            return $label !== '' ? [$label, ...$row] : $row;
        })->all();
    }

    private static function answerForGradingValue(string $format, array $config, mixed $stored, ?string $fallback): string
    {
        $value = is_array($stored) && array_key_exists('value', $stored) ? $stored['value'] : $fallback;
        if ($format === 'code') {
            return '```'.($config['language'] ?? 'text')."\n".(string) $value."\n```";
        }
        if ($format === 'table' && is_array($value)) {
            return self::normalizeAnswer('table', $config, null, $value)['text'];
        }

        return (string) $value;
    }

    private static function escapeTableCell(mixed $value): string
    {
        return str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], trim((string) $value));
    }
}
