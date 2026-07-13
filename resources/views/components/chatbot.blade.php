{{--
    Chatbot widget — Alpine x-teleport mueve la ventana al <body> directamente,
    evitando cualquier problema de stacking context / z-index del padre.
--}}
<div x-data="chatbotWidget()" x-init="init()">

    {{-- ── Botón flotante ────────────────────────────────────── --}}
    <button
        @click="toggle()"
        class="fixed bottom-6 left-6 z-[200] h-14 w-14 rounded-full bg-gradient-to-br from-gold to-gold/70 text-black shadow-[0_8px_32px_rgba(212,175,55,0.4)] flex items-center justify-center hover:scale-110 active:scale-95 transition-all duration-300 group"
        title="Asistente Virtual"
    >
        <svg x-show="!open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
        </svg>
        <svg x-show="open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>

        {{-- Badge mensajes no leídos --}}
        <span x-show="unread > 0 && !open" x-text="unread"
              class="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-red-500 text-white text-[9px] font-black flex items-center justify-center"
              style="display:none"></span>
    </button>

    {{-- ── Ventana de chat — teleportada al body ─────────────── --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-95"
            class="fixed z-[199] flex flex-col rounded-2xl border border-white/[0.08] bg-[#0d0d0d] shadow-[0_20px_60px_rgba(0,0,0,0.9)] overflow-hidden"
            style="bottom: 88px; left: 24px; width: min(390px, calc(100vw - 48px)); height: min(540px, calc(100dvh - 120px)); display: none;"
        >
            {{-- Header --}}
            <div class="px-5 py-4 border-b border-white/[0.06] bg-white/[0.03] flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-gold/10 border border-gold/20 flex items-center justify-center shrink-0">
                        <svg class="h-5 w-5 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-[11px] font-black text-white uppercase tracking-widest">Concierge UrbanBlade</h3>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-[8px] font-bold text-white/50 uppercase tracking-widest">Asistente IA · En línea</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button @click="clearConversation()" title="Nueva conversación" aria-label="Nueva conversación"
                            class="h-7 w-7 rounded-lg flex items-center justify-center text-white/45 hover:text-white/60 hover:bg-white/5 transition-all">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>
                    <button @click="toggle()" aria-label="Cerrar chat"
                            class="h-7 w-7 rounded-lg flex items-center justify-center text-white/45 hover:text-white/60 hover:bg-white/5 transition-all">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Mensajes --}}
            <div x-ref="chatBox" class="flex-1 overflow-y-auto px-4 py-4 space-y-3 overscroll-contain">
                <template x-for="(msg, i) in messages" :key="i">
                    <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start gap-2'">
                        <div x-show="msg.role === 'bot'"
                             class="h-6 w-6 rounded-lg bg-gold/10 border border-gold/20 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="h-3 w-3 text-gold/60" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2a2 2 0 012 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 017 7h1a1 1 0 110 2h-1v1a2 2 0 01-2 2H5a2 2 0 01-2-2v-1H2a1 1 0 110-2h1a7 7 0 017-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 012-2zM9 9a5 5 0 00-5 5v3h16v-3a5 5 0 00-5-5H9z"/>
                            </svg>
                        </div>
                        <div
                            :class="msg.role === 'user'
                                ? 'bg-gold text-black rounded-2xl rounded-tr-sm max-w-[78%] px-3.5 py-2.5 text-[12px] font-medium leading-relaxed'
                                : 'bg-white/[0.06] text-white/90 border border-white/[0.06] rounded-2xl rounded-tl-sm max-w-[82%] px-3.5 py-2.5 text-[12px] leading-relaxed'"
                            style="white-space: pre-line; word-break: break-word;"
                            x-text="msg.text">
                        </div>
                    </div>
                </template>

                {{-- Typing --}}
                <div x-show="typing" class="flex justify-start gap-2" style="display:none">
                    <div class="h-6 w-6 rounded-lg bg-gold/10 border border-gold/20 flex items-center justify-center shrink-0"></div>
                    <div class="bg-white/[0.06] border border-white/[0.06] rounded-2xl rounded-tl-sm px-4 py-3 flex gap-1.5 items-center">
                        <span class="h-1.5 w-1.5 bg-white/40 rounded-full animate-bounce" style="animation-delay:0s"></span>
                        <span class="h-1.5 w-1.5 bg-white/40 rounded-full animate-bounce" style="animation-delay:0.15s"></span>
                        <span class="h-1.5 w-1.5 bg-white/40 rounded-full animate-bounce" style="animation-delay:0.3s"></span>
                    </div>
                </div>
            </div>

            {{-- Acciones rápidas --}}
            <div x-show="messages.length <= 2" class="px-4 pb-3 flex flex-wrap gap-1.5 shrink-0">
                @auth
                    @if(auth()->user()->hasRole('cliente'))
                        <button @click="quickSend('¿Cuál es mi próxima cita?')" class="quick-chip">Mi cita</button>
                        <button @click="quickSend('¿Cuántos puntos tengo?')" class="quick-chip">Mis puntos</button>
                        <button @click="quickSend('¿Cuáles son los servicios?')" class="quick-chip">Servicios</button>
                        <button @click="quickSend('¿Cómo cancelo mi cita?')" class="quick-chip">Cancelar</button>
                    @elseif(auth()->user()->hasRole('administrador'))
                        <button @click="quickSend('¿Cuánto se facturó hoy?')" class="quick-chip">Caja hoy</button>
                        <button @click="quickSend('¿Cuáles son los barberos activos?')" class="quick-chip">Barberos</button>
                        <button @click="quickSend('¿Cómo gestionar usuarios?')" class="quick-chip">Usuarios</button>
                    @elseif(auth()->user()->hasRole('barbero'))
                        <button @click="quickSend('¿Cómo subo trabajos al muro?')" class="quick-chip">Muro</button>
                        <button @click="quickSend('¿Cuáles son mis citas de hoy?')" class="quick-chip">Mis citas</button>
                    @else
                        <button @click="quickSend('¿Qué servicios ofrecen?')" class="quick-chip">Servicios</button>
                        <button @click="quickSend('¿Cómo agendar una cita?')" class="quick-chip">Reservar</button>
                    @endif
                @else
                    <button @click="quickSend('¿Cómo agendar una cita?')" class="quick-chip">Reservar</button>
                    <button @click="quickSend('¿Cuáles son los precios?')" class="quick-chip">Precios</button>
                    <button @click="quickSend('¿Dónde están ubicados?')" class="quick-chip">Ubicación</button>
                    <button @click="quickSend('¿Qué métodos de pago aceptan?')" class="quick-chip">Pagos</button>
                @endauth
            </div>

            {{-- Input --}}
            <div class="px-4 pb-4 pt-2 border-t border-white/[0.06] shrink-0">
                <div class="flex items-end gap-2">
                    <textarea
                        x-model="input"
                        x-ref="inputBox"
                        @keydown.enter.exact.prevent="send()"
                        @input="$el.style.height='auto'; $el.style.height=Math.min($el.scrollHeight,100)+'px'"
                        :disabled="typing"
                        rows="1"
                        placeholder="Escribe tu consulta…"
                        class="flex-1 bg-white/[0.05] border border-white/10 rounded-xl px-3.5 py-2.5 text-[12px] text-white placeholder-white/20 focus:ring-1 focus:ring-gold/40 focus:border-gold/40 transition-all resize-none overflow-hidden leading-relaxed"
                        style="min-height:40px; max-height:100px;"
                    ></textarea>
                    <button
                        @click="send()"
                        :disabled="!input.trim() || typing"
                        :class="!input.trim() || typing ? 'opacity-30 cursor-not-allowed' : 'hover:bg-gold/90'"
                        class="h-10 w-10 rounded-xl bg-gold flex items-center justify-center text-black transition-all shrink-0"
                        aria-label="Enviar mensaje">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </div>
                <p class="text-[8px] font-bold text-white/15 uppercase mt-2 tracking-widest text-center">
                    Enter para enviar · Shift+Enter nueva línea
                </p>
            </div>
        </div>
    </template>
</div>

<style>
.quick-chip {
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.04);
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgba(255,255,255,0.4);
    cursor: pointer;
    transition: all 0.15s;
}
.quick-chip:hover {
    border-color: rgba(212,175,55,0.4);
    color: #D4AF37;
    background: rgba(212,175,55,0.06);
}
</style>

<script>
function chatbotWidget() {
    return {
        open: false,
        messages: [
            { role: 'bot', text: '¡Hola! Soy el Concierge de UrbanBlade.\n¿En qué puedo ayudarte hoy?' }
        ],
        input: '',
        typing: false,
        unread: 0,

        init() {
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === '/') {
                    e.preventDefault();
                    this.toggle();
                }
            });
        },

        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.unread = 0;
                this.$nextTick(() => {
                    this.scrollBottom();
                    this.$refs.inputBox?.focus();
                });
            }
        },

        quickSend(text) {
            this.input = text;
            this.send();
        },

        async send() {
            const text = this.input.trim();
            if (!text || this.typing) return;

            this.input = '';
            if (this.$refs.inputBox) {
                this.$refs.inputBox.style.height = '40px';
            }
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
                    const secs = data.retry_after ? ` Espera ${data.retry_after}s.` : '';
                    this.messages.push({ role: 'bot', text: 'Demasiadas consultas seguidas.' + secs });
                } else if (res.status === 422) {
                    this.messages.push({ role: 'bot', text: 'Escribe tu consulta primero.' });
                } else {
                    this.messages.push({ role: 'bot', text: data.response ?? 'Sin respuesta disponible.' });
                }
            } catch {
                this.messages.push({ role: 'bot', text: 'Sin conexión. Intenta de nuevo.' });
            } finally {
                this.typing = false;
                if (!this.open) this.unread++;
                this.$nextTick(() => this.scrollBottom());
            }
        },

        clearConversation() {
            this.messages = [{ role: 'bot', text: 'Conversación reiniciada. ¿En qué puedo ayudarte?' }];
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
    };
}
</script>
