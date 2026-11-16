<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionMedia extends Model
{
    use BelongsToOrganization;

    protected $table = 'question_media';

    protected $fillable = [
        'organization_id',
        'question_id',
        'path',
        'mime_type',
        'size_bytes',
        'original_name',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
