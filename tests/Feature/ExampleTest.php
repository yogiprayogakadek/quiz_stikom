<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_tamu_melihat_halaman_utama(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Kuis kelas.')
            ->assertSee('Nama atau alamat')
            ->assertSee(route('register.organization'));
    }

    public function test_alamat_login_lama_diarahkan_ke_halaman_utama(): void
    {
        $this->get('/login')->assertRedirect(route('home'));
    }
}