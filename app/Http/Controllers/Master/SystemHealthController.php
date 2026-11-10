<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use App\Services\SystemHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemHealthController extends Controller
{
    public function index(SystemHealth $health, DatabaseBackupService $backup): View
    {
        return view('master.system.index', [
            'snapshot' => $health->snapshot(),
            'backups' => $backup->daftar(),
            'keep' => (int) config('backup.keep', 10),
        ]);
    }

    public function backupStore(DatabaseBackupService $backup): RedirectResponse
    {
        try {
            $nama = $backup->buat();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'backup' => 'Backup gagal. Pastikan mysqldump tersedia di server dan kredensial database benar.',
            ]);
        }

        return back()->with('sukses', 'Backup dibuat: '.$nama);
    }

    public function backupDownload(DatabaseBackupService $backup, string $nama): StreamedResponse
    {
        abort_unless($backup->valid($nama), 404);
        abort_unless(Storage::disk('local')->exists('backups/'.$nama), 404);

        return Storage::disk('local')->download('backups/'.$nama);
    }

    public function backupDestroy(DatabaseBackupService $backup, string $nama): RedirectResponse
    {
        try {
            $backup->hapus($nama);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        return back()->with('sukses', 'Backup dihapus: '.$nama);
    }

    public function retryFailedJob(string $id): RedirectResponse
    {
        $job = $this->failedJobs()->where('uuid', $id)->first()
            ?? $this->failedJobs()->where('id', $id)->first();

        abort_if($job === null, 404);

        Artisan::call('queue:retry', ['id' => [(string) ($job->uuid ?: $job->id)]]);

        return back()->with('sukses', 'Job gagal dikirim ulang ke antrean.');
    }

    public function forgetFailedJob(string $id): RedirectResponse
    {
        $deleted = $this->failedJobs()->where('uuid', $id)->delete();
        if ($deleted === 0) {
            $deleted = $this->failedJobs()->where('id', $id)->delete();
        }

        abort_if($deleted === 0, 404);

        return back()->with('sukses', 'Job gagal dihapus dari daftar.');
    }

    private function failedJobs(): \Illuminate\Database\Query\Builder
    {
        return DB::connection(config('queue.failed.database') ?: config('database.default'))
            ->table(config('queue.failed.table', 'failed_jobs'));
    }
}
