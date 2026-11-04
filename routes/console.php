<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('kuis:tutup-attempt-kedaluwarsa')->everyMinute()->withoutOverlapping();
