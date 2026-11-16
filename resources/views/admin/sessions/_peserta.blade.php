@php
    $bisaTambahWaktu = $session->mode === 'acak' && ! $session->isTerjadwal() && $session->status === 'berjalan';
    $adaBerjalan = $attempts->contains(fn ($attempt) => $attempt->status === 'berjalan');
    $tampilkanPilihan = $bisaTambahWaktu && $adaBerjalan;
    $pesertaAktif = $attempts->where('status', 'berjalan');
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

@if ($session->status === 'berjalan' && $pesertaAktif->isNotEmpty())
    @include('admin.sessions._pesan', ['pesertaAktif' => $pesertaAktif])
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
                            @if ($session->max_pelanggaran !== null && $attempt->jumlah_pelanggaran > 0 && $attempt->jumlah_pelanggaran % $session->max_pelanggaran === 0)
                                <span class="pill pill-coral">Batas tercapai</span>
                            @else
                                <span class="text-ink-muted">{{ $attempt->jumlah_pelanggaran }}</span>
                            @endif
                            @if ($attempt->total_pengurangan_waktu_detik > 0)
                                <p class="mt-1 text-xs text-ink-muted">Dikurangi {{ $attempt->total_pengurangan_waktu_detik }} detik</p>
                            @endif
                            @if ($attempt->last_pelanggaran_action)
                                <p class="mt-1 text-xs font-bold text-ink-muted">
                                    {{ $attempt->last_pelanggaran_action === 'reduce_time' ? 'Sanksi terakhir: potong waktu' : 'Sanksi terakhir: dikumpulkan' }}
                                </p>
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
                                    <button type="button" class="btn btn-ghost px-3 text-amber-700 hover:bg-amber-50 whitespace-nowrap"
                                        x-on:click="document.getElementById('reset-attempt-{{ $attempt->id }}').showModal()">
                                        Ulangi attempt
                                    </button>
                                    <dialog id="reset-attempt-{{ $attempt->id }}" class="m-auto max-h-[calc(100dvh-2rem)] w-[min(92vw,34rem)] overflow-y-auto rounded-2xl border border-outline bg-surface p-0 text-ink shadow-xl backdrop:bg-ink/40">
                                        <form method="POST" action="{{ route('admin.sessions.attempts.reset', [$session, $attempt]) }}"
                                            class="p-5 sm:p-6" x-data="{ resetMode: 'fresh' }" data-progress-message="Membuka ulang attempt peserta...">
                                            @csrf
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="min-w-0">
                                                    <h3 class="text-xl font-bold">Ulangi attempt {{ $attempt->user?->nama }}</h3>
                                                    <p class="mt-2 text-sm text-ink-muted">Pilih data yang tetap tersedia saat peserta masuk kembali.</p>
                                                </div>
                                                <button type="button" class="btn btn-ghost min-h-11 px-3" aria-label="Tutup dialog"
                                                    x-on:click="document.getElementById('reset-attempt-{{ $attempt->id }}').close()">Tutup</button>
                                            </div>

                                            <div class="mt-5 space-y-3">
                                                <label class="block rounded-xl border-2 border-outline p-4 has-[:checked]:border-coral">
                                                    <span class="flex items-start gap-3">
                                                        <input type="radio" name="reset_mode" value="fresh" x-model="resetMode" class="mt-1 h-5 w-5" checked>
                                                        <span><span class="block font-bold">Mulai dari awal</span><span class="mt-1 block text-sm text-ink-muted">Hapus jawaban, urutan soal, penanda, dan log pelanggaran.</span></span>
                                                    </span>
                                                </label>
                                                <label class="block rounded-xl border-2 border-outline p-4 has-[:checked]:border-brand {{ $session->mode !== 'acak' || $session->status !== 'berjalan' ? 'opacity-60' : '' }}">
                                                    <span class="flex items-start gap-3">
                                                        <input type="radio" name="reset_mode" value="resume" x-model="resetMode" class="mt-1 h-5 w-5"
                                                            @disabled($session->mode !== 'acak' || $session->status !== 'berjalan')>
                                                        <span><span class="block font-bold">Lanjut dari jawaban terakhir</span><span class="mt-1 block text-sm text-ink-muted">Pertahankan jawaban, urutan soal, opsi, dan penanda. Nilai dihitung ulang.</span></span>
                                                    </span>
                                                </label>
                                            </div>

                                            <div x-show="resetMode === 'resume'" x-cloak class="mt-4">
                                                <label for="retry-duration-{{ $attempt->id }}" class="label">Durasi baru (menit)</label>
                                                <input id="retry-duration-{{ $attempt->id }}" name="retry_duration_minutes" type="number" min="1" max="600" value="60" class="input"
                                                    x-bind:required="resetMode === 'resume'" x-bind:disabled="resetMode !== 'resume'">
                                                <p class="help mt-2">Waktu mulai saat peserta menekan Mulai. Jadwal selesai server tetap menjadi batas akhir.</p>
                                            </div>

                                            <label x-show="resetMode === 'fresh'" class="mt-4 flex items-start gap-3 rounded-xl bg-coral-soft p-4">
                                                <input type="checkbox" name="konfirmasi" value="HAPUS" class="mt-1 h-5 w-5"
                                                    x-bind:required="resetMode === 'fresh'" x-bind:disabled="resetMode !== 'fresh'">
                                                <span class="text-sm font-bold text-coral-deep">Saya memahami seluruh pekerjaan attempt ini akan dihapus.</span>
                                            </label>

                                            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                                <button type="button" class="btn btn-secondary" x-on:click="document.getElementById('reset-attempt-{{ $attempt->id }}').close()">Batal</button>
                                                <button type="submit" class="btn btn-primary">Buka ulang attempt</button>
                                            </div>
                                        </form>
                                    </dialog>
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
