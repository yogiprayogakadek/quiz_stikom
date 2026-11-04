<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('ADMIN_USERNAME', 'admin');
        $password = env('ADMIN_PASSWORD');

        if (blank($password)) {
            throw new RuntimeException('Isi ADMIN_PASSWORD di .env sebelum menjalankan seeder.');
        }

        User::updateOrCreate(
            ['username' => $username],
            [
                'nama' => env('ADMIN_NAMA', 'Admin Kuis'),
                'password' => $password,
                'role' => 'admin',
            ],
        );
    }
}
