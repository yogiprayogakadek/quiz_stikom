<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\QuestionContent;
use App\Support\SubQuestionTree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Question extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'quiz_id',
        'organization_id',
        'teks',
        'tipe',
        'durasi_detik',
        'bobot',
        'pembahasan',
        'kunci_essay',
        'rubrik_essay',
        'petunjuk_json',
        'sub_soal_json',
        'content_blocks_json',
        'answer_format',
        'answer_config_json',
    ];

    protected function casts(): array
    {
        return [
            'quiz_id' => 'integer',
            'durasi_detik' => 'integer',
            'bobot' => 'integer',
            'rubrik_essay' => 'array',
            'petunjuk_json' => 'array',
            'sub_soal_json' => 'array',
            'content_blocks_json' => 'array',
            'answer_config_json' => 'array',
        ];
    }

    public function isEssay(): bool
    {
        return $this->tipe === 'essay';
    }

    public function teksUntukPenilaian(): string
    {
        $teks = (string) $this->teks;
        $konten = QuestionContent::forGrading($this->content_blocks_json ?? []);
        if ($konten !== '') {
            $teks .= "\n\n".$konten;
        }

        foreach ($this->subSoalTerstruktur() as $bagian) {
            $label = $bagian['code'];
            $isi = trim((string) ($bagian['teks'] ?? ''));

            if ($isi === '') {
                continue;
            }

            $teks .= "\n\n".($label !== '' ? $label.'. ' : '').$isi;
            $kontenBagian = QuestionContent::forGrading($bagian['content_blocks'] ?? []);
            if ($kontenBagian !== '') {
                $teks .= "\n".$kontenBagian;
            }
        }

        return $teks;
    }

    public function subSoalTerstruktur(): array
    {
        return SubQuestionTree::decorate($this->sub_soal_json ?? []);
    }

    public function subSoalJawaban(): array
    {
        return SubQuestionTree::leaves($this->sub_soal_json ?? []);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(QuestionMedia::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Question $question) {
            $question->media()->get()->each(function (QuestionMedia $media) {
                Storage::disk('local')->delete($media->path);
                $media->delete();
            });
        });
    }

    public function correctOption(): ?Option
    {
        return $this->options->firstWhere('is_correct', true);
    }

    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'quiz_questions');
    }

    public function scopeUnlocked(Builder $query): Builder
    {
        return $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('session_questions')
                ->join('quiz_sessions', 'quiz_sessions.id', '=', 'session_questions.quiz_session_id')
                ->whereColumn('session_questions.question_id', 'questions.id')
                ->whereIn('quiz_sessions.status', ['lobby', 'berjalan', 'selesai']);
        })->whereDoesntHave('quiz.sessions', fn (Builder $sq) => $sq->whereIn('status', ['lobby', 'berjalan', 'selesai']));
    }

    public function isLocked(): bool
    {
        $sudahDibuka = fn (Builder $q) => $q->whereIn('status', ['lobby', 'berjalan', 'selesai']);

        return SessionQuestion::query()
            ->where('question_id', $this->id)
            ->whereHas('quizSession', $sudahDibuka)
            ->exists()
            || ($this->quiz && $this->quiz->sessions()->where($sudahDibuka)->exists());
    }
}
