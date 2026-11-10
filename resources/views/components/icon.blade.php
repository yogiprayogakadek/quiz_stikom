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
        @case('eye-off')
            <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
            <path d="M9.9 4.3A10.9 10.9 0 0 1 12 4c5.5 0 9 6 9 6a18 18 0 0 1-4.1 4.7" />
            <path d="M14.1 19.4A10.9 10.9 0 0 1 12 20c-5.5 0-9-6-9-6a18 18 0 0 1 4.8-5" />
            <path d="m3 3 18 18" />
            @break
        @case('user')
            <circle cx="12" cy="8" r="4" />
            <path d="M5 20c0-3.5 3.1-6 7-6s7 2.5 7 6" />
            @break
        @case('shield-check')
            <path d="M12 3s7 2.5 7 8c0 5-4.5 8.5-7 10-2.5-1.5-7-5-7-10 0-5.5 7-8 7-8Z" />
            <path d="m9 11.5 2 2 4-4" />
            @break
        @case('zap')
            <path d="M13 2 4 14h7l-2 8 11-12h-7Z" />
            @break
        @case('flag')
            <path d="M6 21V4" />
            <path d="M6 5h10l-2 3.5L16 12H6" />
            @break
        @case('printer')
            <path d="M6 9V2h12v7" />
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
            <path d="M6 14h12v8H6z" />
            @break
        @case('star')
            <path d="m12 4 2.3 4.8 5.2.7-3.8 3.7.9 5.2-4.6-2.5-4.6 2.5.9-5.2L4.5 9.5l5.2-.7Z" />
            @break
        @case('kelas')
            <path d="M3 8.5 12 5l9 3.5-9 3.5-9-3.5Z" />
            <path d="M7 10.8V16c0 1.4 2.2 2.5 5 2.5s5-1.1 5-2.5v-5.2" />
            <path d="M21 8.5V14" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.3-4.3" />
            @break
        @case('flame')
            <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 3.5z" />
            @break
        @case('diamond')
            <path d="M2.7 10.5 12 2l9.3 8.5L12 22l-9.3-11.5Z" />
            <path d="M2.7 10.5h18.6" />
            <path d="M12 2v20" />
            @break
        @case('crown')
            <path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7Z" />
            <circle cx="12" cy="19" r="1" />
            @break
        @case('wifi')
            <path d="M12 20h.01" />
            <path d="M8.5 16.429a5 5 0 0 1 7 0" />
            <path d="M5 12.857a10 10 0 0 1 14 0" />
            <path d="M1.5 9.286a15 15 0 0 1 21 0" />
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z" />
            @break
        @case('sparkles')
            <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z" />
            @break
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="m3 7 9 6 9-6" />
            @break
        @case('server')
            <rect x="3" y="4" width="18" height="7" rx="2" />
            <rect x="3" y="13" width="18" height="7" rx="2" />
            <path d="M7 7.5h.01M7 16.5h.01" />
            @break
        @default
            <circle cx="12" cy="12" r="8" />
    @endswitch
</svg>
