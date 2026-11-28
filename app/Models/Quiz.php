<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\ExamTools;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'source_quiz_id',
        'kelas_id',
        'organization_id',
        'judul',
        'jumlah_soal',
        'jumlah_soal_pg',
        'jumlah_soal_essay',
        'acak_soal',
        'kelompokkan_tipe_soal',
        'acak_opsi',
        'auto_grade_essay',
        'is_percobaan',
        'exam_tools_json',
        'remidi_nilai_min',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'source_quiz_id' => 'integer',
            'kelas_id' => 'integer',
            'jumlah_soal' => 'integer',
            'jumlah_soal_pg' => 'integer',
            'jumlah_soal_essay' => 'integer',
            'acak_soal' => 'boolean',
            'kelompokkan_tipe_soal' => 'boolean',
            'acak_opsi' => 'boolean',
            'auto_grade_essay' => 'boolean',
            'is_percobaan' => 'boolean',
            'exam_tools_json' => 'array',
            'remidi_nilai_min' => 'integer',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function sourceQuiz(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_quiz_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function soalTerpilih(): int
    {
        if ($this->jumlah_soal_pg !== null || $this->jumlah_soal_essay !== null) {
            $totalObjektif = $this->questions()->where('tipe', '!=', 'essay')->count();
            $totalEssay = $this->questions()->where('tipe', 'essay')->count();

            $dipakaiObjektif = $this->jumlah_soal_pg === null
                ? $totalObjektif
                : min($this->jumlah_soal_pg, $totalObjektif);
            $dipakaiEssay = $this->jumlah_soal_essay === null
                ? $totalEssay
                : min($this->jumlah_soal_essay, $totalEssay);

            return $dipakaiObjektif + $dipakaiEssay;
        }

        $total = $this->questions()->count();

        return $this->jumlah_soal === null ? $total : min($this->jumlah_soal, $total);
    }

    public function isLocked(): bool
    {
        return $this->sessions()
            ->whereIn('status', ['lobby', 'berjalan', 'selesai'])
            ->exists();
    }

    /** @return list<string> */
    public function examTools(): array
    {
        return ExamTools::forQuiz($this->exam_tools_json, (bool) $this->is_percobaan);
    }
}
