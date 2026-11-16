<?php

namespace App\Support;

class SubQuestionTree
{
    public static function decorate(array $items): array
    {
        $normalized = [];
        $knownKeys = [];

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $storedKey = trim((string) ($item['key'] ?? ''));
            $key = $storedKey !== '' ? $storedKey : $label;

            if ($key === '' || isset($knownKeys[$key])) {
                $key = 'legacy-'.$index;
            }

            $knownKeys[$key] = true;
            $normalized[] = [
                'key' => $key,
                'parent_key' => filled($item['parent_key'] ?? null) ? trim((string) $item['parent_key']) : null,
                'label' => $label,
                'teks' => trim((string) ($item['teks'] ?? '')),
                'petunjuk' => array_values(array_filter(
                    array_map(fn ($point) => trim((string) $point), (array) ($item['petunjuk'] ?? [])),
                    fn (string $point) => $point !== '',
                )),
                'content_blocks' => QuestionContent::normalizeBlocks((array) ($item['content_blocks'] ?? [])),
                'answer_format' => in_array($item['answer_format'] ?? null, QuestionContent::FORMATS, true)
                    ? $item['answer_format']
                    : 'rich_text',
                'answer_config' => QuestionContent::normalizeAnswerConfig(
                    in_array($item['answer_format'] ?? null, QuestionContent::FORMATS, true) ? $item['answer_format'] : 'rich_text',
                    (array) ($item['answer_config'] ?? []),
                ),
                'legacy' => $storedKey === '',
            ];
        }

        $children = [];
        foreach ($normalized as $item) {
            $children[$item['parent_key'] ?? ''][] = $item;
        }

        $ordered = [];
        $visited = [];
        $visit = function (string $parentKey) use (&$visit, &$ordered, &$visited, $children): void {
            foreach ($children[$parentKey] ?? [] as $item) {
                if (isset($visited[$item['key']])) {
                    continue;
                }

                $visited[$item['key']] = true;
                $ordered[] = $item;
                $visit($item['key']);
            }
        };
        $visit('');

        foreach ($normalized as $item) {
            if (! isset($visited[$item['key']])) {
                $ordered[] = $item;
            }
        }
        $normalized = $ordered;

        $byKey = collect($normalized)->keyBy('key');
        $childCounts = collect($normalized)->countBy(fn (array $item) => $item['parent_key'] ?? '__root__');
        $paths = [];

        $resolvePath = function (array $item, array $trail = []) use (&$resolvePath, &$paths, $byKey): array {
            if (isset($paths[$item['key']])) {
                return $paths[$item['key']];
            }

            if (isset($trail[$item['key']])) {
                return [$item['label']];
            }

            $parent = $item['parent_key'] ? $byKey->get($item['parent_key']) : null;
            if (! $parent) {
                return $paths[$item['key']] = [$item['label']];
            }

            $trail[$item['key']] = true;

            return $paths[$item['key']] = [...$resolvePath($parent, $trail), $item['label']];
        };

        return collect($normalized)
            ->map(function (array $item) use ($resolvePath, $childCounts) {
                $path = $resolvePath($item);
                $hasChildren = ($childCounts[$item['key']] ?? 0) > 0;

                return [
                    ...$item,
                    'depth' => max(0, count($path) - 1),
                    'code' => implode('.', $path),
                    'has_children' => $hasChildren,
                    'is_leaf' => ! $hasChildren,
                ];
            })
            ->values()
            ->all();
    }

    public static function leaves(array $items): array
    {
        return array_values(array_filter(
            self::decorate($items),
            fn (array $item) => $item['is_leaf'],
        ));
    }
}
