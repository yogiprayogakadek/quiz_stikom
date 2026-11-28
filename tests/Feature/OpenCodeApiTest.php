<?php

namespace Tests\Feature;

use App\Jobs\ProcessWithOpenCode;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OpenCodeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_models_endpoint_returns_free_models_with_variants_and_defaults(): void
    {
        $this->configureApi();
        $this->fakeModels();

        $this->getJson('/api/opencode/models')
            ->assertOk()
            ->assertJsonPath('connected', true)
            ->assertJsonPath('defaults.model', 'deepseek-v4.1-flash-free')
            ->assertJsonPath('defaults.variant', 'high')
            ->assertJsonPath('defaults.agent', 'data-processor')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'deepseek-v4.1-flash-free')
            ->assertJsonCount(2, 'data.0.variants')
            ->assertJsonPath('data.0.variants.1.reasoning_effort', 'high');
    }

    public function test_models_endpoint_nulls_an_unsupported_configured_variant(): void
    {
        $this->configureApi(['model' => 'big-pickle', 'variant' => 'high']);
        Http::fake([
            '*/api/model' => Http::response(['data' => [
                [
                    'id' => 'big-pickle',
                    'providerID' => 'opencode',
                    'name' => 'Big Pickle',
                    'status' => 'active',
                    'cost' => [['input' => 0, 'output' => 0]],
                    'variants' => [],
                ],
            ]], 200),
        ]);

        $this->getJson('/api/opencode/models')
            ->assertOk()
            ->assertJsonPath('defaults.model', 'big-pickle')
            ->assertJsonPath('defaults.variant', null)
            ->assertJsonCount(1, 'data');
    }

    public function test_models_endpoint_returns_503_when_opencode_is_unreachable(): void
    {
        $this->configureApi();
        Http::fake(['*/api/model' => Http::response(['message' => 'down'], 500)]);

        $this->getJson('/api/opencode/models')
            ->assertStatus(503)
            ->assertJsonPath('connected', false);
    }

    public function test_process_returns_503_when_variant_validation_cannot_reach_opencode(): void
    {
        Queue::fake();
        $this->configureApi();
        Http::fake(['*/api/model' => Http::response(['message' => 'down'], 500)]);

        $this->postJson('/api/opencode/process', [
            'data' => 'test',
            'model' => 'deepseek-v4.1-flash-free',
            'variant' => 'high',
        ])->assertStatus(503)
            ->assertJsonPath('connected', false);

        Queue::assertNothingPushed();
    }

    public function test_models_endpoint_ignores_non_scalar_model_and_variant_ids(): void
    {
        $this->configureApi();
        Http::fake([
            '*/api/model' => Http::response(['data' => [
                [
                    'id' => ['nested'],
                    'providerID' => 'opencode',
                    'name' => 'Broken Model',
                    'status' => 'active',
                    'cost' => [['input' => 0, 'output' => 0]],
                    'variants' => [],
                ],
                [
                    'id' => 'deepseek-v4.1-flash-free',
                    'providerID' => 'opencode',
                    'name' => 'DeepSeek V4.1 Flash Free',
                    'status' => 'active',
                    'cost' => [['input' => 0, 'output' => 0]],
                    'variants' => [
                        ['id' => ['nested'], 'settings' => ['reasoningEffort' => 'ignored']],
                        ['id' => 'high', 'settings' => ['reasoningEffort' => 'high']],
                    ],
                ],
            ]], 200),
        ]);

        $this->getJson('/api/opencode/models')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'deepseek-v4.1-flash-free')
            ->assertJsonCount(1, 'data.0.variants')
            ->assertJsonPath('data.0.variants.0.id', 'high');
    }

    public function test_process_validation_ignores_non_scalar_variant_ids(): void
    {
        Queue::fake();
        $this->configureApi();
        Http::fake([
            '*/api/model' => Http::response(['data' => [
                [
                    'id' => 'deepseek-v4.1-flash-free',
                    'providerID' => 'opencode',
                    'name' => 'DeepSeek V4.1 Flash Free',
                    'status' => 'active',
                    'cost' => [['input' => 0, 'output' => 0]],
                    'variants' => [
                        ['id' => ['nested']],
                        ['id' => 'high'],
                    ],
                ],
            ]], 200),
        ]);

        $this->postJson('/api/opencode/process', [
            'data' => 'test',
            'model' => 'deepseek-v4.1-flash-free',
            'variant' => 'nested',
        ])->assertStatus(422)
            ->assertJsonPath('available_variants', ['high']);

        Queue::assertNothingPushed();
    }

    public function test_models_endpoint_reports_disconnected_after_cache_warm_when_opencode_goes_down(): void
    {
        $this->configureApi();
        $down = false;

        Http::fake(function (Request $request) use (&$down) {
            if (str_ends_with($request->url(), '/api/model')) {
                return $down
                    ? Http::response(['message' => 'down'], 500)
                    : $this->fakeModelsResponse();
            }

            return Http::response(['message' => 'unexpected'], 500);
        });

        $this->getJson('/api/opencode/models')->assertOk();

        $down = true;

        $this->getJson('/api/opencode/models')
            ->assertStatus(503)
            ->assertJsonPath('connected', false);
    }

    public function test_process_validation_reports_503_after_cache_warm_when_opencode_goes_down(): void
    {
        Queue::fake();
        $this->configureApi();
        $down = false;

        Http::fake(function (Request $request) use (&$down) {
            if (str_ends_with($request->url(), '/api/model')) {
                return $down
                    ? Http::response(['message' => 'down'], 500)
                    : $this->fakeModelsResponse();
            }

            return Http::response(['message' => 'unexpected'], 500);
        });

        $this->getJson('/api/opencode/models')->assertOk();

        $down = true;

        $this->postJson('/api/opencode/process', [
            'data' => 'test',
            'model' => 'deepseek-v4.1-flash-free',
            'variant' => 'high',
        ])->assertStatus(503)
            ->assertJsonPath('connected', false);

        Queue::assertNothingPushed();
    }

    public function test_models_endpoint_falls_back_to_the_first_free_model_when_the_default_is_unavailable(): void
    {
        $this->configureApi(['model' => 'missing-paid-model', 'variant' => 'high']);
        $this->fakeModels();

        $this->getJson('/api/opencode/models')
            ->assertOk()
            ->assertJsonPath('defaults.model', 'deepseek-v4.1-flash-free')
            ->assertJsonPath('defaults.variant', 'high');
    }

    public function test_models_endpoint_returns_503_when_the_password_is_missing(): void
    {
        $this->configureApi(['password' => '']);

        $this->getJson('/api/opencode/models')
            ->assertStatus(503)
            ->assertJsonPath('connected', false);
    }

    public function test_process_rejects_an_unsupported_variant(): void
    {
        Queue::fake();
        $this->configureApi();
        $this->fakeModels();

        $this->postJson('/api/opencode/process', [
            'data' => 'test',
            'model' => 'deepseek-v4.1-flash-free',
            'variant' => 'max',
        ])->assertStatus(422)
            ->assertJsonPath('available_variants', ['low', 'high']);

        Queue::assertNothingPushed();
    }

    public function test_process_rejects_a_variant_for_an_unknown_model(): void
    {
        Queue::fake();
        $this->configureApi();
        $this->fakeModels();

        $this->postJson('/api/opencode/process', [
            'data' => 'test',
            'model' => 'missing-model',
            'variant' => 'high',
        ])->assertStatus(422)
            ->assertJsonPath('available_variants', []);

        Queue::assertNothingPushed();
    }

    public function test_process_validates_a_variant_against_the_configured_default_model(): void
    {
        Queue::fake();
        $this->configureApi();
        $this->fakeModels();

        $this->postJson('/api/opencode/process', [
            'data' => 'test',
            'variant' => 'high',
        ])->assertAccepted();

        Queue::assertPushed(ProcessWithOpenCode::class, function (ProcessWithOpenCode $job): bool {
            return $job->model === null && $job->variant === 'high';
        });
    }

    public function test_models_endpoint_returns_null_default_variant_when_none_is_configured(): void
    {
        $this->configureApi(['variant' => '']);
        $this->fakeModels();

        $this->getJson('/api/opencode/models')
            ->assertOk()
            ->assertJsonPath('defaults.variant', null);
    }

    public function test_process_dispatches_the_selected_variant(): void
    {
        Queue::fake();
        $this->configureApi();
        $this->fakeModels();

        $this->postJson('/api/opencode/process', [
            'data' => 'test',
            'model' => 'deepseek-v4.1-flash-free',
            'variant' => 'high',
        ])->assertAccepted();

        Queue::assertPushed(ProcessWithOpenCode::class, function (ProcessWithOpenCode $job): bool {
            return $job->model === 'deepseek-v4.1-flash-free'
                && $job->variant === 'high';
        });
    }

    public function test_process_without_variant_does_not_fetch_models(): void
    {
        Queue::fake();
        $this->configureApi(['variant' => '']);

        $this->postJson('/api/opencode/process', ['data' => 'test'])->assertAccepted();

        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/model'));
        Queue::assertPushed(ProcessWithOpenCode::class, fn (ProcessWithOpenCode $job): bool => $job->variant === null);
    }

    public function test_opencode_endpoints_reject_unauthenticated_requests(): void
    {
        $this->configureApi(['without_auth_header' => true]);

        $this->getJson('/api/opencode/models')->assertUnauthorized();
        $this->postJson('/api/opencode/process', ['data' => 'test'])->assertUnauthorized();
        $this->getJson('/api/opencode/result/sample-key')->assertUnauthorized();
    }

    public function test_opencode_endpoints_reject_non_admin_user_without_bearer_token(): void
    {
        $this->configureApi(['without_auth_header' => true]);

        $peserta = User::create([
            'nama' => 'Peserta API',
            'username' => 'peserta-opencode',
            'password' => Hash::make('password123'),
            'role' => 'peserta',
        ]);

        $this->actingAs($peserta)
            ->getJson('/api/opencode/models')
            ->assertUnauthorized();
    }

    public function test_opencode_endpoints_allow_authenticated_admin_without_bearer_token(): void
    {
        $this->configureApi(['without_auth_header' => true]);
        $this->fakeModels();

        $admin = User::create([
            'nama' => 'Admin API',
            'username' => 'admin-opencode',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/opencode/models')
            ->assertOk()
            ->assertJsonPath('connected', true);
    }

    public function test_process_returns_429_when_workspace_ai_quota_is_exhausted(): void
    {
        Queue::fake();
        config(['platform.ai_monthly_requests' => 0]);
        $this->configureApi(['variant' => '', 'without_auth_header' => true]);

        $organization = Organization::create([
            'name' => 'Kampus Kuota',
            'slug' => 'kampus-kuota',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Admin Kuota',
            'username' => 'admin-kuota-opencode',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
        $organization->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'admin', 'status' => 'active'],
        ]);

        $this->actingAs($admin)
            ->postJson('/api/opencode/process', ['data' => 'test'])
            ->assertStatus(429);

        Queue::assertNothingPushed();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureApi(array $overrides = []): void
    {
        $withoutHeader = (bool) ($overrides['without_auth_header'] ?? false);
        unset($overrides['without_auth_header']);

        config()->set('services.opencode', array_merge([
            'url' => 'http://opencode.test',
            'username' => 'opencode',
            'password' => 'secret',
            'api_token' => 'secret-api-token',
            'agent' => 'data-processor',
            'model' => 'deepseek-v4.1-flash-free',
            'model_fallbacks' => [],
            'directory' => '/srv/opencode-web',
            'timeout' => 5,
            'poll_interval' => 1,
            'session_timeout' => 5,
            'max_attempts' => 1,
            'variant' => 'high',
        ], $overrides));

        if (! $withoutHeader) {
            $this->withToken((string) config('services.opencode.api_token'));
        }
    }

    private function fakeModels(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/api/model')) {
                return $this->fakeModelsResponse();
            }

            return Http::response(['message' => 'unexpected '.$request->method().' '.$request->url()], 500);
        });
    }

    private function fakeModelsResponse()
    {
        return Http::response(['data' => [
            [
                'id' => 'deepseek-v4.1-flash-free',
                'providerID' => 'opencode',
                'name' => 'DeepSeek V4.1 Flash Free',
                'status' => 'active',
                'cost' => [['input' => 0, 'output' => 0]],
                'variants' => [
                    ['id' => 'low', 'settings' => ['reasoningEffort' => 'low']],
                    'malformed',
                    ['settings' => ['reasoningEffort' => 'ignored']],
                    ['id' => 'high', 'settings' => ['reasoningEffort' => 'high']],
                ],
            ],
            [
                'id' => 'paid-model',
                'providerID' => 'opencode',
                'name' => 'Paid Model',
                'status' => 'active',
                'cost' => [['input' => 1, 'output' => 2]],
                'variants' => [],
            ],
        ]], 200);
    }
}
