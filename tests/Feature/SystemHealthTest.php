<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
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
            ->assertSee('Job gagal')
            ->assertSee('Backup database');
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
