<?php

namespace App\Console\Commands;

use App\Services\QuizEngine;
use App\Services\SystemHealth;
use Illuminate\Console\Command;

class CloseExpiredAttempts extends Command
{
    protected $signature = 'kuis:tutup-attempt-kedaluwarsa';

    protected $description = 'Menutup attempt Mode Acak yang melewati batas waktu dan menghitung nilainya';

    public function handle(QuizEngine $engine, SystemHealth $health): int
    {
        $health->recordSchedulerHeartbeat();

        $jumlah = $engine->tutupAttemptKedaluwarsa();
        $sessions = $engine->reconcileScheduledSessions();
        $serempak = $engine->reconcileSerempakSessions();

        $this->info("{$jumlah} attempt ditutup; {$sessions['started']} sesi dimulai; {$sessions['closed']} sesi ditutup; {$serempak['question_closed']} soal serempak ditutup; {$serempak['question_advanced']} soal serempak dilanjutkan.");

        return self::SUCCESS;
    }
}
