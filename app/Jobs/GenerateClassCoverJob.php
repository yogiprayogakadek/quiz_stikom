<?php

namespace App\Jobs;

use App\Models\Kelas;
use App\Services\Ai\AiManager;
use App\Services\OrganizationUsage;
use App\Support\OrganizationContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateClassCoverJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public function __construct(public int $kelasId) {}

    public function handle(OrganizationUsage $usage, OrganizationContext $context): void
    {
        $kelas = Kelas::withoutGlobalScopes()->find($this->kelasId);

        if (! $kelas) {
            return;
        }

        $context->force($kelas->organization);

        try {
            $settings = app('app.settings');

            if ($settings->class_cover_mode !== 'ai') {
                return;
            }

            $image = app(AiManager::class)->image();

            if ($image === null) {
                Log::warning('Cover AI dilewati karena konfigurasi image API belum lengkap.', ['kelas_id' => $this->kelasId]);

                return;
            }

            try {
                if ($kelas->organization_id !== null) {
                    $usage->assertAiQuota($kelas->organization_id);
                }
            } catch (RuntimeException $e) {
                Log::warning('Cover AI dilewati karena kuota AI habis.', [
                    'kelas_id' => $this->kelasId,
                    'error' => $e->getMessage(),
                ]);

                return;
            }

            try {
                $bytes = $image->generate(
                    "Wide editorial cover for an education class named '{$kelas->nama}'. Abstract academic objects and structured learning modules, confident composition, no people, no logos, no letters, no text, landscape format.",
                    (string) $settings->image_model,
                    '1536x1024',
                );

                if ($kelas->organization_id !== null) {
                    $usage->recordAi($kelas->organization_id, 'cover');
                }

                $imageInfo = getimagesizefromstring($bytes);
                if ($imageInfo === false || strlen($bytes) > 10 * 1024 * 1024) {
                    throw new RuntimeException('File dari Image API bukan gambar yang valid atau melebihi 10 MB.');
                }

                $extension = match ($imageInfo['mime'] ?? '') {
                    'image/jpeg' => 'jpg',
                    'image/webp' => 'webp',
                    default => 'png',
                };

                if ($kelas->organization_id !== null && ! $usage->canStore($kelas->organization_id, strlen($bytes), $kelas->cover_path)) {
                    Log::warning('Cover AI dibuang karena kuota penyimpanan workspace habis.', ['kelas_id' => $this->kelasId]);

                    return;
                }

                $path = 'class-covers/'.$kelas->id.'-'.now()->timestamp.'.'.$extension;
                Storage::disk('public')->put($path, $bytes);

                if ($kelas->cover_path) {
                    Storage::disk('public')->delete($kelas->cover_path);
                }
                $kelas->update(['cover_path' => $path]);
            } catch (Throwable $e) {
                Log::warning('Generate cover kelas gagal; cover lokal tetap digunakan.', [
                    'kelas_id' => $this->kelasId,
                    'error' => $e->getMessage(),
                ]);
            }
        } finally {
            $context->force(null);
        }
    }
}
