@props([
    'name',
    'size' => 'md',
    'label' => null,
    'strokeWidth' => 2,
])

@php
    $sizeClasses = [
        'xs' => 'h-3.5 w-3.5',
        'sm' => 'h-4 w-4',
        'md' => 'h-5 w-5',
        'lg' => 'h-6 w-6',
        'xl' => 'h-8 w-8',
    ];

    $iconClass = $sizeClasses[$size] ?? $sizeClasses['md'];
    $normalizedName = strtolower(trim((string) $name));
@endphp

<svg
    {{ $attributes->class([$iconClass, 'shrink-0']) }}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $strokeWidth }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    @if($label)
        role="img"
        aria-label="{{ $label }}"
    @else
        aria-hidden="true"
    @endif
>
    @switch($normalizedName)
        @case('save')
            <path d="M5 4h12l2 2v14H5z" />
            <path d="M8 4v6h8V4" />
            <path d="M8 20v-6h8v6" />
            @break

        @case('edit')
        @case('pencil')
            <path d="M4 20l4.5-1 10-10a2 2 0 0 0-3-3l-10 10z" />
            <path d="M14.5 7.5l3 3" />
            @break

        @case('trash')
        @case('delete')
            <path d="M4 7h16" />
            <path d="M9 7V4h6v3" />
            <path d="M7 7l1 13h8l1-13" />
            <path d="M10 11v5M14 11v5" />
            @break

        @case('plus')
        @case('add')
            <path d="M12 5v14M5 12h14" />
            @break

        @case('minus')
            <path d="M5 12h14" />
            @break

        @case('search')
            <circle cx="11" cy="11" r="6" />
            <path d="M16 16l4 4" />
            @break

        @case('filter')
            <path d="M4 6h16M7 12h10M10 18h4" />
            @break

        @case('refresh')
        @case('reload')
            <path d="M20 7v5h-5" />
            <path d="M4 17v-5h5" />
            <path d="M6 8a7 7 0 0 1 11-2l3 6M18 16a7 7 0 0 1-11 2l-3-6" />
            @break

        @case('download')
            <path d="M12 4v11" />
            <path d="M8 11l4 4 4-4" />
            <path d="M5 20h14" />
            @break

        @case('upload')
            <path d="M12 20V9" />
            <path d="M8 13l4-4 4 4" />
            <path d="M5 4h14" />
            @break

        @case('arrow-left')
        @case('back')
            <path d="M19 12H5" />
            <path d="M11 6l-6 6 6 6" />
            @break

        @case('arrow-right')
        @case('next')
            <path d="M5 12h14" />
            <path d="M13 6l6 6-6 6" />
            @break

        @case('arrow-up')
            <path d="M12 19V5" />
            <path d="M6 11l6-6 6 6" />
            @break

        @case('arrow-down')
            <path d="M12 5v14" />
            <path d="M6 13l6 6 6-6" />
            @break

        @case('chevron-left')
            <path d="M15 18l-6-6 6-6" />
            @break

        @case('chevron-right')
            <path d="M9 6l6 6-6 6" />
            @break

        @case('chevron-up')
            <path d="M6 15l6-6 6 6" />
            @break

        @case('chevron-down')
            <path d="M6 9l6 6 6-6" />
            @break

        @case('check')
        @case('success')
            <path d="M5 12l4 4 10-10" />
            @break

        @case('x')
        @case('close')
            <path d="M6 6l12 12M18 6L6 18" />
            @break

        @case('eye')
        @case('view')
            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6S2.5 12 2.5 12z" />
            <circle cx="12" cy="12" r="2.5" />
            @break

        @case('printer')
        @case('print')
            <path d="M7 9V4h10v5" />
            <path d="M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2" />
            <path d="M7 14h10v6H7z" />
            @break

        @case('document')
        @case('file')
            <path d="M6 3h8l4 4v14H6z" />
            <path d="M14 3v5h5" />
            <path d="M9 13h6M9 17h6" />
            @break

        @case('database')
            <ellipse cx="12" cy="5" rx="7" ry="3" />
            <path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5" />
            <path d="M5 11v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6" />
            @break

        @case('settings')
        @case('gear')
            <circle cx="12" cy="12" r="3" />
            <path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6L7 7M17 17l1.4 1.4M18.4 5.6L17 7M7 17l-1.4 1.4" />
            @break

        @case('home')
            <path d="M3 11l9-7 9 7" />
            <path d="M5 10v10h14V10" />
            <path d="M9 20v-6h6v6" />
            @break

        @case('user')
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21c.8-4 3.5-6 8-6s7.2 2 8 6" />
            @break

        @case('users')
            <circle cx="9" cy="8" r="3" />
            <circle cx="17" cy="9" r="2.5" />
            <path d="M3 20c.6-3.7 2.8-5.5 6-5.5s5.4 1.8 6 5.5" />
            <path d="M15 15c3.1 0 5 1.5 5.7 4.5" />
            @break

        @case('calendar')
            <rect x="4" y="5" width="16" height="15" rx="2" />
            <path d="M8 3v4M16 3v4M4 10h16" />
            @break

        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v6l4 2" />
            @break

        @case('info')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 11v6" />
            <path d="M12 7h.01" />
            @break

        @case('warning')
        @case('alert')
            <path d="M12 4l9 16H3z" />
            <path d="M12 9v5" />
            <path d="M12 17h.01" />
            @break

        @case('lock')
            <rect x="5" y="10" width="14" height="10" rx="2" />
            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
            @break

        @case('unlock')
            <rect x="5" y="10" width="14" height="10" rx="2" />
            <path d="M8 10V7a4 4 0 0 1 7-2" />
            @break

        @case('external-link')
            <path d="M14 5h5v5" />
            <path d="M19 5l-8 8" />
            <path d="M18 13v6H5V6h6" />
            @break

        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" />
            @break

        @default
            <circle cx="12" cy="12" r="9" />
            <path d="M9.5 9a2.5 2.5 0 1 1 4 2c-1 .7-1.5 1.2-1.5 2.5" />
            <path d="M12 17h.01" />
    @endswitch
</svg>
