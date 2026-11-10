<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_syarat_dapat_dibuka(): void
    {
        $this->get(route('terms.show'))
            ->assertOk()
            ->assertSee('Syarat Penggunaan')
            ->assertSee('Draf, belum ditinjau secara hukum')
            ->assertSee(route('privacy.show'));
    }

    public function test_halaman_privasi_dapat_dibuka(): void
    {
        $this->get(route('privacy.show'))
            ->assertOk()
            ->assertSee('Kebijakan Privasi')
            ->assertSee('Draf, belum ditinjau secara hukum')
            ->assertSee(route('terms.show'));
    }
}
