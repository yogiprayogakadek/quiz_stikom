<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSetting extends Model
{
    protected $fillable = [
        'organization_id',
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

    protected function casts(): array
    {
        return [
            'ai_api_key' => 'encrypted',
            'image_api_key' => 'encrypted',
            'ai_timeout' => 'integer',
            'allow_pin_enrollment' => 'boolean',
        ];
    }

    public static function effective(int $organizationId): self
    {
        $platform = AppSetting::current();
        $setting = static::query()->where('organization_id', $organizationId)->first()
            ?? new static(['organization_id' => $organizationId]);

        foreach (AppSetting::fields() as $field) {
            if ($setting->getAttribute($field) === null) {
                $setting->setAttribute($field, $platform->getAttribute($field));
            }
        }

        return $setting;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function logoUrl(): string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : asset('logo.png');
    }
}
