<x-layouts.admin :judul="'Generate Soal AI: ' . $quiz->judul" keterangan="Soal dibuat di latar belakang. Halaman ini boleh ditutup; hasil tetap tersimpan.">
    <x-slot:aksi>
        <a href="{{ route('admin.quizzes.questions.index', $quiz) }}" class="btn btn-ghost">
            <x-icon name="arrow-left" :size="18" />
            <span>Kembali ke Soal</span>
        </a>
    </x-slot:aksi>

    <div
        class="card max-w-3xl"
        id="ai-progress"
        data-state-url="{{ route('admin.quizzes.questions.ai.state', [$quiz, $generation]) }}"
        data-fallback-url="{{ route('admin.quizzes.questions.ai.form', $quiz) }}"
    >
        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                <x-icon name="sparkles" :size="24" />
            </div>
            <div>
                <h2 class="text-xl font-bold text-slate-900">AI sedang menyusun soal</h2>
                <p class="text-sm text-slate-500">
                    {{ $generation->params['jumlah_pg'] ?? 0 }} pilihan ganda ·
                    {{ $generation->params['jumlah_essay'] ?? 0 }} essay ·
                    target nilai {{ $generation->params['nilai_maksimal'] ?? 100 }}
                </p>
            </div>
        </div>

        <div class="mt-6">
            <div class="flex justify-between text-sm font-semibold text-slate-600">
                <span id="ai-status-teks">Menunggu antrean...</span>
                <span id="ai-status-jumlah">0 / {{ max(1, $generation->total) }}</span>
            </div>
            <div class="progress mt-2">
                <span id="ai-progress-fill" style="width: 0%"></span>
            </div>
        </div>

        <div id="ai-error" class="mt-6 hidden rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"></div>

        <p class="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
            Proses berjalan di queue worker. Jika status berhenti di "Menunggu antrean", pastikan
            <code class="font-mono text-xs">php artisan queue:work</code> aktif di server.
        </p>

        <div class="mt-6 flex justify-end">
            <a href="{{ route('admin.quizzes.questions.ai.form', $quiz) }}" class="btn btn-secondary">Batalkan</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const root = document.getElementById('ai-progress');
            if (!root) return;

            const stateUrl = root.dataset.stateUrl;
            const fallbackUrl = root.dataset.fallbackUrl;
            const statusText = document.getElementById('ai-status-teks');
            const statusCount = document.getElementById('ai-status-jumlah');
            const fill = document.getElementById('ai-progress-fill');
            const errorBox = document.getElementById('ai-error');

            const label = {
                pending: 'Menunggu antrean...',
                running: 'Menyusun soal...',
                selesai: 'Selesai. Membuka pratinjau...',
                gagal: 'Gagal membuat soal.',
            };

            const polling = setInterval(async () => {
                let res;
                try {
                    res = await fetch(stateUrl, { headers: { Accept: 'application/json' } });
                } catch (e) {
                    statusText.textContent = 'Koneksi terputus. Mencoba lagi...';
                    return;
                }

                if (!res.ok) return;

                const data = await res.json();
                const total = Math.max(1, data.total || 1);
                const persen = Math.min(100, Math.round((data.done / total) * 100));

                statusText.textContent = label[data.status] || data.status;
                statusCount.textContent = data.done + ' / ' + total;
                fill.style.width = persen + '%';

                if (data.status === 'selesai' && data.redirect) {
                    clearInterval(polling);
                    window.location.href = data.redirect;
                }

                if (data.status === 'gagal') {
                    clearInterval(polling);
                    errorBox.textContent = data.error || 'Layanan AI tidak mengembalikan hasil.';
                    errorBox.classList.remove('hidden');
                }
            }, 2000);
        });
    </script>
</x-layouts.admin>
