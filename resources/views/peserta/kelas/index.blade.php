<x-layouts.peserta judul="Kelas" keterangan="Pilih kelas untuk masuk. Kelas yang belum kamu ikuti meminta PIN dari admin.">
    @if ($kelas->isEmpty())
        <x-empty-state
            judul="Belum ada kelas"
            keterangan="Tunggu admin membuat kelas. Daftar kelas akan muncul di halaman ini."
        />
    @else
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($kelas as $k)
                @php $ikut = in_array($k->id, $diikuti, true); @endphp

                <article class="card-hover flex flex-col overflow-hidden rounded-2xl border border-outline bg-page">
                    <x-class-cover :kelas="$k" :show-title="true" />
                    <div class="flex flex-1 flex-col p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($ikut)
                            <span class="pill pill-leaf">Sudah bergabung</span>
                        @elseif ($appSettings->allow_pin_enrollment)
                            <span class="pill pill-sun">Perlu PIN</span>
                        @else
                            <span class="pill pill-neutral">Belum terdaftar</span>
                        @endif
                        <span class="pill pill-neutral">{{ $k->quizzes_count }} kuis</span>
                        <span class="pill pill-neutral">{{ $k->sessions_count }} sesi</span>
                    </div>

                    <div class="mt-auto pt-5">
                        <a href="{{ route('peserta.kelas.show', $k) }}" class="btn btn-primary btn-anak w-full">
                            {{ $ikut ? 'Buka kelas' : ($appSettings->allow_pin_enrollment ? 'Masukkan PIN' : 'Lihat detail') }}
                        </a>
                    </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.peserta>
