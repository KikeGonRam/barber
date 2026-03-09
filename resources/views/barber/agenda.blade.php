<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Mi <span class="text-gold">Agenda Maestra</span></h2>
                <p class="ui-subtitle">Visualiza y gestiona tus servicios asignados para hoy.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex h-10 items-center rounded-xl bg-bg-accent border border-line p-1">
                    <a href="{{ route('barber.agenda', ['period' => 'day']) }}" class="px-4 py-1.5 text-[10px] font-black uppercase tracking-widest transition-all rounded-lg {{ $period === 'day' ? 'bg-gold text-black' : 'text-muted hover:text-white' }}">Hoy</a>
                    <a href="{{ route('barber.agenda', ['period' => 'week']) }}" class="px-4 py-1.5 text-[10px] font-black uppercase tracking-widest transition-all rounded-lg {{ $period === 'week' ? 'bg-gold text-black' : 'text-muted hover:text-white' }}">Semana</a>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-8 px-4 sm:px-6 lg:px-8">
            
            @php
                $nextAppt = $agenda->where('estado', 'pendiente')->first();
            @endphp

            @if($nextAppt)
                <!-- Next Client Spotlight (Surprise) -->
                <section class="ui-card-premium p-0 overflow-hidden border-gold/30 gold-glow">
                    <div class="flex flex-col md:flex-row">
                        <div class="bg-gradient-to-br from-gold to-gold-dim p-8 md:w-72 flex flex-col justify-center items-center text-black">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-2 opacity-70">Siguiente Cita</p>
                            <p class="text-4xl font-black">{{ substr($nextAppt->hora_inicio, 0, 5) }}</p>
                            <div class="mt-4 h-1 w-12 bg-black/20 rounded-full"></div>
                        </div>
                        <div class="p-8 flex-1 flex flex-col sm:flex-row justify-between items-center gap-6 bg-white/5 backdrop-blur-md">
                            <div>
                                <h3 class="text-2xl font-black text-white uppercase tracking-tight">{{ $nextAppt->client?->user?->name }}</h3>
                                <p class="text-gold font-bold uppercase tracking-widest text-xs mt-1">{{ $nextAppt->service?->nombre }}</p>
                                <div class="mt-4 flex gap-4">
                                    <div class="flex items-center gap-2 text-[10px] font-bold text-muted uppercase">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/></svg>
                                        {{ $nextAppt->service?->duracion_min }} Minutos
                                    </div>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('barber.appointments.status', $nextAppt) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="estado" value="en_proceso">
                                <button type="submit" class="ui-btn px-10 py-4 shadow-[0_0_30px_rgba(212,175,55,0.2)]">
                                    Iniciar Servicio &rarr;
                                </button>
                            </form>
                        </div>
                    </div>
                </section>
            @endif

            <!-- Stats Bar -->
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <article class="ui-card-premium p-6 flex items-center justify-between border-l-4 border-l-gold">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-muted">Servicios Completados</p>
                        <p class="text-2xl font-black text-white mt-1">{{ $stats['completed_count'] }}</p>
                    </div>
                    <div class="h-10 w-10 rounded-full bg-gold/10 flex items-center justify-center text-gold">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </div>
                </article>

                <article class="ui-card-premium p-6 flex items-center justify-between border-l-4 border-l-green-600">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-muted">Ingresos del Periodo</p>
                        <p class="text-2xl font-black text-white mt-1">${{ number_format($stats['income_total'], 2) }}</p>
                    </div>
                    <div class="h-10 w-10 rounded-full bg-green-600/10 flex items-center justify-center text-green-500">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </article>

                <article class="ui-card-premium p-6 flex items-center justify-between border-l-4 border-l-blue-600">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-muted">Productividad</p>
                        <p class="text-2xl font-black text-white mt-1">92%</p>
                    </div>
                    <div class="h-10 w-10 rounded-full bg-blue-600/10 flex items-center justify-center text-blue-500">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                    </div>
                </article>
            </section>

            <!-- Agenda List -->
            <section class="space-y-6">
                <div class="flex items-center gap-3">
                    <div class="h-px flex-1 bg-line"></div>
                    <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-gold">Listado de Citas</h3>
                    <div class="h-px flex-1 bg-line"></div>
                </div>

                <div class="space-y-4">
                    @forelse($agenda as $appointment)
                        <article class="ui-card-premium group">
                            <div class="flex flex-col lg:flex-row lg:items-center">
                                <!-- Time & Status -->
                                <div class="bg-panel/50 lg:w-48 p-6 flex flex-col justify-center border-b lg:border-b-0 lg:border-r border-line">
                                    <p class="text-xs font-bold uppercase tracking-widest text-muted">{{ \Carbon\Carbon::parse($appointment->fecha)->translatedFormat('d M') }}</p>
                                    <p class="text-xl font-black text-white mt-1">{{ substr($appointment->hora_inicio, 0, 5) }}</p>
                                    <span class="mt-4 inline-flex items-center rounded-full border border-gold/30 bg-gold/5 px-3 py-1 text-[9px] font-black uppercase tracking-widest text-gold w-fit">
                                        {{ $appointment->estado }}
                                    </span>
                                </div>

                                <!-- Client Info -->
                                <div class="flex-1 p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 rounded-2xl bg-bg-accent border border-line flex items-center justify-center text-sm font-bold text-ink">
                                            {{ substr($appointment->client?->user?->name, 0, 2) }}
                                        </div>
                                        <div>
                                            <p class="text-lg font-black text-white">{{ $appointment->client?->user?->name }}</p>
                                            <p class="text-xs text-muted font-bold uppercase tracking-widest">{{ $appointment->service?->nombre }}</p>
                                        </div>
                                    </div>

                                    <!-- Quick Actions -->
                                    <form method="POST" action="{{ route('barber.appointments.status', $appointment) }}" class="flex flex-wrap items-end gap-3">
                                        @csrf
                                        @method('PATCH')
                                        <div class="w-40">
                                            <label class="ui-label">Actualizar Estado</label>
                                            <select name="estado" class="ui-input !py-1.5 !text-[11px] !bg-panel">
                                                @foreach(['pendiente','en_proceso','completada'] as $estado)
                                                    <option value="{{ $estado }}" @selected($appointment->estado === $estado)>{{ ucfirst($estado) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" class="ui-btn py-2 text-[10px] uppercase tracking-widest px-4 shadow-none">Actualizar</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="ui-empty py-20 border-line bg-panel/30">
                            <p class="text-muted italic">Sin servicios programados para este periodo.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
