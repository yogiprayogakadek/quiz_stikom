<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;
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

    public function test_command_backup_database_menghormati_konfigurasi_jadwal_dan_memanggil_service(): void
    {
        $mock = Mockery::mock(DatabaseBackupService::class);
        $mock->shouldReceive('buat')->once()->andReturn('backup-20261128-020000-test.sql');
        $this->app->instance(DatabaseBackupService::class, $mock);

        config(['backup.schedule_enabled' => false]);
        $this->artisan('kuis:backup-database')->assertExitCode(0);

        config(['backup.schedule_enabled' => true]);
        $this->artisan('kuis:backup-database')->assertExitCode(0);
    }

    public function test_command_bersihkan_data_usang_menghapus_generasi_ai_lama_yang_gagal_atau_pending(): void
    {
        $admin = $this->admin();
        $quizId = DB::table('quizzes')->insertGetId([
            'judul' => 'Kuis Prune',
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lama = now()->subDays(10);
        $baru = now()->subDays(2);

        $idGagalLama = DB::table('ai_generations')->insertGetId([
            'user_id' => $admin->id,
            'quiz_id' => $quizId,
            'status' => 'gagal',
            'params' => json_encode([]),
            'created_at' => $lama,
            'updated_at' => $lama,
        ]);
        $idPendingLama = DB::table('ai_generations')->insertGetId([
            'user_id' => $admin->id,
            'quiz_id' => $quizId,
            'status' => 'pending',
            'params' => json_encode([]),
            'created_at' => $lama,
            'updated_at' => $lama,
        ]);
        $idSelesaiLama = DB::table('ai_generations')->insertGetId([
            'user_id' => $admin->id,
            'quiz_id' => $quizId,
            'status' => 'selesai',
            'params' => json_encode([]),
            'created_at' => $lama,
            'updated_at' => $lama,
        ]);
        $idGagalBaru = DB::table('ai_generations')->insertGetId([
            'user_id' => $admin->id,
            'quiz_id' => $quizId,
            'status' => 'gagal',
            'params' => json_encode([]),
            'created_at' => $baru,
            'updated_at' => $baru,
        ]);

        $this->artisan('kuis:bersihkan-data-usang')->assertExitCode(0);

        $this->assertNull(AiGeneration::withoutGlobalScopes()->find($idGagalLama));
        $this->assertNull(AiGeneration::withoutGlobalScopes()->find($idPendingLama));
        $this->assertNotNull(AiGeneration::withoutGlobalScopes()->find($idSelesaiLama));
        $this->assertNotNull(AiGeneration::withoutGlobalScopes()->find($idGagalBaru));
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
