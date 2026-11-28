<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionMedia;
use App\Support\QuestionContent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuestionMediaService
{
    public function __construct(private OrganizationUsage $usage) {}

    public function persistBlocks(Question $question, array $blocks, array &$storedPaths = []): array
    {
        return collect(QuestionContent::normalizeBlocks($blocks))->map(function (array $block) use ($question, &$storedPaths) {
            if ($block['type'] !== 'image') {
                return $block;
            }

            $upload = $block['image'] ?? null;
            unset($block['image']);

            if ($upload instanceof UploadedFile) {
                if (! $this->usage->canStore((int) $question->organization_id, (int) $upload->getSize())) {
                    throw ValidationException::withMessages([
                        'content_blocks' => 'Kuota penyimpanan workspace tidak cukup untuk gambar soal ini.',
                    ]);
                }

                $extension = strtolower($upload->guessExtension() ?: $upload->extension() ?: 'bin');
                $path = 'question-media/'.$question->organization_id.'/'.Str::uuid().'.'.$extension;
                if (! Storage::disk('local')->putFileAs(dirname($path), $upload, basename($path))) {
                    throw ValidationException::withMessages([
                        'content_blocks' => 'Gambar soal gagal disimpan. Coba unggah kembali.',
                    ]);
                }
                $storedPaths[] = $path;

                $media = $question->media()->create([
                    'organization_id' => $question->organization_id,
                    'path' => $path,
                    'mime_type' => (string) $upload->getMimeType(),
                    'size_bytes' => (int) $upload->getSize(),
                    'original_name' => Str::limit($upload->getClientOriginalName(), 255, ''),
                ]);
                $block['media_id'] = $media->id;
            } else {
                $media = $question->media()->whereKey((int) ($block['media_id'] ?? 0))->first();
                if (! $media) {
                    throw ValidationException::withMessages([
                        'content_blocks' => 'Gambar soal tidak ditemukan. Unggah kembali gambar tersebut.',
                    ]);
                }
                $block['media_id'] = $media->id;
            }

            return $block;
        })->values()->all();
    }

    public function pruneUnused(Question $question): void
    {
        $blocks = collect($question->content_blocks_json ?? [])
            ->merge(collect($question->sub_soal_json ?? [])->flatMap(fn ($part) => $part['content_blocks'] ?? []));
        $used = $blocks->where('type', 'image')->pluck('media_id')->filter()->map(fn ($id) => (int) $id)->all();

        $question->media()->when($used !== [], fn ($query) => $query->whereNotIn('id', $used))->get()
            ->each(function (QuestionMedia $media) {
                Storage::disk('local')->delete($media->path);
                $media->delete();
            });
    }

    public function cloneBlocks(Question $source, Question $target, array $blocks, array &$storedPaths = [], string $errorKey = 'trial'): array
    {
        $mapping = [];

        return collect(QuestionContent::normalizeBlocks($blocks))->map(function (array $block) use ($source, $target, &$storedPaths, &$mapping, $errorKey) {
            if ($block['type'] !== 'image') {
                return $block;
            }

            $sourceId = (int) ($block['media_id'] ?? 0);
            if (isset($mapping[$sourceId])) {
                $block['media_id'] = $mapping[$sourceId];

                return $block;
            }

            $media = $source->media()->whereKey($sourceId)->firstOrFail();
            if (! $this->usage->canStore((int) $target->organization_id, $media->size_bytes)) {
                throw ValidationException::withMessages([$errorKey => 'Kuota penyimpanan tidak cukup untuk menyalin media soal.']);
            }

            $extension = pathinfo($media->path, PATHINFO_EXTENSION);
            $path = 'question-media/'.$target->organization_id.'/'.Str::uuid().($extension !== '' ? '.'.$extension : '');
            if (! Storage::disk('local')->copy($media->path, $path)) {
                throw ValidationException::withMessages([$errorKey => 'Media soal gagal disalin. Coba lagi.']);
            }
            $storedPaths[] = $path;

            $copy = $target->media()->create([
                'organization_id' => $target->organization_id,
                'path' => $path,
                'mime_type' => $media->mime_type,
                'size_bytes' => $media->size_bytes,
                'original_name' => $media->original_name,
            ]);
            $mapping[$sourceId] = $copy->id;
            $block['media_id'] = $copy->id;

            return $block;
        })->values()->all();
    }

    public function cleanupPaths(array $paths): void
    {
        Storage::disk('local')->delete(array_values(array_unique($paths)));
    }
}
