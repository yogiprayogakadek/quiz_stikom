<style>
    :root {
        --color-brand: {{ $appSettings->primary_color }};
        --color-brand-bright: color-mix(in srgb, {{ $appSettings->primary_color }} 76%, white);
        --color-brand-deep: color-mix(in srgb, {{ $appSettings->primary_color }} 72%, #0f172a);
        --color-brand-deeper: color-mix(in srgb, {{ $appSettings->primary_color }} 58%, #0f172a);
        --color-brand-soft: color-mix(in srgb, {{ $appSettings->primary_color }} 15%, white);
        --color-blue-50: color-mix(in srgb, {{ $appSettings->primary_color }} 6%, white);
        --color-blue-100: color-mix(in srgb, {{ $appSettings->primary_color }} 14%, white);
        --color-blue-200: color-mix(in srgb, {{ $appSettings->primary_color }} 26%, white);
        --color-blue-500: color-mix(in srgb, {{ $appSettings->primary_color }} 82%, #0f172a);
        --color-blue-600: color-mix(in srgb, {{ $appSettings->primary_color }} 72%, #0f172a);
        --color-blue-700: color-mix(in srgb, {{ $appSettings->primary_color }} 60%, #0f172a);
        --color-blue-800: color-mix(in srgb, {{ $appSettings->primary_color }} 48%, #0f172a);
        --color-blue-900: color-mix(in srgb, {{ $appSettings->primary_color }} 36%, #0f172a);
    }
</style>
