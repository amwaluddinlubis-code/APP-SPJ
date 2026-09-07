<section data-spj-refresh="documents" class="mx-5 mt-5 overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3.5" style="border-color: var(--ui-line)">
                            <div><h2 class="text-base font-bold" style="color: var(--ui-fg)">Dokumen &amp; Template</h2><p class="mt-0.5 text-xs" style="color: var(--ui-fg-muted)">PDF paket disusun dari template aktif yang sesuai dengan kategori transaksi.</p></div>
                            @unless($validationIssues || $package->status === 'CANCELLED')
                                <div class="flex items-center gap-2">
                                    <button type="button" data-template-preview="{{ route('spj.preview-package', $package->id) }}" data-template-name="Pratinjau Paket SPJ" title="Pratinjau Paket" class="ui-btn ui-btn-secondary min-h-10 min-w-10 justify-center px-3 py-2"><x-ui-icon name="preview" class="h-5 w-5" /><span class="sr-only">Pratinjau Paket</span></button>
                                    <form method="POST" action="{{ route('spj.download-package-excel', $package->id) }}">@csrf<button type="submit" title="Unduh Excel Paket" class="ui-btn ui-btn-secondary min-h-10 min-w-10 justify-center px-3 py-2"><x-ui-icon name="excel" class="h-5 w-5" /><span class="sr-only">Unduh Excel Paket</span></button></form>
                                    <form method="POST" action="{{ route('spj.download', $package->id) }}" target="_blank">@csrf<button type="submit" title="Unduh Paket PDF dari template aktif" class="ui-btn ui-btn-primary min-h-10 min-w-10 justify-center px-3 py-2"><x-ui-icon name="pdf" class="h-5 w-5" /><span class="sr-only">Unduh Paket PDF</span></button></form>
                                </div>
                            @endunless
                        </div>
                        @if($templates->isNotEmpty())
                            @php
                                $documentGroupMeta = [
                                    'needs' => ['Perlu dilengkapi', 'Lengkapi data paket sebelum dokumen dapat diunduh.', 'BELUM_LENGKAP'],
                                    'preview' => ['Siap dipratinjau', 'Data paket lengkap. Periksa template atau unduh dokumen yang diperlukan.', 'READY'],
                                    'completed' => ['Selesai', 'Dokumen telah difinalkan dan tersimpan sebagai bagian dari lifecycle paket.', 'FINAL'],
                                ];
                                $documentGroups = ['needs' => collect(), 'preview' => collect(), 'completed' => collect()];

                                foreach ($templates as $template) {
                                    $document = $package->documents->first(fn ($item) => (int) $item->document_template_id === (int) $template->id && $item->scope_key === 'MAIN' && $item->status !== 'CANCELLED');
                                    $group = $document?->status === 'FINAL' ? 'completed' : (($validationIssues || $package->status === 'CANCELLED') ? 'needs' : 'preview');
                                    $documentGroups[$group]->push($template);
                                }
                            @endphp
                            @foreach($documentGroupMeta as $group => $metadata)
                                @php
                                    $title = $metadata[0];
                                    $description = $metadata[1];
                                    $status = $metadata[2];
                                    $groupTemplates = $documentGroups[$group];
                                @endphp
                                @continue($groupTemplates->isEmpty())
                                <section class="border-t border-[var(--ui-line)]" aria-label="{{ $title }}">
                                    <header class="flex flex-wrap items-center justify-between gap-3 bg-slate-50/70 px-4 py-3">
                                        <div>
                                            <h3 class="font-semibold" style="color: var(--ui-fg)">{{ $title }}</h3>
                                            <p class="mt-0.5 text-xs" style="color: var(--ui-fg-muted)">{{ $description }}</p>
                                        </div>
                                        <x-ui.status-badge :status="$status" :label="$groupTemplates->count().' dokumen'" />
                                    </header>
                                    <div class="grid gap-px bg-[var(--ui-line)] md:grid-cols-2">
                                        @foreach($groupTemplates as $template)
                                            <div class="flex items-center justify-between gap-3 bg-[var(--ui-surface-base)] px-4 py-3">
                                                <div><p class="font-semibold" style="color: var(--ui-fg)">{{ $template->name }}</p><p class="mt-0.5 font-mono text-[11px]" style="color: var(--theme-content-accent)">{{ $template->document_type }} · {{ strtoupper($template->format) }}</p></div>
                                                <div class="flex shrink-0 items-center gap-2">
                                                    @if(strtolower($template->format) === 'xlsx')<button type="button" data-template-preview="{{ route('spj.preview-template', [$package->id, $template->id]) }}" data-template-name="{{ $template->name }}" title="Pratinjau {{ $template->name }}" class="ui-btn ui-btn-secondary min-h-10 min-w-10 justify-center px-3 py-2"><x-ui-icon name="preview" class="h-5 w-5" /><span class="sr-only">Pratinjau</span></button>@endif
                                                    @if($group === 'needs')<x-ui.status-badge :status="$package->status === 'CANCELLED' ? 'CANCELLED' : 'BELUM_LENGKAP'" :label="$package->status === 'CANCELLED' ? 'Nomor dibatalkan' : 'Lengkapi validasi dahulu'" />@else<form method="POST" action="{{ route('spj.download-template', [$package->id, $template->id]) }}">@csrf<button type="submit" title="Unduh Excel {{ $template->name }}" class="ui-btn ui-btn-secondary min-h-10 min-w-10 justify-center px-3 py-2"><x-ui-icon name="excel" class="h-5 w-5" /><span class="sr-only">Unduh Excel</span></button></form><form method="POST" action="{{ route('spj.download-template-pdf', [$package->id, $template->id]) }}" target="_blank">@csrf<button type="submit" title="Unduh PDF {{ $template->name }}" class="ui-btn ui-btn-primary min-h-10 min-w-10 justify-center px-3 py-2"><x-ui-icon name="pdf" class="h-5 w-5" /><span class="sr-only">Unduh PDF</span></button></form>@endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        @else
                            <div class="px-4 py-6 text-center text-sm" style="color: var(--ui-fg-muted)">Belum ada template aktif yang sesuai dengan kategori {{ $spjTypeLabel($packageCategory) }}.</div>
                        @endif
                    </section>
