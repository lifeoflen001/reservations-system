@props(['name', 'size' => 18])

<svg {{ $attributes->merge(['class' => 'ui-icon', 'width' => $size, 'height' => $size]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('building') <path d="M4 21h16M6 21V5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v16M9 8h1m4 0h1M9 12h1m4 0h1M9 16h1m4 0h1M10 21v-3h4v3" /> @break
        @case('menu') <path d="M4 6h16M4 12h16M4 18h16" /> @break
        @case('search') <circle cx="11" cy="11" r="6.5" /><path d="m16 16 4 4" /> @break
        @case('calendar') <rect x="3.5" y="5" width="17" height="15" rx="1.5" /><path d="M7 3v4M17 3v4M3.5 9h17M8 13h.01M12 13h.01M16 13h.01M8 16h.01M12 16h.01" /> @break
        @case('moon') <path d="M20 15.2A8.5 8.5 0 0 1 8.8 4 8.5 8.5 0 1 0 20 15.2Z" /> @break
        @case('sun') <circle cx="12" cy="12" r="3.5" /><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42" /> @break
        @case('globe') <circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" /> @break
        @case('refresh') <path d="M20 11a8 8 0 0 0-14.7-4L3 10m0 0V5m0 5h5M4 13a8 8 0 0 0 14.7 4L21 14m0 0v5m0-5h-5" /> @break
        @case('fullscreen') <path d="M8 3H3v5M16 3h5v5M8 21H3v-5M21 16v5h-5" /> @break
        @case('fullscreen-exit') <path d="M9 3v6H3M15 3v6h6M9 21v-6H3M21 15h-6v6" /> @break
        @case('bell') <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /> @break
        @case('chevron-down') <path d="m6 9 6 6 6-6" /> @break
        @case('chevron-right') <path d="m9 18 6-6-6-6" /> @break
        @case('target') <circle cx="12" cy="12" r="7" /><circle cx="12" cy="12" r="2" /><path d="M12 2v3M12 19v3M2 12h3M19 12h3" /> @break
        @case('grid') <rect x="4" y="4" width="6" height="6" rx="1" /><rect x="14" y="4" width="6" height="6" rx="1" /><rect x="4" y="14" width="6" height="6" rx="1" /><rect x="14" y="14" width="6" height="6" rx="1" /> @break
        @case('users') <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM17 3.2a4 4 0 0 1 0 7.6M21 21v-2a4 4 0 0 0-3-3.87" /> @break
        @case('bed') <path d="M3 19v-8M3 15h18v4M6 11V7h5a4 4 0 0 1 4 4M3 19h18M18 11h3v4" /> @break
        @case('broom') <path d="m14 4 6 6M13 5l-8 8 6 6 8-8M5 19l-2 2M3 16l5 5" /> @break
        @case('wrench') <path d="M14.7 6.3a5 5 0 0 0-6.4 6.4L3 18l3 3 5.3-5.3a5 5 0 0 0 6.4-6.4L15 12l-3-3 2.7-2.7Z" /> @break
        @case('card') <rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 10h18M7 15h3" /> @break
        @case('currency') <circle cx="12" cy="12" r="9" /><path d="M14.5 9.5c-.5-.7-1.3-1-2.5-1-1.4 0-2.5.7-2.5 1.7 0 2.5 5 1.1 5 3.7 0 1-1.1 1.7-2.5 1.7-1.2 0-2-.3-2.5-1M12 7v10" /> @break
        @case('document') <path d="M6 3h8l4 4v14H6V3Z" /><path d="M14 3v5h5M9 12h6M9 16h6" /> @break
        @case('chart') <path d="M4 19V5M4 19h17M8 15l3-4 3 2 5-6" /> @break
        @case('cube') <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3ZM4.5 7.5 12 12l7.5-4.5M12 12v9" /> @break
        @case('plug') <path d="M9 7V3m6 4V3M7 7h10v3a5 5 0 0 1-10 0V7Zm5 8v6" /> @break
        @case('share') <circle cx="18" cy="5" r="2.5" /><circle cx="6" cy="12" r="2.5" /><circle cx="18" cy="19" r="2.5" /><path d="m8.2 10.8 7.6-4.6m-7.6 7 7.6 4.6" /> @break
        @case('settings') <path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z" /><path d="m19.4 15 .1.1a2 2 0 1 1-2.8 2.8l-.1-.1a2 2 0 0 0-3.4 1.4V19a2 2 0 1 1-4 0v-.2A2 2 0 0 0 5.8 17l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A2 2 0 0 0 1.6 11H2a2 2 0 1 1 0-4h.2A2 2 0 0 0 4 3.6l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A2 2 0 0 0 10.2 0H10a2 2 0 1 1 4 0h-.2a2 2 0 0 0 3.4.8l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A2 2 0 0 0 21.4 7h.2a2 2 0 1 1 0 4h-.2a2 2 0 0 0-2 4Z" transform="scale(.75) translate(4 4)" /> @break
        @case('plus') <path d="M12 5v14M5 12h14" /> @break
        @case('filter') <path d="M4 5h16l-6 7v5l-4 2v-7L4 5Z" /> @break
        @case('download') <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 20h16" /> @break
        @case('print') <path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M6 14h12v7H6v-7Z" /> @break
        @case('eye') <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" /><circle cx="12" cy="12" r="2.5" /> @break
        @case('eye-off') <path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.7 10.7 0 0 1 12 5c6.5 0 10 7 10 7a18.2 18.2 0 0 1-3.1 3.9M6.2 6.2C3.6 8 2 12 2 12s3.5 7 10 7c1.2 0 2.3-.2 3.3-.6" /> @break
        @case('edit') <path d="m4 16-.8 4.8L8 20l11.3-11.3a2.2 2.2 0 0 0-3.1-3.1L4 16Z" /><path d="m14.5 7.5 2 2" /> @break
        @case('trash') <path d="M4 7h16M10 11v6M14 11v6M6 7l1 14h10l1-14M9 7V4h6v3" /> @break
        @case('save') <path d="M5 3h12l2 2v16H5V3ZM8 3v6h7V3M8 21v-7h8v7" /> @break
        @case('logout') <path d="M10 17l5-5-5-5M15 12H3M21 19V5a2 2 0 0 0-2-2h-6" /> @break
        @case('key') <circle cx="8" cy="15" r="4" /><path d="m11 12 8-8m-2 2 2 2m-5 1 2 2" /> @break
        @case('folder') <path d="M3 6.5A2.5 2.5 0 0 1 5.5 4H10l2 2h6.5A2.5 2.5 0 0 1 21 8.5v8A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-10Z" /> @break
        @case('shield') <path d="M12 3 20 6v5c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-3Z" /><path d="m9 12 2 2 4-4" /> @break
        @case('history') <path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5M12 7v5l3 2" /> @break
        @case('check') <path d="m5 12 4 4L19 6" /> @break
        @case('check-square') <rect x="4" y="4" width="16" height="16" rx="2" /><path d="m8 12 2.5 2.5L16 9" /> @break
        @case('arrow-right') <path d="M5 12h14m-6-6 6 6-6 6" /> @break
        @case('info') <circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /> @break
        @case('alert') <path d="m12 3 9 17H3L12 3Z" /><path d="M12 9v4M12 16h.01" /> @break
        @case('camera') <path d="M4 7h3l1.5-2h7L17 7h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z" /><circle cx="12" cy="13" r="3.5" /> @break
        @case('upload') <path d="M12 16V4m0 0L7 9m5-5 5 5M4 20h16" /> @break
        @default <circle cx="12" cy="12" r="8" />
    @endswitch
</svg>
