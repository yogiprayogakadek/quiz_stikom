<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AppSetting extends Model
{
    public static function fields(): array
    {
        return [
            'site_name',
            'site_tagline',
            'organization_name',
            'primary_color',
            'logo_path',
            'login_title',
            'login_description',
            'login_layout',
            'locale',
            'allow_pin_enrollment',
            'ai_base_url',
            'ai_api_key',
            'ai_default_model',
            'ai_timeout',
            'class_cover_mode',
            'image_base_url',
            'image_api_key',
            'image_model',
        ];
    }

    protected $fillable = [
        'site_name',
        'site_tagline',
        'organization_name',
        'primary_color',
        'logo_path',
        'login_title',
        'login_description',
        'login_layout',
        'locale',
        'allow_pin_enrollment',
        'ai_base_url',
        'ai_api_key',
        'ai_default_model',
        'ai_timeout',
        'class_cover_mode',
        'image_base_url',
        'image_api_key',
        'image_model',
        'maintenance_enabled',
        'maintenance_message',
        'maintenance_started_at',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'mail_from_address',
        'mail_from_name',
    ];

    protected function casts(): array
    {
        return [
            'ai_api_key' => 'encrypted',
            'image_api_key' => 'encrypted',
            'ai_timeout' => 'integer',
            'allow_pin_enrollment' => 'boolean',
            'maintenance_enabled' => 'boolean',
            'maintenance_started_at' => 'datetime',
            'mail_password' => 'encrypted',
            'mail_port' => 'integer',
        ];
    }

    public static function current(): self
    {
        $current = Schema::hasTable('app_settings') ? static::query()->find(1) : null;

        return $current ?? new static([
            'site_name' => config('app.name', 'STIKOM Quiz'),
            'site_tagline' => 'Kuis kelas yang cepat dan terpantau',
            'primary_color' => '#2563eb',
            'login_title' => 'Masuk ke ruang kuis',
            'login_description' => 'Gunakan akun yang diberikan oleh pengelola kelas.',
            'login_layout' => 'split',
            'locale' => config('app.locale', 'id'),
            'allow_pin_enrollment' => true,
            'ai_base_url' => config('services.ai.base_url'),
            'ai_default_model' => config('services.ai.default_model'),
            'ai_timeout' => config('services.ai.timeout', 60),
            'class_cover_mode' => 'local',
            'maintenance_enabled' => false,
            'maintenance_message' => 'Sistem sedang menjalani pemeliharaan. Silakan coba kembali beberapa saat lagi.',
        ]);
    }

    public function logoUrl(): string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : asset('logo.png');
    }
}
