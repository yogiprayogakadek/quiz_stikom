<?php

return [
    'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
    'keep' => (int) env('BACKUP_KEEP', 10),
    'schedule_enabled' => (bool) env('BACKUP_SCHEDULE_ENABLED', true),
    'schedule_time' => env('BACKUP_SCHEDULE_TIME', '02:00'),
];
