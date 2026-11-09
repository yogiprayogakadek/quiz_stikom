<?php

return [
    'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
    'keep' => (int) env('BACKUP_KEEP', 10),
];
