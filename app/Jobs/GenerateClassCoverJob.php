<?php

namespace App\Jobs;

use App\Models\Kelas;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateClassCoverJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public function __construct(public int $kelasId) {}

    public function handle(): void
    {
        $kelas = Kelas::find($this->kelasId);
        $settings = app('app.settings');

        if (! $kelas || $settings->class_cover_mode !== 'ai') {
            return;
        }

        $baseUrl = rtrim((string) ($settings->image_base_url ?: $settings->ai_base_url), '/');
        $apiKey = $settings->image_api_key ?: $settings->ai_api_key;
        if ($baseUrl === '' || blank($apiKey) || blank($settings->image_model)) {
            Log::warning('Cover AI dilewati karena konfigurasi image API belum lengkap.', ['kelas_id' => $this->kelasId]);

            return;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(150)
                ->post($baseUrl.'/images/generations', [
                    'model' => $settings->image_model,
                    'prompt' => "Wide editorial cover for an education class named '{$kelas->nama}'. Abstract academic objects and structured learning modules, confident composition, no people, no logos, no letters, no text, landscape format.",
                    'size' => '1536x1024',
                    'n' => 1,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('Image API merespons status '.$response->status());
            }

            $base64 = $response->json('data.0.b64_json');
            $url = $response->json('data.0.url');
            $bytes = is_string($base64) && $base64 !== ''
                ? base64_decode($base64, true)
                : (is_string($url) && $url !== '' ? Http::timeout(90)->get($url)->throw()->body() : false);

            if (! is_string($bytes) || $bytes === '') {
                throw new RuntimeException('Image API tidak mengembalikan gambar.');
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
    }
}
