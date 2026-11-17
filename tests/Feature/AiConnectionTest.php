<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tes_koneksi_memakai_isian_form_dan_menguji_model(): void
    {
        [$admin, $organization] = $this->adminWorkspace();

        Http::fake([
            'https://form.example.test/v1/models' => Http::response([
                'data' => [
                    ['id' => 'openai/gpt-4o', 'owned_by' => 'openai'],
                    ['id' => 'ag/gemini-3.8-flash-medium', 'owned_by' => 'google'],
                ],
            ]),
            'https://form.example.test/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'ok']]],
            ]),
        ]);

        $response = $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->postJson(route('admin.settings.ai.test'), [
                'ai_base_url' => 'https://form.example.test/v1',
                'ai_api_key' => 'form-secret',
                'ai_default_model' => 'openai/gpt-4o',
                'ai_timeout' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('endpoint.ok', true)
            ->assertJsonPath('endpoint.status', 200)
            ->assertJsonPath('probe.ok', true)
            ->assertJsonPath('probe.status', 200)
            ->assertJsonPath('model.OpenAI.0', 'openai/gpt-4o')
            ->assertJsonPath('model.Google.0', 'ag/gemini-3.8-flash-medium');

        $this->assertStringContainsString('openai/gpt-4o', $response->json('probe.pesan'));
        $this->assertStringNotContainsString('form-secret', $response->getContent());

        Http::assertSent(fn ($request) => $request->url() === 'https://form.example.test/v1/models'
            && $request->header('Authorization')[0] === 'Bearer form-secret');

        Http::assertSent(fn ($request) => $request->url() === 'https://form.example.test/v1/chat/completions'
            && $request['model'] === 'openai/gpt-4o'
            && $request->header('Authorization')[0] === 'Bearer form-secret');

        $this->assertDatabaseMissing('organization_settings', ['organization_id' => $organization->id]);
    }

    public function test_isian_kosong_memakai_pengaturan_tersimpan(): void
    {
        [$admin, $organization] = $this->adminWorkspace();

        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'ai_base_url' => 'https://saved.example.test/v1',
            'ai_api_key' => 'saved-secret',
            'ai_default_model' => 'gpt-4o-mini',
            'ai_timeout' => 45,
        ]);

        Http::fake([
            'https://saved.example.test/v1/models' => Http::response([
                'data' => [['id' => 'gpt-4o-mini', 'owned_by' => 'openai']],
            ]),
            'https://saved.example.test/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'ok']]],
            ]),
        ]);

        $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->postJson(route('admin.settings.ai.test'), [
                'ai_base_url' => '',
                'ai_api_key' => '',
                'ai_default_model' => '',
                'ai_timeout' => '',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('endpoint.ok', true)
            ->assertJsonPath('probe.ok', true)
            ->assertJsonPath('model.OpenAI.0', 'gpt-4o-mini');

        Http::assertSent(fn ($request) => $request->url() === 'https://saved.example.test/v1/chat/completions'
            && $request['model'] === 'gpt-4o-mini'
            && $request->header('Authorization')[0] === 'Bearer saved-secret');
    }

    public function test_probe_429_menampilkan_alasan_dan_daftar_model(): void
    {
        [$admin, $organization] = $this->adminWorkspace();

        Http::fake([
            'https://form.example.test/v1/models' => Http::response([
                'data' => [['id' => 'openai/gpt-4o', 'owned_by' => 'openai']],
            ]),
            'https://form.example.test/v1/chat/completions' => Http::response([
                'error' => ['message' => 'rate limit reached for requests'],
            ], 429, ['Retry-After' => '30']),
        ]);

        $response = $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->postJson(route('admin.settings.ai.test'), [
                'ai_base_url' => 'https://form.example.test/v1',
                'ai_api_key' => 'form-secret',
                'ai_default_model' => 'openai/gpt-4o',
                'ai_timeout' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('endpoint.ok', true)
            ->assertJsonPath('probe.ok', false)
            ->assertJsonPath('probe.status', 429)
            ->assertJsonPath('model.OpenAI.0', 'openai/gpt-4o');

        $pesan = $response->json('probe.pesan');
        $this->assertStringContainsString('membatasi permintaan', $pesan);
        $this->assertStringContainsString('rate limit reached for requests', $pesan);
        $this->assertStringContainsString('30 detik', $pesan);
        $this->assertStringNotContainsString('form-secret', $response->getContent());
    }

    public function test_api_key_ditolak_dilaporkan_tanpa_membocorkan_key(): void
    {
        [$admin, $organization] = $this->adminWorkspace();

        Http::fake(['*' => Http::response(['error' => 'unauthorized'], 401)]);

        $response = $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->postJson(route('admin.settings.ai.test'), [
                'ai_base_url' => 'https://form.example.test/v1',
                'ai_api_key' => 'salah-secret',
                'ai_default_model' => 'openai/gpt-4o',
                'ai_timeout' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('endpoint.ok', false)
            ->assertJsonPath('endpoint.status', 401)
            ->assertJsonPath('probe', null)
            ->assertJsonPath('model', []);

        $this->assertStringContainsString('API key ditolak', $response->json('endpoint.pesan'));
        $this->assertStringNotContainsString('salah-secret', $response->getContent());
        Http::assertSentCount(1);
    }

    public function test_kegagalan_koneksi_dilaporkan(): void
    {
        [$admin, $organization] = $this->adminWorkspace();

        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->postJson(route('admin.settings.ai.test'), [
                'ai_base_url' => 'https://form.example.test/v1',
                'ai_timeout' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('endpoint.ok', false)
            ->assertJsonPath('endpoint.status', null)
            ->assertJsonPath('probe', null)
            ->assertJsonPath('model', []);
    }

    public function test_endpoint_tanpa_model_tetap_dianggap_terhubung(): void
    {
        [$admin, $organization] = $this->adminWorkspace();

        Http::fake([
            'https://form.example.test/v1/models' => Http::response(['data' => []]),
            'https://form.example.test/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'ok']]],
            ]),
        ]);

        $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->postJson(route('admin.settings.ai.test'), [
                'ai_base_url' => 'https://form.example.test/v1',
                'ai_default_model' => 'openai/gpt-4o',
                'ai_timeout' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('endpoint.ok', true)
            ->assertJsonPath('probe.ok', true)
            ->assertJsonPath('model', []);
    }

    public function test_tab_ai_menampilkan_tombol_tes_koneksi(): void
    {
        [$admin, $organization] = $this->adminWorkspace();

        $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->get(route('admin.settings.edit', ['tab' => 'ai']))
            ->assertOk()
            ->assertSee('Tes koneksi');
    }

    public function test_peserta_tidak_bisa_tes_koneksi(): void
    {
        [$admin, $organization] = $this->adminWorkspace();
        $peserta = $this->peserta($organization);

        $this->actingAs($peserta)
            ->withSession($this->sesi($peserta, $organization))
            ->postJson(route('admin.settings.ai.test'), [
                'ai_base_url' => 'https://form.example.test/v1',
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function adminWorkspace(): array
    {
        $slug = 'ai-'.Str::lower(Str::random(6));

        $organization = Organization::create([
            'name' => 'Kampus AI',
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Admin AI',
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

    private function peserta(Organization $organization): User
    {
        $peserta = User::create([
            'nama' => 'Peserta AI',
            'username' => 'peserta_'.Str::lower(Str::random(6)),
            'password' => Hash::make('password'),
            'role' => 'peserta',
            'current_session_token' => 'peserta-'.Str::lower(Str::random(8)),
        ]);

        $peserta->organizations()->syncWithoutDetaching([
            $organization->id => ['role' => 'participant', 'status' => 'active'],
        ]);

        return $peserta;
    }

    private function sesi(User $user, Organization $organization): array
    {
        return [
            'auth_token' => $user->current_session_token,
            'organization_id' => $organization->id,
        ];
    }
}
