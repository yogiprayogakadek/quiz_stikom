@php
    $ruteIndex = isset($quiz) ? route('admin.quizzes.questions.index', $quiz) : route('admin.quizzes.index');
    $ruteImporLain = isset($quiz) ? route('admin.quizzes.questions.import', $quiz) : route('admin.quizzes.index');
@endphp

<x-layouts.admin judul="Ringkasan impor soal" keterangan="Periksa hasil impor soal CSV di bawah ini.">
    <x-slot:aksi>
        <a href="{{ $ruteIndex }}" class="btn btn-secondary">Lihat soal kuis</a>
        <a href="{{ $ruteImporLain }}" class="btn btn-ghost">Impor file lain</a>
    </x-slot:aksi>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="card border-leaf bg-leaf-soft">
            <p class="text-base font-bold text-leaf-deep">Berhasil diimpor</p>
            <p class="display-number mt-2 text-5xl text-ink">{{ $ringkasan['berhasil'] }}</p>
            @if (!empty($ringkasan['quiz_nama']))
                <p class="mt-2 text-sm text-leaf-deep">
                    Ditautkan ke kuis: <strong>{{ $ringkasan['quiz_nama'] }}</strong>
                </p>
            @endif
        </div>
        <div class="card border-coral bg-coral-soft">
            <p class="text-base font-bold text-coral-deep">Dilewati / Gagal</p>
            <p class="display-number mt-2 text-5xl text-ink">{{ $ringkasan['gagal'] }}</p>
            @if ($ringkasan['gagal'] > 0)
                <p class="mt-2 text-sm text-coral-deep">Periksa rincian alasan kegagalan pada tabel di bawah.</p>
            @endif
        </div>
    </div>

    <div class="mt-8">
        <h2 class="text-2xl">Rincian per baris</h2>

        @if (count($ringkasan['baris']) === 0)
            <p class="mt-3 text-base text-ink-muted">File tidak berisi baris data.</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-card border border-outline bg-page">
                <table class="table">
                    <caption class="sr-only">Rincian hasil impor soal per baris</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="w-20">Baris</th>
                            <th scope="col">Teks Soal</th>
                            <th scope="col" class="w-32">Status</th>
                            <th scope="col">Keterangan / Alasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ringkasan['baris'] as $baris)
                            <tr>
                                <td class="font-mono text-ink-muted">{{ $baris['baris'] }}</td>
                                <td class="font-bold text-ink">{{ $baris['teks'] }}</td>
                                <td>
                                    @if ($baris['status'] === 'berhasil')
                                        <span class="pill pill-leaf">Berhasil</span>
                                    @else
                                        <span class="pill pill-coral">Dilewati</span>
                                    @endif
                                </td>
                                <td class="text-sm text-ink-muted">{{ $baris['alasan'] ?? 'Tersimpan ke kuis.' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.admin>
