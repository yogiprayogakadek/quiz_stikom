<?php

namespace App\Console\Commands;

use App\Services\QuizEngine;
use Illuminate\Console\Command;

class CloseExpiredAttempts extends Command
{
    protected $signature = 'kuis:tutup-attempt-kedaluwarsa';

    protected $description = 'Menutup attempt Mode Acak yang melewati batas waktu dan menghitung nilainya';

    public function handle(QuizEngine $engine): int
    {
        $jumlah = $engine->tutupAttemptKedaluwarsa();

        $this->info("{$jumlah} attempt ditutup.");

        return self::SUCCESS;
    }
}
