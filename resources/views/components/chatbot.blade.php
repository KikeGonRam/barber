<div
    x-data="chatbotWidget()"
    x-init="init()"
    class="fixed bottom-6 left-6 z-[150]"
>
    {{-- Toggle button --}}
    <button
        @click="toggle()"
        class="relative h-14 w-14 rounded-full bg-gradient-to-br from-gold to-gold/70 text-black shadow-[0_8px_32px_rgba(212,175,55,0.35)] flex items-center justify-center hover:scale-110 active:scale-95 transition-all duration-300 group"
    >
        <svg x-show="!open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
        </svg>
        <svg x-show="open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>

        {{-- Unread badge --}}
        <span x-show="unread > 0 && !open"
              x-text="unread"
              class="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-red-500 text-white text-[9px] font-black flex items-center justify-center"
              style="display:none">
        </span>

        <span class="absolute left-full ml-3 px-3 py-1 bg-black/90 text-white text-[9px] font-black uppercase tracking-widest rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap border border-white/10 pointer-events-none">
            Asistente Virtual
        </span>
    </button>

    {{-- Chat window --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-6 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-6 scale-95"
        class="absolute bottom-[72px] left-0 w-[340px] sm:w-[380px] rounded-2xl border border-white/[0.08] bg-[#0d0d0d] shadow-[0_20px_60px_rgba(0,0,0,0.85)] overflow-hidden flex flex-col"
        style="display:none; height: min(560px, calc(100dvh - 100px));"
    >
        {{-- Header --}}
        <div class="px-5 py-4 border-b border-white/[0.06] bg-white/[0.03] flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-xl bg-gold/10 border border-gold/20 flex items-center justify-center shrink-0">
                    <svg class="h-5 w-5 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-[11px] font-black text-white uppercase tracking-widest">Concierge UrbanBlade</h3>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-[8px] font-bold text-white/30 uppercase tracking-widest">Asistente IA · En línea</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button @click="clearConversation()"
                        title="Limpiar conversación"
                        class="h-7 w-7 rounded-lg flex items-center justify-center text-white/20 hover:text-white/60 hover:bg-white/5 transition-all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </button>
                <button @click="toggle()"
                        class="h-7 w-7 rounded-lg flex items-center justify-center text-white/20 hover:text-white/60 hover:bg-white/5 transition-all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Messages --}}
        <div x-ref="chatBox" class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
            <template x-for="(msg, i) in messages" :key="i">
                <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start gap-2'">
                    {{-- Bot avatar --}}
                    <div x-show="msg.role === 'bot'"
                         class="h-6 w-6 rounded-lg bg-gold/10 border border-gold/20 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="h-3.5 w-3.5 text-gold/70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </div>

                    <div :class="msg.role === 'user'
                            ? 'bg-gold text-black rounded-2xl rounded-tr-sm max-w-[78%] px-3.5 py-2.5 text-[12px] font-medium leading-relaxed'
                            : 'bg-white/[0.06] text-white/90 border border-white/[0.06] rounded-2xl rounded-tl-sm max-w-[82%] px-3.5 py-2.5 text-[12px] leading-relaxed'"
                        style="white-space: pre-line;"
                        x-text="msg.text">
                    </div>
                </div>
            </template>

            {{-- Typing indicator --}}
            <div x-show="typing" class="flex justify-start gap-2">
                <div class="h-6 w-6 rounded-lg bg-gold/10 border border-gold/20 flex items-center justify-center shrink-0"></div>
                <div class="bg-white/[0.06] border border-white/[0.06] rounded-2xl rounded-tl-sm px-4 py-3 flex gap-1.5 items-center">
                    <span class="h-1.5 w-1.5 bg-white/40 rounded-full animate-bounce"></span>
                    <span class="h-1.5 w-1.5 bg-white/40 rounded-full animate-bounce [animation-delay:0.15s]"></span>
                    <span class="h-1.5 w-1.5 bg-white/40 rounded-full animate-bounce [animation-delay:0.3s]"></span>
                </div>
            </div>
        </div>

        {{-- Quick actions --}}
        <div x-show="messages.length <= 2"
             x-transition
             class="px-4 pb-3 flex flex-wrap gap-1.5 shrink-0">
            @auth
                @if(auth()->user()->hasRole('cliente'))
                    <button @click="send('¿Cuál es mi próxima cita?')" class="chip-action">Mi cita</button>
                    <button @click="send('¿Cuántos puntos tengo?')" class="chip-action">Mis puntos</button>
                    <button @click="send('¿Cómo cancelo mi cita?')" class="chip-action">Cancelar</button>
                    <button @click="send('¿Qué servicios tienen?')" class="chip-action">Servicios</button>
                @elseif(auth()->user()->hasRole('administrador'))
                    <button @click="send('¿Cuánto se ha facturado hoy?')" class="chip-action">Caja hoy</button>
                    <button @click="send('¿Cómo gestionar usuarios?')" class="chip-action">Usuarios</button>
                    <button @click="send('¿Cuáles son los barberos activos?')" class="chip-action">Barberos</button>
                @elseif(auth()->user()->hasRole('barbero'))
                    <button @click="send('¿Cómo subo trabajos al muro?')" class="chip-action">Muro</button>
                    <button @click="send('¿Cuáles son mis citas de hoy?')" class="chip-action">Mis citas</button>
                @else
                    <button @click="send('¿Qué servicios ofrecen?')" class="chip-action">Servicios</button>
                    <button @click="send('¿Cómo agendar una cita?')" class="chip-action">Reservar</button>
                    <button @click="send('¿Cuáles son los precios?')" class="chip-action">Precios</button>
                @endif
            @else
                <button @click="send('¿Cómo agendar una cita?')" class="chip-action">Reservar</button>
                <button @click="send('¿Cuáles son los precios?')" class="chip-action">Precios</button>
                <button @click="send('¿Dónde están ubicados?')" class="chip-action">Ubicación</button>
                <button @click="send('¿Qué métodos de pago aceptan?')" class="chip-action">Pagos</button>
            @endauth
        </div>

        {{-- Input --}}
        <div class="px-4 pb-4 pt-3 border-t border-white/[0.06] bg-white/[0.02] shrink-0">
            <div class="flex items-end gap-2">
                <textarea
                    x-model="input"
                    @keydown.enter.exact.prevent="send()"
                    @keydown.shift.enter="null"
                    @input="resize($el)"
                    rows="1"
                    placeholder="Escribe tu consulta..."
                    :disabled="typing"
                    class="flex-1 bg-white/[0.05] border border-white/10 rounded-xl px-3.5 py-2.5 text-[12px] text-white placeholder-white/20 focus:ring-1 focus:ring-gold/40 focus:border-gold/40 transition-all resize-none overflow-hidden leading-relaxed"
                    style="min-height: 40px; max-height: 120px;"
                ></textarea>
                <button
                    @click="send()"
                    :disabled="input.trim() === '' || typing"
                    :class="input.trim() === '' || typing ? 'opacity-30 cursor-not-allowed' : 'hover:bg-gold/90 hover:shadow-lg hover:shadow-gold/20'"
                    class="h-10 w-10 rounded-xl bg-gold flex items-center justify-center text-black transition-all shrink-0">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </button>
            </div>
            <p class="text-[8px] font-bold text-white/15 uppercase mt-2 tracking-widest text-center">Enter para enviar · Shift+Enter nueva línea</p>
        </div>
    </div>
</div>

<style>
.chip-action {
    @apply px-3 py-1.5 rounded-lg border border-white/[0.08] bg-white/[0.04] text-[9px] font-black uppercase tracking-widest text-white/40 hover:border-gold/40 hover:text-gold hover:bg-gold/5 transition-all;
}
</style>

<script>
function chatbotWidget() {
    return {
        open: false,
        messages: [
            { role: 'bot', text: '¡Hola! Soy el Concierge de UrbanBlade ✨\n¿En qué puedo ayudarte hoy?' }
        ],
        input: '',
        typing: false,
        unread: 0,

        init() {
            // Keyboard shortcut: Ctrl+/ to open/close
            document.addEventListener('keydown', (e) => {
                if (e.ctrlKey && e.key === '/') { e.preventDefault(); this.toggle(); }
            });
        },

        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.unread = 0;
                this.$nextTick(() => this.scrollBottom());
            }
        },

        async send(quickText = null) {
            const text = (quickText ?? this.input).trim();
            if (!text || this.typing) return;

            this.input = '';
            this.messages.push({ role: 'user', text });
            this.typing = true;
            this.$nextTick(() => this.scrollBottom());

            try {
                const res = await fetch('{{ route('chatbot.query') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ message: text })
                });

                const data = await res.json();

                if (res.status === 429) {
                    const wait = data.retry_after ? ` Intenta en ${data.retry_after}s.` : '';
                    this.messages.push({ role: 'bot', text: `⚠️ Demasiadas consultas seguidas.${wait}` });
                } else if (res.status === 422) {
                    this.messages.push({ role: 'bot', text: '✏️ Por favor escribe tu consulta.' });
                } else {
                    this.messages.push({ role: 'bot', text: data.response ?? '🤔 Sin respuesta disponible.' });
                }
            } catch {
                this.messages.push({ role: 'bot', text: '⚡ Problema de conexión. Intenta de nuevo.' });
            } finally {
                this.typing = false;
                if (!this.open) this.unread++;
                this.$nextTick(() => this.scrollBottom());
            }
        },

        clearConversation() {
            this.messages = [{ role: 'bot', text: 'Conversación reiniciada. ¿En qué puedo ayudarte?' }];

            // Notify server to clear history (best-effort)
            @auth
            fetch('{{ route('chatbot.clear-history') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
            }).catch(() => {});
            @endauth
        },

        scrollBottom() {
            const box = this.$refs.chatBox;
            if (box) box.scrollTop = box.scrollHeight;
        },

        resize(el) {
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 120) + 'px';
        },
    };
}
</script>
