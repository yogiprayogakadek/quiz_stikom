<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'kuis:backup-database {--force : Jalankan backup meskipun jadwal otomatis dinonaktifkan}';

    protected $description = 'Membuat cadangan database otomatis dan menerapkan batas retensi';

    public function handle(DatabaseBackupService $backup): int
    {
        if (! $this->option('force') && ! config('backup.schedule_enabled', true)) {
            $this->info('Jadwal backup otomatis dinonaktifkan.');

            return self::SUCCESS;
        }

        try {
            $nama = $backup->buat();
            $this->info("Backup berhasil dibuat: {$nama}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Backup database terjadwal gagal: '.$exception->getMessage());
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
