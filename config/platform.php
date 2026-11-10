<?php

return [
    'ai_monthly_requests' => (int) env('PLATFORM_AI_MONTHLY_REQUESTS', 1000),
    'storage_mb' => (int) env('PLATFORM_STORAGE_MB', 1024),
    'admin_limit' => (int) env('PLATFORM_ADMIN_LIMIT', 5),
    'participant_limit' => (int) env('PLATFORM_PARTICIPANT_LIMIT', 1000),
    'class_limit' => (int) env('PLATFORM_CLASS_LIMIT', 50),
];
