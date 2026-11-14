@php
    $bisaTambahWaktu = $session->mode === 'acak' && $session->status === 'berjalan';
    $adaBerjalan = $attempts->contains(fn ($attempt) => $attempt->status === 'berjalan');
    $tampilkanPilihan = $bisaTambahWaktu && $adaBerjalan;
@endphp

<div class="flex flex-wrap items-center justify-between gap-3">
    <h2 id="peserta-sesi" class="text-2xl">Peserta di sesi ini</h2>
    <a href="{{ route('admin.reports.session', $session) }}" class="btn btn-secondary">Lihat laporan</a>
</div>

@if ($tampilkanPilihan)
    <form
        id="form-tambah-waktu"
        method="POST"
        action="{{ route('admin.sessions.attempts.add-time-bulk', $session) }}"
        class="mt-4 rounded-2xl border border-outline bg-page p-4"
    >
        @csrf
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label for="minutes" class="label">Tambahan menit</label>
                <input id="minutes" name="minutes" type="number" min="1" max="180" value="{{ old('minutes', 10) }}" class="input w-28" required>
                @error('minutes') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn btn-primary">Tambah waktu ke peserta terpilih</button>
        </div>
        <p class="help mt-2">Centang peserta yang masih mengerjakan di tabel. Pilihan tetap tersimpan saat status diperbarui.</p>
        @error('attempt_ids') <p class="field-error">{{ $message }}</p> @enderror
    </form>
@endif

@if ($attempts->isEmpty())
    <p class="mt-3 text-base text-ink-muted">Belum ada peserta di sesi ini.</p>
@else
    <div class="mt-4 overflow-x-auto">
        <table class="table">
            <caption class="sr-only">Peserta dan status attempt</caption>
            <thead>
                <tr>
                    @if ($tampilkanPilihan)
                        <th scope="col"><span class="sr-only">Pilih peserta</span></th>
                    @endif
                    <th scope="col">Nama</th>
                    <th scope="col">Status</th>
                    <th scope="col">Benar</th>
                    <th scope="col">Skor</th>
                    <th scope="col">Pelanggaran</th>
                    <th scope="col">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($attempts as $attempt)
                    <tr>
                        @if ($tampilkanPilihan)
                            <td>
                                @if ($attempt->status === 'berjalan')
                                    <input
                                        type="checkbox"
                                        form="form-tambah-waktu"
                                        name="attempt_ids[]"
                                        value="{{ $attempt->id }}"
                                        data-attempt-id="{{ $attempt->id }}"
                                        class="h-5 w-5"
                                        aria-label="Pilih {{ $attempt->user?->nama ?? 'peserta' }}"
                                    >
                                @endif
                            </td>
                        @endif
                        <td class="font-bold text-ink">{{ $attempt->user?->nama ?? '(peserta dihapus)' }}</td>
                        <td>
                            <span class="pill {{ $attempt->status === 'selesai' ? 'pill-leaf' : ($attempt->status === 'berjalan' ? 'pill-brand' : 'pill-neutral') }}">
                                {{ ucfirst($attempt->status) }}
                            </span>
                            @if (! $attempt->sudahMengikuti())
                                <div class="mt-2">
                                    <span class="pill pill-sun">Belum bergabung</span>
                                </div>
                            @elseif (in_array($session->status, ['lobby', 'berjalan'], true))
                                @php
                                    $online = $attempt->isOnline();
                                @endphp
                                <div class="mt-2">
                                    <span class="pill {{ $online ? 'pill-leaf' : 'pill-neutral' }}">{{ $online ? 'Online' : 'Offline' }}</span>
                                </div>
                            @endif
                        </td>
                        <td class="font-mono">{{ $attempt->jumlah_benar }}</td>
                        <td class="font-mono">
                            @if ($session->mode === 'acak' && $attempt->poin_maksimal > 0)
                                {{ $attempt->poin_total }}/{{ $attempt->poin_maksimal }}
                            @else
                                {{ $attempt->skor_total }}
                            @endif
                        </td>
                        <td>
                            @if ($session->max_pelanggaran !== null && $attempt->jumlah_pelanggaran >= $session->max_pelanggaran)
                                <span class="pill pill-coral">Batas tercapai</span>
                            @else
                                <span class="text-ink-muted">{{ $attempt->jumlah_pelanggaran }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.reports.attempt', $attempt) }}" class="btn btn-ghost px-3">Detail</a>

                                @if ($session->status === 'berjalan' && $attempt->status === 'berjalan')
                                    <form
                                        method="POST"
                                        action="{{ route('admin.sessions.attempts.finish', [$session, $attempt]) }}"
                                        onsubmit="return confirm('Kumpulkan pekerjaan peserta ini sekarang? Nilai dihitung dari jawaban yang sudah tersimpan.')"
                                    >
                                        @csrf
                                        <button type="submit" class="btn btn-secondary px-3 whitespace-nowrap">Kumpulkan</button>
                                    </form>
                                @endif

                                @if (in_array($session->status, ['lobby', 'berjalan'], true))
                                    <form
                                        method="POST"
                                        action="{{ route('admin.sessions.attempts.reset', [$session, $attempt]) }}"
                                        onsubmit="return confirm('Reset attempt peserta ini? Jawaban dihapus dan peserta bisa masuk/mengulang dari awal tanpa dikeluarkan dari sesi.')"
                                    >
                                        @csrf
                                        <button type="submit" class="btn btn-ghost px-2 py-1 text-xs text-amber-700 hover:bg-amber-50 whitespace-nowrap" title="Reset attempt agar peserta bisa mengerjakan ulang">Reset</button>
                                    </form>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.sessions.attempts.kick', [$session, $attempt]) }}"
                                        onsubmit="return confirm('Keluarkan peserta ini dari sesi? Jawaban yang sudah tersimpan ikut terhapus dan peserta tidak bisa bergabung lagi.')"
                                    >
                                        @csrf
                                        <button type="submit" class="btn btn-soft-danger px-3 whitespace-nowrap">Keluarkan</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
