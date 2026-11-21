<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpenCodeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_platform_memakai_config_env(): void
    {
        config(['services.opencode.username' => 'opencode', 'services.opencode.timeout' => 120]);

        $settings = AppSetting::current();

        $this->assertSame('openai', $settings->ai_provider);
        $this->assertSame('opencode', $settings->opencode_username);
        $this->assertSame(120, $settings->opencode_timeout);
        $this->assertContains('opencode_password', AppSetting::fields());
        $this->assertContains('opencode_variant', AppSetting::fields());
    }

    public function test_workspace_mewarisi_provider_dan_field_opencode_dari_platform(): void
    {
        AppSetting::query()->create([
            'id' => 1,
            'ai_provider' => 'opencode',
            'opencode_base_url' => 'https://oc.example.test',
            'opencode_model' => 'model-dari-platform',
        ]);
        $organization = Organization::create([
            'name' => 'Kampus Warisan', 'slug' => 'kampus-warisan', 'type' => 'education',
            'timezone' => 'Asia/Jakarta', 'is_active' => true,
        ]);

        $effective = OrganizationSetting::effective($organization->id);

        $this->assertSame('opencode', $effective->ai_provider);
        $this->assertSame('https://oc.example.test', $effective->opencode_base_url);
        $this->assertSame('model-dari-platform', $effective->opencode_model);
    }

    public function test_simpan_provider_opencode_dan_password_kosong_mempertahankan_yang_lama(): void
    {
        [$admin, $organization] = $this->adminWorkspace();
        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'ai_timeout' => 30,
            'opencode_password' => 'password-lama',
        ]);

        $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), [
                '_section' => 'ai',
                'ai_provider' => 'opencode',
                'ai_base_url' => 'https://ai.example.test/v1',
                'ai_default_model' => 'model-lama',
                'ai_timeout' => 45,
                'opencode_base_url' => 'https://oc.example.test',
                'opencode_username' => 'opencode',
                'opencode_password' => '',
                'opencode_model' => 'dipilih-dari-daftar',
                'opencode_variant' => 'high',
                'opencode_timeout' => 150,
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('settings_tab', 'ai');

        $settings = OrganizationSetting::where('organization_id', $organization->id)->firstOrFail();

        $this->assertSame('opencode', $settings->ai_provider);
        $this->assertSame('https://oc.example.test', $settings->opencode_base_url);
        $this->assertSame('dipilih-dari-daftar', $settings->opencode_model);
        $this->assertSame('high', $settings->opencode_variant);
        $this->assertSame(150, $settings->opencode_timeout);
        $this->assertSame('password-lama', $settings->opencode_password);
    }

    public function test_provider_tidak_dikenal_ditolak(): void
    {
        [$admin, $organization] = $this->adminWorkspace();

        $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), [
                '_section' => 'ai',
                'ai_provider' => 'lain',
                'ai_timeout' => 45,
                'opencode_timeout' => 120,
            ])
            ->assertSessionHasErrors('ai_provider');
    }

    public function test_tes_koneksi_opencode_mengembalikan_daftar_model_tanpa_password(): void
    {
        [$admin, $organization] = $this->adminWorkspace();

        Http::fake(array_merge([
            'https://oc.example.test/api/model*' => Http::response(['data' => [
                [
                    'id' => 'nemotron-3.5-super-vl-free',
                    'providerID' => 'opencode',
                    'type' => 'chat',
                    'variants' => [
                        ['id' => 'low'],
                        ['id' => 'high'],
                    ],
                ],
            ]]),
        ], $this->fakeSessionResponses('https://oc.example.test')));

        $response = $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->postJson(route('admin.settings.opencode.test'), [
                'opencode_base_url' => 'https://oc.example.test',
                'opencode_username' => 'opencode',
                'opencode_password' => 'rahasia',
                'opencode_model' => 'nemotron-3.5-super-vl-free',
                'opencode_variant' => 'high',
                'opencode_timeout' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('endpoint.ok', true)
            ->assertJsonPath('probe.ok', true)
            ->assertJsonPath('model.OpenCode.0', 'nemotron-3.5-super-vl-free');

        $this->assertSame(['low', 'high'], $response->json('variants')['nemotron-3.5-super-vl-free'] ?? []);
        $this->assertStringNotContainsString('rahasia', $response->getContent());
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/model')
            && $request->header('Authorization')[0] === 'Basic '.base64_encode('opencode:rahasia'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/session')
            && $request->method() === 'POST'
            && ($request['model']['variant'] ?? null) === 'high');
    }

    public function test_tes_koneksi_opencode_memakai_pengaturan_tersimpan_saat_isian_kosong(): void
    {
        [$admin, $organization] = $this->adminWorkspace();
        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'ai_provider' => 'opencode',
            'opencode_base_url' => 'https://saved.example.test',
            'opencode_password' => 'saved-password',
            'opencode_model' => 'model-tersimpan',
            'opencode_timeout' => 30,
        ]);

        Http::fake(array_merge([
            'https://saved.example.test/api/model*' => Http::response(['data' => [
                ['id' => 'model-tersimpan', 'providerID' => 'opencode', 'type' => 'chat'],
            ]]),
        ], $this->fakeSessionResponses('https://saved.example.test')));

        $response = $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->postJson(route('admin.settings.opencode.test'), [
                'opencode_base_url' => '',
                'opencode_password' => '',
                'opencode_model' => '',
            ]);

        $response->assertOk()
            ->assertJsonPath('endpoint.ok', true)
            ->assertJsonPath('probe.ok', true)
            ->assertJsonPath('probe.pesan', 'Model default model-tersimpan merespons.');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'https://saved.example.test/api/model'));
    }

    public function test_daftar_model_gambar_hanya_menerima_image(): void
    {
        [$admin, $organization] = $this->adminWorkspace();
        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'ai_provider' => 'opencode',
            'opencode_base_url' => 'https://oc.example.test',
            'opencode_password' => 'rahasia',
        ]);

        Http::fake(['https://oc.example.test/api/model*' => Http::response(['data' => [
            ['id' => 'grok-imagine-image-2.0', 'providerID' => 'opencode', 'type' => 'image'],
        ]])]);

        $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->postJson(route('admin.settings.opencode.image-models'))
            ->assertOk()
            ->assertJsonPath('model.0', 'grok-imagine-image-2.0');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/model')
            && ! str_contains($request->url(), '?'));
    }

    public function test_tab_ai_menampilkan_pilihan_provider_dan_isian_opencode(): void
    {
        [$admin, $organization] = $this->adminWorkspace();
        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'opencode_password' => 'rahasia-lama',
        ]);

        $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->get(route('admin.settings.edit', ['tab' => 'ai']))
            ->assertOk()
            ->assertSee('OpenCode server')
            ->assertSee('name="ai_provider"', false)
            ->assertSee('name="opencode_base_url"', false)
            ->assertSee('name="opencode_username"', false)
            ->assertSee('name="opencode_password"', false)
            ->assertSee('name="opencode_model"', false)
            ->assertSee('name="opencode_variant"', false)
            ->assertSee("variantInput: 'opencode_variant'", false)
            ->assertSee('name="opencode_timeout"', false)
            ->assertDontSee('rahasia-lama');
    }

    public function test_tab_cover_menampilkan_tombol_muat_model_gambar_saat_provider_opencode(): void
    {
        [$admin, $organization] = $this->adminWorkspace();
        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'ai_provider' => 'opencode',
        ]);

        $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->get(route('admin.settings.edit', ['tab' => 'cover']))
            ->assertOk()
            ->assertSee('Muat model gambar');
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function adminWorkspace(): array
    {
        $slug = 'oc-'.Str::lower(Str::random(6));

        $organization = Organization::create([
            'name' => 'Kampus OpenCode',
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Admin OpenCode',
            'username' => 'admin_'.$slug,
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-'.$slug,
        ]);

        $admin->organizations()->syncWithoutDetaching([
            $organization->id => ['role' => 'admin', 'status' => 'active'],
        ]);

        return [$admin, $organization];
    }

    private function fakeSessionResponses(string $baseUrl, string $sessionId = 'ses_test', string $text = 'ok'): array
    {
        $baseUrl = rtrim($baseUrl, '/');

        return [
            $baseUrl."/api/session/{$sessionId}/prompt" => Http::response(['data' => ['id' => 'msg_1']]),
            $baseUrl."/api/session/{$sessionId}/message" => Http::response(['data' => [
                [
                    'type' => 'assistant',
                    'time' => ['created' => 100],
                    'content' => [['type' => 'text', 'text' => $text]],
                ],
            ]]),
            $baseUrl."/api/session/{$sessionId}" => function ($request) {
                if ($request->method() === 'DELETE') {
                    return Http::response([], 204);
                }

                return Http::response(['data' => ['outcome' => 'succeeded', 'time' => ['idle' => 1]]]);
            },
            $baseUrl.'/api/session' => Http::response(['data' => ['id' => $sessionId]], 201),
        ];
    }

    private function sesi(User $user, Organization $organization): array
    {
        return [
            'auth_token' => $user->current_session_token,
            'organization_id' => $organization->id,
        ];
    }
}
