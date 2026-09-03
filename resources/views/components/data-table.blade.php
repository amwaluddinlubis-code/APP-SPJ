@props([
    'columns' => [],
    'data' => null,
    'mobileCard' => null,
    'emptyMessage' => 'Belum ada data tersedia.',
    'emptyAction' => null,
])
@php($rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
    {{-- Mobile Cards --}}
    <div class="grid gap-3 p-4 lg:hidden">
        @forelse($data as $item)
            @if($mobileCard)
                {{ $mobileCard($item) }}
            @else
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow hover:shadow-md transition">
                    @foreach($columns as $column)
                        <div class="mb-2 last:mb-0">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $column['label'] }}</p>
                            <p class="mt-1 text-base {{ $column['class'] ?? 'text-slate-800' }}">
                                @if(isset($column['format']) && $column['format'] === 'currency')
                                    {{ $rupiah($column['value']($item)) }}
                                @elseif(isset($column['format']) && $column['format'] === 'boolean')
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $column['value']($item) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $column['value']($item) ? 'Ya' : 'Tidak' }}
                                    </span>
                                @else
                                    {{ $column['value']($item) }}
                                @endif
                            </p>
                        </div>
                    @endforeach
                </article>
            @endif
        @empty
            <div class="rounded-xl border border-dashed p-8 text-center">
                <p class="font-semibold text-slate-700">{{ $emptyMessage }}</p>
                @if($emptyAction)
                    <p class="mt-1 text-base text-slate-500">{{ $emptyAction }}</p>
                @endif
            </div>
        @endforelse
    </div>

    {{-- Desktop Table --}}
    <div class="hidden lg:block overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-base">
            <thead class="bg-slate-50">
                <tr>
                    @foreach($columns as $column)
                        <th class="px-4 py-3 {{ $column['headerClass'] ?? 'text-left' }} text-xs font-bold uppercase tracking-wide text-slate-500">
                            {{ $column['label'] }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($data as $item)
                    <tr class="transition hover:bg-indigo-50/50">
                        @foreach($columns as $column)
                            <td class="px-4 py-4 {{ $column['class'] ?? '' }}">
                                @if(isset($column['format']) && $column['format'] === 'currency')
                                    <span class="whitespace-nowrap font-semibold text-slate-800">{{ $rupiah($column['value']($item)) }}</span>
                                @elseif(isset($column['format']) && $column['format'] === 'boolean')
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $column['value']($item) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $column['value']($item) ? 'Ya' : 'Tidak' }}
                                    </span>
                                @elseif(isset($column['action']))
                                    {{ $column['action']($item) }}
                                @else
                                    {{ $column['value']($item) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="px-5 py-14 text-center">
                            <p class="font-semibold text-slate-700">{{ $emptyMessage }}</p>
                            @if($emptyAction)
                                <p class="mt-1 text-base text-slate-500">{{ $emptyAction }}</p>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
