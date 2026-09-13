<div
    x-data="{
        open: false,
        reference: '',
        loading: false,
        error: '',
        result: null,
        async openChecker() {
            this.open = true;
            this.error = '';
            this.result = null;
            await this.$nextTick();
            this.$refs.referenceInput?.focus();
        },
        closeChecker() {
            this.open = false;
        },
        async inspect() {
            const reference = this.reference.trim();
            if (!reference) {
                this.error = 'Masukkan nomor dokumen, nomor SPJ, atau No. Bukti terlebih dahulu.';
                this.result = null;
                return;
            }

            this.loading = true;
            this.error = '';
            this.result = null;

            try {
                const url = new URL(this.$root.dataset.endpoint, window.location.origin);
                url.searchParams.set('placeholder_reference', reference);

                const response = await fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const validationMessage = payload.errors
                        ? Object.values(payload.errors).flat()[0]
                        : null;
                    throw new Error(validationMessage || payload.message || 'Data placeholder tidak dapat dimuat.');
                }

                this.result = payload;
            } catch (exception) {
                this.error = exception instanceof Error
                    ? exception.message
                    : 'Data placeholder tidak dapat dimuat.';
            } finally {
                this.loading = false;
            }
        },
        async copyMarker(marker) {
            if (!navigator.clipboard) {
                return;
            }

            await navigator.clipboard.writeText(marker);
        },
    }"
    x-on:open-placeholder-checker.window="openChecker()"
    data-endpoint="{{ route('document-templates.index') }}"
>
    <div
        x-show="open"
        x-cloak
        class="ui-modal-backdrop"
        x-on:keydown.escape.window="closeChecker()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="placeholder-checker-title"
    >
        <div class="ui-modal w-full" style="max-width: 72rem; max-height: calc(100vh - 2rem); display: flex; flex-direction: column;" x-on:click.outside="closeChecker()">
            <div class="ui-modal-header">
                <div>
                    <h2 id="placeholder-checker-title" class="ui-modal-title">Cek Placeholder</h2>
                    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">
                        Masukkan nomor dokumen/SPJ atau No. Bukti. Nilai di bawah memakai resolver yang sama dengan generator dokumen.
                    </p>
                </div>
            </div>

            <div class="ui-modal-body space-y-4" style="overflow-y: auto;">
                <form class="flex flex-col gap-3 sm:flex-row sm:items-end" x-on:submit.prevent="inspect()">
                    <div class="min-w-0 flex-1">
                        <x-ui.field label="Nomor Dokumen / No. Bukti" for="placeholder_reference"
                            hint="Contoh: nomor SPJ, nomor SPK/Pesanan yang sudah terbit, atau No. Bukti BKU.">
                            <x-ui.input
                                id="placeholder_reference"
                                x-ref="referenceInput"
                                x-model="reference"
                                autocomplete="off"
                                placeholder="Masukkan nomor dokumen..."
                            />
                        </x-ui.field>
                    </div>
                    <x-ui.button type="submit" x-bind:disabled="loading">
                        <span x-show="!loading">Cek Placeholder</span>
                        <span x-show="loading">Memuat...</span>
                    </x-ui.button>
                </form>

                <div
                    x-show="error"
                    x-cloak
                    class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"
                    role="alert"
                    x-text="error"
                ></div>

                <div x-show="loading" x-cloak class="py-3">
                    <x-ui.loading label="Mengambil nilai placeholder..." />
                </div>

                <template x-if="result">
                    <div class="space-y-4">
                        <section class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
                            <div class="grid gap-3 text-xs sm:grid-cols-2 lg:grid-cols-5">
                                <div>
                                    <p class="font-semibold text-[var(--ui-fg-muted)]">Nomor SPJ</p>
                                    <p class="mt-1 font-mono font-bold text-[var(--ui-fg-strong)]" x-text="result.package.document_number"></p>
                                </div>
                                <div>
                                    <p class="font-semibold text-[var(--ui-fg-muted)]">No. Bukti</p>
                                    <p class="mt-1 font-mono font-bold text-[var(--ui-fg-strong)]" x-text="result.package.no_bukti"></p>
                                </div>
                                <div>
                                    <p class="font-semibold text-[var(--ui-fg-muted)]">Kategori</p>
                                    <p class="mt-1 font-bold text-[var(--ui-fg-strong)]" x-text="result.package.category"></p>
                                </div>
                                <div>
                                    <p class="font-semibold text-[var(--ui-fg-muted)]">Status Paket</p>
                                    <p class="mt-1 font-bold text-[var(--ui-fg-strong)]" x-text="result.package.status"></p>
                                </div>
                                <div>
                                    <p class="font-semibold text-[var(--ui-fg-muted)]">Tanggal Transaksi</p>
                                    <p class="mt-1 font-bold text-[var(--ui-fg-strong)]" x-text="result.package.transaction_date"></p>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-[var(--ui-fg-muted)]">
                                <span class="font-bold" x-text="result.total"></span> placeholder tersedia. Nilai <span class="font-mono">-</span> berarti data belum tersedia pada paket ini.
                            </p>
                        </section>

                        <template x-for="group in result.groups" x-bind:key="group.name">
                            <section class="overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                                <header class="border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-4 py-3">
                                    <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]" x-text="group.name"></h3>
                                </header>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-[var(--ui-surface-muted)]">
                                            <tr class="text-left text-xs font-bold text-[var(--ui-fg-muted)]">
                                                <th class="w-[24rem] px-4 py-2.5">Placeholder</th>
                                                <th class="px-4 py-2.5">Nilai Aktual</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-[var(--ui-line)]">
                                            <template x-for="placeholder in group.placeholders" x-bind:key="placeholder.key">
                                                <tr>
                                                    <td class="px-4 py-3 align-top">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <code class="rounded bg-[var(--ui-surface-muted)] px-2 py-1 text-xs font-bold text-[var(--theme-content-accent)]" x-text="placeholder.marker"></code>
                                                            <span
                                                                x-show="placeholder.kind !== 'scalar'"
                                                                class="rounded-full border border-[var(--ui-line)] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]"
                                                                x-text="placeholder.kind === 'repeat' ? 'baris berulang' : 'gambar'"
                                                            ></span>
                                                            <button
                                                                type="button"
                                                                class="text-xs font-semibold text-[var(--theme-content-accent)] hover:underline"
                                                                x-on:click="copyMarker(placeholder.marker)"
                                                            >Salin</button>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 align-top">
                                                        <pre class="whitespace-pre-wrap break-words font-sans text-xs leading-5 text-[var(--ui-fg)]" x-text="placeholder.value"></pre>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </template>
                    </div>
                </template>
            </div>

            <div class="ui-modal-actions">
                <x-ui.button type="button" variant="secondary" x-on:click="closeChecker()">Tutup</x-ui.button>
            </div>
        </div>
    </div>
</div>
