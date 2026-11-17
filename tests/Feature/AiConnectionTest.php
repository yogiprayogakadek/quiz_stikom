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

    public function test_tes_koneksi_memakai_isian_form_dan_menampilkan_model(): void
    {
        [$admin, $organization] = $this->adminWorkspace();

        Http::fake([
            'https://form.example.test/v1/models' => Http::response([
                'data' => [
                    ['id' => 'openai/gpt-4o', 'owned_by' => 'openai'],
                    ['id' => 'ag/gemini-3.8-flash-medium', 'owned_by' => 'google'],
                ],
            ]),
        ]);

        $response = $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->postJson(route('admin.settings.ai.test'), [
                'ai_base_url' => 'https://form.example.test/v1',
                'ai_api_key' => 'form-secret',
                'ai_timeout' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('model.OpenAI.0', 'openai/gpt-4o')
            ->assertJsonPath('model.Google.0', 'ag/gemini-3.8-flash-medium');

        $this->assertStringNotContainsString('form-secret', $response->getContent());

        Http::assertSent(fn ($request) => $request->url() === 'https://form.example.test/v1/models'
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
            'ai_timeout' => 45,
        ]);

        Http::fake([
            'https://saved.example.test/v1/models' => Http::response([
                'data' => [['id' => 'gpt-4o-mini', 'owned_by' => 'openai']],
            ]),
        ]);

        $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->postJson(route('admin.settings.ai.test'), [
                'ai_base_url' => '',
                'ai_api_key' => '',
                'ai_timeout' => '',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('model.OpenAI.0', 'gpt-4o-mini');

        Http::assertSent(fn ($request) => $request->url() === 'https://saved.example.test/v1/models'
            && $request->header('Authorization')[0] === 'Bearer saved-secret');
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
                'ai_timeout' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('status', 401)
            ->assertJsonPath('model', []);

        $this->assertStringContainsString('API key ditolak', $response->json('pesan'));
        $this->assertStringNotContainsString('salah-secret', $response->getContent());
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
            ->assertJsonPath('status', null)
            ->assertJsonPath('model', []);
    }

    public function test_endpoint_tanpa_model_tetap_dianggap_terhubung(): void
    {
        [$admin, $organization] = $this->adminWorkspace();

        Http::fake(['*' => Http::response(['data' => []])]);

        $this->actingAs($admin)
            ->withSession($this->sesi($admin, $organization))
            ->postJson(route('admin.settings.ai.test'), [
                'ai_base_url' => 'https://form.example.test/v1',
                'ai_timeout' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
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
