<?php

namespace App\Services;

use App\Models\AppSetting;

class PlatformMail
{
    public static function apply(): void
    {
        $settings = AppSetting::current();

        if (blank($settings->mail_host)) {
            return;
        }

        $scheme = $settings->mail_encryption === 'ssl' ? 'smtps' : 'smtp';
        $port = $settings->mail_port ?: ($scheme === 'smtps' ? 465 : 587);

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $settings->mail_host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => $settings->mail_username,
            'mail.mailers.smtp.password' => $settings->mail_password,
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.from.address' => $settings->mail_from_address ?: config('mail.from.address'),
            'mail.from.name' => $settings->mail_from_name ?: config('mail.from.name'),
        ]);
    }
}
