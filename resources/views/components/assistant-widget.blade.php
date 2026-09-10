@php
    $widgetAskUrl = route('asisten.ask');
@endphp

<div
    x-data="{
        open: false,
        busy: false,
        status: 'idle',
        question: '',
        asked: '',
        answer: '',
        error: '',
        ask() {
            if (!this.question.trim() || this.busy) return;
            this.busy = true; this.status = 'pending'; this.answer = ''; this.error = '';
            this.asked = this.question.trim();
            fetch('{{ $widgetAskUrl }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                },
                body: JSON.stringify({ question: this.asked }),
            })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(data => this.poll(data.token))
            .catch(() => { this.busy = false; this.status = 'failed'; this.error = 'Gagal mengirim pertanyaan.'; });
        },
        poll(token) {
            fetch('{{ url('/asisten/status') }}/' + token, { headers: { 'Accept': 'application/json' } })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(data => {
                this.status = data.status;
                this.asked = data.question || this.asked;
                this.answer = data.answer || '';
                if (data.status === 'pending') setTimeout(() => this.poll(token), 3000);
                else this.busy = false;
            })
            .catch(() => { this.busy = false; this.status = 'failed'; this.error = 'Gagal memeriksa jawaban.'; });
        },
        reset() { this.status = 'idle'; this.question = ''; this.asked = ''; this.answer = ''; this.error = ''; },
    }"
    class="print:hidden"
>
    <button
        type="button"
        @click="open = !open"
        aria-label="Buka Asisten Operator"
        title="Asisten Operator"
        class="fixed bottom-5 right-5 z-40 grid h-12 w-12 place-items-center rounded-full shadow-lg transition hover:-translate-y-0.5"
        style="background: var(--theme-action-bg); color: var(--theme-action-fg)"
    >
        <span x-show="!open" class="text-xl font-black leading-none">?</span>
        <span x-show="open" class="text-xl leading-none">×</span>
    </button>

    <div
        x-show="open"
        x-transition.opacity
        class="fixed bottom-20 right-5 z-40 flex max-h-[70vh] w-[calc(100%-2.5rem)] max-w-sm flex-col overflow-hidden rounded-2xl border shadow-xl"
        style="background: var(--ui-surface-base); border-color: var(--ui-line)"
        role="dialog"
        aria-label="Asisten Operator"
    >
        <div class="flex items-center justify-between gap-2 border-b px-4 py-3" style="border-color: var(--ui-line)">
            <p class="text-sm font-bold" style="color: var(--ui-fg-strong)">Asisten Operator</p>
            <a href="{{ route('asisten.index') }}" class="text-xs font-semibold underline" style="color: var(--theme-content-accent)">Halaman penuh</a>
        </div>

        <div class="min-h-24 flex-1 overflow-y-auto px-4 py-3">
            <template x-if="status === 'idle'">
                <p class="text-sm" style="color: var(--ui-fg-muted)">Tanya apa saja soal pemakaian aplikasi. Dijawab AI lokal (1-2 menit).</p>
            </template>
            <template x-if="asked">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Anda</p>
                    <p class="mt-1 text-sm font-semibold" x-text="asked"></p>
                </div>
            </template>
            <template x-if="status === 'pending'">
                <p class="mt-3 text-sm" style="color: var(--ui-fg-muted)">Menunggu jawaban…</p>
            </template>
            <template x-if="status !== 'idle' && status !== 'pending'">
                <p class="mt-3 text-sm leading-6" style="white-space: pre-wrap" x-text="answer || error"></p>
            </template>
        </div>

        <div class="border-t px-4 py-3" style="border-color: var(--ui-line)">
            <div class="flex gap-2">
                <input
                    type="text"
                    x-model="question"
                    @keydown.enter.prevent="ask()"
                    :disabled="busy"
                    maxlength="500"
                    placeholder="Tulis pertanyaan…"
                    aria-label="Pertanyaan untuk asisten"
                    class="h-10 min-w-0 flex-1 rounded-lg border px-3 text-sm"
                    style="border-color: var(--ui-line-strong); background: var(--ui-surface-base)"
                >
                <button
                    type="button"
                    @click="ask()"
                    :disabled="busy || !question.trim()"
                    class="h-10 shrink-0 rounded-lg px-4 text-sm font-bold text-white disabled:opacity-40"
                    style="background: var(--theme-action-bg)"
                >Kirim</button>
            </div>
            <template x-if="status === 'done' || status === 'failed'">
                <button type="button" @click="reset()" class="mt-2 text-xs font-semibold underline" style="color: var(--theme-content-accent)">Tanya lagi</button>
            </template>
        </div>
    </div>
</div>
