<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    private const FOLDER = 'backups';

    /**
     * @return array<int, array{nama: string, ukuran: int, diubah: int}>
     */
    public function daftar(): array
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::FOLDER))
            ->filter(fn (string $path) => str_ends_with($path, '.sql'))
            ->map(fn (string $path) => [
                'nama' => basename($path),
                'ukuran' => $disk->size($path),
                'diubah' => $disk->lastModified($path),
            ])
            ->sortByDesc('nama')
            ->values()
            ->all();
    }

    public function buat(): string
    {
        $koneksi = config('database.connections.mysql');
        $binary = (string) config('backup.mysqldump', 'mysqldump');
        $nama = 'backup-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(4)).'.sql';
        $disk = Storage::disk('local');

        $disk->makeDirectory(self::FOLDER);
        $tujuan = $disk->path(self::FOLDER.'/'.$nama);

        $process = new Process([
            $binary,
            '--host='.($koneksi['host'] ?? '127.0.0.1'),
            '--port='.($koneksi['port'] ?? '3306'),
            '--user='.($koneksi['username'] ?? ''),
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--routines',
            '--events',
            '--triggers',
            '--default-character-set=utf8mb4',
            '--result-file='.$tujuan,
            (string) ($koneksi['database'] ?? ''),
        ]);

        $process->setEnv(['MYSQL_PWD' => (string) ($koneksi['password'] ?? '')]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($tujuan)) {
            @unlink($tujuan);

            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump tidak dapat dijalankan.');
        }

        $this->bersihkan((int) config('backup.keep', 10));

        return $nama;
    }

    public function hapus(string $nama): void
    {
        if (! $this->valid($nama)) {
            throw new \InvalidArgumentException('Nama backup tidak valid.');
        }

        Storage::disk('local')->delete(self::FOLDER.'/'.$nama);
    }

    public function valid(string $nama): bool
    {
        return ! str_contains($nama, '..')
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.sql$/', $nama) === 1;
    }

    public function path(string $nama): string
    {
        if (! $this->valid($nama)) {
            throw new \InvalidArgumentException('Nama backup tidak valid.');
        }

        return Storage::disk('local')->path(self::FOLDER.'/'.$nama);
    }

    public function bersihkan(int $simpan = 10): void
    {
        $disk = Storage::disk('local');
        $file = collect($disk->files(self::FOLDER))
            ->filter(fn (string $path) => str_ends_with($path, '.sql'))
            ->sortDesc()
            ->values();

        $file->slice(max(1, $simpan))->each(fn (string $path) => $disk->delete($path));
    }
}
