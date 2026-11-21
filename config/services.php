<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ai' => [
        'base_url' => env('AI_BASE_URL', 'https://api.9router.com/v1'),
        'api_key' => env('AI_API_KEY'),
        'default_model' => env('AI_DEFAULT_MODEL', 'ag/gemini-3.8-flash-medium'),
        'timeout' => (int) env('AI_TIMEOUT', 60),
    ],

    'opencode' => [
        'base_url' => env('OPENCODE_URL', 'http://192.168.0.247:4096'),
        'url' => env('OPENCODE_URL', 'http://192.168.0.247:4096'),
        'username' => env('OPENCODE_USERNAME', 'opencode'),
        'password' => env('OPENCODE_PASSWORD'),
        'agent' => env('OPENCODE_AGENT', 'data-processor'),
        'model' => env('OPENCODE_MODEL', 'deepseek-v4.1-flash-free'),
        'model_fallbacks' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('OPENCODE_MODEL_FALLBACKS', 'big-pickle,iceman-free')),
        ))),
        'directory' => env('OPENCODE_DIRECTORY', '/srv/opencode-web'),
        'timeout' => (int) env('OPENCODE_TIMEOUT', 30),
        'poll_interval' => (int) env('OPENCODE_POLL_INTERVAL', 2),
        'session_timeout' => (int) env('OPENCODE_SESSION_TIMEOUT', 120),
        'max_attempts' => (int) env('OPENCODE_MAX_ATTEMPTS', 3),
    ],

];
