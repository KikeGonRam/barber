<div
    x-data="{ 
        open: false, 
        messages: [
            { role: 'bot', text: '¡Hola! Soy tu asistente de BarberPro. ¿En qué puedo ayudarte hoy?' }
        ],
        userInput: '',
        isTyping: false,
        
        async sendMessage() {
            if (this.userInput.trim() === '') return;
            
            const text = this.userInput;
            this.messages.push({ role: 'user', text: text });
            this.userInput = '';
            this.isTyping = true;
            
            // Simular respuesta del servidor
            try {
                const response = await fetch('{{ route('chatbot.query') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ message: text })
                });
                const data = await response.json();
                this.messages.push({ role: 'bot', text: data.response });
            } catch (e) {
                this.messages.push({ role: 'bot', text: 'Lo siento, tuve un problema técnico. ¿Puedes intentar de nuevo?' });
            } finally {
                this.isTyping = false;
                this.$nextTick(() => {
                    const container = this.$refs.chatContainer;
                    container.scrollTop = container.scrollHeight;
                });
            }
        },

        sendQuickQuery(text) {
            this.userInput = text;
            this.sendMessage();
        }
    }"
    class="fixed bottom-6 left-6 z-[150]"
>
    <!-- Chat Toggle Button -->
    <button 
        @click="open = !open"
        class="h-14 w-14 rounded-full bg-gradient-to-br from-gold to-gold-dim text-black shadow-[0_10px_40px_rgba(212,175,55,0.4)] flex items-center justify-center hover:scale-110 transition-all duration-300 relative group"
    >
        <svg x-show="!open" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
        <svg x-show="open" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        
        <!-- Tooltip -->
        <span class="absolute right-full mr-4 px-3 py-1 bg-black/80 text-white text-[10px] font-black uppercase tracking-widest rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap border border-white/10">Asistente Virtual</span>
    </button>

    <!-- Chat Window -->
    <div 
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-10 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-10 scale-95"
        class="absolute bottom-20 left-0 w-[350px] sm:w-[400px] h-[550px] ui-card-premium flex flex-col shadow-[0_20px_60px_rgba(0,0,0,0.8)] border-white/10 glass-dark"
        style="display: none;"
    >
        <!-- Header -->
        <div class="p-6 border-b border-white/5 bg-white/5">
            <div class="flex items-center gap-4">
                <div class="h-10 w-10 rounded-xl bg-gold/10 flex items-center justify-center text-gold border border-gold/20">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" /></svg>
                </div>
                <div>
                    <h3 class="text-sm font-black text-white uppercase tracking-widest">Concierge Digital</h3>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse"></span>
                        <span class="text-[9px] font-bold text-muted uppercase tracking-tighter">Soporte BarberPro</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages Area -->
        <div x-ref="chatContainer" class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar bg-black/20">
            <template x-for="(msg, index) in messages" :key="index">
                <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="msg.role === 'user' 
                        ? 'bg-gold text-black rounded-2xl rounded-tr-none shadow-lg' 
                        : 'bg-white/5 text-white border border-white/10 rounded-2xl rounded-tl-none shadow-md'" 
                        class="max-w-[85%] px-4 py-3 text-sm font-medium leading-relaxed"
                    >
                        <p x-text="msg.text"></p>
                    </div>
                </div>
            </template>
            
            <div x-show="isTyping" class="flex justify-start">
                <div class="bg-white/5 rounded-2xl px-4 py-3 flex gap-1 items-center border border-white/10">
                    <span class="h-1 w-1 bg-muted rounded-full animate-bounce"></span>
                    <span class="h-1 w-1 bg-muted rounded-full animate-bounce [animation-delay:0.2s]"></span>
                    <span class="h-1 w-1 bg-muted rounded-full animate-bounce [animation-delay:0.4s]"></span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div x-show="messages.length < 4" class="px-6 pb-4 flex flex-wrap gap-2 overflow-x-auto no-scrollbar">
            @auth
                <button @click="sendQuickQuery('¿Cómo cambio mi contraseña?')" class="px-3 py-1.5 rounded-lg border border-white/10 bg-white/5 text-[9px] font-black uppercase text-muted hover:border-gold/50 hover:text-gold transition-all">Seguridad</button>
                <button @click="sendQuickQuery('Ver mi próxima cita')" class="px-3 py-1.5 rounded-lg border border-white/10 bg-white/5 text-[9px] font-black uppercase text-muted hover:border-gold/50 hover:text-gold transition-all">Mi Agenda</button>
            @else
                <button @click="sendQuickQuery('¿Cómo agendar una cita?')" class="px-3 py-1.5 rounded-lg border border-white/10 bg-white/5 text-[9px] font-black uppercase text-muted hover:border-gold/50 hover:text-gold transition-all">Reserva</button>
                <button @click="sendQuickQuery('¿Cuáles son los precios?')" class="px-3 py-1.5 rounded-lg border border-white/10 bg-white/5 text-[9px] font-black uppercase text-muted hover:border-gold/50 hover:text-gold transition-all">Precios</button>
            @endauth
        </div>

        <!-- Input Area -->
        <div class="p-6 border-t border-white/5 bg-black/40">
            <form @submit.prevent="sendMessage" class="relative">
                <input 
                    x-model="userInput"
                    type="text" 
                    placeholder="Escribe tu duda aquí..." 
                    class="w-full bg-white/5 border border-white/10 rounded-xl pl-4 pr-12 py-3 text-sm text-white placeholder-muted focus:ring-1 focus:ring-gold/50 focus:border-gold/50 transition-all"
                >
                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 h-8 w-8 flex items-center justify-center text-gold hover:text-white transition">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" /></svg>
                </button>
            </form>
        </div>
    </div>
</div>
