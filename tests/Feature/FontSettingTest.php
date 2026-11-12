<?php

namespace Tests\Feature;

use App\Models\Font;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\User;
use App\Services\FontLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FontSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pustaka_font_menampilkan_font_organisasi_dan_platform(): void
    {
        Storage::fake('public');

        $organisasi = $this->organisasi();

        $milikSendiri = $this->font($organisasi->id, 'Alpha Sans', 'fonts/alpha-sans.woff2');
        $platform = $this->font(null, 'Platform Display', 'fonts/platform-display.woff2');

        $opsi = app(FontLibrary::class)->options($organisasi->id);

        $this->assertTrue($opsi->contains('id', $milikSendiri->id));
        $this->assertTrue($opsi->contains('id', $platform->id));
    }

    public function test_pustaka_font_menolak_font_organisasi_lain(): void
    {
        $organisasi = $this->organisasi();
        $lain = $this->organisasi('Beta', 'beta');

        $fontLain = $this->font($lain->id, 'Beta Sans', 'fonts/beta.woff2');

        $this->assertNull(app(FontLibrary::class)->resolve($fontLain->id, $organisasi->id));
    }

    public function test_nama_family_css_tidak_memakai_input_admin(): void
    {
        $font = new Font(['nama' => "Jahat'; } body { display:none }"]);

        $this->assertSame('font-', $font->cssFamily());
    }

    public function test_admin_mengunggah_font_baru(): void
    {
        Storage::fake('public');

        [$admin, $organisasi] = $this->admin();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-font', 'organization_id' => $organisasi->id])
            ->post(route('admin.settings.fonts.store'), [
                'nama' => 'Alpha Sans',
                'berat' => '400',
                'gaya' => 'normal',
                'berkas' => UploadedFile::fake()->create('alpha.woff2', 200, 'font/woff2'),
            ])
            ->assertRedirect();

        $font = Font::firstOrFail();

        $this->assertSame('Alpha Sans', $font->nama);
        $this->assertSame($organisasi->id, $font->organization_id);
        $this->assertSame('woff2', $font->format);
        Storage::disk('public')->assertExists($font->file_path);
    }

    public function test_unggahan_font_menolak_format_lain(): void
    {
        Storage::fake('public');

        [$admin, $organisasi] = $this->admin();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-font', 'organization_id' => $organisasi->id])
            ->post(route('admin.settings.fonts.store'), [
                'nama' => 'Font Palsu',
                'berat' => '400',
                'gaya' => 'normal',
                'berkas' => UploadedFile::fake()->create('jahat.php', 10, 'application/x-php'),
            ])
            ->assertSessionHasErrors('berkas');

        $this->assertDatabaseCount('fonts', 0);
    }

    public function test_font_yang_masih_dipakai_tidak_bisa_dihapus(): void
    {
        Storage::fake('public');

        [$admin, $organisasi] = $this->admin();

        $font = $this->font($organisasi->id, 'Alpha Sans', 'fonts/alpha.woff2');
        Storage::disk('public')->put($font->file_path, 'isi-font');

        OrganizationSetting::create([
            'organization_id' => $organisasi->id,
            'font_utama_id' => $font->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-font', 'organization_id' => $organisasi->id])
            ->delete(route('admin.settings.fonts.destroy', $font))
            ->assertSessionHasErrors('font');

        $this->assertDatabaseHas('fonts', ['id' => $font->id]);
        Storage::disk('public')->assertExists($font->file_path);
    }

    public function test_font_yang_tidak_dipakai_dihapus_beserta_file(): void
    {
        Storage::fake('public');

        [$admin, $organisasi] = $this->admin();

        $font = $this->font($organisasi->id, 'Alpha Sans', 'fonts/alpha.woff2');
        Storage::disk('public')->put($font->file_path, 'isi-font');

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-font', 'organization_id' => $organisasi->id])
            ->delete(route('admin.settings.fonts.destroy', $font))
            ->assertRedirect();

        $this->assertDatabaseMissing('fonts', ['id' => $font->id]);
        Storage::disk('public')->assertMissing($font->file_path);
    }

    public function test_admin_menyimpan_pilihan_font(): void
    {
        Storage::fake('public');

        [$admin, $organisasi] = $this->admin();

        $fontIsi = $this->font($organisasi->id, 'Alpha Sans', 'fonts/alpha.woff2');
        $fontJudul = $this->font($organisasi->id, 'Alpha Display', 'fonts/alpha-display.woff2', '700');

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-font', 'organization_id' => $organisasi->id])
            ->put(route('admin.settings.update'), $this->payload($organisasi, [
                'font_utama_id' => $fontIsi->id,
                'font_judul_id' => $fontJudul->id,
            ]))
            ->assertRedirect();

        $settings = OrganizationSetting::where('organization_id', $organisasi->id)->firstOrFail();

        $this->assertSame($fontIsi->id, $settings->font_utama_id);
        $this->assertSame($fontJudul->id, $settings->font_judul_id);
    }

    public function test_admin_tidak_bisa_memilih_font_organisasi_lain(): void
    {
        Storage::fake('public');

        [$admin, $organisasi] = $this->admin();

        $lain = $this->organisasi('Beta', 'beta');
        $fontLain = $this->font($lain->id, 'Beta Sans', 'fonts/beta.woff2');

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-font', 'organization_id' => $organisasi->id])
            ->put(route('admin.settings.update'), $this->payload($organisasi, [
                'font_utama_id' => $fontLain->id,
            ]))
            ->assertSessionHasErrors('font_utama_id');
    }

    public function test_brand_style_memancarkan_font_face_untuk_font_terpilih(): void
    {
        Storage::fake('public');

        [$admin, $organisasi] = $this->admin();

        $font = $this->font($organisasi->id, 'Alpha Sans', 'fonts/alpha.woff2');

        OrganizationSetting::create([
            'organization_id' => $organisasi->id,
            'font_utama_id' => $font->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-font', 'organization_id' => $organisasi->id])
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('@font-face', false)
            ->assertSee("font-family: 'font-".$font->id."'", false)
            ->assertSee("--font-sans: 'font-".$font->id."'", false);
    }

    public function test_brand_style_tidak_memuat_semua_font_unggahan(): void
    {
        Storage::fake('public');

        [$admin, $organisasi] = $this->admin();

        $dipakai = $this->font($organisasi->id, 'Alpha Sans', 'fonts/alpha.woff2');
        $tidakDipakai = $this->font($organisasi->id, 'Alpha Lain', 'fonts/alpha-lain.woff2');

        OrganizationSetting::create([
            'organization_id' => $organisasi->id,
            'font_utama_id' => $dipakai->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-font', 'organization_id' => $organisasi->id])
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertDontSee("font-family: 'font-".$tidakDipakai->id."'", false)
            ->assertDontSee("--font-sans: 'font-".$tidakDipakai->id."'", false);
    }

    private function organisasi(string $nama = 'Alpha', string $slug = 'alpha'): Organization
    {
        return Organization::create([
            'name' => $nama,
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Singapore',
            'is_active' => true,
        ]);
    }

    private function admin(): array
    {
        $organisasi = Organization::query()->orderBy('id')->firstOrFail();

        $admin = User::create([
            'nama' => 'Admin Font',
            'username' => 'admin_font',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'token-font',
        ]);

        $admin->organizations()->syncWithoutDetaching([
            $organisasi->id => ['role' => 'admin', 'status' => 'active'],
        ]);

        return [$admin, $organisasi];
    }

    private function font(?int $organizationId, string $nama, string $path, string $berat = '400'): Font
    {
        return Font::create([
            'organization_id' => $organizationId,
            'nama' => $nama,
            'file_path' => $path,
            'berat' => $berat,
            'gaya' => 'normal',
            'format' => 'woff2',
        ]);
    }

    private function payload(Organization $organisasi, array $tambahan = []): array
    {
        return array_merge([
            'organization_slug' => $organisasi->slug,
            'organization_timezone' => $organisasi->timezone,
            'site_name' => 'Quiz Alpha',
            'site_tagline' => 'Kuis kelas',
            'organization_name' => $organisasi->name,
            'primary_color' => '#1d4ed8',
            'login_title' => 'Masuk',
            'login_description' => 'Gunakan akun kelas.',
            'login_layout' => 'split',
            'locale' => 'id',
            'ai_timeout' => 60,
            'class_cover_mode' => 'local',
            'font_utama_id' => null,
            'font_judul_id' => null,
        ], $tambahan);
    }
}
