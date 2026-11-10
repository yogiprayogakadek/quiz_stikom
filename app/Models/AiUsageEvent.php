<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AiUsageEvent extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'kind',
        'requests',
    ];

    protected function casts(): array
    {
        return [
            'requests' => 'integer',
        ];
    }
}
