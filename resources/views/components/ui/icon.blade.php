@props([
    'name',
    'size' => 'md',
    'label' => null,
    'strokeWidth' => 1.8,
])

@php
    $sizeClasses = [
        'xs' => 'h-3.5 w-3.5',
        'sm' => 'h-4 w-4',
        'md' => 'h-5 w-5',
        'lg' => 'h-6 w-6',
        'xl' => 'h-8 w-8',
    ];

    $aliases = [
        'add' => 'plus',
        'alert' => 'warning',
        'back' => 'arrow-left',
        'delete' => 'trash',
        'file' => 'document',
        'gear' => 'settings',
        'next' => 'arrow-right',
        'pencil' => 'edit',
        'preview' => 'eye',
        'print' => 'printer',
        'reload' => 'refresh',
        'success' => 'check',
        'view' => 'eye',
        'close' => 'x',
        'employee' => 'users',
    ];

    $paths = [
        'dashboard' => 'M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z',
        'budget' => 'M4 19h16M6 17V9m4 8V5m4 12v-6m4 6V3',
        'transaction' => 'm7 7 5-5 5 5m-5-5v16m5-4-5 5-5-5',
        'tax' => 'M9 14h6m-7-4h8m-1-7H9a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6l4-4V5a2 2 0 0 0-2-2Zm2 14v-4h4',
        'document' => 'M6 2h8l4 4v16H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 0v5h5M8 12h8M8 16h6',
        'report' => 'M4 19V5m0 14h16M8 16v-5m4 5V7m4 9v-8',
        'audit' => 'M4 5h16v14H4zM8 9h8M8 13h5M8 17h3',
        'database' => 'M4 5c0-1.1 3.6-2 8-2s8 .9 8 2-3.6 2-8 2-8-.9-8-2Zm0 0v7c0 1.1 3.6 2 8 2s8-.9 8-2V5m-16 7v7c0 1.1 3.6 2 8 2s8-.9 8-2v-7',
        'sync' => 'M20 11a8.1 8.1 0 0 0-14.8-4L3 10m0 0V4m0 6h6M4 13a8.1 8.1 0 0 0 14.8 4L21 14m0 0v6m0-6h-6',
        'calendar' => 'M6 3v3m12-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z',
        'settings' => 'M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm0-12v2m0 13v2m8.5-8.5h-2m-13 0h-2m14.2-6.2-1.4 1.4M5.7 18.3l-1.4 1.4m14.4 0-1.4-1.4M5.7 5.7 4.3 4.3',
        'archive' => 'M3 7h18M5 7l1-4h12l1 4M5 7v13h14V7M9 11h6',
        'server' => 'M4 4h16v6H4zM4 14h16v6H4zM7 7h.01M7 17h.01',
        'logout' => 'M10 17l5-5-5-5m5 5H3m9-9V3h9v18h-9v-2',
        'edit' => 'M4 20h4l11-11a2.8 2.8 0 0 0-4-4L4 16v4Zm10-14 4 4M13 20h7',
        'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21c.8-4 3.5-6 8-6s7.2 2 8 6',
        'users' => 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM2 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2m1-10a3 3 0 1 0 0-6m1 9a5 5 0 0 1 4 5v2',
        'search' => 'm21 21-4.35-4.35M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z',
        'eye' => 'M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Zm9.5 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
        'excel' => 'M5 3h10l4 4v14H5V3Zm10 0v5h5M8 12h8m-8 4h8m-8-8h2',
        'pdf' => 'M5 3h10l4 4v14H5V3Zm10 0v5h5M8 16h2.5a1.5 1.5 0 0 0 0-3H8v3Zm6 0v-3h1a1.5 1.5 0 0 1 0 3h-1Z',
        'inbox' => 'M4 5h16v14H4V5Zm0 9h4l2 3h4l2-3h4',
        'work' => 'M9 6V4h6v2m-10 0h14a2 2 0 0 1 2 2v10H3V8a2 2 0 0 1 2-2Zm-2 5h18M9 11v2h6v-2',
        'number' => 'M8 3 6 21m10-18-2 18M4 9h16M3 15h16',
        'clock' => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm0-15v5l3 2',
        'priority' => 'M12 3 4 7v5c0 5 3.4 8.2 8 9 4.6-.8 8-4 8-9V7l-8-4Zm0 5v5m0 4h.01',
        'progress' => 'M4 19V5m0 14h16M8 15v-4m4 4V7m4 8v-6',
        'queue' => 'M5 6h14M5 12h14M5 18h9',
        'system' => 'M12 3v3m0 12v3m9-9h-3M6 12H3m15.4-6.4-2.1 2.1M7.7 16.3l-2.1 2.1m12.8 0-2.1-2.1M7.7 7.7 5.6 5.6M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
        'balance' => 'M3 7h18v12H3V7Zm0 0 3-4h12l3 4M16 12h5v4h-5a2 2 0 1 1 0-4Z',
        'save' => 'M5 4h12l2 2v14H5zM8 4v6h8V4M8 20v-6h8v6',
        'trash' => 'M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5m4-5v5',
        'plus' => 'M12 5v14M5 12h14',
        'minus' => 'M5 12h14',
        'filter' => 'M4 6h16M7 12h10M10 18h4',
        'refresh' => 'M20 7v5h-5M4 17v-5h5M6 8a7 7 0 0 1 11-2l3 6M18 16a7 7 0 0 1-11 2l-3-6',
        'download' => 'M12 4v11M8 11l4 4 4-4M5 20h14',
        'upload' => 'M12 20V9M8 13l4-4 4 4M5 4h14',
        'arrow-left' => 'M19 12H5M11 6l-6 6 6 6',
        'arrow-right' => 'M5 12h14M13 6l6 6-6 6',
        'arrow-up' => 'M12 19V5M6 11l6-6 6 6',
        'arrow-down' => 'M12 5v14M6 13l6 6 6-6',
        'chevron-left' => 'm15 18-6-6 6-6',
        'chevron-right' => 'm9 18 6-6-6-6',
        'chevron-up' => 'm6 15 6-6 6 6',
        'chevron-down' => 'm6 9 6 6 6-6',
        'check' => 'M5 12l4 4 10-10',
        'x' => 'M6 6l12 12M18 6 6 18',
        'printer' => 'M7 9V4h10v5M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 14h10v6H7z',
        'home' => 'M3 11l9-7 9 7M5 10v10h14V10M9 20v-6h6v6',
        'info' => 'M12 11v6M12 7h.01M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z',
        'warning' => 'M12 4l9 16H3zM12 9v5M12 17h.01',
        'lock' => 'M5 10h14v10H5zM8 10V7a4 4 0 0 1 8 0v3',
        'unlock' => 'M5 10h14v10H5zM8 10V7a4 4 0 0 1 7-2',
        'external-link' => 'M14 5h5v5M19 5l-8 8M18 13v6H5V6h6',
        'menu' => 'M4 7h16M4 12h16M4 17h16',
    ];

    $normalizedName = strtolower(trim((string) $name));
    $canonicalName = $aliases[$normalizedName] ?? $normalizedName;
    $iconClass = $sizeClasses[$size] ?? $sizeClasses['md'];
    $path = $paths[$canonicalName] ?? $paths['document'];
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
    <path d="{{ $path }}" />
</svg>
