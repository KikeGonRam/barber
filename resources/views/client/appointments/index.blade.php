<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Mi <span class="text-gold">Historial de Estilo</span></h2>
                <p class="ui-subtitle">Gestiona tus próximas citas y revisa tus visitas anteriores.</p>
            </div>
            <a href="{{ route('client.appointments.create') }}" class="ui-btn shadow-gold/10">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Agendar Nueva Cita
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-8 px-4 sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            @if ($errors->any())
                <div class="rounded-xl border border-red-900/50 bg-red-950/20 p-4 text-sm text-red-400">
                    {{ $errors->first() }}
                </div>
            @endif

            <!-- Próximas Citas -->
            <section class="space-y-6">
                <div class="flex items-center gap-3">
                    <div class="h-px flex-1 bg-line"></div>
                    <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-gold">Tus Citas</h3>
                    <div class="h-px flex-1 bg-line"></div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    @forelse($appointments as $appointment)
                        @php
                            $isPast = \Carbon\Carbon::parse($appointment->fecha)->isPast();
                            $statusColors = [
                                'pendiente' => 'border-yellow-900/50 bg-yellow-950/20 text-yellow-500',
                                'confirmada' => 'border-blue-900/50 bg-blue-950/20 text-blue-400',
                                'completada' => 'border-green-900/50 bg-green-950/20 text-green-400',
                                'cancelada' => 'border-red-900/50 bg-red-950/20 text-red-400',
                            ];
                            $statusColor = $statusColors[strtolower($appointment->estado)] ?? 'border-line bg-bg-card text-muted';
                        @endphp
                        
                        <article class="ui-card-premium group">
                            <div class="p-6">
                                <div class="flex items-start justify-between mb-6">
                                    <div class="flex items-center gap-4">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-bg-accent border border-line group-hover:border-gold/50 transition-colors">
                                            <svg class="h-6 w-6 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold uppercase tracking-widest text-muted">{{ \Carbon\Carbon::parse($appointment->fecha)->translatedFormat('d F, Y') }}</p>
                                            <p class="text-lg font-black text-white">{{ substr($appointment->hora_inicio, 0, 5) }} - {{ substr($appointment->hora_fin, 0, 5) }}</p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-[9px] font-black uppercase tracking-widest {{ $statusColor }}">
                                        {{ $appointment->estado }}
                                    </span>
                                </div>

                                <div class="space-y-4 mb-8">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-muted">Servicio</span>
                                        <span class="font-bold text-ink">{{ $appointment->service?->nombre }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-muted">Barbero</span>
                                        <span class="font-bold text-ink">{{ $appointment->barber?->user?->name }}</span>
                                    </div>
                                </div>

                                @if(!$isPast && $appointment->estado !== 'cancelada')
                                    <div class="flex items-center gap-3 pt-6 border-t border-line">
                                        <a href="{{ route('client.appointments.edit', $appointment) }}" class="flex-1 ui-btn-secondary text-[10px] py-2 uppercase tracking-widest">Reagendar</a>
                                        <form action="{{ route('client.appointments.destroy', $appointment) }}" method="POST" class="flex-1" onsubmit="return confirm('¿Estás seguro de cancelar esta cita?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="w-full rounded-xl border border-red-900/50 bg-red-950/10 py-2 text-[10px] font-black uppercase tracking-widest text-red-500 hover:bg-red-500 hover:text-white transition-all">Cancelar</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="md:col-span-2 ui-empty py-16 border-line bg-panel/30">
                            <svg class="h-12 w-12 text-line mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-muted">No tienes citas programadas por el momento.</p>
                            <a href="{{ route('client.appointments.create') }}" class="mt-6 inline-block text-gold hover:text-white transition font-bold text-sm uppercase tracking-widest">Comenzar Reserva &rarr;</a>
                        </div>
                    @endforelse
                </div>

                <div class="mt-8">
                    {{ $appointments->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
