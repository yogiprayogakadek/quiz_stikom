<?php

namespace App\Support;

final class ExamTools
{
    public const CALCULATOR = 'calculator';

    public const SUBNET_CALCULATOR = 'subnet_calculator';

    /**
     * @return array<string, array{label: string, description: string, real_exam_safe: bool, component: string, rule_summary: string}>
     */
    public static function registry(): array
    {
        return [
            self::CALCULATOR => [
                'label' => 'Kalkulator standar',
                'description' => 'Operasi dasar, kurung, persen, akar kuadrat, dan pangkat dua.',
                'real_exam_safe' => true,
                'component' => 'calculator',
                'rule_summary' => 'Kalkulator standar tersedia tanpa memasukkan hasil langsung ke jawaban.',
            ],
            self::SUBNET_CALCULATOR => [
                'label' => 'Kalkulator subnet IPv4',
                'description' => 'Menghitung mask, network, broadcast, rentang host, dan ukuran blok.',
                'real_exam_safe' => false,
                'component' => 'subnet-calculator',
                'rule_summary' => 'Kalkulator subnet IPv4 tersedia sebagai alat belajar simulasi.',
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::registry());
    }

    /** @param mixed $tools @return list<string> */
    public static function normalize(mixed $tools): array
    {
        if (! is_array($tools)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($tool) => is_string($tool) ? $tool : '', $tools),
            fn (string $tool) => array_key_exists($tool, self::registry()),
        )));
    }

    /** @param mixed $tools @return list<string> */
    public static function forQuiz(mixed $tools, bool $isTrial): array
    {
        $normalized = self::normalize($tools);

        return $isTrial
            ? $normalized
            : array_values(array_filter($normalized, fn (string $tool) => self::registry()[$tool]['real_exam_safe']));
    }

    /** @param mixed $tools @return list<string> */
    public static function forNewTrial(mixed $tools): array
    {
        return array_values(array_filter(
            self::normalize($tools),
            fn (string $tool) => self::registry()[$tool]['real_exam_safe'],
        ));
    }

    /** @param mixed $tools @return list<array{key: string, label: string, description: string, rule_summary: string}> */
    public static function details(mixed $tools): array
    {
        $registry = self::registry();

        return array_map(fn (string $key) => [
            'key' => $key,
            'label' => $registry[$key]['label'],
            'description' => $registry[$key]['description'],
            'rule_summary' => $registry[$key]['rule_summary'],
        ], self::normalize($tools));
    }
}
