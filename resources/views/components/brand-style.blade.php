@php
    $organizationId = app(\App\Support\OrganizationContext::class)->id();
    $fontLibrary = app(\App\Services\FontLibrary::class);
    $fontUtama = $fontLibrary->resolve($appSettings->font_utama_id, $organizationId);
    $fontJudul = $fontLibrary->resolve($appSettings->font_judul_id, $organizationId);
@endphp
<style>
    @if ($fontUtama)
        @font-face {
            font-family: '{{ $fontUtama->cssFamily() }}';
            src: url('{{ $fontUtama->url() }}') format('{{ $fontUtama->formatCss() }}');
            font-weight: {{ $fontUtama->berat }};
            font-style: {{ $fontUtama->gaya }};
            font-display: swap;
        }
    @endif
    @if ($fontJudul && $fontJudul->id !== $fontUtama?->id)
        @font-face {
            font-family: '{{ $fontJudul->cssFamily() }}';
            src: url('{{ $fontJudul->url() }}') format('{{ $fontJudul->formatCss() }}');
            font-weight: {{ $fontJudul->berat }};
            font-style: {{ $fontJudul->gaya }};
            font-display: swap;
        }
    @endif

    :root {
        --color-brand: {{ $appSettings->primary_color }};
        --color-brand-bright: color-mix(in srgb, {{ $appSettings->primary_color }} 76%, white);
        --color-brand-deep: color-mix(in srgb, {{ $appSettings->primary_color }} 72%, #0f172a);
        --color-brand-soft: color-mix(in srgb, {{ $appSettings->primary_color }} 15%, white);
        --color-blue-50: color-mix(in srgb, {{ $appSettings->primary_color }} 6%, white);
        --color-blue-100: color-mix(in srgb, {{ $appSettings->primary_color }} 14%, white);
        --color-blue-200: color-mix(in srgb, {{ $appSettings->primary_color }} 26%, white);
        --color-blue-500: color-mix(in srgb, {{ $appSettings->primary_color }} 82%, #0f172a);
        --color-blue-600: color-mix(in srgb, {{ $appSettings->primary_color }} 72%, #0f172a);
        --color-blue-700: color-mix(in srgb, {{ $appSettings->primary_color }} 60%, #0f172a);
        --color-blue-800: color-mix(in srgb, {{ $appSettings->primary_color }} 48%, #0f172a);
        --color-blue-900: color-mix(in srgb, {{ $appSettings->primary_color }} 36%, #0f172a);
        @if ($fontUtama)
            --font-sans: '{{ $fontUtama->cssFamily() }}', 'Atkinson Hyperlegible', ui-sans-serif, system-ui, sans-serif;
        @endif
        @if ($fontJudul)
            --font-display: '{{ $fontJudul->cssFamily() }}', 'Hubot Sans Variable', ui-sans-serif, sans-serif;
        @endif
    }
</style>
