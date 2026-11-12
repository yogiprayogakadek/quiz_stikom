<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Font extends Model
{
    protected $fillable = [
        'organization_id',
        'nama',
        'file_path',
        'berat',
        'gaya',
        'format',
        'uploaded_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function cssFamily(): string
    {
        return 'font-'.($this->id ?? '');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function formatCss(): string
    {
        return match ($this->format) {
            'woff2' => 'woff2',
            'woff' => 'woff',
            'ttf' => 'truetype',
            'otf' => 'opentype',
            default => 'woff2',
        };
    }

    public function label(): string
    {
        return $this->nama.' · '.$this->berat.($this->gaya === 'italic' ? ' miring' : '');
    }
}
