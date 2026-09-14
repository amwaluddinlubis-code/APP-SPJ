<div class="space-y-5">
    <div><h2 class="text-base font-bold text-[var(--ui-fg-strong)]">Tentukan konteks kerja</h2><p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Pilih satu tahun anggaran dan satu sumber dana. Pilihan ini akan menyaring seluruh data aplikasi.</p></div>

    <form wire:submit="selectContext" class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4 sm:p-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="Tahun anggaran" for="context-year" hint="Pilih periode pembukuan yang aktif." :error="$errors->first('selectedYear')" required>
                <x-ui.select id="context-year" wire:model.live="selectedYear" class="w-full" required>
                    <option value="">Pilih tahun anggaran</option>
                    @foreach($years as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field label="Sumber dana" for="context-fund-source" hint="Hanya sumber dana yang tersedia pada tahun terpilih yang ditampilkan." :error="$errors->first('selectedFundSourceId')" required>
                <x-ui.select id="context-fund-source" wire:model="selectedFundSourceId" class="w-full" :disabled="!$selectedYear || $fundSources->isEmpty()" required>
                    <option value="">Pilih sumber dana</option>
                    @foreach($fundSources as $fundSource)<option value="{{ $fundSource->id }}">{{ $fundSource->name }} ({{ $fundSource->code }})</option>@endforeach
                </x-ui.select>
            </x-ui.field>
        </div>
        @if($errors->has('selectedYear') || $errors->has('selectedFundSourceId'))<p class="mt-3 text-sm text-rose-700">Lengkapi tahun anggaran dan sumber dana sebelum melanjutkan.</p>@endif
        <div class="mt-5 flex justify-end"><x-ui.button type="submit" wire:loading.attr="disabled" wire:target="selectContext" icon="arrow-right" iconPosition="end">Gunakan konteks</x-ui.button></div>
    </form>

    @if($years->isEmpty())
        <div class="rounded-2xl border border-dashed border-[var(--ui-line-strong)] bg-[var(--ui-surface-soft)] px-5 py-8 text-center"><div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-[var(--ui-surface-muted)] text-[var(--ui-fg-muted)]"><x-ui.icon name="calendar" /></div><p class="mt-3 font-bold text-[var(--ui-fg-strong)]">Belum ada pilihan yang tersedia</p><p class="mx-auto mt-1 max-w-md text-sm text-[var(--ui-fg-muted)]">Simpan pengaturan ARKAS, lalu lakukan sinkronisasi untuk mengimpor tahun dan sumber dana.</p><form method="POST" action="{{ route('years.synchronize') }}" data-confirm="Sinkronisasi akan mengimpor data tahun dan sumber dana dari ARKAS. Lanjutkan?" class="mt-5">@csrf<input type="hidden" name="confirm_sync" value="1"><x-ui.button type="submit" icon="sync">Sinkronkan ARKAS sekarang</x-ui.button></form></div>
    @elseif($selectedYear && $fundSources->isEmpty())
        <div class="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900">Belum ada sumber dana yang terhubung dengan tahun {{ $selectedYear }}. Pilih tahun lain atau lakukan sinkronisasi ARKAS.</div>
    @endif
</div>
