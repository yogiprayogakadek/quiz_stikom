<x-layouts.admin judul="Peserta" keterangan="Kelola akun peserta untuk masuk ke kuis.">
    <x-slot:aksi>
        <a href="{{ route('admin.participants.template') }}" class="btn btn-secondary">
            <x-icon name="download" :size="20" />
            Contoh CSV
        </a>
        <a href="{{ route('admin.participants.import') }}" class="btn btn-secondary">
            <x-icon name="upload" :size="20" />
            Impor CSV
        </a>
        <a href="{{ route('admin.participants.create') }}" class="btn btn-primary">
            <x-icon name="plus" :size="20" />
            Tambah peserta
        </a>
    </x-slot:aksi>

    @if ($participants->isEmpty())
        <x-empty-state
            class="mt-6"
            :judul="$cari !== '' ? 'Peserta tidak ditemukan' : 'Belum ada peserta'"
            :keterangan="$cari !== '' ? 'Coba kata kunci lain atau tambahkan peserta baru.' : 'Tambahkan satu per satu atau impor daftar kelas dari CSV.'"
        >
            <a href="{{ route('admin.participants.create') }}" class="btn btn-primary mt-2">Tambah peserta pertama</a>
        </x-empty-state>
    @else
        <div class="card shadow-xs border border-slate-200/90 bg-white">
            <x-data-table
                search-placeholder="Cari nama atau username peserta..."
                :per-page="10"
                :per-page-options="[10, 25, 50, 100]"
                empty-message="Tidak ada peserta yang cocok dengan pencarian."
            >
                <div class="overflow-x-auto rounded-xl border border-slate-200/90">
                    <table class="table">
                        <caption class="sr-only">Daftar peserta kuis</caption>
                        <thead>
                            <tr>
                                <th scope="col">Nama</th>
                                <th scope="col">Username</th>
                                <th scope="col">Attempt</th>
                                <th scope="col" class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($participants as $peserta)
                                <tr
                                    data-item
                                    data-search="{{ strtolower($peserta->nama . ' ' . $peserta->username) }}"
                                    class="transition hover:bg-slate-50/75"
                                >
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-700">
                                                {{ strtoupper(substr($peserta->nama, 0, 1)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-bold text-slate-900 text-sm sm:text-base leading-snug">
                                                    {{ $peserta->nama }}
                                                </p>
                                                @if (! $peserta->hasVerifiedEmail())
                                                    @if (($peserta->active_workspace_count ?? 0) > 1)
                                                        <span class="pill pill-neutral mt-1 text-[10px]">Perlu verifikasi master</span>
                                                    @else
                                                        <span class="pill pill-coral mt-1 text-[10px]">Belum verifikasi</span>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="font-mono text-xs sm:text-sm font-semibold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ $peserta->username }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="pill pill-neutral text-xs">
                                            {{ $peserta->attempts_count }} ujian
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap items-center justify-end gap-1.5">
                                            @if (! $peserta->hasVerifiedEmail() && ($peserta->active_workspace_count ?? 0) <= 1)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.participants.verify', $peserta) }}"
                                                    onsubmit="return confirm('Tandai email {{ $peserta->username }} terverifikasi?')"
                                                >
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="btn btn-ghost !py-1.5 !px-2.5 text-xs text-leaf-deep hover:bg-leaf-soft"
                                                        title="Tandai email terverifikasi"
                                                    >
                                                        Verifikasi
                                                    </button>
                                                </form>
                                            @endif
                                            <a
                                                href="{{ route('admin.participants.edit', $peserta) }}"
                                                class="btn btn-secondary !py-1.5 !px-3 text-xs"
                                            >
                                                Edit
                                            </a>
                                            @if (($peserta->active_workspace_count ?? 0) <= 1)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.participants.reset-password', $peserta) }}"
                                                    onsubmit="return confirm('Reset password {{ $peserta->username }}? Password lama tidak berlaku lagi.')"
                                                >
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="btn btn-ghost !py-1.5 !px-2.5 text-xs text-amber-700 hover:bg-amber-50"
                                                        title="Reset password ke acak"
                                                    >
                                                        Reset Pass
                                                    </button>
                                                </form>
                                            @endif
                                            <form
                                                method="POST"
                                                action="{{ route('admin.participants.destroy', $peserta) }}"
                                                onsubmit="return confirm('Hapus peserta {{ $peserta->username }}? Tindakan ini tidak bisa dibatalkan.')"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="btn btn-ghost !py-1.5 !px-2.5 text-xs text-rose-600 hover:bg-rose-50"
                                                    title="Hapus peserta"
                                                >
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-data-table>
        </div>
    @endif
</x-layouts.admin>
