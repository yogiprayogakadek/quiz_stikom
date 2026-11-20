<?php

namespace App\Services\Ai;

use RuntimeException;

interface ImageClient
{
    /**
     * Hasilkan gambar dan kembalikan byte-nya.
     *
     * @throws RuntimeException
     */
    public function generate(string $prompt, string $model, string $size): string;
}
