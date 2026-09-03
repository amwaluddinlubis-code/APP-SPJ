@php($paths = [
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
    'employee' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
])
<svg {{ $attributes->merge(['class' => 'h-5 w-5 shrink-0', 'fill' => 'none', 'viewBox' => '0 0 24 24', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }} aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $paths[$name] ?? $paths['document'] }}" /></svg>
