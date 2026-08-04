<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="ui-title">Preferencias de <span class="text-gold">notificación</span></h2>
            <p class="ui-subtitle">Elige cómo y para qué quieres que te contactemos.</p>
        </div>
    </x-slot>

    <div class="py-4 max-w-2xl">
        @if(session('status'))
            <div class="mb-6 rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm font-bold text-emerald-400">
                {{ session('status') }}
            </div>
        @endif

        <div x-data="{
                soundEnabled: localStorage.getItem('ub_notif_sound') === null ? true : localStorage.getItem('ub_notif_sound') === 'true',
                soundType: localStorage.getItem('ub_notif_sound_type') || 'chime',
                soundOptions: [
                    { key: 'chime', label: 'Carillón' },
                    { key: 'bell',  label: 'Campana'  },
                    { key: 'soft',  label: 'Suave'    },
                    { key: 'ping',  label: 'Ping'     },
                ],
                savePrefs() {
                    localStorage.setItem('ub_notif_sound', this.soundEnabled ? 'true' : 'false');
                    localStorage.setItem('ub_notif_sound_type', this.soundType);
                },
                toggleSound() { this.soundEnabled = !this.soundEnabled; this.savePrefs(); },
                setSoundType(t) { this.soundType = t; this.savePrefs(); this.playSound(t); },
                playSound(type) {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const sounds = {
                            chime(ctx) { [[880, 0], [1100, 0.13], [1320, 0.26]].forEach(([freq, delay]) => { const osc = ctx.createOscillator(); const gain = ctx.createGain(); osc.connect(gain); gain.connect(ctx.destination); osc.type = 'sine'; osc.frequency.value = freq; const t0 = ctx.currentTime + delay; gain.gain.setValueAtTime(0, t0); gain.gain.linearRampToValueAtTime(0.22, t0 + 0.03); gain.gain.exponentialRampToValueAtTime(0.001, t0 + 0.65); osc.start(t0); osc.stop(t0 + 0.65); }); },
                            bell(ctx) { [[440, 0.18], [880, 0.11], [1320, 0.06]].forEach(([freq, vol]) => { const osc = ctx.createOscillator(); const gain = ctx.createGain(); osc.connect(gain); gain.connect(ctx.destination); osc.type = 'sine'; osc.frequency.value = freq; gain.gain.setValueAtTime(vol, ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1.8); osc.start(); osc.stop(ctx.currentTime + 1.8); }); },
                            soft(ctx) { [[523, 0, 0.12], [659, 0.22, 0.10]].forEach(([freq, delay, vol]) => { const osc = ctx.createOscillator(); const gain = ctx.createGain(); osc.connect(gain); gain.connect(ctx.destination); osc.type = 'sine'; osc.frequency.value = freq; const t0 = ctx.currentTime + delay; gain.gain.setValueAtTime(0, t0); gain.gain.linearRampToValueAtTime(vol, t0 + 0.08); gain.gain.exponentialRampToValueAtTime(0.001, t0 + 1.0); osc.start(t0); osc.stop(t0 + 1.0); }); },
                            ping(ctx) { const osc = ctx.createOscillator(); const gain = ctx.createGain(); osc.connect(gain); gain.connect(ctx.destination); osc.type = 'triangle'; osc.frequency.setValueAtTime(1568, ctx.currentTime); osc.frequency.exponentialRampToValueAtTime(784, ctx.currentTime + 0.22); gain.gain.setValueAtTime(0.28, ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.22); osc.start(); osc.stop(ctx.currentTime + 0.22); },
                        };
                        (sounds[type] || sounds.chime)(ctx);
                    } catch (e) {}
                },
             }"
             class="ui-card-premium p-6 sm:p-8 mb-6">
            <p class="text-[10px] font-black uppercase tracking-widest text-muted mb-2">Sonido</p>
            <div class="flex items-center justify-between py-2 border-b border-white/5">
                <div>
                    <p class="text-sm font-bold text-white">Sonido de notificaciones</p>
                    <p class="text-[11px] text-muted mt-0.5" x-text="soundEnabled ? 'Activado' : 'Desactivado'"></p>
                </div>
                <button @click="toggleSound()"
                        :class="soundEnabled ? 'bg-gold' : 'bg-white/10'"
                        class="relative h-5 w-9 rounded-full transition-colors duration-250 focus:outline-none shrink-0">
                    <span :class="soundEnabled ? 'translate-x-4' : 'translate-x-0.5'"
                          class="absolute top-0.5 left-0 h-4 w-4 rounded-full bg-white shadow-md transition-transform duration-200 block"></span>
                </button>
            </div>
            <div x-show="soundEnabled" class="pt-4">
                <p class="text-[10px] font-black uppercase tracking-widest text-muted mb-2">Tipo de sonido</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 mb-3">
                    <template x-for="s in soundOptions" :key="s.key">
                        <button
                            @click="setSoundType(s.key)"
                            :class="soundType === s.key
                                ? 'border-gold/50 bg-gold/10 text-gold'
                                : 'border-white/10 text-muted hover:border-white/25 hover:text-white/80'"
                            class="flex flex-col items-center gap-0.5 px-2 py-2 rounded-xl border text-center transition"
                        >
                            <span class="w-1.5 h-1.5 rounded-full bg-current opacity-60 mb-0.5"></span>
                            <span class="text-[9px] font-black uppercase tracking-wider" x-text="s.label"></span>
                        </button>
                    </template>
                </div>
                <button @click="playSound(soundType)"
                        class="w-full py-2 rounded-xl border border-gold/20 text-[9px] font-black uppercase tracking-widest text-gold/60 hover:text-gold hover:border-gold/40 hover:bg-gold/5 transition">
                    ▶ Probar sonido
                </button>
            </div>
        </div>

        <form method="POST" action="{{ route('notifications.preferences.update') }}" class="ui-card-premium p-6 sm:p-8 space-y-2">
            @csrf
            @method('PATCH')

            @php
                $channels = [
                    ['key' => 'in_app',      'label' => 'Notificaciones en la app', 'desc' => 'Avisos dentro de tu panel.'],
                    ['key' => 'email',       'label' => 'Correo electrónico',        'desc' => 'Confirmaciones, recibos y recordatorios por email.'],
                    ['key' => 'sms',         'label' => 'SMS',                        'desc' => 'Mensajes de texto. Requiere teléfono registrado.'],
                    ['key' => 'whatsapp',    'label' => 'WhatsApp',                   'desc' => 'Avisos por WhatsApp. Requiere teléfono registrado.'],
                ];
            @endphp

            <p class="text-[10px] font-black uppercase tracking-widest text-muted mb-2">Canales</p>
            @foreach($channels as $ch)
                <label for="pref_{{ $ch['key'] }}" class="flex items-center justify-between gap-4 py-3.5 border-b border-white/5 cursor-pointer group">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-white">{{ $ch['label'] }}</p>
                        <p class="text-[11px] text-muted mt-0.5">{{ $ch['desc'] }}</p>
                    </div>
                    <div class="relative shrink-0">
                        <input type="checkbox" id="pref_{{ $ch['key'] }}" name="{{ $ch['key'] }}" value="1"
                               class="peer sr-only" @checked($prefs[$ch['key']] ?? false)>
                        <div class="h-6 w-11 rounded-full bg-white/10 border border-white/10 peer-checked:bg-gold/80 peer-checked:border-gold transition-all"></div>
                        <div class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition-all peer-checked:translate-x-5"></div>
                    </div>
                </label>
            @endforeach

            <p class="text-[10px] font-black uppercase tracking-widest text-muted pt-5 pb-2">Marketing</p>
            <label for="pref_promociones" class="flex items-center justify-between gap-4 py-3.5 cursor-pointer group">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-white">Promociones y ofertas</p>
                    <p class="text-[11px] text-muted mt-0.5">Novedades, descuentos y campañas. Puedes darte de baja cuando quieras.</p>
                </div>
                <div class="relative shrink-0">
                    <input type="checkbox" id="pref_promociones" name="promociones" value="1"
                           class="peer sr-only" @checked($prefs['promociones'] ?? false)>
                    <div class="h-6 w-11 rounded-full bg-white/10 border border-white/10 peer-checked:bg-gold/80 peer-checked:border-gold transition-all"></div>
                    <div class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition-all peer-checked:translate-x-5"></div>
                </div>
            </label>

            <div class="pt-6 flex justify-end">
                <button type="submit" class="ui-btn px-8 py-3 text-[11px] tracking-widest">Guardar preferencias</button>
            </div>
        </form>
    </div>
</x-app-layout>
