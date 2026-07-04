<div
    x-data="{ 
        show: false, 
        message: '', 
        type: 'success',
        timer: null
    }"
    x-on:notify.window="
        message = $event.detail.message; 
        type = $event.detail.type || 'success';
        show = true;
        clearTimeout(timer);
        timer = setTimeout(() => show = false, 5000);
    "
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 transform translate-y-4"
    x-transition:enter-end="opacity-100 transform translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 transform translate-y-0"
    x-transition:leave-end="opacity-0 transform translate-y-4"
    class="fixed bottom-6 right-6 z-[100] max-w-sm w-full"
    style="display: none;"
    role="status"
    aria-live="polite"
>
    <div class="ui-card-premium p-4 flex items-center gap-4 shadow-[0_10px_40px_rgba(0,0,0,0.5)] border-white/10 glass-dark">
        <div :class="{
            'bg-green-500/20 text-green-400': type === 'success',
            'bg-red-500/20 text-red-400': type === 'error',
            'bg-gold/20 text-gold': type === 'info'
        }" class="h-10 w-10 rounded-full flex items-center justify-center border border-white/5">
            <template x-if="type === 'success'">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </template>
            <template x-if="type === 'error'">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </template>
            <template x-if="type === 'info'">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </template>
        </div>
        <div class="flex-1">
            <p class="text-[10px] font-black uppercase tracking-widest text-gold mb-0.5" x-text="type === 'success' ? 'Éxito' : (type === 'error' ? 'Error' : 'Notificación')"></p>
            <p class="text-sm font-bold text-white leading-tight" x-text="message"></p>
        </div>
        <button @click="show = false" class="text-muted hover:text-white transition" aria-label="Cerrar notificación">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>
</div>
