<?php

namespace App\Console\Commands;

use App\Models\AiGeneration;
use Illuminate\Console\Command;

class PruneStaleDataCommand extends Command
{
    protected $signature = 'kuis:bersihkan-data-usang {--days=7 : Usia minimum hari untuk data gagal atau menggantung}';

    protected $description = 'Membersihkan riwayat generasi soal AI yang gagal atau menggantung melewati batas hari';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $batas = now()->subDays($days);

        $dihapus = AiGeneration::withoutGlobalScopes()
            ->whereIn('status', ['gagal', 'pending'])
            ->where('updated_at', '<', $batas)
            ->delete();

        $this->info("{$dihapus} generasi AI usang dibersihkan.");

        return self::SUCCESS;
    }
}
