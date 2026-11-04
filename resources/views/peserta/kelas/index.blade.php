<x-layouts.peserta judul="Kelas" keterangan="Pilih kelas untuk masuk. Kelas yang belum kamu ikuti meminta PIN dari admin.">
    @if ($kelas->isEmpty())
        <x-empty-state
            judul="Belum ada kelas"
            keterangan="Tunggu admin membuat kelas. Daftar kelas akan muncul di halaman ini."
        />
    @else
        <div class="grid gap-5 md:grid-cols-2">
            @foreach ($kelas as $k)
                @php $ikut = in_array($k->id, $diikuti, true); @endphp

                <article class="card card-hover flex flex-col">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($ikut)
                            <span class="pill pill-leaf">Sudah bergabung</span>
                        @else
                            <span class="pill pill-sun">Perlu PIN</span>
                        @endif
                        <span class="pill pill-neutral">{{ $k->quizzes_count }} kuis</span>
                        <span class="pill pill-neutral">{{ $k->sessions_count }} sesi</span>
                    </div>

                    <h2 class="mt-3 text-2xl">{{ $k->nama }}</h2>

                    <div class="mt-auto pt-5">
                        <a href="{{ route('peserta.kelas.show', $k) }}" class="btn btn-primary btn-anak w-full">
                            {{ $ikut ? 'Masuk kelas' : 'Pilih kelas' }}
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.peserta>
