<?php

namespace Tests\Unit;

use App\Exceptions\AiRequestException;
use App\Services\Ai\AiConfig;
use App\Services\Ai\OpenAiTextClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiTextClientTest extends TestCase
{
    private function client(array $openai = []): OpenAiTextClient
    {
        return new OpenAiTextClient(new AiConfig('openai', array_merge([
            'base_url' => 'https://api.example.test/v1', 'api_key' => 'kunci', 'model' => 'gpt-4o-mini', 'timeout' => 10,
        ], $openai), ['base_url' => '', 'username' => 'opencode', 'password' => null, 'model' => '', 'timeout' => 120],
            ['base_url' => '', 'api_key' => null, 'model' => '', 'timeout' => 150]));
    }

    public function test_models_mengelompokkan_per_provider_dan_memakai_bearer(): void
    {
        Http::fake(['https://api.example.test/v1/models' => Http::response(['data' => [
            ['id' => 'ag/gemini-3.8-flash-medium', 'owned_by' => 'google'],
            ['id' => 'openai/gpt-4o-mini', 'owned_by' => 'openai'],
        ]])]);

        $models = $this->client()->models(10);

        $this->assertSame(['ag/gemini-3.8-flash-medium'], $models['Google']);
        $this->assertSame(['openai/gpt-4o-mini'], $models['OpenAI']);
        Http::assertSent(fn ($r) => $r->header('Authorization')[0] === 'Bearer kunci');
    }

    public function test_chat_mengirim_payload_dan_mengembalikan_teks(): void
    {
        Http::fake(['https://api.example.test/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => '{"ok":true}']]],
        ])]);

        $text = $this->client()->chat([['role' => 'user', 'content' => 'halo']], 'gpt-4o-mini', 0.2);

        $this->assertSame('{"ok":true}', $text);
        Http::assertSent(fn ($r) => $r['model'] === 'gpt-4o-mini' && $r['temperature'] === 0.2 && $r['stream'] === false);
    }

    public function test_429_memakai_retry_after_header(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'rate limit']], 429, ['Retry-After' => '45'])]);

        try {
            $this->client()->chat([['role' => 'user', 'content' => 'x']], 'gpt-4o-mini', 0.2);
            $this->fail('Harus melempar AiRequestException');
        } catch (AiRequestException $e) {
            $this->assertSame(429, $e->status);
            $this->assertSame(45, $e->retryAfter);
            $this->assertStringContainsString('membatasi permintaan', $e->getMessage());
            $this->assertStringContainsString('45 detik', $e->getMessage());
        }
    }
}
