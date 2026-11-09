@php
    $mode = $session->pembahasanMode();
    $label = match ($mode) {
        'terbuka' => 'Terbuka sekarang',
        'otomatis' => 'Otomatis setelah selesai',
        default => 'Tersembunyi',
    };
    $badge = match ($mode) {
        'terbuka' => 'pill-leaf',
        'otomatis' => 'pill-brand',
        default => 'pill',
    };
@endphp

<section class="card" aria-labelledby="kontrol-pembahasan">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="kontrol-pembahasan" class="text-2xl">Kontrol pembahasan</h2>
            <p class="mt-2 max-w-2xl text-base text-ink-muted">
                Buka kunci jawaban dan alasan saat sesi berjalan. Peserta yang sudah selesai akan menerima pembaruan langsung.
            </p>
        </div>
        <span class="pill {{ $badge }}">{{ $label }}</span>
    </div>

    <p class="mt-3 text-base text-ink-muted">
        {{ $pembahasanSelesai }} dari {{ $totalPeserta }} peserta sudah selesai.
        @if ($session->mode === 'serempak')
            Mode Serempak membuka pembahasan otomatis saat sesi ditutup.
        @endif
    </p>

    <div class="mt-4 flex flex-wrap gap-2">
        @foreach ([
            'tersembunyi' => ['label' => 'Sembunyikan', 'class' => 'btn btn-secondary'],
            'otomatis' => ['label' => 'Buka otomatis setelah semua selesai', 'class' => 'btn btn-secondary'],
            'terbuka' => ['label' => 'Buka sekarang', 'class' => 'btn btn-primary'],
        ] as $nilai => $tombol)
            <form method="POST" action="{{ route('admin.sessions.pembahasan', $session) }}">
                @csrf
                <input type="hidden" name="mode" value="{{ $nilai }}">
                <button type="submit" class="{{ $tombol['class'] }}" @disabled($mode === $nilai)>
                    {{ $tombol['label'] }}
                </button>
            </form>
        @endforeach
    </div>
</section>
