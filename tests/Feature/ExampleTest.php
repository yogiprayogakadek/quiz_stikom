<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
