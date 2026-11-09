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

    public function test_halaman_backup_menampilkan_file_sql_saja(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/backup-20260101-120000-abcd.sql', '-- dump');
        Storage::disk('local')->put('backups/catatan.txt', 'bukan backup');

        $this->actingAs($this->admin())
            ->withSession(['auth_token' => 'token-admin-backup'])
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('backup-20260101-120000-abcd.sql')
            ->assertDontSee('catatan.txt');
    }

    public function test_admin_bisa_membuat_backup_lewat_halaman(): void
    {
        Storage::fake('local');

        $this->mock(DatabaseBackupService::class, function ($mock) {
            $mock->shouldReceive('buat')->once()->andReturn('backup-20260101-120000-abcd.sql');
        });

        $this->actingAs($this->admin())
            ->withSession(['auth_token' => 'token-admin-backup'])
            ->post(route('admin.backups.store'))
            ->assertRedirect()
            ->assertSessionHas('sukses');
    }

    public function test_backup_bisa_diunduh_dan_dihapus(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/backup-20260101-120000-abcd.sql', '-- dump');

        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-backup'])
            ->get(route('admin.backups.download', 'backup-20260101-120000-abcd.sql'))
            ->assertDownload('backup-20260101-120000-abcd.sql');

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-backup'])
            ->delete(route('admin.backups.destroy', 'backup-20260101-120000-abcd.sql'))
            ->assertRedirect()
            ->assertSessionHas('sukses');

        Storage::disk('local')->assertMissing('backups/backup-20260101-120000-abcd.sql');
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
