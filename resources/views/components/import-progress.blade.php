<div
    x-data="{
        uploading: false,
        progress: 0,
        stage: 'Membaca dan memvalidasi file...',
        fileName: '',
        fileSizeText: '',
        timer: null,

        handleFileSelect(e) {
            const file = e.target.files[0];
            if (file) {
                this.fileName = file.name;
                const kb = (file.size / 1024).toFixed(1);
                this.fileSizeText = kb > 1024 ? (kb / 1024).toFixed(2) + ' MB' : kb + ' KB';
            } else {
                this.fileName = '';
                this.fileSizeText = '';
            }
        },

        handleSubmit(e) {
            const form = e.target;
            const fileInput = form.querySelector('input[type=file]');
            if (!fileInput || !fileInput.files.length) {
                return;
            }

            this.uploading = true;
            this.progress = 8;
            this.stage = 'Mengunggah file ke server...';

            const stages = [
                { at: 25, text: 'Memeriksa format & header kolom...' },
                { at: 55, text: 'Memproses baris data & validasi...' },
                { at: 85, text: 'Menyimpan data ke database...' },
                { at: 96, text: 'Menyiapkan ringkasan hasil...' }
            ];

            let step = 0;
            this.timer = setInterval(() => {
                if (this.progress < 95) {
                    this.progress += Math.floor(Math.random() * 8) + 3;
                    if (this.progress > 95) this.progress = 95;

                    for (const s of stages) {
                        if (this.progress >= s.at) {
                            this.stage = s.text;
                        }
                    }
                }
            }, 250);
        }
    }"
    class="relative"
>
    <!-- Overlay Modal Progression Animasi -->
    <div
        x-show="uploading"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 backdrop-blur-none"
        x-transition:enter-end="opacity-100 backdrop-blur-sm"
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="progress-title"
    >
        <div
            x-show="uploading"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            class="w-full max-w-md rounded-panel bg-page p-6 sm:p-8 shadow-modal border border-outline"
        >
            <div class="flex items-center gap-4">
                <div class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-brand-soft text-brand-deep">
                    <x-icon name="upload" :size="28" class="animate-bounce" />
                    <span class="absolute -top-1 -right-1 flex h-4 w-4">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-bright opacity-75"></span>
                        <span class="relative inline-flex h-4 w-4 rounded-full bg-brand"></span>
                    </span>
                </div>
                <div>
                    <h3 id="progress-title" class="text-xl font-bold text-ink">Mengimpor Data</h3>
                    <p class="text-sm text-ink-muted truncate max-w-[220px]" x-text="fileName ? fileName : 'Memproses file...'"></p>
                </div>
            </div>

            <!-- Progression Bar -->
            <div class="mt-6">
                <div class="flex items-center justify-between text-sm font-bold text-ink mb-2">
                    <span class="flex items-center gap-1.5 text-brand-deep">
                        <span class="inline-block h-2 w-2 rounded-full bg-brand animate-pulse"></span>
                        <span x-text="stage"></span>
                    </span>
                    <span class="font-mono text-base text-ink" x-text="progress + '%'"></span>
                </div>

                <div class="h-3.5 w-full overflow-hidden rounded-full bg-surface-sunken p-0.5 border border-outline">
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-brand to-brand-bright transition-all duration-300 ease-out relative overflow-hidden"
                        :style="'width: ' + progress + '%'"
                    >
                        <!-- Shimmer highlight line -->
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/40 to-transparent animate-[shimmer_1.5s_infinite] -skew-x-12"></div>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between text-xs text-ink-muted">
                <span x-text="fileSizeText ? 'Ukuran: ' + fileSizeText : 'Mohon tunggu'"></span>
                <span>Jangan tutup halaman ini</span>
            </div>
        </div>
    </div>

    {{ $slot }}
</div>
