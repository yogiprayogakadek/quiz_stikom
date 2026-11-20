<?php

namespace App\Services\Ai;

use App\Exceptions\AiRequestException;

interface TextClient
{
    /**
     * Daftar model yang tersedia, dikelompokkan per provider.
     *
     * @return array<string, array<string>>
     *
     * @throws AiRequestException
     */
    public function models(int $timeout): array;

    /**
     * Kirim percakapan dan kembalikan teks asisten mentah.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     *
     * @throws AiRequestException
     */
    public function chat(array $messages, ?string $model, float $temperature): string;
}
