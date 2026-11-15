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
        $sessions = $engine->reconcileScheduledSessions();

        $this->info("{$jumlah} attempt ditutup; {$sessions['started']} sesi dimulai; {$sessions['closed']} sesi ditutup.");

        return self::SUCCESS;
    }
}
