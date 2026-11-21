<?php

namespace Tests\Feature;

use App\Services\OpenCode\OpenCodeClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenCodeClientTest extends TestCase
{
    public function test_it_processes_a_session_and_returns_json(): void
    {
        config()->set('services.opencode', [
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
        ]);

        Http::fake(function (Request $request) {
            $url = $request->url();

            if ($request->method() === 'POST' && str_ends_with($url, '/api/session')) {
                return Http::response(['data' => ['id' => 'ses_test']], 200);
            }

            if ($request->method() === 'POST' && str_ends_with($url, '/api/session/ses_test/prompt')) {
                return Http::response(['data' => ['id' => 'msg_1']], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($url, '/api/session/ses_test')) {
                return Http::response([
                    'data' => [
                        'id' => 'ses_test',
                        'outcome' => 'succeeded',
                        'time' => ['idle' => 1],
                    ],
                ], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($url, '/api/session/ses_test/message')) {
                return Http::response([
                    'data' => [
                        [
                            'type' => 'assistant',
                            'time' => ['created' => 1],
                            'content' => [
                                ['type' => 'reasoning', 'text' => 'thinking'],
                                [
                                    'type' => 'text',
                                    'text' => '{"classification":{"category":"keluhan","confidence":0.9,"tags":[]},"extraction":{"fields":{},"missing":[]},"validation":{"valid":true,"issues":[]},"summary":"ok"}',
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            if ($request->method() === 'DELETE' && str_ends_with($url, '/api/session/ses_test')) {
                return Http::response(null, 204);
            }

            return Http::response(['message' => 'unexpected '.$request->method().' '.$url], 500);
        });

        $result = app(OpenCodeClient::class)->process("Data: test\n");

        $this->assertSame('ses_test', $result['session_id']);
        $this->assertTrue($result['json_valid']);
        $this->assertSame('keluhan', $result['data']['classification']['category']);
    }
}
