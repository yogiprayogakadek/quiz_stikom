<x-layouts.guest judul="Maintenance">
    <main class="flex min-h-screen items-center justify-center bg-surface px-5 py-12">
        <section class="w-full max-w-xl text-center" aria-labelledby="maintenance-title">
            <img src="{{ $appSettings->logoUrl() }}" alt="Logo {{ $appSettings->site_name }}" class="mx-auto h-16 w-16 object-contain">
            <h1 id="maintenance-title" class="mt-8 font-display text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">
                Sistem sedang dalam pemeliharaan
            </h1>
            <p class="mx-auto mt-4 max-w-prose text-base leading-7 text-ink-muted">
                {{ $maintenance->maintenance_message }}
            </p>
            <div class="mt-8 border-t border-outline pt-5 text-sm text-ink-subtle">
                Tidak perlu menyegarkan halaman berulang kali. Silakan coba kembali beberapa saat lagi.
            </div>
        </section>
    </main>
</x-layouts.guest>
