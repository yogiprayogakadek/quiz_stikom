<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_organisasi_tidak_bisa_mengakses_backup_platform(): void
    {
        $this->actingAs($this->admin())
            ->withSession(['auth_token' => 'token-admin-backup'])
            ->get('/admin/backups')
            ->assertNotFound();
    }

    public function test_nama_backup_tidak_valid_ditolak(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('rahasia.sql', 'jangan dihapus');

        $this->expectException(\InvalidArgumentException::class);

        app(DatabaseBackupService::class)->hapus('../rahasia.sql');
    }

    public function test_retensi_menyisakan_sepuluh_backup_terbaru(): void
    {
        Storage::fake('local');

        foreach (range(1, 12) as $i) {
            Storage::disk('local')->put(
                sprintf('backups/backup-202601%02d-120000-abcd.sql', $i),
                '-- dump'
            );
        }

        app(DatabaseBackupService::class)->bersihkan(10);

        $this->assertCount(10, Storage::disk('local')->files('backups'));
        Storage::disk('local')->assertMissing('backups/backup-20260101-120000-abcd.sql');
        Storage::disk('local')->assertExists('backups/backup-20260112-120000-abcd.sql');
    }

    private function admin(): User
    {
        return User::create([
            'nama' => 'Admin Backup',
            'username' => 'admin-backup',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-admin-backup',
        ]);
    }
}
