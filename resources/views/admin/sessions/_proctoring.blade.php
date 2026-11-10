@forelse ($kejadianTerbaru as $kejadian)
    @php
        $label = \App\Models\AttemptEvent::label($kejadian->tipe);
    @endphp
    <div class="flex items-start justify-between gap-3 p-3 rounded-xl border border-slate-200/80 bg-slate-50/60 hover:bg-slate-50 transition text-xs">
        <div class="flex items-start gap-2.5 min-w-0">
            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg {{ $label['level'] === 'danger' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' }}">
                <x-icon name="alert" :size="14" />
            </span>
            <div class="min-w-0">
                <p class="font-bold text-slate-900">
                    {{ $kejadian->attempt?->user?->nama ?? 'Peserta' }}
                    <span class="font-mono text-slate-500 font-normal">({{ $kejadian->attempt?->user?->username ?? '-' }})</span>
                </p>
                <p class="text-slate-600 mt-0.5">{{ $label['pesan'] }}</p>
                @if ($kejadian->durasi_detik)
                    <p class="text-slate-500 mt-0.5">Kembali ke layar ujian setelah {{ $kejadian->durasi_detik }} detik.</p>
                @endif
            </div>
        </div>
        <span class="font-mono text-[11px] text-slate-400 shrink-0">
            {{ $kejadian->created_at ? $kejadian->created_at->timezone($orgTimezone)->format('H:i:s') : '-' }}
        </span>
    </div>
@empty
    <div id="proctoring-empty" class="p-6 text-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50">
        <p class="text-xs font-bold text-slate-600">Belum ada pelanggaran terdeteksi.</p>
        <p class="text-[11px] text-slate-400 mt-0.5">Notifikasi otomatis muncul di sini bila ada siswa yang berpindah tab atau keluar fullscreen.</p>
    </div>
@endforelse
