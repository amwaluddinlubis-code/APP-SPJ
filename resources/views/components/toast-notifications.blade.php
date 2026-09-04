@vite('resources/js/theme-profiles.js')

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
        <div class="ui-toast pointer-events-auto w-full overflow-hidden"
            :class="{'ui-toast-success':item.type==='success','ui-toast-danger':item.type==='error','ui-toast-warning':item.type==='warning','ui-toast-theme':!['success','error','warning'].includes(item.type)}"
            x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-8 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0 opacity-100" x-transition:leave-end="translate-x-8 opacity-0" role="alert">
            <div class="flex items-start gap-4 p-5">
                <span class="ui-toast-icon grid h-11 w-11 shrink-0 place-items-center text-xl font-black"
                    x-text="item.type==='success'?'✓':(item.type==='error'?'!':(item.type==='warning'?'⚠':'i'))"></span>
                <div class="min-w-0 flex-1">
                    <p class="ui-toast-title text-base font-extrabold"
                        x-text="item.type==='success'?'Berhasil':(item.type==='error'?'Ada yang perlu diperbaiki':(item.type==='warning'?'Perhatian':'Informasi'))"></p>
                    <p class="ui-toast-message mt-1 text-sm leading-6" x-text="item.message"></p>
                </div>
                <button type="button" class="ui-toast-close" @click="remove(item.id)" aria-label="Tutup pemberitahuan">✕</button>
            </div>
            <div class="ui-toast-progress h-1"></div>
        </div>
    </template>
</div>

<x-ui-language />
