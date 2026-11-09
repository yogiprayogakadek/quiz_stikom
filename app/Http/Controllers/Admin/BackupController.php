<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(DatabaseBackupService $backup): View
    {
        return view('admin.backups.index', [
            'backups' => $backup->daftar(),
            'keep' => (int) config('backup.keep', 10),
        ]);
    }

    public function store(DatabaseBackupService $backup): RedirectResponse
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

    public function download(DatabaseBackupService $backup, string $nama): StreamedResponse
    {
        abort_unless($backup->valid($nama), 404);
        abort_unless(Storage::disk('local')->exists('backups/'.$nama), 404);

        return Storage::disk('local')->download('backups/'.$nama);
    }

    public function destroy(DatabaseBackupService $backup, string $nama): RedirectResponse
    {
        try {
            $backup->hapus($nama);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        return back()->with('sukses', 'Backup dihapus: '.$nama);
    }
}
