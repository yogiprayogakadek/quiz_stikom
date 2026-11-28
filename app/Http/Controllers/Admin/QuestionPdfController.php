<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionMedia;
use App\Models\Quiz;
use App\Support\OrganizationContext;
use App\Support\QuestionContent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuestionPdfController extends Controller
{
    public function preview(Request $request, Quiz $quiz): View
    {
        $payload = $this->buildPayload($request, $quiz);

        return view('admin.questions.pdf', $payload);
    }

    public function download(Request $request, Quiz $quiz): Response
    {
        $payload = $this->buildPayload($request, $quiz);
        abort_if($payload['questions']->isEmpty(), 422, 'Kuis belum memiliki soal untuk diekspor ke PDF.');

        $suffix = $payload['meta']['varian'] === 'pengajar' ? '-kunci-pengajar' : '';
        $filename = 'naskah-soal-'.Str::slug($quiz->judul ?: 'kuis').$suffix.'.pdf';

        $pdf = Pdf::loadView('admin.questions.pdf-export', $payload)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 120,
            ]);

        return $pdf->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Request $request, Quiz $quiz): array
    {
        $quiz->load(['kelas', 'creator']);
        $questions = $quiz->questions()
            ->with(['options' => fn ($query) => $query->orderBy('id'), 'media'])
            ->orderBy('id')
            ->get();

        $organization = app(OrganizationContext::class)->current();
        $appSettings = app('app.settings');

        $defaultMenit = $questions->contains(fn (Question $q) => $q->isEssay())
            ? 100
            : max(1, (int) ceil(((int) $questions->sum('durasi_detik')) / 60));

        $validated = $request->validate([
            'varian' => ['nullable', 'in:peserta,pengajar'],
            'judul_ujian' => ['nullable', 'string', 'max:160'],
            'subjudul' => ['nullable', 'string', 'max:160'],
            'mata_kuliah' => ['nullable', 'string', 'max:160'],
            'dosen' => ['nullable', 'string', 'max:120'],
            'program_studi' => ['nullable', 'string', 'max:120'],
            'waktu_menit' => ['nullable', 'integer', 'min:1', 'max:600'],
            'sifat_ujian' => ['nullable', 'string', 'max:60'],
            'kode_cpmk' => ['nullable', 'string', 'max:80'],
            'kolom_identitas' => ['nullable', 'boolean'],
        ]);

        $varian = $validated['varian'] ?? 'peserta';
        $kolomIdentitas = $request->has('kolom_identitas')
            ? $request->boolean('kolom_identitas')
            : ($varian === 'peserta');

        $meta = [
            'varian' => $varian,
            'judul_ujian' => trim((string) ($validated['judul_ujian'] ?? '')) ?: $quiz->judul,
            'subjudul' => trim((string) ($validated['subjudul'] ?? '')),
            'mata_kuliah' => trim((string) ($validated['mata_kuliah'] ?? '')) ?: ($quiz->kelas?->nama ?? $quiz->judul),
            'dosen' => trim((string) ($validated['dosen'] ?? '')) ?: ($quiz->creator?->nama ?? $request->user()->nama),
            'kelas' => $quiz->kelas?->nama ?? '-',
            'program_studi' => trim((string) ($validated['program_studi'] ?? '')),
            'waktu_menit' => (int) ($validated['waktu_menit'] ?? $defaultMenit),
            'sifat_ujian' => trim((string) ($validated['sifat_ujian'] ?? 'TERTUTUP (CLOSE)')),
            'kode_cpmk' => trim((string) ($validated['kode_cpmk'] ?? '')),
            'kolom_identitas' => $kolomIdentitas,
        ];

        $mediaMap = $this->encodeQuestionMedia($questions, $quiz->organization_id);
        $preparedQuestions = $questions->map(fn (Question $q, int $idx) => $this->prepareQuestion($q, $idx + 1, $mediaMap));

        return [
            'quiz' => $quiz,
            'questions' => $preparedQuestions,
            'rawQuestionCount' => $questions->count(),
            'pgCount' => $questions->where('tipe', 'pilihan_ganda')->count(),
            'essayCount' => $questions->where('tipe', 'essay')->count(),
            'totalBobot' => (int) $questions->sum('bobot'),
            'meta' => $meta,
            'institutionName' => $organization?->name ?? ($appSettings->site_name ?? 'Platform Kuis'),
            'institutionTagline' => trim((string) ($appSettings->site_tagline ?? '')),
            'primaryColor' => $appSettings->primary_color ?? '#1d4ed8',
            'logoDataUri' => $this->resolveLogoDataUri($appSettings->logo_path ?? null),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Question>  $questions
     * @return array<int, string>
     */
    private function encodeQuestionMedia($questions, ?int $organizationId): array
    {
        $encoded = [];

        foreach ($questions as $question) {
            foreach ($question->media as $media) {
                if (! $media instanceof QuestionMedia) {
                    continue;
                }
                if ($organizationId !== null && (int) $media->organization_id !== (int) $organizationId) {
                    continue;
                }
                if (! Storage::disk('local')->exists($media->path)) {
                    continue;
                }

                $bytes = Storage::disk('local')->get($media->path);
                $mime = $media->mime_type ?: 'image/png';
                $encoded[$media->id] = 'data:'.$mime.';base64,'.base64_encode($bytes);
            }
        }

        return $encoded;
    }

    /**
     * @param  array<int, string>  $mediaMap
     * @return array<string, mixed>
     */
    private function prepareQuestion(Question $question, int $nomor, array $mediaMap): array
    {
        $subSoal = collect($question->subSoalTerstruktur())->map(function (array $part) use ($mediaMap) {
            $format = $part['answer_format'] ?? 'rich_text';
            $config = QuestionContent::normalizeAnswerConfig($format, (array) ($part['answer_config'] ?? []));

            return [
                ...$part,
                'blocks' => $this->prepareBlocks($part['content_blocks'] ?? [], $mediaMap),
                'answer_format' => $format,
                'answer_config' => $config,
            ];
        })->all();

        $def = QuestionContent::definition($question);

        return [
            'id' => $question->id,
            'nomor' => $nomor,
            'tipe' => $question->tipe,
            'bobot' => (int) ($question->bobot ?? 1),
            'durasi_detik' => (int) ($question->durasi_detik ?? 30),
            'teks' => $question->teks,
            'blocks' => $this->prepareBlocks($question->content_blocks_json ?? [], $mediaMap),
            'options' => $question->options->values()->map(fn ($opt, int $idx) => [
                'huruf' => chr(65 + $idx),
                'teks' => $opt->teks,
                'is_correct' => (bool) $opt->is_correct,
            ])->all(),
            'sub_soal' => $subSoal,
            'answer_format' => $def['format'],
            'answer_config' => $def['config'],
            'pembahasan' => trim((string) ($question->pembahasan ?? '')),
            'kunci_essay' => trim((string) ($question->kunci_essay ?? '')),
            'rubrik_essay' => is_array($question->rubrik_essay) ? $question->rubrik_essay : [],
        ];
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @param  array<int, string>  $mediaMap
     * @return array<int, array<string, mixed>>
     */
    private function prepareBlocks(array $blocks, array $mediaMap): array
    {
        return collect(QuestionContent::normalizeBlocks($blocks))->map(function (array $block) use ($mediaMap) {
            if ($block['type'] === 'text') {
                $block['html'] = QuestionContent::safeMarkdown($block['text']);
            } elseif ($block['type'] === 'image') {
                $block['data_uri'] = $mediaMap[(int) ($block['media_id'] ?? 0)] ?? null;
            }

            return $block;
        })->filter(fn (array $block) => $block['type'] !== 'image' || ! empty($block['data_uri']))->values()->all();
    }

    private function resolveLogoDataUri(?string $logoPath): ?string
    {
        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            $bytes = Storage::disk('public')->get($logoPath);
            $mime = Storage::disk('public')->mimeType($logoPath) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($bytes);
        }

        $fallback = public_path('logo.png');
        if (is_file($fallback)) {
            $bytes = (string) file_get_contents($fallback);

            return 'data:image/png;base64,'.base64_encode($bytes);
        }

        return null;
    }
}
