<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Mi <span class="text-gold">Horario Semanal</span></h2>
                <p class="ui-subtitle">Define tus días laborales y horarios de entrada/salida.</p>
            </div>
            <a href="{{ route('barber.agenda') }}"
               class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-muted hover:text-white transition">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                Volver a Agenda
            </a>
        </div>
    </x-slot>

    <div class="space-y-5 py-4" x-data="scheduleManager()">
        <x-auth-session-status :status="session('status')" />

        {{-- ── ACCIONES RÁPIDAS ────────────────────────────── --}}
        <section class="flex flex-wrap items-center gap-3">
            <span class="text-[9px] font-black uppercase tracking-widest text-muted">Acciones rápidas:</span>
            <button type="button" @click="copyFirstToAll()"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-white/10 px-3.5 py-2 text-[10px] font-black uppercase tracking-widest text-muted hover:text-gold hover:border-gold/30 transition-all">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                Copiar Lunes a todos
            </button>
            <button type="button" @click="copyFirstToWorking()"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-white/10 px-3.5 py-2 text-[10px] font-black uppercase tracking-widest text-muted hover:text-gold hover:border-gold/30 transition-all">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Copiar a días laborales
            </button>
            <button type="button" @click="setAllWorking(true)"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-500/20 px-3.5 py-2 text-[10px] font-black uppercase tracking-widest text-emerald-400/70 hover:text-emerald-300 hover:border-emerald-500/40 transition-all">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Activar todos
            </button>
            <button type="button" @click="setAllWorking(false)"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-red-500/15 px-3.5 py-2 text-[10px] font-black uppercase tracking-widest text-red-400/60 hover:text-red-400 hover:border-red-500/30 transition-all">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Descanso todos
            </button>
        </section>

        {{-- ── FORM ────────────────────────────────────────── --}}
        <section class="ui-card-premium p-0 overflow-hidden">
            <form method="POST" action="{{ route('barber.schedule.update') }}" id="scheduleForm">
                @csrf @method('PUT')

                @php $days = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado']; @endphp

                <div class="divide-y divide-white/5">
                    @foreach($schedules as $schedule)
                        @php $isWeekend = $schedule->day_of_week === 0 || $schedule->day_of_week === 6; @endphp
                        <div class="group px-6 py-5 hover:bg-white/[0.02] transition-colors"
                             :class="days[{{ $schedule->day_of_week }}].working ? '' : 'opacity-60'"
                             x-init>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-5">

                                {{-- Day label --}}
                                <div class="flex items-center gap-3 sm:w-40 shrink-0">
                                    <div class="h-10 w-10 rounded-xl flex items-center justify-center text-xs font-black border transition-all"
                                         :class="days[{{ $schedule->day_of_week }}].working
                                             ? 'bg-gold/15 border-gold/25 text-gold'
                                             : 'bg-white/5 border-white/8 text-muted'">
                                        {{ strtoupper(mb_substr($days[$schedule->day_of_week], 0, 2)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-black text-white uppercase tracking-wide">{{ $days[$schedule->day_of_week] }}</p>
                                        <p class="text-[9px] text-muted uppercase tracking-wider">{{ $isWeekend ? 'Fin de semana' : 'Día laboral' }}</p>
                                    </div>
                                </div>

                                {{-- Toggle --}}
                                <div class="shrink-0">
                                    <label class="relative inline-flex cursor-pointer items-center gap-3 select-none">
                                        <input type="checkbox"
                                               name="schedules[{{ $schedule->day_of_week }}][is_working]"
                                               value="1"
                                               class="sr-only peer"
                                               @checked($schedule->is_working)
                                               x-model="days[{{ $schedule->day_of_week }}].working"
                                               @change="onWorkingChange({{ $schedule->day_of_week }})">
                                        <div class="relative h-6 w-11 rounded-full border transition-all duration-300
                                                    bg-white/5 border-white/10
                                                    peer-checked:bg-gold/20 peer-checked:border-gold/40">
                                            <div class="absolute left-1 top-1 h-4 w-4 rounded-full transition-all duration-300
                                                        bg-white/30
                                                        peer-checked:bg-gold peer-checked:translate-x-5"
                                                 :class="days[{{ $schedule->day_of_week }}].working ? 'translate-x-5 bg-gold' : 'translate-x-0 bg-white/30'">
                                            </div>
                                        </div>
                                        <span class="text-[10px] font-black uppercase tracking-widest"
                                              :class="days[{{ $schedule->day_of_week }}].working ? 'text-gold' : 'text-muted'">
                                            <span x-text="days[{{ $schedule->day_of_week }}].working ? 'Laboral' : 'Descanso'"></span>
                                        </span>
                                    </label>
                                </div>

                                {{-- Hours --}}
                                <div class="flex-1 grid grid-cols-2 gap-4"
                                     :class="days[{{ $schedule->day_of_week }}].working ? 'opacity-100' : 'opacity-30 pointer-events-none'">
                                    <div>
                                        <label class="text-[9px] font-black uppercase tracking-widest text-muted block mb-1.5">Entrada</label>
                                        <input type="time"
                                               name="schedules[{{ $schedule->day_of_week }}][start_time]"
                                               value="{{ substr($schedule->start_time, 0, 5) }}"
                                               x-model="days[{{ $schedule->day_of_week }}].start"
                                               @change="updatePreview({{ $schedule->day_of_week }})"
                                               class="h-10 w-full rounded-xl border border-white/10 bg-black/30 px-3 text-sm text-white focus:border-gold/50 focus:outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-black uppercase tracking-widest text-muted block mb-1.5">Salida</label>
                                        <input type="time"
                                               name="schedules[{{ $schedule->day_of_week }}][end_time]"
                                               value="{{ substr($schedule->end_time, 0, 5) }}"
                                               x-model="days[{{ $schedule->day_of_week }}].end"
                                               @change="updatePreview({{ $schedule->day_of_week }})"
                                               class="h-10 w-full rounded-xl border border-white/10 bg-black/30 px-3 text-sm text-white focus:border-gold/50 focus:outline-none transition-all">
                                    </div>
                                </div>

                                {{-- Hours preview --}}
                                <div class="sm:w-24 shrink-0 text-right hidden sm:block">
                                    <template x-if="days[{{ $schedule->day_of_week }}].working && days[{{ $schedule->day_of_week }}].start && days[{{ $schedule->day_of_week }}].end">
                                        <div>
                                            <p class="text-[9px] text-muted uppercase tracking-wider">Horas</p>
                                            <p class="text-lg font-black text-gold" x-text="calcHours({{ $schedule->day_of_week }})"></p>
                                        </div>
                                    </template>
                                    <template x-if="!days[{{ $schedule->day_of_week }}].working">
                                        <p class="text-[9px] font-black uppercase tracking-wider text-muted/40">Libre</p>
                                    </template>
                                </div>

                                {{-- Copy button --}}
                                <div class="shrink-0">
                                    <button type="button"
                                            @click="copyDayToAll({{ $schedule->day_of_week }})"
                                            class="h-9 w-9 rounded-xl border border-white/8 bg-white/3 flex items-center justify-center text-muted hover:text-gold hover:border-gold/30 transition-all"
                                            title="Copiar este horario a todos los días">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </button>
                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- ── RESUMEN SEMANAL ─── --}}
                <div class="px-6 py-4 border-t border-white/5 bg-black/20 flex flex-wrap gap-6 items-center justify-between">
                    <div class="flex flex-wrap gap-4">
                        <div>
                            <p class="text-[9px] text-muted uppercase tracking-wider">Días laborales</p>
                            <p class="text-xl font-black text-white" x-text="workingDaysCount()"></p>
                        </div>
                        <div>
                            <p class="text-[9px] text-muted uppercase tracking-wider">Horas semanales</p>
                            <p class="text-xl font-black text-gold" x-text="totalWeeklyHours() + 'h'"></p>
                        </div>
                    </div>
                    <button type="submit"
                            class="ui-btn px-10 py-3 text-[11px] uppercase tracking-[0.15em] shadow-lg shadow-gold/20">
                        Guardar Disponibilidad &rarr;
                    </button>
                </div>

            </form>
        </section>
    </div>

    @php
        $scheduleData = $schedules->map(fn($s) => [
            'working' => (bool) $s->is_working,
            'start'   => substr($s->start_time ?? '09:00:00', 0, 5),
            'end'     => substr($s->end_time   ?? '21:00:00', 0, 5),
        ])->values()->toArray();
    @endphp

    <script>
    function scheduleManager() {
        return {
            days: @json($scheduleData),

            onWorkingChange(idx) { /* reactive already */ },
            updatePreview(idx) { /* reactive already */ },

            copyDayToAll(fromIdx) {
                const src = this.days[fromIdx];
                this.days = this.days.map(d => ({
                    ...d,
                    working: src.working,
                    start: src.start,
                    end: src.end,
                }));
                this.syncInputs();
            },

            copyFirstToAll() { this.copyDayToAll(1); }, // Monday = index 1
            copyFirstToWorking() {
                const src = this.days[1]; // Monday
                this.days = this.days.map(d => ({
                    ...d,
                    start: d.working ? src.start : d.start,
                    end: d.working ? src.end : d.end,
                }));
                this.syncInputs();
            },

            setAllWorking(val) {
                this.days = this.days.map(d => ({...d, working: val}));
                this.syncInputs();
            },

            syncInputs() {
                this.$nextTick(() => {
                    this.days.forEach((d, idx) => {
                        const form = document.getElementById('scheduleForm');
                        const startInput = form.querySelector(`input[name="schedules[${idx}][start_time]"]`);
                        const endInput   = form.querySelector(`input[name="schedules[${idx}][end_time]"]`);
                        const chk        = form.querySelector(`input[name="schedules[${idx}][is_working]"]`);
                        if (startInput) startInput.value = d.start;
                        if (endInput)   endInput.value   = d.end;
                        if (chk)        chk.checked      = d.working;
                    });
                });
            },

            calcHours(idx) {
                const d = this.days[idx];
                if (!d || !d.start || !d.end) return '—';
                const [sh, sm] = d.start.split(':').map(Number);
                const [eh, em] = d.end.split(':').map(Number);
                const mins = (eh * 60 + em) - (sh * 60 + sm);
                if (mins <= 0) return '—';
                const h = Math.floor(mins / 60);
                const m = mins % 60;
                return h + (m ? `.${m}` : '') + 'h';
            },

            workingDaysCount() {
                return this.days.filter(d => d.working).length;
            },

            totalWeeklyHours() {
                let total = 0;
                this.days.forEach((d, idx) => {
                    if (!d.working || !d.start || !d.end) return;
                    const [sh, sm] = d.start.split(':').map(Number);
                    const [eh, em] = d.end.split(':').map(Number);
                    const mins = (eh * 60 + em) - (sh * 60 + sm);
                    if (mins > 0) total += mins;
                });
                const h = Math.floor(total / 60);
                const m = total % 60;
                return h + (m ? `.${Math.round(m/60*10)}` : '');
            },
        };
    }
    </script>
</x-app-layout>
