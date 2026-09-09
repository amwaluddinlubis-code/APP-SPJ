<x-layouts.tailwind-app>
    <div class="mx-auto max-w-7xl space-y-6">
        <x-page-header
            title="ARKAS Database Explorer"
            subtitle="Jelajahi tabel dan struktur database ARKAS melalui Bridge generik sebelum membuat konfigurasi sinkronisasi."
            kicker="Importer ARKAS"
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="route('arkas.settings')">Pengaturan Sumber</x-ui.button>
            </x-slot:actions>
        </x-page-header>

        @if($error)
            <div class="rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $error }}</div>
        @endif
        @if(session('success'))
            <div class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <section class="grid gap-6 lg:grid-cols-[18rem_1fr]">
            <x-ui.form-section title="Pilih Tabel" description="Daftar dibaca langsung dari database ARKAS aktif.">
                <form method="GET" action="{{ route('arkas.importer') }}" class="space-y-4">
                    <x-ui.field label="Tabel ARKAS" for="arkas-table">
                        <x-ui.select id="arkas-table" name="table" onchange="this.form.submit()">
                            <option value="">Pilih tabel</option>
                            @foreach($tables as $table)
                                <option value="{{ $table }}" @selected($selectedTable === $table)>{{ $table }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field label="Jumlah contoh baris" for="arkas-limit">
                        <x-ui.select id="arkas-limit" name="limit">
                            @foreach([10, 25, 50, 100] as $option)
                                <option value="{{ $option }}" @selected($limit === $option)>{{ $option }} baris</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.button type="submit" icon="database">Tampilkan Preview</x-ui.button>
                </form>
                <p class="mt-4 text-xs" style="color: var(--ui-fg-muted)">Tahap ini bersifat read-only. Belum ada data lokal yang ditulis.</p>
                @if($profiles->isNotEmpty())
                    <div class="mt-5 border-t pt-4" style="border-color: var(--ui-line)">
                        <p class="text-xs font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Konfigurasi tersimpan</p>
                        <div class="mt-2 space-y-2">
                            @foreach($profiles as $savedProfile)
                                <a href="{{ route('arkas.importer', ['table' => $savedProfile->source_table]) }}" class="flex items-center justify-between rounded-lg border px-3 py-2 text-xs transition hover:brightness-95" style="border-color: var(--ui-line); color: var(--ui-fg-strong)">
                                    <span><strong>{{ $savedProfile->label }}</strong><br><span class="font-mono" style="color: var(--ui-fg-muted)">{{ $savedProfile->source_table }}</span></span>
                                    <span class="rounded-full px-2 py-0.5" style="background: var(--ui-surface-soft); color: var(--ui-fg-muted)">{{ $savedProfile->last_synced_at?->diffForHumans() ?: 'Belum sync' }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-ui.form-section>

            <div class="space-y-6">
                @if($selectedTable !== '')
                    <x-ui.form-section title="Mapping & Sinkronisasi" description="Simpan aturan tabel ini sebelum menjalankan sinkronisasi generik.">
                        <div class="mb-4 grid gap-3 lg:grid-cols-[1.25fr_1fr]">
                            <div class="rounded-xl border px-3 py-2.5" style="border-color: var(--ui-line); background: var(--ui-surface-soft)">
                                <p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Preset proses sinkronisasi saat ini</p>
                                <p class="mt-1 text-sm font-semibold" style="color: var(--ui-fg-strong)">{{ $selectedTable }} → Bridge <span class="font-mono">{{ $preset['bridge_command'] }}</span> → {{ $preset['target_table'] ?: 'snapshot generik' }}</p>
                                <p class="mt-1 text-xs" style="color: var(--ui-fg-muted)">{{ $preset['description'] }}</p>
                            </div>
                            @if($currentStatus)
                                <div class="rounded-xl border px-3 py-2.5" style="border-color: var(--ui-line); background: var(--ui-surface-soft)">
                                    <p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Kondisi lokal sekarang</p>
                                    <p class="mt-1 text-sm font-semibold" style="color: var(--ui-fg-strong)">{{ number_format($currentStatus['rows']) }} baris di <span class="font-mono">{{ $currentStatus['target_table'] }}</span></p>
                                    <p class="mt-1 text-xs" style="color: var(--ui-fg-muted)">{{ $currentStatus['profile'] ? 'Profil tersimpan: '.$currentStatus['profile'] : 'Belum ada profil importer tersimpan untuk tabel ini.' }}</p>
                                </div>
                            @endif
                        </div>
                        @if($previewDiff)
                            <div class="mb-4 rounded-xl border px-3 py-2.5" style="border-color: var(--ui-line); background: var(--ui-surface-soft)">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Preview perubahan staged</p>
                                        <p class="mt-1 text-xs" style="color: var(--ui-fg-muted)">Perbandingan berdasarkan {{ $previewDiff['sample'] }} contoh baris yang sedang ditampilkan.</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2 text-xs font-semibold">
                                        <span class="rounded-full border px-2 py-1" style="border-color: #86efac; color: #15803d">Baru {{ $previewDiff['new'] }}</span>
                                        <span class="rounded-full border px-2 py-1" style="border-color: #fcd34d; color: #a16207">Berubah {{ $previewDiff['changed'] }}</span>
                                        <span class="rounded-full border px-2 py-1" style="border-color: var(--ui-line); color: var(--ui-fg-muted)">Tetap {{ $previewDiff['unchanged'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                        @if($recentRun)
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2 text-xs" style="border-color: var(--ui-line); background: var(--ui-surface-muted)">
                                <span style="color: var(--ui-fg-muted)">Run terakhir: <strong style="color: var(--ui-fg-strong)">{{ $recentRun->status }}</strong> · {{ $recentRun->finished_at?->diffForHumans() ?: 'sedang berjalan' }}</span>
                                <span style="color: var(--ui-fg-muted)">{{ number_format($recentRun->records_written) }} baris tersimpan</span>
                            </div>
                        @endif
                        @if($errors->has('mapping'))
                            <div class="mb-4 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-800">{!! implode('<br>', $errors->get('mapping')) !!}</div>
                        @endif
                        @if($reconciliation)
                            <div class="mb-4 rounded-xl border px-3 py-3" style="border-color: var(--theme-content-accent); background: var(--ui-surface-soft)">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div><p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Rekonsiliasi penuh</p><p class="mt-1 text-xs" style="color: var(--ui-fg-muted)">Bridge {{ number_format($reconciliation['source_count']) }} baris · staging {{ number_format($reconciliation['staged_count']) }} baris</p></div>
                                    <div class="flex flex-wrap gap-2 text-xs font-semibold"><span class="rounded-full border px-2 py-1" style="border-color: #86efac; color: #15803d">Baru {{ $reconciliation['new'] }}</span><span class="rounded-full border px-2 py-1" style="border-color: #fcd34d; color: #a16207">Berubah {{ $reconciliation['changed'] }}</span><span class="rounded-full border px-2 py-1" style="border-color: var(--ui-line); color: var(--ui-fg-muted)">Tetap {{ $reconciliation['unchanged'] }}</span><span class="rounded-full border px-2 py-1" style="border-color: #fda4af; color: #be123c">Hilang {{ $reconciliation['removed'] }}</span></div>
                                </div>
                            </div>
                        @endif
                        @if($schemaDrift['new'] !== [] || $schemaDrift['missing'] !== [])
                            <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2.5 text-xs text-amber-900">
                                <p class="font-bold">Struktur sumber berubah</p>
                                @if($schemaDrift['new'] !== [])<p class="mt-1">Kolom baru: {{ implode(', ', $schemaDrift['new']) }}</p>@endif
                                @if($schemaDrift['missing'] !== [])<p class="mt-1">Kolom mapping tidak ditemukan: {{ implode(', ', $schemaDrift['missing']) }}</p>@endif
                                <p class="mt-1">Periksa ulang mapping sebelum menjalankan sinkronisasi.</p>
                            </div>
                        @endif
                        <form method="POST" action="{{ route('arkas.importer.mapping.store') }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="source_table" value="{{ $selectedTable }}">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <x-ui.field label="Nama konfigurasi" for="import-label" required>
                                    <x-ui.input id="import-label" name="label" :value="old('label', $profile?->label ?: strtoupper($selectedTable))" required />
                                </x-ui.field>
                                <x-ui.field label="Target domain" for="target-domain" hint="Snapshot tetap disimpan; target domain mengisi tabel aplikasi terkait.">
                                    <x-ui.select id="target-domain" name="target_domain">
                                        @foreach($targetDomains as $domain => $domainLabel)
                                            <option value="{{ $domain }}" @selected(old('target_domain', $effectiveTargetDomain) === $domain)>{{ $domainLabel }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </x-ui.field>
                                <x-ui.field label="Mode sinkronisasi" for="import-mode" hint="Incremental hanya memproses baris yang berubah; Full refresh menghapus snapshot tabel ini untuk tahun aktif.">
                                    <x-ui.select id="import-mode" name="sync_mode">
                                        <option value="incremental" @selected(old('sync_mode', $profile?->sync_mode ?: 'upsert') === 'incremental')>Incremental</option>
                                        <option value="upsert" @selected(old('sync_mode', $profile?->sync_mode ?: 'upsert') === 'upsert')>Upsert</option>
                                        <option value="full_refresh" @selected(old('sync_mode', $profile?->sync_mode) === 'full_refresh')>Full refresh</option>
                                    </x-ui.select>
                                </x-ui.field>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-3">
                                <x-ui.field label="Kolom kunci sumber" for="source-key-column"><x-ui.select id="source-key-column" name="source_key_column"><option value="">Otomatis</option>@foreach($columns as $column)<option value="{{ $column['name'] }}" @selected(old('source_key_column', $effectiveSourceKeyColumn) === $column['name'])>{{ $column['name'] }}</option>@endforeach</x-ui.select></x-ui.field>
                                <x-ui.field label="Kolom tahun" for="year-column"><x-ui.select id="year-column" name="year_column"><option value="">Deteksi Bridge</option>@foreach($columns as $column)<option value="{{ $column['name'] }}" @selected(old('year_column', $profile?->year_column) === $column['name'])>{{ $column['name'] }}</option>@endforeach</x-ui.select></x-ui.field>
                                <x-ui.field label="Kolom sumber dana" for="fund-column"><x-ui.select id="fund-column" name="fund_source_column"><option value="">Deteksi Bridge</option>@foreach($columns as $column)<option value="{{ $column['name'] }}" @selected(old('fund_source_column', $profile?->fund_source_column) === $column['name'])>{{ $column['name'] }}</option>@endforeach</x-ui.select></x-ui.field>
                                <x-ui.field label="Kolom terakhir berubah" for="updated-column" hint="Wajib untuk mode incremental."><x-ui.select id="updated-column" name="source_updated_column"><option value="">Pilih jika tersedia</option>@foreach($columns as $column)<option value="{{ $column['name'] }}" @selected(old('source_updated_column', $profile?->source_updated_column) === $column['name'])>{{ $column['name'] }}</option>@endforeach</x-ui.select></x-ui.field>
                            </div>
                            <div class="rounded-xl border p-3" style="border-color: var(--ui-line); background: var(--ui-surface-soft)">
                                <p class="text-xs font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Peran kolom</p>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach($columns as $column)
                                        <label class="flex items-center justify-between gap-2 rounded-lg border px-2 py-1.5 text-xs" style="border-color: var(--ui-line); background: var(--ui-surface-base)"><span class="truncate font-mono" title="{{ $column['name'] }}">{{ $column['name'] }}</span><select name="mapping[{{ $column['name'] }}]" class="ui-select !min-h-7 !w-auto !py-0.5 !text-[11px]"><option value="ignore">Abaikan</option>@foreach(\App\Services\ArkasDomainAdapter::mappingRoles() as $role => $roleLabel)<option value="{{ $role }}" @selected(($effectiveMapping[$column['name']] ?? 'ignore') === $role)>{{ $roleLabel }}</option>@endforeach</select></label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <x-ui.button type="submit" icon="save">Simpan Mapping</x-ui.button>
                                @if($profile)
                                    <button type="submit" formaction="{{ route('arkas.importer.preview', $profile->id) }}" formmethod="POST" class="inline-flex min-h-9 items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-semibold transition hover:brightness-95" style="border-color: var(--ui-line-strong); color: var(--ui-fg-strong); background: var(--ui-bg)"><x-ui-icon name="search" class="h-4 w-4" />Preview Rekonsiliasi Penuh</button>
                                    <button type="submit" formaction="{{ route('arkas.importer.sync', $profile->id) }}" formmethod="POST" class="inline-flex min-h-9 items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-semibold transition hover:brightness-95" style="border-color: var(--theme-content-accent); color: var(--theme-content-accent); background: var(--ui-bg)"><x-ui-icon name="refresh" class="h-4 w-4" />Sinkronkan Sekarang</button>
                                @endif
                            </div>
                            <p class="text-xs" style="color: var(--ui-fg-muted)">Simpan Mapping terlebih dahulu jika Anda mengubah target, kunci, atau peran kolom. Sinkronisasi hanya menulis setelah tombol ditekan.</p>
                        </form>
                        @if($runHistory->isNotEmpty())
                            <div class="mt-4 overflow-x-auto rounded-xl border" style="border-color: var(--ui-line)">
                                <table class="min-w-full text-xs"><thead style="background: var(--ui-surface-soft)"><tr><th class="px-3 py-2 text-left" style="color: var(--ui-fg-muted)">Waktu</th><th class="px-3 py-2 text-left" style="color: var(--ui-fg-muted)">Status</th><th class="px-3 py-2 text-right" style="color: var(--ui-fg-muted)">Dibaca</th><th class="px-3 py-2 text-right" style="color: var(--ui-fg-muted)">Ditulis</th><th class="px-3 py-2 text-left" style="color: var(--ui-fg-muted)">Pesan</th></tr></thead><tbody class="divide-y" style="border-color: var(--ui-line)">@foreach($runHistory as $run)<tr><td class="px-3 py-2">{{ $run->finished_at?->format('d/m/Y H:i') ?: 'Berjalan' }}</td><td class="px-3 py-2 font-semibold">{{ $run->status }}</td><td class="px-3 py-2 text-right">{{ number_format($run->records_read) }}</td><td class="px-3 py-2 text-right">{{ number_format($run->records_written) }}</td><td class="max-w-sm truncate px-3 py-2" title="{{ $run->message }}">{{ $run->message }}</td></tr>@endforeach</tbody></table>
                            </div>
                        @endif
                    </x-ui.form-section>

                    <x-ui.form-section title="Struktur Tabel: {{ $selectedTable }}" description="Schema aktual yang dikembalikan Bridge.">
                        <div class="overflow-x-auto rounded-xl border" style="border-color: var(--ui-line)">
                            <table data-pagination="none" class="min-w-full text-sm">
                                <thead style="background: var(--ui-surface-soft)"><tr>
                                    <th class="px-3 py-2 text-left text-xs uppercase" style="color: var(--ui-fg-muted)">Posisi</th>
                                    <th class="px-3 py-2 text-left text-xs uppercase" style="color: var(--ui-fg-muted)">Kolom</th>
                                    <th class="px-3 py-2 text-left text-xs uppercase" style="color: var(--ui-fg-muted)">Tipe</th>
                                    <th class="px-3 py-2 text-left text-xs uppercase" style="color: var(--ui-fg-muted)">Nullable</th>
                                    <th class="px-3 py-2 text-left text-xs uppercase" style="color: var(--ui-fg-muted)">Primary</th>
                                </tr></thead>
                                <tbody class="divide-y" style="border-color: var(--ui-line)">
                                    @foreach($columns as $column)
                                        <tr><td class="px-3 py-1.5 font-mono text-xs">{{ $column['position'] }}</td><td class="px-3 py-1.5 font-mono text-xs font-semibold">{{ $column['name'] }}</td><td class="px-3 py-1.5 text-xs">{{ $column['type'] ?: '—' }}</td><td class="px-3 py-1.5 text-xs">{{ $column['nullable'] }}</td><td class="px-3 py-1.5 text-xs">{{ $column['primary'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-ui.form-section>

                    <x-ui.form-section title="Preview Data" description="Contoh data maksimal sesuai pilihan jumlah baris.">
                        <div class="max-w-full overflow-hidden rounded-xl border" style="border-color: var(--ui-line)">
                            <table data-pagination="none" class="w-full table-fixed text-[11px]" style="table-layout: fixed">
                                <thead style="background: var(--ui-surface-soft)"><tr>@foreach($columns as $column)<th class="px-2 py-1.5 text-left font-bold uppercase" style="color: var(--ui-fg-muted)"><span class="block truncate" title="{{ $column['name'] }}">{{ $column['name'] }}</span></th>@endforeach</tr></thead>
                                <tbody class="divide-y" style="border-color: var(--ui-line)">
                                    @forelse($rows as $row)
                                        <tr>@foreach($columns as $column)<td class="overflow-hidden px-2 py-1 align-top" title="{{ $row[$column['name']] ?? '' }}"><span class="block truncate">{{ mb_strimwidth($row[$column['name']] ?? '—', 0, 36, '…') }}</span></td>@endforeach</tr>
                                    @empty
                                        <tr><td colspan="{{ max(count($columns), 1) }}" class="px-3 py-8 text-center" style="color: var(--ui-fg-muted)">Tidak ada data preview.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-ui.form-section>
                @else
                    <x-ui.form-section title="Mulai Sinkronisasi Terarah" description="Gunakan alur sederhana ini untuk menambahkan data ARKAS tanpa mengubah paket SPJ operator.">
                        <div class="grid gap-3 md:grid-cols-3">
                            @foreach([
                                ['number' => '1', 'title' => 'Pilih tabel', 'text' => 'Pilih tabel ARKAS yang ingin diperiksa dari daftar di sebelah kiri.'],
                                ['number' => '2', 'title' => 'Periksa mapping', 'text' => 'Mapping awal akan dikenali otomatis dari preset dan nama kolom.'],
                                ['number' => '3', 'title' => 'Jalankan sync', 'text' => 'Simpan konfigurasi, lihat preview perubahan, lalu sinkronkan.'],
                            ] as $step)
                                <div class="rounded-xl border p-4" style="border-color: var(--ui-line); background: var(--ui-surface-soft)">
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold text-white" style="background: var(--theme-content-accent)">{{ $step['number'] }}</span>
                                    <h3 class="mt-3 text-sm font-bold" style="color: var(--ui-fg-strong)">{{ $step['title'] }}</h3>
                                    <p class="mt-1 text-xs leading-5" style="color: var(--ui-fg-muted)">{{ $step['text'] }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 rounded-xl border border-dashed px-4 py-3 text-sm" style="border-color: var(--ui-line-strong); color: var(--ui-fg-muted)">
                            Sinkronisasi penuh RKAS dan BKU tetap tersedia melalui menu <strong style="color: var(--ui-fg-strong)">Sinkron Semua ARKAS</strong>. Modul ini digunakan untuk sinkronisasi tabel tambahan secara aman dan terkontrol.
                        </div>
                    </x-ui.form-section>
                @endif
            </div>
        </section>
    </div>
</x-layouts.tailwind-app>
