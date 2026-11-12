<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pencarian_hanya_menampilkan_organisasi_aktif_yang_cocok(): void
    {
        $alpha = Organization::create([
            'name' => 'Kampus Alpha',
            'slug' => 'kampus-alpha',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        Organization::create([
            'name' => 'Kampus Beta',
            'slug' => 'kampus-beta',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => false,
        ]);

        $this->get(route('home', ['q' => 'kampus']))
            ->assertOk()
            ->assertSee('Kampus Alpha')
            ->assertSee(route('organizations.login', $alpha))
            ->assertSee('Dibuat oleh Yogi Prayoga')
            ->assertDontSee('Kampus Beta');
    }

    public function test_pencarian_tanpa_hasil_memberi_langkah_berikutnya(): void
    {
        $this->get(route('home', ['q' => 'tidak-ada-organisasi']))
            ->assertOk()
            ->assertSee('Tidak ada organisasi aktif yang cocok')
            ->assertSee('meminta tautan login ke admin organisasi');
    }

    public function test_pencarian_kosong_tidak_menampilkan_daftar_organisasi(): void
    {
        Organization::create([
            'name' => 'Kampus Gamma',
            'slug' => 'kampus-gamma',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Kampus Gamma');
    }
}
