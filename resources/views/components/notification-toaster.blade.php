@auth
@php
    $u           = \Illuminate\Support\Facades\Auth::user();
    $notifUnread = $u?->unreadNotifications()->count() ?? 0;
    $notifRole   = 'cliente';
    if ($u?->hasRole('administrador'))     $notifRole = 'administrador';
    elseif ($u?->hasRole('recepcionista')) $notifRole = 'recepcionista';
    elseif ($u?->hasRole('barbero'))       $notifRole = 'barbero';

    $notifRoutes = [
        'appointment' => [
            'administrador' => route('appointments.index'),
            'recepcionista' => route('appointments.index'),
            'barbero'       => route('barber.agenda'),
            'cliente'       => route('client.appointments.index'),
        ],
        'payment' => [
            'administrador' => route('payments.index'),
            'recepcionista' => route('payments.index'),
            'barbero'       => route('dashboard'),
            'cliente'       => route('client.facturas.index'),
        ],
        'default' => route('notifications.index'),
    ];
@endphp

{{-- Config injected via <script> so JSON double-quotes don't break the x-data HTML attribute --}}
<script>
    window.__notifCfg = {
        initialUnread: {{ $notifUnread }},
        role:    @json($notifRole),
        pollUrl: @json(route('notifications.poll')),
        routes:  @json($notifRoutes),
    };

    document.addEventListener('alpine:init', () => {
        Alpine.store('notif', { unread: {{ $notifUnread }} });
    });
</script>

<div x-data="notifToaster()" x-init="init()">

    {{-- ══════════════════════════════════════════
         TOAST STACK — fixed top-right corner
    ══════════════════════════════════════════ --}}
    <div class="fixed top-5 right-5 z-[9999] w-[22rem] max-w-[calc(100vw-2.5rem)] space-y-2.5 pointer-events-none">
        <template x-for="t in visibleToasts" :key="t.uid">
            <div
                x-show="t.visible"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                class="pointer-events-auto relative overflow-hidden rounded-2xl border shadow-2xl shadow-black/60 cursor-pointer group"
                :class="t.isPayment
                    ? 'bg-[#0b1a10] border-emerald-500/30'
                    : 'bg-[#1a1409] border-gold/30'"
                @click="openToast(t)"
            >
                {{-- Animated progress bar --}}
                <div class="absolute inset-x-0 top-0 h-[2px]"
                     :class="t.isPayment ? 'bg-emerald-900/30' : 'bg-gold/10'">
                    <div class="h-full"
                         :class="t.isPayment ? 'bg-emerald-400' : 'bg-gold'"
                         :style="`width:${t.progress}%;transition:width 0.06s linear`">
                    </div>
                </div>

                <div class="flex gap-3.5 p-4 pt-[18px]">
                    {{-- Icon --}}
                    <div class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0 ring-1 transition-transform group-hover:scale-110"
                         :class="t.isPayment
                            ? 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20'
                            : 'bg-gold/10 text-gold ring-gold/20'">
                        <template x-if="t.isPayment">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </template>
                        <template x-if="!t.isPayment">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </template>
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2 mb-0.5">
                            <p class="text-xs font-black text-white uppercase tracking-tight leading-tight line-clamp-1"
                               x-text="t.title"></p>
                            <button @click.stop="dismissToast(t)" aria-label="Descartar notificación"
                                    class="shrink-0 text-white/45 hover:text-white/60 transition mt-0.5">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <p class="text-[11px] leading-relaxed line-clamp-2"
                           :class="t.isPayment ? 'text-emerald-200/50' : 'text-amber-100/45'"
                           x-text="t.message"></p>
                        <div class="flex items-center gap-1.5 mt-2">
                            <span class="h-1.5 w-1.5 rounded-full animate-pulse"
                                  :class="t.isPayment ? 'bg-emerald-400' : 'bg-gold'"></span>
                            <span class="text-[9px] font-black uppercase tracking-widest"
                                  :class="t.isPayment ? 'text-emerald-400' : 'text-gold'">
                                Toca para abrir
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- ══════════════════════════════════════════
         FLOATING NOTIFICATION BELL + SETTINGS
    ══════════════════════════════════════════ --}}
    <div class="fixed bottom-[5.25rem] right-6 z-[60]" @click.away="settingsOpen = false">

        {{-- Bell button --}}
        <div class="relative">
            <button
                @click="settingsOpen = !settingsOpen"
                title="Configurar notificaciones"
                aria-label="Configurar notificaciones"
                class="h-9 w-9 rounded-full border flex items-center justify-center transition-all duration-200 shadow-lg"
                :class="settingsOpen
                    ? 'bg-gold/20 border-gold/50 text-gold shadow-gold/20'
                    : 'bg-[#181818] border-white/10 text-muted hover:text-gold hover:border-gold/20'"
            >
                <svg class="h-[17px] w-[17px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
            </button>

            {{-- Live unread badge on bell --}}
            <span
                x-show="unreadCount > 0"
                class="absolute -top-1.5 -right-1.5 h-4 w-4 rounded-full bg-gold flex items-center justify-center ring-2 ring-[#0a0a0a]"
            >
                <span class="text-[8px] font-black text-black leading-none"
                      x-text="unreadCount > 9 ? '9+' : unreadCount"></span>
            </span>
        </div>

        {{-- Settings panel --}}
        <div
            x-show="settingsOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-2"
            class="absolute bottom-11 right-0 w-64 bg-[#121212] border border-white/10 rounded-2xl shadow-2xl shadow-black/60 p-4 origin-bottom-right"
            style="display:none"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between mb-4">
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-muted">Notificaciones</p>
                <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-gold/10 text-gold font-black"
                      x-text="unreadCount + ' sin leer'"></span>
            </div>

            {{-- Sound toggle --}}
            <div class="flex items-center justify-between mb-4 py-2 border-b border-white/5">
                <div>
                    <p class="text-xs font-bold text-white">Sonido</p>
                    <p class="text-[9px] text-muted mt-0.5"
                       x-text="soundEnabled ? 'Activado' : 'Desactivado'"></p>
                </div>
                <button @click="toggleSound()"
                        :class="soundEnabled ? 'bg-gold' : 'bg-white/10'"
                        class="relative h-5 w-9 rounded-full transition-colors duration-250 focus:outline-none shrink-0">
                    <span :class="soundEnabled ? 'translate-x-4' : 'translate-x-0.5'"
                          class="absolute top-0.5 left-0 h-4 w-4 rounded-full bg-white shadow-md transition-transform duration-200 block"></span>
                </button>
            </div>

            {{-- Sound type picker --}}
            <div x-show="soundEnabled">
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-muted mb-2">Tipo de sonido</p>
                <div class="grid grid-cols-2 gap-1.5 mb-3">
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
                <button @click="testSound()"
                        class="w-full py-2 rounded-xl border border-gold/20 text-[9px] font-black uppercase tracking-widest text-gold/60 hover:text-gold hover:border-gold/40 hover:bg-gold/5 transition">
                    ▶ Probar sonido
                </button>
            </div>

            <div class="my-3 border-t border-white/5"></div>

            {{-- Link to all notifications --}}
            <a href="{{ route('notifications.index') }}"
               class="flex items-center justify-between group/link">
                <span class="text-[10px] font-bold text-muted group-hover/link:text-white transition">
                    Ver todas las notificaciones
                </span>
                <svg class="h-3 w-3 text-muted group-hover/link:text-white transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>
</div>

<script>
function notifToaster() {
    const cfg = window.__notifCfg || {};

    return {
        // ── Config (from window.__notifCfg) ─────────────────────
        role:       cfg.role       || 'cliente',
        pollUrl:    cfg.pollUrl    || '/notifications/poll',
        routes:     cfg.routes     || {},

        // ── State ───────────────────────────────────────────────
        visibleToasts: [],
        toastQueue:    [],
        settingsOpen:  false,
        soundEnabled:  true,
        soundType:     'chime',
        unreadCount:   cfg.initialUnread || 0,
        seenIds:       new Set(),
        _uidCounter:   0,

        soundOptions: [
            { key: 'chime', label: 'Carillón' },
            { key: 'bell',  label: 'Campana'  },
            { key: 'soft',  label: 'Suave'    },
            { key: 'ping',  label: 'Ping'     },
        ],

        // ── Lifecycle ───────────────────────────────────────────
        init() {
            this.loadPrefs();
            // Sync Alpine store (store already created via alpine:init listener)
            if (Alpine.store('notif')) Alpine.store('notif').unread = this.unreadCount;
            // Populate seenIds so we don't re-toast existing notifications
            this.fetchSeenIds();
            // Poll every 30 s
            setInterval(() => this.poll(), 30000);
        },

        loadPrefs() {
            const stored = localStorage.getItem('ub_notif_sound');
            this.soundEnabled = stored === null ? true : stored === 'true';
            this.soundType    = localStorage.getItem('ub_notif_sound_type') || 'chime';
        },

        savePrefs() {
            localStorage.setItem('ub_notif_sound', this.soundEnabled ? 'true' : 'false');
            localStorage.setItem('ub_notif_sound_type', this.soundType);
        },

        // ── Polling ─────────────────────────────────────────────
        async fetchSeenIds() {
            try {
                const data = await this.fetchPoll();
                (data.notifications || []).forEach(n => this.seenIds.add(n.id));
            } catch (e) {}
        },

        async poll() {
            try {
                const data = await this.fetchPoll();
                const newOnes = (data.notifications || []).filter(n => !this.seenIds.has(n.id));

                newOnes.forEach(n => {
                    this.seenIds.add(n.id);
                    this.toastQueue.push(n);
                });

                if (newOnes.length > 0) {
                    if (this.soundEnabled) this.playSound(this.soundType);
                    this.drainQueue();
                }

                this.unreadCount = data.unread;
                if (Alpine.store('notif')) Alpine.store('notif').unread = data.unread;
            } catch (e) {}
        },

        async fetchPoll() {
            const res = await fetch(this.pollUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('Poll failed');
            return res.json();
        },

        // ── Toast management ────────────────────────────────────
        drainQueue() {
            while (this.toastQueue.length > 0 && this.visibleToasts.length < 3) {
                const raw = this.toastQueue.shift();
                const t   = this.buildToast(raw);
                this.visibleToasts.push(t);
                this.$nextTick(() => this.startProgress(t.uid));
            }
        },

        buildToast(n) {
            const d         = n.data || {};
            const isPayment = d.type === 'payment';
            return {
                uid:      ++this._uidCounter,
                title:    d.title    || (isPayment ? 'Pago registrado'            : 'Nueva notificación'),
                message:  d.message  || (isPayment ? 'Tu pago ha sido procesado.' : ''),
                isPayment,
                url:      this.resolveUrl(d.type),
                progress: 100,
                visible:  true,
                _timer:   null,
            };
        },

        resolveUrl(type) {
            if (type === 'appointment') return (this.routes.appointment || {})[this.role] || this.routes.default;
            if (type === 'payment')     return (this.routes.payment     || {})[this.role] || this.routes.default;
            return this.routes.default;
        },

        startProgress(uid) {
            const DURATION = 6000;
            const TICK     = 60;
            const step     = (TICK / DURATION) * 100;

            const tick = () => {
                const idx = this.visibleToasts.findIndex(t => t.uid === uid);
                if (idx === -1) return;
                const next = Math.max(0, this.visibleToasts[idx].progress - step);
                this.visibleToasts[idx].progress = next;
                if (next <= 0) {
                    this.dismissToast(this.visibleToasts[idx]);
                } else {
                    this.visibleToasts[idx]._timer = setTimeout(tick, TICK);
                }
            };

            const idx = this.visibleToasts.findIndex(t => t.uid === uid);
            if (idx !== -1) this.visibleToasts[idx]._timer = setTimeout(tick, TICK);
        },

        openToast(t) {
            const url = t.url;
            this.dismissToast(t);
            if (url) window.location.href = url;
        },

        dismissToast(t) {
            clearTimeout(t._timer);
            const idx = this.visibleToasts.findIndex(x => x.uid === t.uid);
            if (idx === -1) return;
            this.visibleToasts[idx].visible = false;
            setTimeout(() => {
                this.visibleToasts = this.visibleToasts.filter(x => x.uid !== t.uid);
                this.drainQueue();
            }, 250);
        },

        // ── Sound settings ──────────────────────────────────────
        toggleSound() {
            this.soundEnabled = !this.soundEnabled;
            this.savePrefs();
        },

        setSoundType(type) {
            this.soundType = type;
            this.savePrefs();
            this.playSound(type);
        },

        testSound() {
            this.playSound(this.soundType);
        },

        // ── Web Audio API sounds (no external files) ────────────
        playSound(type) {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();

                const sounds = {
                    // Three ascending sine tones — classic notification chime
                    chime(ctx) {
                        [[880, 0], [1100, 0.13], [1320, 0.26]].forEach(([freq, delay]) => {
                            const osc  = ctx.createOscillator();
                            const gain = ctx.createGain();
                            osc.connect(gain); gain.connect(ctx.destination);
                            osc.type = 'sine';
                            osc.frequency.value = freq;
                            const t0 = ctx.currentTime + delay;
                            gain.gain.setValueAtTime(0, t0);
                            gain.gain.linearRampToValueAtTime(0.22, t0 + 0.03);
                            gain.gain.exponentialRampToValueAtTime(0.001, t0 + 0.65);
                            osc.start(t0); osc.stop(t0 + 0.65);
                        });
                    },
                    // Rich bell with overtones and slow decay
                    bell(ctx) {
                        [[440, 0.18], [880, 0.11], [1320, 0.06]].forEach(([freq, vol]) => {
                            const osc  = ctx.createOscillator();
                            const gain = ctx.createGain();
                            osc.connect(gain); gain.connect(ctx.destination);
                            osc.type = 'sine';
                            osc.frequency.value = freq;
                            gain.gain.setValueAtTime(vol, ctx.currentTime);
                            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1.8);
                            osc.start(); osc.stop(ctx.currentTime + 1.8);
                        });
                    },
                    // Two gentle sine notes: C5 then E5
                    soft(ctx) {
                        [[523, 0, 0.12], [659, 0.22, 0.10]].forEach(([freq, delay, vol]) => {
                            const osc  = ctx.createOscillator();
                            const gain = ctx.createGain();
                            osc.connect(gain); gain.connect(ctx.destination);
                            osc.type = 'sine';
                            osc.frequency.value = freq;
                            const t0 = ctx.currentTime + delay;
                            gain.gain.setValueAtTime(0, t0);
                            gain.gain.linearRampToValueAtTime(vol, t0 + 0.08);
                            gain.gain.exponentialRampToValueAtTime(0.001, t0 + 1.0);
                            osc.start(t0); osc.stop(t0 + 1.0);
                        });
                    },
                    // Sharp triangle glide — digital ping
                    ping(ctx) {
                        const osc  = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.connect(gain); gain.connect(ctx.destination);
                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(1568, ctx.currentTime);
                        osc.frequency.exponentialRampToValueAtTime(784, ctx.currentTime + 0.22);
                        gain.gain.setValueAtTime(0.28, ctx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.22);
                        osc.start(); osc.stop(ctx.currentTime + 0.22);
                    },
                };

                (sounds[type] || sounds.chime)(ctx);
            } catch (e) {}
        },
    };
}
</script>
@endauth
