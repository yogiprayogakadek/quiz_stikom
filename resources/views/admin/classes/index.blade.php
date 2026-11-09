<x-layouts.admin judul="Kelas" keterangan="Kelompokkan peserta per kelas. Peserta bergabung dengan PIN, lalu sesi kelas hanya tampil untuk anggotanya.">
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-7 items-start w-full">
        {{-- KOLOM KIRI: FORM BUAT KELAS (Sticky di Desktop) --}}
        <div class="xl:col-span-4 w-full">
            <div class="card xl:sticky xl:top-6 shadow-xs border border-slate-200/90 bg-white">
                <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-[#1e6ffb]">
                        <x-icon name="plus" :size="20" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 leading-tight">Buat Kelas Baru</h2>
                        <p class="text-xs text-slate-500">Tambahkan kelas dan bagikan PIN</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.classes.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="nama" class="label text-sm">Nama kelas</label>
                        <input
                            id="nama"
                            name="nama"
                            type="text"
                            class="input text-sm"
                            value="{{ old('nama') }}"
                            required
                            maxlength="120"
                            placeholder="Contoh: XII IPA 1 atau Pemrograman Web"
                        >
                    </div>

                    <div>
                        <label for="pin" class="label text-sm">PIN kelas</label>
                        <input
                            id="pin"
                            name="pin"
                            type="text"
                            class="input text-sm font-mono tracking-wider uppercase"
                            value="{{ old('pin') }}"
                            required
                            minlength="4"
                            maxlength="12"
                            autocomplete="off"
                            placeholder="Contoh: IPA12"
                        >
                        <p class="mt-1.5 text-xs text-slate-500 leading-relaxed">
                            4 sampai 12 karakter alfanumerik. Peserta memasukkan PIN ini untuk bergabung.
                        </p>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn btn-primary w-full justify-center text-sm py-2.5">
                            <x-icon name="plus" :size="18" />
                            <span>Simpan & Buat Kelas</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- KOLOM KANAN: DATA TABLE & DAFTAR KELAS --}}
        <div class="xl:col-span-8 w-full min-w-0" x-data="{ tampilan: 'tabel' }">
            @if ($kelas->isEmpty())
                <x-empty-state
                    judul="Belum ada kelas"
                    keterangan="Buat kelas pertama menggunakan formulir di sebelah kiri, lalu atur peserta dan kuis."
                />
            @else
                <div class="card shadow-xs border border-slate-200/90 bg-white">
                    <x-data-table
                        search-placeholder="Cari nama kelas atau PIN..."
                        :per-page="10"
                        :per-page-options="[5, 10, 20, 50]"
                        empty-message="Tidak ada kelas yang cocok dengan pencarian."
                    >
                        <x-slot:toolbarAksi>
                            {{-- View Switcher: Tabel / Kartu Grid --}}
                            <div class="inline-flex items-center rounded-xl bg-slate-100 p-1 border border-slate-200/80 text-xs font-bold text-slate-600">
                                <button
                                    type="button"
                                    @click="tampilan = 'tabel'"
                                    :class="tampilan === 'tabel' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
                                    class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 transition"
                                    title="Tampilan Tabel"
                                >
                                    <x-icon name="menu" :size="15" />
                                    <span>Tabel</span>
                                </button>
                                <button
                                    type="button"
                                    @click="tampilan = 'kartu'"
                                    :class="tampilan === 'kartu' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
                                    class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 transition"
                                    title="Tampilan Kartu"
                                >
                                    <x-icon name="home" :size="15" />
                                    <span>Kartu</span>
                                </button>
                            </div>
                        </x-slot:toolbarAksi>

                        {{-- TAMPILAN 1: DATA TABLE VIEW --}}
                        <div x-show="tampilan === 'tabel'" class="overflow-x-auto rounded-xl border border-slate-200/90">
                            <table class="table">
                                <caption class="sr-only">Daftar kelas terdaftar</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Nama Kelas & PIN</th>
                                        <th scope="col">Peserta</th>
                                        <th scope="col">Kuis & Sesi</th>
                                        <th scope="col" class="text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($kelas as $k)
                                        <tr
                                            data-item
                                            data-search="{{ strtolower($k->nama . ' ' . $k->pin) }}"
                                            class="transition hover:bg-slate-50/75"
                                        >
                                            <td>
                                                <div class="min-w-0 py-0.5">
                                                    <p class="font-bold text-slate-900 text-sm sm:text-base leading-snug">
                                                        {{ $k->nama }}
                                                    </p>
                                                    <div class="mt-1 flex items-center gap-1.5">
                                                        <span class="inline-flex items-center gap-1 font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200">
                                                            PIN: {{ $k->pin }}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            onclick="salinTeks('{{ $k->pin }}', this)"
                                                            class="text-[11px] font-semibold text-blue-600 hover:text-blue-800 transition"
                                                            title="Salin PIN"
                                                        >
                                                            Salin
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="pill pill-brand text-xs font-bold">
                                                    {{ $k->peserta_count }} peserta
                                                </span>
                                            </td>
                                            <td>
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="pill pill-neutral text-xs">
                                                        {{ $k->quizzes_count }} kuis
                                                    </span>
                                                    <span class="pill {{ $k->sessions_count > 0 ? 'pill-leaf' : 'pill-neutral' }} text-xs">
                                                        {{ $k->sessions_count }} sesi
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <a
                                                        href="{{ route('admin.classes.edit', $k) }}"
                                                        class="btn btn-secondary !py-1.5 !px-3 text-xs"
                                                    >
                                                        Kelola
                                                    </a>
                                                    @if ($k->sessions_count === 0)
                                                        <form
                                                            method="POST"
                                                            action="{{ route('admin.classes.destroy', $k) }}"
                                                            onsubmit="return confirm('Hapus kelas {{ $k->nama }}?')"
                                                        >
                                                            @csrf
                                                            @method('DELETE')
                                                            <button
                                                                type="submit"
                                                                class="btn btn-ghost !py-1.5 !px-2.5 text-xs text-rose-600 hover:bg-rose-50"
                                                                title="Hapus kelas"
                                                            >
                                                                Hapus
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span
                                                            class="text-[11px] font-medium text-slate-400 px-2"
                                                            title="Kelas yang memiliki sesi tidak bisa dihapus"
                                                        >
                                                            Terkunci
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- TAMPILAN 2: BENTO CARDS VIEW (Responsif, Anti-Terpotong) --}}
                        <div
                            x-show="tampilan === 'kartu'"
                            x-cloak
                            class="grid grid-cols-1 md:grid-cols-2 gap-4"
                        >
                            @foreach ($kelas as $k)
                                <article
                                    data-item
                                    data-search="{{ strtolower($k->nama . ' ' . $k->pin) }}"
                                    class="card card-hover flex flex-col justify-between !p-5 border border-slate-200/90 rounded-2xl bg-white shadow-xs min-w-0"
                                >
                                    <div>
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="pill pill-brand text-xs">{{ $k->peserta_count }} peserta</span>
                                            <span class="pill pill-neutral text-xs">{{ $k->quizzes_count }} kuis</span>
                                            <span class="pill {{ $k->sessions_count > 0 ? 'pill-leaf' : 'pill-neutral' }} text-xs">{{ $k->sessions_count }} sesi</span>
                                        </div>

                                        <h3 class="mt-3 text-lg font-bold text-slate-900 leading-snug break-words">
                                            {{ $k->nama }}
                                        </h3>
                                        <div class="mt-2 flex items-center gap-2">
                                            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200">
                                                PIN: {{ $k->pin }}
                                            </span>
                                            <button
                                                type="button"
                                                onclick="salinTeks('{{ $k->pin }}', this)"
                                                class="text-xs font-semibold text-blue-600 hover:text-blue-800"
                                            >
                                                Salin
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-5 pt-3.5 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                                        <a href="{{ route('admin.classes.edit', $k) }}" class="btn btn-secondary !py-1.5 !px-3 text-xs">
                                            Kelola kelas
                                        </a>

                                        @if ($k->sessions_count === 0)
                                            <form method="POST" action="{{ route('admin.classes.destroy', $k) }}" onsubmit="return confirm('Hapus kelas {{ $k->nama }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-ghost !py-1.5 !px-2.5 text-xs text-rose-600 hover:bg-rose-50">
                                                    Hapus
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-slate-400">Punya sesi ujian</span>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </x-data-table>
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
