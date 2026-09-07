                                <fieldset @disabled($selectedSpjType !== 'PEMELIHARAAN') @if($selectedSpjType !== 'PEMELIHARAAN') hidden @endif data-spj-section="PEMELIHARAAN" x-data="{ rows: @js($workerRows), addWorker() { this.rows.push({name:'', job_description:'', work_days:1, daily_rate:0, is_receipt_recipient:false, notes:''}); }, total() { return this.rows.reduce((sum, row) => sum + (Number(row.work_days) || 0) * (Number(row.daily_rate) || 0), 0); }, money(value) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value); } }" class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Work order pemeliharaan</h3>
                                            <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Uraian dan lokasi menjadi dasar SPK/RAB; nomor dokumen diterbitkan saat penomoran.</p>
                                        </div>
                                        <button type="button" @click="addWorker()" class="ui-btn ui-btn-secondary px-3 py-1.5 text-xs font-bold">+ Pekerja</button>
                                    </div>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                        <x-ui.field label="Uraian pekerjaan" required>
                                            <x-ui.textarea name="work_description" rows="2" required>{{ old('work_description', $workDetails?->work_description ?: $transaction->work_description) }}</x-ui.textarea>
                                        </x-ui.field>
                                        <x-ui.field label="Lokasi pekerjaan" required>
                                            <x-ui.input name="work_location" :value="old('work_location', $workDetails?->work_location ?: $transaction->work_location)" required />
                                        </x-ui.field>
                                        <x-ui.field label="Tanggal mulai">
                                            <x-ui.input type="date" name="work_started_at" :value="old('work_started_at', $workDetails?->work_started_at?->format('Y-m-d') ?: $transactionDateLimit)" :max="$transactionDateLimit" />
                                        </x-ui.field>
                                        <x-ui.field label="Tanggal selesai">
                                            <x-ui.input type="date" name="work_completed_at" :value="old('work_completed_at', $workDetails?->work_completed_at?->format('Y-m-d') ?: $transactionDateLimit)" :max="$transactionDateLimit" />
                                        </x-ui.field>
                                        <x-ui.field label="Nomor SPK">
                                            <x-ui.input readonly class="ui-input-readonly" name="spk_number" :value="$workDetails?->spk_number ?: $transaction->spk_number" placeholder="Terbit setelah penomoran" />
                                        </x-ui.field>
                                        <x-ui.field label="Tanggal SPK">
                                            <x-ui.input type="date" name="spk_date" :value="old('spk_date', $workDetails?->spk_date?->format('Y-m-d') ?: $transactionDateLimit)" :max="$transactionDateLimit" />
                                        </x-ui.field>
                                        <x-ui.field label="Nomor RAB">
                                            <x-ui.input readonly class="ui-input-readonly" name="rab_number" :value="$workDetails?->rab_number ?: $transaction->rab_number" placeholder="Terbit setelah penomoran" />
                                        </x-ui.field>
                                        <x-ui.field label="Tanggal RAB">
                                            <x-ui.input type="date" name="rab_date" :value="old('rab_date', $workDetails?->rab_date?->format('Y-m-d') ?: $transactionDateLimit)" :max="$transactionDateLimit" />
                                        </x-ui.field>
                                    </div>
                                    <div class="mt-3 overflow-x-auto rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                                        <table class="min-w-full text-sm">
                                            <thead class="bg-[var(--ui-surface-muted)] text-left text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]"><tr><th class="px-3 py-2">Pekerja</th><th class="px-3 py-2">Uraian tugas</th><th class="px-3 py-2">Hari</th><th class="px-3 py-2">Tarif/hari</th><th class="px-3 py-2">Penerima kuitansi</th><th class="px-3 py-2 text-right">Aksi</th></tr></thead>
                                            <tbody class="divide-y divide-[var(--ui-line)]">
                                                <template x-for="(row, index) in rows" :key="index"><tr><td class="px-3 py-2"><input :name="`workers[${index}][name]`" x-model="row.name" class="ui-input w-full text-sm" placeholder="Nama pekerja"></td><td class="px-3 py-2"><input :name="`workers[${index}][job_description]`" x-model="row.job_description" class="ui-input w-full text-sm" placeholder="Jenis pekerjaan"></td><td class="px-3 py-2"><input type="number" min="0" step=".5" :name="`workers[${index}][work_days]`" x-model.number="row.work_days" class="ui-input w-20 text-right text-sm"></td><td class="px-3 py-2"><input type="hidden" :name="`workers[${index}][daily_rate]`" :value="row.daily_rate"><input type="text" inputmode="numeric" :value="new Intl.NumberFormat('id-ID').format(Number(row.daily_rate) || 0)" @input="row.daily_rate = Number($event.target.value.replace(/[^0-9]/g, '')); $event.target.value = new Intl.NumberFormat('id-ID').format(row.daily_rate)" class="ui-input w-32 text-right text-sm"></td><td class="px-3 py-2"><label class="inline-flex items-center gap-2 text-xs text-[var(--ui-fg)]"><input type="hidden" :name="`workers[${index}][is_receipt_recipient]`" value="0"><input type="checkbox" :name="`workers[${index}][is_receipt_recipient]`" value="1" x-model="row.is_receipt_recipient"> Ya</label></td><td class="px-3 py-2 text-right"><button type="button" @click="rows.splice(index, 1)" class="ui-btn ui-btn-ghost px-2 py-1 text-xs text-rose-700">Hapus</button></td></tr></template>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="mt-2 text-right text-xs font-semibold text-[var(--ui-fg-muted)]">Total rincian pekerja: <span class="text-[var(--ui-fg-strong)]" x-text="money(total())"></span></p>
                                </fieldset>
