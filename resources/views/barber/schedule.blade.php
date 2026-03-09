<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Mi <span class="text-gold">Horario Semanal</span></h2>
                <p class="ui-subtitle">Define tus horas de trabajo y días de descanso para la agenda.</p>
            </div>
            <a href="{{ route('barber.agenda') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-white transition">
                &larr; Volver a la agenda
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <section class="ui-surface">
                <form method="POST" action="{{ route('barber.schedule.update') }}" class="space-y-8">
                    @csrf
                    @method('PUT')

                    <div class="space-y-4">
                        @php
                            $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                        @endphp

                        @foreach($schedules as $schedule)
                            <div class="p-6 rounded-2xl border border-white/5 bg-black/20 hover:border-gold/20 transition-all group">
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                                    <div class="flex items-center gap-4 min-w-[150px]">
                                        <div class="h-10 w-10 rounded-xl bg-white/5 flex items-center justify-center text-xs font-black text-gold border border-white/5 group-hover:bg-gold group-hover:text-black transition-all">
                                            {{ substr($days[$schedule->day_of_week], 0, 2) }}
                                        </div>
                                        <h3 class="text-sm font-black text-white uppercase tracking-widest">{{ $days[$schedule->day_of_week] }}</h3>
                                    </div>

                                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-6 items-center">
                                        <!-- Toggle Working -->
                                        <div>
                                            <label class="relative inline-flex cursor-pointer items-center group">
                                                <input type="checkbox" name="schedules[{{ $schedule->day_of_week }}][is_working]" value="1" class="sr-only peer" @checked($schedule->is_working)>
                                                <div class="h-6 w-11 rounded-full bg-white/5 border border-white/10 peer-checked:bg-gold/20 peer-checked:border-gold transition-all"></div>
                                                <div class="absolute left-1 top-1 h-4 w-4 rounded-full bg-muted peer-checked:bg-gold peer-checked:translate-x-5 transition-all"></div>
                                                <span class="ms-3 text-[10px] font-black uppercase tracking-widest text-muted group-hover:text-white transition-colors">Laboral</span>
                                            </label>
                                        </div>

                                        <!-- Start Time -->
                                        <div>
                                            <label class="ui-label block text-[9px]">Entrada</label>
                                            <input type="time" name="schedules[{{ $schedule->day_of_week }}][start_time]" 
                                                   value="{{ substr($schedule->start_time, 0, 5) }}" 
                                                   class="ui-input !py-2 !text-xs !bg-panel border-white/5 text-white">
                                        </div>

                                        <!-- End Time -->
                                        <div>
                                            <label class="ui-label block text-[9px]">Salida</label>
                                            <input type="time" name="schedules[{{ $schedule->day_of_week }}][end_time]" 
                                                   value="{{ substr($schedule->end_time, 0, 5) }}" 
                                                   class="ui-input !py-2 !text-xs !bg-panel border-white/5 text-white">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="pt-8 border-t border-white/5 flex justify-end">
                        <button type="submit" class="ui-btn px-16 py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                            Actualizar Mi Disponibilidad <span class="ml-2 opacity-50">&rarr;</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
