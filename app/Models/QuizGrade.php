<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizGrade extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'sesi_awal_id',
        'sesi_remidi_id',
        'user_id',
        'nilai_awal',
        'nilai_remidi',
        'nilai_akhir',
        'sumber',
        'ditetapkan_oleh',
        'ditetapkan_at',
    ];

    protected function casts(): array
    {
        return [
            'nilai_awal' => 'integer',
            'nilai_remidi' => 'integer',
            'nilai_akhir' => 'integer',
            'ditetapkan_at' => 'datetime',
        ];
    }

    public function sesiAwal(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'sesi_awal_id');
    }

    public function sesiRemidi(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'sesi_remidi_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function penilai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh')->withTrashed();
    }

    public static function untukAttempt(Attempt $attempt): ?self
    {
        $attempt->loadMissing('quizSession');

        if ($attempt->quizSession === null) {
            return null;
        }

        return $attempt->quizSession->isRemidi()
            ? (static::query()
                ->where('sesi_awal_id', $attempt->quizSession->remidi_dari_session_id)
                ->where('user_id', $attempt->user_id)
                ->first()
                ?? static::query()->where('sesi_remidi_id', $attempt->quiz_session_id)->first())
            : static::query()->where('sesi_awal_id', $attempt->quiz_session_id)->first();
    }
}
