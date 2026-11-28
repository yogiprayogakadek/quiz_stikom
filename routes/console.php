<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('kuis:tutup-attempt-kedaluwarsa')->everyMinute()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=48')->daily();
Schedule::command('kuis:backup-database')
    ->dailyAt((string) config('backup.schedule_time', '02:00'))
    ->withoutOverlapping();
Schedule::command('kuis:bersihkan-data-usang')->dailyAt('02:30');
