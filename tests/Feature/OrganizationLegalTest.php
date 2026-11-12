<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationLegalTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dapat_menyimpan_teks_legal_workspace(): void
    {
        [$owner, $organization] = $this->owner();

        $terms = "## Ketentuan Kustom\n\nParagraf pertama syarat.\n\n## Kontak\n\nHubungi admin workspace.";
        $privacy = "## Data Kustom\n\nKami hanya memakai data untuk kelas.";

        $this->actingAs($owner)
            ->withSession(['auth_token' => 'owner_legal-token', 'organization_id' => $organization->id])
            ->put(route('admin.settings.update'), $this->payload($organization, [
                'terms_content' => $terms,
                'privacy_content' => $privacy,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('organization_settings', [
            'organization_id' => $organization->id,
            'terms_content' => $terms,
            'privacy_content' => $privacy,
        ]);

        $this->get(route('organizations.terms', $organization))
            ->assertOk()
            ->assertSee('Ketentuan Kustom')
            ->assertSee('Paragraf pertama syarat.')
            ->assertDontSee('Draf, belum ditinjau secara hukum');

        $this->get(route('organizations.privacy', $organization))
            ->assertOk()
            ->assertSee('Data Kustom')
            ->assertSee('Kami hanya memakai data untuk kelas.');

        $this->actingAs($owner)
            ->withSession(['auth_token' => 'owner_legal-token', 'organization_id' => $organization->id])
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('name="terms_content"', false)
            ->assertSee('Ketentuan Kustom');
    }

    public function test_teks_legal_kosong_memakai_teks_platform(): void
    {
        [, $organization] = $this->owner();

        $this->get(route('organizations.terms', $organization))
            ->assertOk()
            ->assertSee('Draf, belum ditinjau secara hukum')
            ->assertSee('1. Ruang lingkup layanan')
            ->assertSee(route('organizations.privacy', $organization));

        $this->get(route('organizations.privacy', $organization))
            ->assertOk()
            ->assertSee('Draf, belum ditinjau secara hukum')
            ->assertSee('1. Data yang dikumpulkan')
            ->assertSee(route('organizations.terms', $organization));
    }

    public function test_admin_biasa_tidak_dapat_mengubah_teks_legal(): void
    {
        $admin = $this->user('admin_legal', 'admin');
        $organization = $admin->organizations()->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin_legal-token', 'organization_id' => $organization->id])
            ->put(route('admin.settings.update'), $this->payload($organization, [
                'terms_content' => 'Tidak boleh disimpan.',
                'privacy_content' => 'Tidak boleh disimpan.',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(OrganizationSetting::query()
            ->where('organization_id', $organization->id)
            ->value('terms_content'));

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin_legal-token', 'organization_id' => $organization->id])
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertDontSee('name="terms_content"', false);
    }

    public function test_konten_legal_di_escape(): void
    {
        [$owner, $organization] = $this->owner();

        $content = "## <script>alert('judul')</script>\nTeks <img src=x onerror=alert(1)>";

        $this->actingAs($owner)
            ->withSession(['auth_token' => 'owner_legal-token', 'organization_id' => $organization->id])
            ->put(route('admin.settings.update'), $this->payload($organization, [
                'terms_content' => $content,
            ]))
            ->assertSessionHasNoErrors();

        $this->get(route('organizations.terms', $organization))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&#039;judul&#039;)&lt;/script&gt;', false)
            ->assertDontSee("<script>alert('judul')</script>", false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_login_organisasi_menautkan_legal_workspace_tanpa_hint_master(): void
    {
        [, $organization] = $this->owner();

        $this->get(route('organizations.login', $organization))
            ->assertOk()
            ->assertSee(route('organizations.terms', $organization))
            ->assertSee(route('organizations.privacy', $organization))
            ->assertDontSee('Master platform masuk di sini');
    }

    private function owner(): array
    {
        $owner = $this->user('owner_legal', 'admin');
        $organization = $owner->organizations()->firstOrFail();
        $owner->organizations()->updateExistingPivot($organization->id, ['role' => 'owner']);

        return [$owner, $organization];
    }

    private function user(string $username, string $role): User
    {
        return User::create([
            'nama' => ucfirst($username),
            'username' => $username,
            'password' => Hash::make('password123'),
            'role' => $role,
            'current_session_token' => $username.'-token',
        ]);
    }

    private function payload(Organization $organization, array $overrides = []): array
    {
        return array_merge([
            'organization_slug' => $organization->slug,
            'organization_timezone' => $organization->timezone,
            'organization_name' => $organization->name,
            'site_name' => 'Kuis Publik',
            'site_tagline' => 'Belajar dan evaluasi bersama',
            'primary_color' => '#166534',
            'login_title' => 'Masuk ke kelas',
            'login_description' => 'Gunakan akun yang telah diberikan.',
            'login_layout' => 'centered',
            'locale' => 'id',
            'allow_pin_enrollment' => 1,
            'ai_timeout' => 90,
            'class_cover_mode' => 'local',
        ], $overrides);
    }
}
