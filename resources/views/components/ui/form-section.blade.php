@props([
    'title',
    'description' => null,
])

<section {{ $attributes->class(['ui-form-section overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm']) }}>
    <div class="ui-form-section-header flex flex-col gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div class="min-w-0">
            <h2 class="ui-form-section-title text-base font-bold text-slate-900">{{ $title }}</h2>
            @if($description)<p class="ui-form-section-description mt-1 text-sm leading-5 text-slate-500">{{ $description }}</p>@endif
        </div>
        @if(isset($actions))<div class="ui-form-section-actions flex shrink-0 flex-wrap gap-2">{{ $actions }}</div>@endif
    </div>
    <div class="ui-form-section-body p-5 sm:p-6">{{ $slot }}</div>
</section>
