<?php

namespace App\Console\Commands;

use App\Models\AiGeneration;
use App\Models\AttemptSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PruneStaleDataCommand extends Command
{
    protected $signature = 'kuis:bersihkan-data-usang
        {--days=7 : Usia minimum hari untuk data generasi AI gagal atau menggantung}
        {--snapshot-days=14 : Usia retensi hari untuk gambar bukti pengawasan ujian}';

    protected $description = 'Membersihkan riwayat generasi AI usang serta gambar snapshot pengawasan yang melewati masa retensi';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $batas = now()->subDays($days);

        $dihapus = AiGeneration::withoutGlobalScopes()
            ->whereIn('status', ['gagal', 'pending'])
            ->where('updated_at', '<', $batas)
            ->delete();

        $snapshotDihapus = 0;
        if (Schema::hasTable('attempt_snapshots')) {
            $snapshotDays = max(1, (int) $this->option('snapshot-days'));
            $batasSnapshot = now()->subDays($snapshotDays);

            AttemptSnapshot::withoutGlobalScopes()
                ->where('captured_at', '<', $batasSnapshot)
                ->orderBy('id')
                ->chunkById(200, function ($snapshots) use (&$snapshotDihapus) {
                    $paths = $snapshots->pluck('path')->filter()->values()->all();
                    if ($paths !== []) {
                        Storage::disk('local')->delete($paths);
                    }
                    AttemptSnapshot::withoutGlobalScopes()
                        ->whereIn('id', $snapshots->pluck('id')->all())
                        ->delete();
                    $snapshotDihapus += $snapshots->count();
                });
        }

        $this->info("{$dihapus} generasi AI usang dan {$snapshotDihapus} snapshot pengawasan dibersihkan.");

        return self::SUCCESS;
    }
}
