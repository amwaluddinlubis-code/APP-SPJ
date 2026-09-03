@props([
    'status',
    'label' => null,
    'size' => 'sm',
])

@php
    $normalizedStatus = strtoupper(trim((string) $status));

    $statusMeta = match ($normalizedStatus) {
        'DRAFT', 'BELUM_LENGKAP' => ['label' => 'Belum lengkap', 'class' => 'border-amber-200 bg-amber-50 text-amber-800'],
        'READY', 'SIAP', 'DISIAPKAN' => ['label' => 'Siap diproses', 'class' => 'border-sky-200 bg-sky-50 text-sky-800'],
        'NUMBERED', 'BERNOMOR' => ['label' => 'Sudah bernomor', 'class' => 'border-indigo-200 bg-indigo-50 text-indigo-800'],
        'PRINTED', 'DICETAK' => ['label' => 'Sudah dicetak', 'class' => 'border-violet-200 bg-violet-50 text-violet-800'],
        'FINAL', 'ARCHIVED', 'ARSIP' => ['label' => 'Final', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800'],
        'CANCELLED', 'CANCELED' => ['label' => 'Dibatalkan', 'class' => 'border-rose-200 bg-rose-50 text-rose-800'],
        'SOURCE_MISSING' => ['label' => 'Tidak muncul di sinkronisasi', 'class' => 'border-rose-200 bg-rose-50 text-rose-800'],
        'RECONCILIATION', 'REQUIRES_RECONCILIATION' => ['label' => 'Perlu rekonsiliasi', 'class' => 'border-orange-200 bg-orange-50 text-orange-800'],
        'ACTIVE' => ['label' => 'Aktif', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800'],
        'INACTIVE' => ['label' => 'Tidak aktif', 'class' => 'border-slate-200 bg-slate-50 text-slate-700'],
        'DITETAPKAN' => ['label' => 'Sudah ditetapkan', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800'],
        'PENDING' => ['label' => 'Menunggu diproses', 'class' => 'border-amber-200 bg-amber-50 text-amber-800'],
        'PROCESSING', 'RUNNING' => ['label' => 'Sedang diproses', 'class' => 'border-sky-200 bg-sky-50 text-sky-800'],
        'COMPLETED', 'SUCCESS', 'SUCCEEDED' => ['label' => 'Selesai', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800'],
        'FAILED', 'ERROR' => ['label' => 'Gagal', 'class' => 'border-rose-200 bg-rose-50 text-rose-800'],
        'LOCKED' => ['label' => 'Terkunci', 'class' => 'border-slate-300 bg-slate-100 text-slate-800'],
        'UNLOCKED' => ['label' => 'Dapat diedit', 'class' => 'border-sky-200 bg-sky-50 text-sky-800'],
        'REPLACED' => ['label' => 'Diganti', 'class' => 'border-slate-200 bg-slate-50 text-slate-700'],
        'GENERATED' => ['label' => 'Dokumen dibuat', 'class' => 'border-indigo-200 bg-indigo-50 text-indigo-800'],
        default => ['label' => str($normalizedStatus)->replace('_', ' ')->lower()->ucfirst()->toString(), 'class' => 'border-slate-200 bg-slate-50 text-slate-700'],
    };

    $sizeClass = $size === 'xs'
        ? 'px-2 py-0.5 text-[11px]'
        : 'px-2.5 py-1 text-xs';
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full border font-bold', $sizeClass, $statusMeta['class']]) }} title="Status sistem: {{ $normalizedStatus }}">
    {{ $label ?: $statusMeta['label'] }}
</span>
