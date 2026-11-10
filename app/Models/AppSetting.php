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
        'ai_base_url',
        'ai_api_key',
        'ai_default_model',
        'ai_timeout',
        'class_cover_mode',
        'image_base_url',
        'image_api_key',
        'image_model',
    ];

    protected function casts(): array
    {
        return [
            'ai_api_key' => 'encrypted',
            'image_api_key' => 'encrypted',
            'ai_timeout' => 'integer',
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
            'ai_base_url' => config('services.ai.base_url'),
            'ai_default_model' => config('services.ai.default_model'),
            'ai_timeout' => config('services.ai.timeout', 60),
            'class_cover_mode' => 'local',
        ]);
    }

    public function logoUrl(): string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : asset('logo.png');
    }
}
