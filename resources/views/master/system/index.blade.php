<x-layouts.admin judul="Sistem" keterangan="Temukan kegagalan, cek kesiapan, dan pulihkan layanan.">
    @php
        $formatBytes = function (?int $bytes): string {
            if ($bytes === null) {
                return 'Tidak tersedia';
            }

            return $bytes >= 1024 * 1024
                ? number_format($bytes / 1024 / 1024, 1, ',', '.').' MB'
                : number_format($bytes / 1024, 1, ',', '.').' KB';
        };

        $lastBackupAt = $snapshot['last_backup'] !== null
            ? \Illuminate\Support\Carbon::createFromTimestamp($snapshot['last_backup']['diubah'])
            : null;
    @endphp

    <div class="space-y-6">
        <section class="rounded-2xl border border-outline bg-page p-5 sm:p-6" aria-labelledby="perlu-perhatian">
            <h2 id="perlu-perhatian" class="text-xl">Perlu perhatian</h2>

            <ul class="mt-4 space-y-2 text-base text-ink-muted">
                @if ($snapshot['debug'])
                    <li class="flex gap-2"><x-icon name="alert" :size="18" class="mt-0.5 shrink-0 text-coral-deep" /> <span><span class="font-bold text-ink">Debug aktif.</span> Matikan <code>APP_DEBUG</code> di produksi agar detail error tidak terbuka.</span></li>
                @endif
                @if ($snapshot['failed_jobs'] > 0)
                    <li class="flex gap-2"><x-icon name="alert" :size="18" class="mt-0.5 shrink-0 text-coral-deep" /> <span><span class="font-bold text-ink">{{ number_format($snapshot['failed_jobs'], 0, ',', '.') }} job gagal.</span> Periksa pesan di bawah, lalu retry atau hapus.</span></li>
                @endif
                @if (! $snapshot['mail_configured'])
                    <li class="flex gap-2"><x-icon name="mail" :size="18" class="mt-0.5 shrink-0 text-sun-deep" /> <span><span class="font-bold text-ink">SMTP platform belum diatur.</span> Email verifikasi dan reset password tidak akan terkirim. <a href="{{ route('master.mail.edit') }}" class="font-bold text-brand-deep underline-offset-2 hover:underline">Atur email</a>.</span></li>
                @endif
                @if ($lastBackupAt === null)
                    <li class="flex gap-2"><x-icon name="clock" :size="18" class="mt-0.5 shrink-0 text-sun-deep" /> <span><span class="font-bold text-ink">Belum ada backup.</span> Buat backup pertama dari bagian bawah halaman.</span></li>
                @elseif ($lastBackupAt->lt(now()->subDays(7)))
                    <li class="flex gap-2"><x-icon name="clock" :size="18" class="mt-0.5 shrink-0 text-sun-deep" /> <span><span class="font-bold text-ink">Backup terakhir {{ $lastBackupAt->diffForHumans() }}.</span> Backup rutin disarankan minimal mingguan.</span></li>
                @endif
                @if (! $snapshot['debug'] && $snapshot['failed_jobs'] === 0 && $snapshot['mail_configured'] && $lastBackupAt !== null && $lastBackupAt->gte(now()->subDays(7)))
                    <li class="flex gap-2"><x-icon name="check" :size="18" class="mt-0.5 shrink-0 text-leaf-deep" /> <span>Tidak ada peringatan operasional saat ini.</span></li>
                @endif
            </ul>
        </section>

        <section class="card" aria-labelledby="job-gagal">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="job-gagal" class="text-xl">Job gagal</h2>
                    <p class="mt-1 text-sm text-ink-muted">Antrean menunggu: {{ number_format($snapshot['pending_jobs'], 0, ',', '.') }} · Queue: <span class="font-mono">{{ $snapshot['queue'] }}</span></p>
                </div>
            </div>

            @if ($snapshot['failed_job_list'] === [])
                <p class="mt-5 rounded-xl border border-leaf-deep/20 bg-leaf-soft p-4 font-bold text-leaf-deep">Tidak ada job gagal. Antrean bersih.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[48rem] text-left text-sm">
                        <thead class="text-xs text-ink-subtle">
                            <tr>
                                <th class="px-3 py-2 font-bold">Waktu</th>
                                <th class="px-3 py-2 font-bold">Queue</th>
                                <th class="px-3 py-2 font-bold">Error</th>
                                <th class="px-3 py-2 text-right font-bold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline">
                            @foreach ($snapshot['failed_job_list'] as $job)
                                <tr>
                                    <td class="px-3 py-3 align-top text-ink-muted">
                                        @if ($job['failed_at'])
                                            <time datetime="{{ $job['failed_at'] }}">{{ \Illuminate\Support\Carbon::parse($job['failed_at'])->format('d M Y H:i') }}</time>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 align-top font-mono text-xs text-ink-muted">{{ $job['queue'] }}</td>
                                    <td class="px-3 py-3 align-top text-ink-muted">{{ $job['error'] ?: 'Tanpa pesan error.' }}</td>
                                    <td class="px-3 py-3 align-top">
                                        <div class="flex justify-end gap-2">
                                            <form method="POST" action="{{ route('master.system.failed-jobs.retry', $job['id']) }}" data-progress-message="Mengirim ulang job...">
                                                @csrf
                                                <button type="submit" class="btn btn-secondary !px-3 !py-1.5 text-xs">Retry</button>
                                            </form>
                                            <form method="POST" action="{{ route('master.system.failed-jobs.forget', $job['id']) }}" onsubmit="return confirm('Hapus job gagal ini dari daftar?')" data-progress-message="Menghapus job...">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-soft-danger !px-3 !py-1.5 text-xs">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card" aria-labelledby="backup">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="backup" class="text-xl">Backup database</h2>
                    <p class="mt-1 text-sm text-ink-muted">Maksimal {{ $keep }} file terbaru disimpan. Backup memakai <code>mysqldump</code>.</p>
                </div>
                <form method="POST" action="{{ route('master.system.backups.store') }}" data-progress-message="Membuat backup database...">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="download" :size="18" />
                        Buat backup
                    </button>
                </form>
            </div>

            @error('backup') <p class="mt-3 field-error">{{ $message }}</p> @enderror

            @if ($backups === [])
                <p class="mt-5 rounded-xl border border-outline p-4 text-ink-muted">Belum ada backup. Buat backup sebelum melakukan perubahan besar.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[36rem] text-left text-sm">
                        <thead class="text-xs text-ink-subtle">
                            <tr>
                                <th class="px-3 py-2 font-bold">File</th>
                                <th class="px-3 py-2 font-bold">Ukuran</th>
                                <th class="px-3 py-2 font-bold">Dibuat</th>
                                <th class="px-3 py-2 text-right font-bold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline">
                            @foreach ($backups as $backup)
                                <tr>
                                    <td class="px-3 py-3 font-mono text-xs text-ink">{{ $backup['nama'] }}</td>
                                    <td class="px-3 py-3 text-ink-muted">{{ $formatBytes($backup['ukuran']) }}</td>
                                    <td class="px-3 py-3 text-ink-muted">{{ \Illuminate\Support\Carbon::createFromTimestamp($backup['diubah'])->format('d M Y H:i') }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('master.system.backups.download', $backup['nama']) }}" class="btn btn-secondary !px-3 !py-1.5 text-xs">
                                                <x-icon name="download" :size="16" /> Unduh
                                            </a>
                                            <form method="POST" action="{{ route('master.system.backups.destroy', $backup['nama']) }}" onsubmit="return confirm('Hapus backup ini?')" data-progress-message="Menghapus backup...">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-soft-danger !px-3 !py-1.5 text-xs">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="grid gap-5 lg:grid-cols-2" aria-label="Kesiapan operasional">
            <div class="card">
                <h2 class="text-xl">Kesiapan layanan</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-4 border-b border-outline pb-3">
                        <dt class="text-ink-muted">Email platform</dt>
                        <dd class="text-right font-bold {{ $snapshot['mail_configured'] ? 'text-leaf-deep' : 'text-sun-deep' }}">
                            {{ $snapshot['mail_configured'] ? 'Siap' : 'Belum diatur' }}
                            @if (! $snapshot['mail_configured'])
                                · <a href="{{ route('master.mail.edit') }}" class="text-brand-deep underline-offset-2 hover:underline">Atur</a>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 border-b border-outline pb-3">
                        <dt class="text-ink-muted">Disk bebas</dt>
                        <dd class="text-right font-bold">{{ $formatBytes($snapshot['disk_free']) }} <span class="font-normal text-ink-muted">dari {{ $formatBytes($snapshot['disk_total']) }}</span></dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 border-b border-outline pb-3">
                        <dt class="text-ink-muted">Backup terakhir</dt>
                        <dd class="text-right font-bold">{{ $lastBackupAt?->diffForHumans() ?? 'Belum ada' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-ink-muted">Debug</dt>
                        <dd class="text-right font-bold {{ $snapshot['debug'] ? 'text-coral-deep' : 'text-leaf-deep' }}">{{ $snapshot['debug'] ? 'Aktif' : 'Nonaktif' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="card">
                <h2 class="text-xl">Log dan pemulihan</h2>
                <p class="mt-2 text-sm text-ink-muted">Path log: <code class="break-all">{{ $snapshot['log_path'] }}</code></p>
                <p class="mt-1 text-sm text-ink-muted">Terakhir diubah: {{ $snapshot['log_modified_at'] ? \Illuminate\Support\Carbon::createFromTimestamp($snapshot['log_modified_at'])->format('d M Y H:i') : 'Belum ada file log' }}</p>
                <p class="mt-3 text-sm leading-relaxed text-ink-muted">
                    Prosedur lengkap ada di <code>docs/operations/recovery.md</code>. Ringkasnya: aktifkan maintenance, unduh backup terbaru, pulihkan ke database kosong, jalankan migrasi, bersihkan cache, verifikasi login dan satu attempt, lalu matikan maintenance.
                </p>
            </div>
        </section>
    </div>
</x-layouts.admin>
