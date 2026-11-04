@props(['name', 'size' => 24])

<svg
    xmlns="http://www.w3.org/2000/svg"
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    {{ $attributes }}
>
    @switch($name)
        @case('home')
            <path d="M4 10.5 12 4l8 6.5" />
            <path d="M6.5 9.5V20h11V9.5" />
            <path d="M10 20v-5h4v5" />
            @break
        @case('users')
            <circle cx="9" cy="8" r="3.2" />
            <path d="M3.5 19c.6-3.2 2.7-5 5.5-5s4.9 1.8 5.5 5" />
            <path d="M15.5 5.6a3 3 0 0 1 0 5.8" />
            <path d="M17 14.5c2 .6 3.2 2.2 3.5 4.5" />
            @break
        @case('pencil')
            <path d="m4 20 1-4.5L16.5 4a2.1 2.1 0 0 1 3 3L8 18.5Z" />
            <path d="m14.5 6.5 3 3" />
            @break
        @case('book')
            <path d="M12 6.5C10.5 5.3 8.4 4.8 5 5v13c3.4-.2 5.5.3 7 1.5 1.5-1.2 3.6-1.7 7-1.5V5c-3.4-.2-5.5.3-7 1.5Z" />
            <path d="M12 6.5V19" />
            @break
        @case('play')
            <path d="M8 5.5 19 12 8 18.5Z" />
            @break
        @case('chart')
            <path d="M4 20h16" />
            <path d="M7 20v-6" />
            <path d="M12 20V9" />
            <path d="M17 20V5" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('arrow-left')
            <path d="M19 12H5" />
            <path d="m11 6-6 6 6 6" />
            @break
        @case('download')
            <path d="M12 4v11" />
            <path d="m7 10 5 5 5-5" />
            <path d="M5 20h14" />
            @break
        @case('upload')
            <path d="M12 20V9" />
            <path d="m7 14 5-5 5 5" />
            <path d="M5 4h14" />
            @break
        @case('check')
            <path d="m5 12.5 4.5 4.5L19 7" />
            @break
        @case('x')
            <path d="M6 6l12 12M18 6 6 18" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="8.5" />
            <path d="M12 7.5V12l3 2" />
            @break
        @case('alert')
            <path d="M12 4 3.5 19h17Z" />
            <path d="M12 9v4.5" />
            <path d="M12 16.5h.01" />
            @break
        @case('lock')
            <rect x="5" y="10.5" width="14" height="9.5" rx="2" />
            <path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5" />
            @break
        @case('logout')
            <path d="M14 5h4a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-4" />
            <path d="M10 8l-4 4 4 4" />
            <path d="M6 12h9" />
            @break
        @case('copy')
            <rect x="9" y="9" width="10" height="10" rx="2" />
            <path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" />
            @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" />
            @break
        @case('trophy')
            <path d="M8 4h8v4a4 4 0 0 1-8 0Z" />
            <path d="M8 6H5v1.5A3.5 3.5 0 0 0 8.5 11" />
            <path d="M16 6h3v1.5A3.5 3.5 0 0 1 15.5 11" />
            <path d="M12 12v4" />
            <path d="M8.5 20h7" />
            <path d="M10 20v-2h4v2" />
            @break
        @case('eye')
            <path d="M3 12s3.5-6 9-6 9 6 9 6-3.5 6-9 6-9-6-9-6Z" />
            <circle cx="12" cy="12" r="2.5" />
            @break
        @case('flag')
            <path d="M6 21V4" />
            <path d="M6 5h10l-2 3.5L16 12H6" />
            @break
        @case('star')
            <path d="m12 4 2.3 4.8 5.2.7-3.8 3.7.9 5.2-4.6-2.5-4.6 2.5.9-5.2L4.5 9.5l5.2-.7Z" />
            @break
        @case('kelas')
            <path d="M3 8.5 12 5l9 3.5-9 3.5-9-3.5Z" />
            <path d="M7 10.8V16c0 1.4 2.2 2.5 5 2.5s5-1.1 5-2.5v-5.2" />
            <path d="M21 8.5V14" />
            @break
        @default
            <circle cx="12" cy="12" r="8" />
    @endswitch
</svg>
