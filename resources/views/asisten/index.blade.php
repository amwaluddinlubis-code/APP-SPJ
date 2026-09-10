<x-layouts.tailwind-app>
    <div class="mx-auto max-w-3xl space-y-5">
        <x-page-header
            title="Asisten Operator"
            subtitle="Tanya jawab seputar pemakaian aplikasi SPJ BOSP. Dijawab model AI lokal (Ollama) — bisa 1-2 menit."
            kicker="Bantuan"
            icon="info"
        />

        @if(!$ollamaReady)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">
                Asisten AI belum dikonfigurasi (OLLAMA_HOST/OLLAMA_MODEL kosong). Hubungi administrator.
            </div>
        @endif

        <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow-sm">
            <form method="POST" action="{{ route('asisten.ask') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="asisten-question" class="text-sm font-bold" style="color: var(--ui-fg-strong)">Pertanyaan Anda</label>
                    <textarea id="asisten-question" name="question" rows="3" required maxlength="500"
                        placeholder="Contoh: bagaimana cara memberi nomor SPJ per triwulan?"
                        class="mt-1 w-full rounded-lg border border-[var(--ui-line-strong)] px-3 py-2 text-base">{{ old('question') }}</textarea>
                    @error('question')
                        <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow hover:bg-indigo-700" @disabled(!$ollamaReady)>
                    Tanya Asisten
                </button>
            </form>
        </section>

        @if($token)
            <section
                class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow-sm"
                x-data="{ status: 'pending', answer: '', question: '' }"
                x-init="
                    const poll = () => fetch('{{ route('asisten.status', $token) }}')
                        .then(r => r.json())
                        .then(data => { status = data.status; question = data.question || ''; answer = data.answer || ''; if (status === 'pending') setTimeout(poll, 3000); })
                        .catch(() => { status = 'failed'; answer = 'Gagal memeriksa status jawaban.'; });
                    poll();
                "
            >
                <p class="text-xs font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Pertanyaan</p>
                <p class="mt-1 text-sm font-semibold" x-text="question"></p>
                <div class="mt-4 border-t border-[var(--ui-line)] pt-4">
                    <p class="text-xs font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Jawaban</p>
                    <template x-if="status === 'pending'">
                        <p class="mt-2 text-sm" style="color: var(--ui-fg-muted)">Model lokal sedang menjawab (biasanya 1-2 menit)… halaman memeriksa otomatis.</p>
                    </template>
                    <template x-if="status !== 'pending'">
                        <p class="mt-2 text-sm leading-6" style="white-space: pre-wrap" x-text="answer"></p>
                    </template>
                </div>
            </section>
        @endif
    </div>
</x-layouts.tailwind-app>
