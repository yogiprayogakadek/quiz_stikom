<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_field_is_masked_and_toggle_has_a_static_label(): void
    {
        $this->get(route('master.login'))
            ->assertOk()
            ->assertSee('type="password"', false)
            ->assertSee('aria-label="Lihat password"', false)
            ->assertSee('aria-controls="password"', false)
            ->assertSee('aria-pressed="false"', false);
    }

    public function test_master_login_uses_its_own_context_without_the_participant_panel(): void
    {
        $this->get(route('master.login'))
            ->assertOk()
            ->assertSee('Masuk master')
            ->assertSee('Akses master platform')
            ->assertDontSee('Mode Layar Penuh')
            ->assertDontSee('Ilustrasi peserta mengerjakan kuis');
    }

    public function test_organization_login_keeps_the_split_illustration_and_workspace_help_copy(): void
    {
        $organization = $this->organization();

        $this->get(route('organizations.login', $organization))
            ->assertOk()
            ->assertSee('Ilustrasi peserta mengerjakan kuis')
            ->assertSee('role="img"', false)
            ->assertSee('Kendala akun? Hubungi pengelola workspace.');
    }

    public function test_invalid_login_links_errors_to_their_fields(): void
    {
        $this->from(route('master.login'))
            ->post(route('master.login.store'), [
                'username' => 'tidak_ada',
                'password' => 'salah',
            ])
            ->assertRedirect(route('master.login'));

        $this->get(route('master.login'))
            ->assertOk()
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="username-error"', false)
            ->assertSee('id="username-error"', false);
    }

    public function test_remember_choice_survives_a_failed_login(): void
    {
        $this->from(route('master.login'))
            ->post(route('master.login.store'), [
                'username' => 'tidak_ada',
                'password' => 'salah',
                'remember' => '1',
            ])
            ->assertRedirect(route('master.login'));

        $this->get(route('master.login'))
            ->assertOk()
            ->assertSee('checked', false);
    }

    public function test_login_status_copy_does_not_claim_more_than_the_maintenance_flag(): void
    {
        $organization = $this->organization();

        $this->get(route('organizations.login', $organization))
            ->assertOk()
            ->assertSee('Tidak dalam maintenance')
            ->assertDontSee('Layanan siap');
    }

    private function organization(): Organization
    {
        $organization = Organization::create([
            'name' => 'Alpha Login',
            'slug' => 'alpha-login-page',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'site_name' => 'Kuis Alpha',
            'site_tagline' => 'Belajar bersama Alpha',
            'primary_color' => '#166534',
            'login_title' => 'Masuk Alpha',
            'login_description' => 'Gunakan akun Alpha.',
            'login_layout' => 'split',
            'ai_timeout' => 60,
            'class_cover_mode' => 'local',
        ]);

        return $organization;
    }
}
