<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_dapat_melihat_kesehatan_sistem(): void
    {
        $this->actingAs($this->master())
            ->withSession(['auth_token' => 'master-token'])
            ->get(route('master.system.index'))
            ->assertOk()
            ->assertSee('Perlu perhatian')
            ->assertSee('Redis')
            ->assertSee('Realtime')
            ->assertSee('Database')
            ->assertSee('Reverb')
            ->assertSee('Job gagal')
            ->assertSee('Backup database')
            ->assertDontSee('Tidak ada peringatan operasional saat ini.');
    }

    public function test_kesehatan_memisahkan_kedalaman_queue_realtime_dan_default(): void
    {
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            [
                'queue' => 'realtime',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->subSeconds(12)->timestamp,
            ],
            [
                'queue' => 'default',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ],
        ]);

        $snapshot = app(SystemHealth::class)->snapshot();

        $this->assertSame(['realtime' => 1, 'default' => 1], $snapshot['queue_sizes']);
        $this->assertGreaterThanOrEqual(12, $snapshot['queue_oldest_age_seconds']['realtime']);
        $this->assertSame(2, $snapshot['pending_jobs']);
    }

    public function test_master_melihat_daftar_backup_sql_saja(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/backup-20260101-120000-abcd.sql', '-- dump');
        Storage::disk('local')->put('backups/catatan.txt', 'bukan backup');

        $this->actingAs($this->master())
            ->withSession(['auth_token' => 'master-token'])
            ->get(route('master.system.index'))
            ->assertOk()
            ->assertSee('backup-20260101-120000-abcd.sql')
            ->assertDontSee('catatan.txt');
    }

    public function test_admin_organisasi_tidak_dapat_melihat_kesehatan_sistem(): void
    {
        $this->actingAs($this->admin())
            ->withSession(['auth_token' => 'admin-token'])
            ->get(route('master.system.index'))
            ->assertForbidden();
    }

    public function test_master_dapat_membuat_dan_mengunduh_backup(): void
    {
        Storage::fake('local');

        $this->mock(DatabaseBackupService::class, function (MockInterface $mock) {
            $mock->shouldReceive('buat')->once()->andReturn('backup-test.sql');
            $mock->shouldReceive('valid')->andReturnTrue();
        });

        $this->actingAs($this->master())
            ->withSession(['auth_token' => 'master-token'])
            ->post(route('master.system.backups.store'))
            ->assertRedirect();

        Storage::disk('local')->put('backups/backup-test.sql', 'sql');

        $this->actingAs($this->master())
            ->withSession(['auth_token' => 'master-token'])
            ->get(route('master.system.backups.download', 'backup-test.sql'))
            ->assertOk()
            ->assertDownload('backup-test.sql');
    }

    public function test_master_dapat_retry_dan_menghapus_failed_job(): void
    {
        config([
            'queue.default' => 'database',
            'queue.failed.database' => config('database.default'),
        ]);

        $retryId = $this->failedJob();
        $forgetId = $this->failedJob();
        $master = $this->master();

        $this->actingAs($master)
            ->withSession(['auth_token' => 'master-token'])
            ->post(route('master.system.failed-jobs.retry', $retryId))
            ->assertRedirect();

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $retryId]);
        $this->assertSame(1, DB::table('jobs')->count());

        $this->actingAs($master)
            ->withSession(['auth_token' => 'master-token'])
            ->delete(route('master.system.failed-jobs.forget', $forgetId))
            ->assertRedirect();

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $forgetId]);
    }

    public function test_halaman_menampilkan_empty_state_antrean_bersih(): void
    {
        $this->actingAs($this->master())
            ->withSession(['auth_token' => 'master-token'])
            ->get(route('master.system.index'))
            ->assertOk()
            ->assertSee('Tidak ada job gagal. Antrean bersih.');
    }

    public function test_kesehatan_sistem_memantau_detak_scheduler_aktif_dan_usang(): void
    {
        Storage::fake('local');
        $master = $this->master();

        $awal = app(SystemHealth::class)->snapshot();
        $this->assertFalse($awal['scheduler']['active']);
        $this->assertNull($awal['scheduler']['last_run_at']);

        $this->actingAs($master)
            ->withSession(['auth_token' => 'master-token'])
            ->get(route('master.system.index'))
            ->assertOk()
            ->assertSee('Scheduler tidak aktif.')
            ->assertSee('Belum berjalan');

        $this->artisan('kuis:tutup-attempt-kedaluwarsa')->assertExitCode(0);

        $aktif = app(SystemHealth::class)->snapshot();
        $this->assertTrue($aktif['scheduler']['active']);
        $this->assertNotNull($aktif['scheduler']['last_run_at']);

        $this->actingAs($master)
            ->withSession(['auth_token' => 'master-token'])
            ->get(route('master.system.index'))
            ->assertOk()
            ->assertDontSee('Scheduler tidak aktif.')
            ->assertSee('Scheduler');

        Storage::disk('local')->put(
            SystemHealth::SCHEDULER_HEARTBEAT_FILE,
            (string) json_encode(['last_run_at' => now()->subMinutes(5)->timestamp]),
        );

        $usang = app(SystemHealth::class)->snapshot();
        $this->assertFalse($usang['scheduler']['active']);

        $this->actingAs($master)
            ->withSession(['auth_token' => 'master-token'])
            ->get(route('master.system.index'))
            ->assertOk()
            ->assertSee('Scheduler tidak aktif.')
            ->assertSee('Tertunda');
    }

    private function failedJob(): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => (string) Str::uuid(),
                'displayName' => 'App\Jobs\FakeJob',
                'job' => 'Illuminate\Queue\CallQueuedHandler@call',
                'maxTries' => 1,
                'backoff' => null,
                'timeout' => null,
                'data' => [],
            ]),
            'exception' => "RuntimeException: gagal uji\n#0 {main}",
            'failed_at' => now(),
        ]);

        return $uuid;
    }

    private function master(): User
    {
        return User::create([
            'nama' => 'Master Platform',
            'username' => 'master-system-'.Str::lower(Str::random(6)),
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_platform_admin' => true,
            'current_session_token' => 'master-token',
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'nama' => 'Admin Organisasi',
            'username' => 'admin-system',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => 'admin-token',
        ]);
    }
}
