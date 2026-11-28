@if (isset($snapshotTerbaru) && $snapshotTerbaru->isNotEmpty())
    <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
        <div class="flex items-center justify-between gap-2 mb-2.5">
            <p class="text-xs font-bold text-slate-800">Bukti tangkapan layar &amp; kamera terbaru (khusus pengajar)</p>
            <span class="text-[11px] font-semibold text-slate-500">{{ $snapshotTerbaru->count() }} gambar terbaru</span>
        </div>
        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4 lg:grid-cols-6">
            @foreach ($snapshotTerbaru as $snap)
                <a
                    href="{{ $snap->url() }}"
                    target="_blank"
                    rel="noopener"
                    class="group block overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xs transition hover:border-blue-500"
                >
                    <img
                        src="{{ $snap->url() }}"
                        alt="Bukti {{ $snap->capture_source }} {{ $snap->attempt?->user?->nama ?? 'Peserta' }}"
                        width="160"
                        height="90"
                        loading="lazy"
                        class="aspect-video w-full object-cover bg-slate-900"
                    >
                    <div class="p-1.5 text-[11px]">
                        <p class="truncate font-bold text-slate-900">{{ $snap->attempt?->user?->nama ?? 'Peserta' }}</p>
                        <p class="truncate text-slate-500">
                            {{ $snap->capture_source === 'screen' ? 'Layar' : 'Kamera' }} · {{ $snap->captured_at?->timezone($orgTimezone)->format('H:i:s') }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endif

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
        <p class="text-xs font-bold text-slate-600">Belum ada aktivitas pengawasan.</p>
        <p class="text-[11px] text-slate-400 mt-0.5">Perpindahan tab, keluar layar penuh, dan bukti layar akan muncul di sini.</p>
    </div>
@endforelse
