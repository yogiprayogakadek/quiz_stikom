<?php

namespace Tests\Unit;

use App\Exceptions\AiRequestException;
use App\Services\Ai\AiConfig;
use App\Services\Ai\OpenCodeTextClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenCodeTextClientTest extends TestCase
{
    private function client(array $open = []): OpenCodeTextClient
    {
        return new OpenCodeTextClient(new AiConfig('opencode',
            ['base_url' => '', 'api_key' => null, 'model' => '', 'timeout' => 60],
            array_merge(['base_url' => 'https://oc.example.test/', 'username' => 'opencode', 'password' => 'rahasia', 'model' => 'pilihan-saya', 'timeout' => 120], $open),
            ['base_url' => '', 'api_key' => null, 'model' => '', 'timeout' => 150]));
    }

    public function test_models_selalu_mengirim_types_chat_dan_basic_auth(): void
    {
        Http::fake(['https://oc.example.test/api/model*' => Http::response(['location' => ['directory' => '/srv'], 'data' => [
            ['id' => 'big-pickle', 'providerID' => 'opencode', 'type' => 'chat', 'enabled' => true],
            ['id' => 'nemotron-3.5-super-vl-free', 'providerID' => 'opencode', 'type' => 'chat', 'enabled' => true],
        ]])]);

        $models = $this->client()->models();

        $this->assertSame(['OpenCode'], array_keys($models));
        $this->assertContains('nemotron-3.5-super-vl-free', $models['OpenCode']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'types=chat')
            && ! str_contains($r->url(), '//api')
            && $r->header('Authorization')[0] === 'Basic '.base64_encode('opencode:rahasia'));
    }

    public function test_models_of_type_image_hanya_mengambil_model_gambar(): void
    {
        Http::fake(['https://oc.example.test/api/model*' => Http::response(['data' => [
            ['id' => 'grok-imagine-image-2.0', 'providerID' => 'opencode', 'type' => 'image'],
        ]])]);

        $this->assertSame(['grok-imagine-image-2.0'], $this->client()->modelsOfType('image'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'types=image'));
    }

    public function test_chat_menggabungkan_prompt_dan_memakai_provider_opencode(): void
    {
        Http::fake(['https://oc.example.test/api/experimental/generate/text' => Http::response(['data' => ['text' => '{"ok":true}']])]);

        $text = $this->client()->chat([
            ['role' => 'system', 'content' => 'SISTEM'],
            ['role' => 'user', 'content' => 'PENGGUNA'],
        ], null, 0.7);

        $this->assertSame('{"ok":true}', $text);
        Http::assertSent(function ($r) {
            return $r['model'] === ['id' => 'pilihan-saya', 'providerID' => 'opencode']
                && str_contains($r['prompt'], 'SISTEM')
                && str_contains($r['prompt'], 'PENGGUNA');
        });
    }

    public function test_401_menyebut_kredensial_ditolak(): void
    {
        Http::fake(['*' => Http::response(['_tag' => 'UnauthorizedError', 'message' => 'Authentication required'], 401)]);

        try {
            $this->client()->models();
            $this->fail('Harus melempar AiRequestException');
        } catch (AiRequestException $e) {
            $this->assertSame(401, $e->status);
            $this->assertStringContainsString('ditolak', $e->getMessage());
        }
    }

    public function test_429_memakai_retry_after_ms_sebagai_detik(): void
    {
        Http::fake(['*' => Http::response([
            '_tag' => 'ProviderRequestError', 'reason' => 'rate_limit',
            'message' => 'Free usage limit reached', 'status' => 429, 'retryAfterMs' => 61028000,
        ], 429)]);

        try {
            $this->client()->chat([['role' => 'user', 'content' => 'x']], null, 0.2);
            $this->fail('Harus melempar AiRequestException');
        } catch (AiRequestException $e) {
            $this->assertSame(429, $e->status);
            $this->assertSame(61028, $e->retryAfter);
            $this->assertStringContainsString('Free usage limit reached', $e->getMessage());
        }
    }

    public function test_model_not_found_menyebut_nama_model(): void
    {
        Http::fake(['*' => Http::response([
            '_tag' => 'ModelNotFoundError', 'providerID' => 'opencode', 'modelID' => 'tidak-ada',
            'message' => 'Model not found: opencode/tidak-ada',
        ], 404)]);

        try {
            $this->client()->chat([['role' => 'user', 'content' => 'x']], 'tidak-ada', 0.2);
            $this->fail('Harus melempar AiRequestException');
        } catch (AiRequestException $e) {
            $this->assertStringContainsString('tidak-ada', $e->getMessage());
        }
    }

    public function test_konfigurasi_belum_lengkap_ditolak_lebih_awal(): void
    {
        $this->expectException(AiRequestException::class);
        $this->expectExceptionMessage('OpenCode belum dikonfigurasi');
        $this->client(['password' => null])->models();
    }
}
