<div
    id="global-progress"
    class="fixed inset-0 z-[10000] hidden items-center justify-center bg-slate-950/35 p-4 backdrop-blur-[2px]"
    role="status"
    aria-live="assertive"
    aria-busy="true"
>
    <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-modal">
        <div class="flex items-center gap-4">
            <span class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand-deep" aria-hidden="true">
                <span class="h-5 w-5 animate-spin rounded-full border-2 border-brand/25 border-t-brand"></span>
            </span>
            <div class="min-w-0">
                <p id="global-progress-title" class="font-display text-base font-extrabold text-slate-900">Menyimpan perubahan...</p>
                <p class="mt-0.5 text-sm text-slate-600">Jangan tutup atau muat ulang halaman ini.</p>
            </div>
        </div>
        <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-brand-soft" aria-hidden="true">
            <span class="global-progress-bar block h-full w-1/3 rounded-full bg-brand"></span>
        </div>
    </div>
</div>
