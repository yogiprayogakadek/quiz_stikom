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
        'urutan_tetap',
        'is_bonus',
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
            'urutan_tetap' => 'integer',
            'is_bonus' => 'boolean',
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

    public function labelTipe(): string
    {
        return match ($this->tipe) {
            'essay' => 'Essay',
            'pilihan_majemuk' => 'Pilihan majemuk',
            'isian_singkat' => 'Isian singkat',
            default => 'Pilihan ganda',
        };
    }

    /**
     * @return array{benar_penuh: bool, rasio: float, skor_acak: int, teks_jawaban: ?string, teks_kunci: ?string}
     */
    public function evaluasiOtomatis(?Answer $answer): array
    {
        if (! $this->relationLoaded('options')) {
            $this->load('options');
        }

        $bobot = max(1, (int) $this->bobot);

        $teksKunci = match (true) {
            (bool) $this->is_bonus => 'Bonus (dianulir)',
            $this->tipe === 'pilihan_majemuk' => $this->options->where('is_correct', true)->pluck('teks')->implode(', ') ?: null,
            $this->tipe === 'isian_singkat' => $this->options->where('is_correct', true)->pluck('teks')->implode(' / ') ?: null,
            default => $this->correctOption()?->teks,
        };

        $teksJawaban = null;
        if ($answer !== null) {
            if ($this->tipe === 'pilihan_majemuk') {
                $selectedIds = array_values(array_unique(array_map(
                    'intval',
                    $answer->selected_option_ids_json ?? ($answer->option_id ? [$answer->option_id] : [])
                )));
                $joined = $this->options->whereIn('id', $selectedIds)->pluck('teks')->implode(', ');
                $teksJawaban = $joined !== '' ? $joined : null;
            } elseif ($this->tipe === 'isian_singkat') {
                $trimmed = trim((string) ($answer->jawaban_teks ?? ''));
                $teksJawaban = $trimmed !== '' ? $trimmed : null;
            } else {
                $teksJawaban = $answer->relationLoaded('option') && $answer->option !== null
                    ? $answer->option->teks
                    : ($answer->option_id ? $this->options->firstWhere('id', (int) $answer->option_id)?->teks : null);
            }
        }

        if ($this->is_bonus) {
            return [
                'benar_penuh' => true,
                'rasio' => 1.0,
                'skor_acak' => $bobot,
                'teks_jawaban' => $teksJawaban,
                'teks_kunci' => 'Bonus (dianulir)',
            ];
        }

        if ($this->tipe === 'pilihan_majemuk') {
            $kunciIds = $this->options
                ->where('is_correct', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $selectedIds = collect($answer?->selected_option_ids_json ?? ($answer?->option_id ? [$answer->option_id] : []))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            if ($kunciIds === [] || $selectedIds === []) {
                return [
                    'benar_penuh' => false,
                    'rasio' => 0.0,
                    'skor_acak' => 0,
                    'teks_jawaban' => $teksJawaban,
                    'teks_kunci' => $teksKunci,
                ];
            }

            $benarPenuh = $selectedIds === $kunciIds;
            $scoringMode = (string) ($this->answer_config_json['scoring_mode'] ?? 'all_or_nothing');

            if ($scoringMode === 'partial') {
                $benarDipilih = count(array_intersect($selectedIds, $kunciIds));
                $salahDipilih = count(array_diff($selectedIds, $kunciIds));
                $rasio = max(0.0, min(1.0, ($benarDipilih - $salahDipilih) / max(1, count($kunciIds))));
                $skorAcak = $benarPenuh ? $bobot : (int) round($bobot * $rasio);
            } else {
                $rasio = $benarPenuh ? 1.0 : 0.0;
                $skorAcak = $benarPenuh ? $bobot : 0;
            }

            return [
                'benar_penuh' => $benarPenuh,
                'rasio' => $rasio,
                'skor_acak' => $skorAcak,
                'teks_jawaban' => $teksJawaban,
                'teks_kunci' => $teksKunci,
            ];
        }

        if ($this->tipe === 'isian_singkat') {
            $caseSensitive = (bool) ($this->answer_config_json['case_sensitive'] ?? false);
            $normalisasi = static function (string $nilai) use ($caseSensitive): string {
                $rapi = preg_replace('/\s+/u', ' ', trim($nilai)) ?? '';

                return $caseSensitive ? $rapi : mb_strtolower($rapi);
            };

            $inputNorm = $normalisasi((string) ($answer?->jawaban_teks ?? ''));
            $tepat = $inputNorm !== '' && $this->options
                ->where('is_correct', true)
                ->contains(fn ($opt) => $normalisasi((string) $opt->teks) === $inputNorm);

            return [
                'benar_penuh' => $tepat,
                'rasio' => $tepat ? 1.0 : 0.0,
                'skor_acak' => $tepat ? $bobot : 0,
                'teks_jawaban' => $teksJawaban,
                'teks_kunci' => $teksKunci,
            ];
        }

        $opsiId = (int) ($answer?->option_id ?? 0);
        $tepat = ($answer?->relationLoaded('option') && $answer->option?->is_correct === true)
            || ($opsiId > 0 && $this->options->firstWhere('id', $opsiId)?->is_correct === true);

        return [
            'benar_penuh' => $tepat,
            'rasio' => $tepat ? 1.0 : 0.0,
            'skor_acak' => $tepat ? $bobot : 0,
            'teks_jawaban' => $teksJawaban,
            'teks_kunci' => $teksKunci,
        ];
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
