<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('kuis:tutup-attempt-kedaluwarsa')->everyMinute()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=48')->daily();
