<?php

namespace Tests\Feature;

use App\Services\OpenCode\OpenCodeClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenCodeClientTest extends TestCase
{
    private const VALID_JSON = '{"classification":{"category":"keluhan","confidence":0.9,"tags":[]},"extraction":{"fields":{},"missing":[]},"validation":{"valid":true,"issues":[]},"summary":"ok"}';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_processes_a_session_and_returns_json(): void
    {
        $this->configureOpenCode();
        $this->fakeSuccessfulSession('ses_test', [], self::VALID_JSON);

        $result = app(OpenCodeClient::class)->process("Data: test\n");

        $this->assertSame('ses_test', $result['session_id']);
        $this->assertNull($result['variant']);
        $this->assertTrue($result['json_valid']);
        $this->assertSame('keluhan', $result['data']['classification']['category']);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/model'));
    }

    public function test_it_sends_a_supported_model_variant(): void
    {
        $this->configureOpenCode(['variant' => 'high']);
        $this->fakeSuccessfulSession('ses_variant', [
            [
                'id' => 'deepseek-v4.1-flash-free',
                'providerID' => 'opencode',
                'variants' => [
                    ['id' => 'low', 'settings' => ['reasoningEffort' => 'low']],
                    ['id' => 'high', 'settings' => ['reasoningEffort' => 'high']],
                    ['id' => 'max', 'settings' => ['reasoningEffort' => 'max']],
                ],
            ],
        ], self::VALID_JSON);

        $result = app(OpenCodeClient::class)->process("Data: test\n");

        $this->assertSame('high', $result['variant']);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/api/session')) {
                return false;
            }

            $model = $request->data()['model'] ?? [];

            return ($model['id'] ?? null) === 'deepseek-v4.1-flash-free'
                && ($model['variant'] ?? null) === 'high';
        });
    }

    public function test_it_uses_a_request_variant_over_the_configured_variant(): void
    {
        $this->configureOpenCode(['variant' => 'low']);
        $this->fakeSuccessfulSession('ses_override', [
            [
                'id' => 'deepseek-v4.1-flash-free',
                'providerID' => 'opencode',
                'variants' => [
                    ['id' => 'low', 'settings' => ['reasoningEffort' => 'low']],
                    ['id' => 'high', 'settings' => ['reasoningEffort' => 'high']],
                ],
            ],
        ], self::VALID_JSON);

        $result = app(OpenCodeClient::class)->process("Data: test\n", variant: 'high');

        $this->assertSame('high', $result['variant']);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/api/session')) {
                return false;
            }

            return ($request->data()['model']['variant'] ?? null) === 'high';
        });
    }

    public function test_it_omits_an_unsupported_request_variant(): void
    {
        $this->configureOpenCode(['variant' => '']);
        $this->fakeSuccessfulSession('ses_unsupported', [
            [
                'id' => 'deepseek-v4.1-flash-free',
                'providerID' => 'opencode',
                'variants' => [
                    ['id' => 'low', 'settings' => ['reasoningEffort' => 'low']],
                    ['id' => 'high', 'settings' => ['reasoningEffort' => 'high']],
                ],
            ],
        ], self::VALID_JSON);

        $result = app(OpenCodeClient::class)->process("Data: test\n", variant: 'max');

        $this->assertNull($result['variant']);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/api/session')) {
                return false;
            }

            return ! array_key_exists('variant', $request->data()['model']);
        });
    }

    public function test_it_omits_a_model_variant_that_the_model_does_not_support(): void
    {
        $this->configureOpenCode(['variant' => 'max']);
        $this->fakeSuccessfulSession('ses_no_variant', [
            [
                'id' => 'deepseek-v4.1-flash-free',
                'providerID' => 'opencode',
                'variants' => [
                    ['id' => 'low', 'settings' => ['reasoningEffort' => 'low']],
                    ['id' => 'high', 'settings' => ['reasoningEffort' => 'high']],
                ],
            ],
        ], self::VALID_JSON);

        $result = app(OpenCodeClient::class)->process("Data: test\n");

        $this->assertNull($result['variant']);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/api/session')) {
                return false;
            }

            $model = $request->data()['model'] ?? [];

            return ($model['id'] ?? null) === 'deepseek-v4.1-flash-free'
                && ! array_key_exists('variant', $model);
        });
    }

    public function test_health_lists_free_models_and_their_variants(): void
    {
        $this->configureOpenCode(['variant' => 'high']);

        Http::fake(function (Request $request) {
            $url = $request->url();

            if ($request->method() === 'GET' && str_ends_with($url, '/api/info')) {
                return Http::response(['version' => '2.3.2'], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($url, '/api/model')) {
                return Http::response(['data' => [
                    [
                        'id' => 'deepseek-v4.1-flash-free',
                        'providerID' => 'opencode',
                        'cost' => [['input' => 0, 'output' => 0]],
                        'variants' => [['id' => 'low'], ['id' => 'high']],
                    ],
                    [
                        'id' => 'big-pickle',
                        'providerID' => 'opencode',
                        'cost' => [['input' => 0, 'output' => 0]],
                        'variants' => [],
                    ],
                ]], 200);
            }

            return Http::response(['message' => 'unexpected '.$request->method().' '.$url], 500);
        });

        $this->artisan('opencode:health')
            ->expectsOutputToContain('OpenCode API is reachable.')
            ->expectsOutputToContain('Free models: deepseek-v4.1-flash-free, big-pickle')
            ->expectsOutputToContain('Configured variant: high')
            ->assertSuccessful();
    }

    public function test_it_ignores_malformed_model_variants(): void
    {
        $this->configureOpenCode(['variant' => 'high']);
        $this->fakeSuccessfulSession('ses_malformed', [
            [
                'id' => 'deepseek-v4.1-flash-free',
                'providerID' => 'opencode',
                'variants' => ['high'],
            ],
        ], self::VALID_JSON);

        $result = app(OpenCodeClient::class)->process("Data: test\n");

        $this->assertNull($result['variant']);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/api/session')) {
                return false;
            }

            $model = $request->data()['model'] ?? [];

            return ! array_key_exists('variant', $model);
        });
    }

    public function test_it_omits_variant_for_a_fallback_model_without_that_variant(): void
    {
        $this->configureOpenCode([
            'model_fallbacks' => ['big-pickle'],
            'variant' => '',
            'max_attempts' => 2,
        ]);

        $sessionRequests = [];
        $sessionCount = 0;
        $models = [
            [
                'id' => 'deepseek-v4.1-flash-free',
                'providerID' => 'opencode',
                'variants' => [['id' => 'high', 'settings' => ['reasoningEffort' => 'high']]],
            ],
            [
                'id' => 'big-pickle',
                'providerID' => 'opencode',
                'variants' => [],
            ],
        ];

        Http::fake(function (Request $request) use (&$sessionRequests, &$sessionCount, $models) {
            $url = $request->url();

            if ($request->method() === 'GET' && str_ends_with($url, '/api/model')) {
                return Http::response(['data' => $models], 200);
            }

            if ($request->method() === 'POST' && str_ends_with($url, '/api/session')) {
                $sessionCount++;
                $sessionRequests[] = $request->data();
                $id = $sessionCount === 1 ? 'ses_primary' : 'ses_fallback';

                return Http::response(['data' => ['id' => $id]], 200);
            }

            if ($request->method() === 'POST' && str_ends_with($url, '/api/session/ses_primary/prompt')) {
                return Http::response(['data' => ['id' => 'msg_primary']], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($url, '/api/session/ses_primary/message')) {
                return Http::response([
                    'data' => [[
                        'type' => 'assistant',
                        'time' => ['created' => 1],
                        'error' => ['type' => 'provider.free-limit', 'message' => 'quota'],
                        'content' => [],
                    ]],
                ], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($url, '/api/session/ses_primary')) {
                return Http::response([
                    'data' => ['id' => 'ses_primary', 'outcome' => 'failed', 'time' => ['idle' => 1]],
                ], 200);
            }

            if ($request->method() === 'DELETE' && str_ends_with($url, '/api/session/ses_primary')) {
                return Http::response(null, 204);
            }

            if ($request->method() === 'POST' && str_ends_with($url, '/api/session/ses_fallback/prompt')) {
                return Http::response(['data' => ['id' => 'msg_fallback']], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($url, '/api/session/ses_fallback/message')) {
                return Http::response([
                    'data' => [[
                        'type' => 'assistant',
                        'time' => ['created' => 1],
                        'content' => [['type' => 'text', 'text' => self::VALID_JSON]],
                    ]],
                ], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($url, '/api/session/ses_fallback')) {
                return Http::response([
                    'data' => ['id' => 'ses_fallback', 'outcome' => 'succeeded', 'time' => ['idle' => 1]],
                ], 200);
            }

            if ($request->method() === 'DELETE' && str_ends_with($url, '/api/session/ses_fallback')) {
                return Http::response(null, 204);
            }

            return Http::response(['message' => 'unexpected '.$request->method().' '.$url], 500);
        });

        $result = app(OpenCodeClient::class)->process("Data: test\n", variant: 'high');

        $this->assertSame('big-pickle', $result['model']);
        $this->assertNull($result['variant']);
        $this->assertCount(2, $sessionRequests);
        $this->assertSame('deepseek-v4.1-flash-free', $sessionRequests[0]['model']['id']);
        $this->assertSame('high', $sessionRequests[0]['model']['variant']);
        $this->assertSame('big-pickle', $sessionRequests[1]['model']['id']);
        $this->assertArrayNotHasKey('variant', $sessionRequests[1]['model']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureOpenCode(array $overrides = []): void
    {
        config()->set('services.opencode', array_merge([
            'url' => 'http://opencode.test',
            'username' => 'opencode',
            'password' => 'secret',
            'agent' => 'data-processor',
            'model' => 'deepseek-v4.1-flash-free',
            'model_fallbacks' => [],
            'directory' => '/srv/opencode-web',
            'timeout' => 5,
            'poll_interval' => 1,
            'session_timeout' => 5,
            'max_attempts' => 1,
            'variant' => '',
        ], $overrides));
    }

    /**
     * @param  array<int, array<string, mixed>>  $models
     */
    private function fakeSuccessfulSession(string $sessionId, array $models, string $text): void
    {
        Http::fake(function (Request $request) use ($sessionId, $models, $text) {
            $url = $request->url();

            if ($request->method() === 'GET' && str_ends_with($url, '/api/model')) {
                return Http::response(['data' => $models], 200);
            }

            if ($request->method() === 'POST' && str_ends_with($url, '/api/session')) {
                return Http::response(['data' => ['id' => $sessionId]], 200);
            }

            if ($request->method() === 'POST' && str_ends_with($url, "/api/session/{$sessionId}/prompt")) {
                return Http::response(['data' => ['id' => 'msg_1']], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($url, "/api/session/{$sessionId}/message")) {
                return Http::response([
                    'data' => [[
                        'type' => 'assistant',
                        'time' => ['created' => 1],
                        'content' => [['type' => 'text', 'text' => $text]],
                    ]],
                ], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($url, "/api/session/{$sessionId}")) {
                return Http::response([
                    'data' => [
                        'id' => $sessionId,
                        'outcome' => 'succeeded',
                        'time' => ['idle' => 1],
                    ],
                ], 200);
            }

            if ($request->method() === 'DELETE' && str_ends_with($url, "/api/session/{$sessionId}")) {
                return Http::response(null, 204);
            }

            return Http::response(['message' => 'unexpected '.$request->method().' '.$url], 500);
        });
    }
}
