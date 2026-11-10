<?php

namespace Database\Seeders;

use App\Models\Organization;
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

        $admin = User::updateOrCreate(
            ['username' => $username],
            [
                'nama' => env('ADMIN_NAMA', 'Admin Kuis'),
                'password' => $password,
                'role' => 'admin',
                'is_platform_admin' => true,
            ],
        );

        $organization = Organization::query()->orderBy('id')->first();
        if ($organization) {
            $organization->users()->syncWithoutDetaching([
                $admin->id => ['role' => 'owner', 'status' => 'active'],
            ]);
            $organization->users()->updateExistingPivot($admin->id, [
                'role' => 'owner',
                'status' => 'active',
            ]);
        }

        $this->call(TrialQuizSeeder::class);
    }
}
