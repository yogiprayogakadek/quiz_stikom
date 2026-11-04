<h2 id="status-langsung" class="text-2xl">Status langsung</h2>

@if ($session->status === 'lobby' || $session->status === 'draft')
    @php $bergabung = $attempts->whereNotNull('bergabung_at'); @endphp
    <p class="mt-3 text-base text-ink-muted">Peserta yang sudah bergabung</p>
    <p class="display-number mt-1 text-5xl">{{ $totalPeserta }}</p>
    @if ($bergabung->isNotEmpty())
        <ul class="mt-3 space-y-1 text-base text-ink">
            @foreach ($bergabung as $attempt)
                <li class="truncate">{{ $attempt->user?->nama ?? '(peserta dihapus)' }}</li>
            @endforeach
        </ul>
    @else
        <p class="mt-3 text-base text-ink-muted">Belum ada peserta. Minta peserta masuk dan menekan Gabung dari dashboard mereka.</p>
    @endif
@elseif ($session->status === 'berjalan' && $session->mode === 'acak')
    @php $selesai = $attempts->whereNotNull('bergabung_at')->where('status', 'selesai')->count(); @endphp
    <p class="mt-3 text-base text-ink-muted">Peserta selesai</p>
    <p class="display-number mt-1 text-5xl">{{ $selesai }}<span class="text-3xl text-ink-muted">/{{ $totalPeserta }}</span></p>
    <p class="mt-3 text-base text-ink-muted">Sisa peserta yang masih mengerjakan: {{ $totalPeserta - $selesai }}</p>
@elseif ($session->status === 'berjalan' && $session->mode === 'serempak')
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <x-status-badge :status="$session->status" />
        <span class="pill pill-neutral">{{ $session->fase === 'jeda' ? 'Jeda' : 'Soal terbuka' }}</span>
        <span class="pill pill-brand">Soal {{ $session->soal_aktif_urutan ?? '-' }} dari {{ $session->sessionQuestions()->count() }}</span>
    </div>
    <p class="mt-3 text-base text-ink-muted">Sudah menjawab</p>
    <p class="display-number mt-1 text-5xl">{{ $sudahMenjawab }}<span class="text-3xl text-ink-muted">/{{ $totalPeserta }}</span></p>

    @if ($session->fase === 'soal_terbuka' && $session->soal_berakhir_at)
        <div
            x-data="{
                sisa: 0,
                format(n) {
                    const menit = Math.floor(n / 60);
                    const detik = n % 60;
                    return menit + ':' + String(detik).padStart(2, '0');
                },
                init() {
                    const akhir = {{ $session->soal_berakhir_at }};
                    const selisih = Date.now() - {{ now()->getTimestampMs() }};
                    const hitung = () => {
                        this.sisa = Math.max(0, Math.round((akhir - (Date.now() - selisih)) / 1000));
                    };
                    hitung();
                    setInterval(hitung, 250);
                }
            }"
            class="mt-4 rounded-2xl bg-sun-soft px-4 py-3"
        >
            <p class="text-base font-bold text-sun-deep">Sisa waktu soal</p>
            <p class="timer mt-1 text-4xl text-ink" x-text="format(sisa)">0:00</p>
        </div>
    @else
        <p class="mt-4 rounded-2xl bg-brand-soft px-4 py-3 text-base text-brand-deep">
            Jeda {{ $session->jeda_detik }} detik. Soal berikutnya dibuka otomatis oleh server.
        </p>
    @endif
@else
    <p class="mt-3 text-base text-ink-muted">Sesi selesai. Semua attempt sudah dikunci.</p>
    <p class="display-number mt-1 text-5xl">{{ $totalPeserta }}</p>
    <p class="text-base text-ink-muted">peserta mengikuti sesi ini.</p>
@endif
