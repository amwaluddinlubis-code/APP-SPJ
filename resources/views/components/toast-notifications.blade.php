@php
    $initialNotifications = collect([
        ['type' => 'success', 'message' => session('success')],
        ['type' => 'error', 'message' => session('error')],
        ['type' => 'warning', 'message' => session('warning')],
        ['type' => 'info', 'message' => session('info') ?: session('status')],
        ['type' => 'error', 'message' => isset($errors) && $errors->any() ? $errors->first() : null],
    ])->filter(fn ($item) => filled($item['message']))->values()->all();
@endphp
<div class="pointer-events-none fixed inset-x-4 top-20 z-[100] flex flex-col items-end gap-3 sm:left-auto sm:right-6 sm:w-[30rem]"
    x-data="{ notifications: @js($initialNotifications), push(detail) { const item={id:Date.now()+Math.random(),type:detail.type||'info',message:detail.message||''}; if(!item.message)return; this.notifications.push(item); window.setTimeout(()=>this.remove(item.id),6500); }, remove(id) { this.notifications=this.notifications.filter(item=>item.id!==id); } }"
    x-init="notifications=notifications.map(item=>({...item,id:Date.now()+Math.random()})); notifications.forEach((item,index)=>window.setTimeout(()=>remove(item.id),6500+(index*350)))"
    x-on:app-notify.window="push($event.detail)" aria-live="polite" aria-atomic="true">
    <template x-for="item in notifications" :key="item.id">
        <div class="pointer-events-auto w-full overflow-hidden rounded-2xl border bg-white shadow-2xl ring-1 ring-black/5"
            x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-8 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0 opacity-100" x-transition:leave-end="translate-x-8 opacity-0"
            :class="{'border-emerald-300':item.type==='success','border-rose-300':item.type==='error','border-amber-300':item.type==='warning','theme-border':!['success','error','warning'].includes(item.type)}" role="alert">
            <div class="flex items-start gap-4 p-5">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-xl font-black text-white shadow-sm"
                    :class="{'bg-emerald-600':item.type==='success','bg-rose-600':item.type==='error','bg-amber-500':item.type==='warning','theme-bg':!['success','error','warning'].includes(item.type)}"
                    x-text="item.type==='success'?'✓':(item.type==='error'?'!':(item.type==='warning'?'⚠':'i'))"></span>
                <div class="min-w-0 flex-1">
                    <p class="text-base font-extrabold" :class="{'text-emerald-800':item.type==='success','text-rose-800':item.type==='error','text-amber-800':item.type==='warning','theme-text':!['success','error','warning'].includes(item.type)}"
                        x-text="item.type==='success'?'Berhasil':(item.type==='error'?'Terjadi kesalahan':(item.type==='warning'?'Perhatian':'Informasi'))"></p>
                    <p class="mt-1 text-sm leading-6 text-slate-700" x-text="item.message"></p>
                </div>
                <button type="button" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" @click="remove(item.id)" aria-label="Tutup notifikasi">✕</button>
            </div>
            <div class="h-1" :class="{'bg-emerald-500':item.type==='success','bg-rose-500':item.type==='error','bg-amber-400':item.type==='warning','theme-bg':!['success','error','warning'].includes(item.type)}"></div>
        </div>
    </template>
</div>
