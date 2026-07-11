<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Perfil de <span class="text-gold">{{ $client->user?->name }}</span></h2>
                <p class="ui-subtitle">Historial completo, estadísticas y actividad del cliente.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('clients.edit', $client) }}" class="ui-btn-secondary px-5 text-[11px] tracking-widest">
                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Editar
                </a>
                <a href="{{ route('appointments.create') }}" class="ui-btn">
                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nueva Cita
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6 py-4">

        {{-- ── HEADER PERFIL ─────────────────────────────── --}}
        <section class="ui-card-premium p-0 overflow-hidden">
            <div class="flex flex-col sm:flex-row">
                {{-- Avatar + datos básicos --}}
                <div class="flex flex-col items-center justify-center p-8 sm:w-64 border-b sm:border-b-0 sm:border-r border-white/6 bg-[#0f0f0f]">
                    <div class="h-24 w-24 rounded-3xl bg-gradient-to-br from-gold/30 to-gold/10 border border-gold/20 flex items-center justify-center text-4xl font-black text-gold mb-4">
                        {{ strtoupper(substr($client->user?->name ?? 'CL', 0, 2)) }}
                    </div>
                    <h3 class="text-lg font-black text-white text-center">{{ $client->user?->name }}</h3>
                    <p class="text-[10px] text-muted mt-0.5 text-center">{{ $client->user?->email }}</p>

                    @if($client->user?->email_verified_at)
                        <span class="mt-3 inline-flex items-center gap-1.5 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-3 py-1 text-[9px] font-black uppercase tracking-wider text-emerald-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Verificado
                        </span>
                    @endif
                </div>

                {{-- Info de contacto --}}
                <div class="flex-1 p-8">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-6">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-1">Teléfono</p>
                            <p class="text-sm font-bold text-white">{{ $client->telefono ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-1">Nacimiento</p>
                            <p class="text-sm font-bold text-white">{{ $client->fecha_nacimiento?->format('d M, Y') ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-1">Cliente desde</p>
                            <p class="text-sm font-bold text-white">{{ $client->created_at?->format('d M, Y') }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-1">Última Visita</p>
                            <p class="text-sm font-bold text-white">
                                {{ $stats['ultima_visita'] ? \Carbon\Carbon::parse($stats['ultima_visita'])->format('d M, Y') : '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-1">Barbero Favorito</p>
                            <p class="text-sm font-bold text-white">{{ $stats['barbero_favorito'] }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-1">Servicio Favorito</p>
                            <p class="text-sm font-bold text-gold text-xs">{{ $stats['servicio_favorito'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ── KPI CARDS ─────────────────────────────────── --}}
        <section class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-white/8 bg-[#111] p-5 text-center">
                <p class="text-3xl font-black text-white">{{ $stats['total_citas'] }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-muted mt-1">Total Citas</p>
            </div>
            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5 text-center">
                <p class="text-3xl font-black text-emerald-400">{{ $stats['completadas'] }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-400/70 mt-1">Completadas</p>
            </div>
            <div class="rounded-2xl border border-red-500/20 bg-red-500/5 p-5 text-center">
                <p class="text-3xl font-black text-red-400">{{ $stats['canceladas'] }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-red-400/70 mt-1">Canceladas</p>
            </div>
            <div class="rounded-2xl border border-gold/20 bg-gold/5 p-5 text-center">
                <p class="text-2xl font-black text-gold">${{ number_format($stats['total_gastado'], 2) }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-gold/70 mt-1">Total Gastado</p>
            </div>
        </section>

        {{-- ── HISTORIAL DE CITAS ────────────────────────── --}}
        <section class="ui-card-premium p-6 sm:p-8">
            <div class="flex items-center justify-between mb-7">
                <h3 class="text-sm font-black text-white uppercase tracking-widest">Historial de Citas</h3>
                <span class="text-[10px] font-bold text-muted uppercase tracking-wider">Últimas {{ $recentAppointments->count() }}</span>
            </div>

            @if($recentAppointments->isEmpty())
                <div class="flex flex-col items-center py-12 border border-dashed border-white/10 rounded-2xl">
                    <svg class="h-10 w-10 text-white/5 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-sm font-bold text-muted">Sin citas registradas</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($recentAppointments as $appt)
                        @php
                            $statusCfg = [
                                'pendiente'  => 'bg-amber-500/15 text-amber-300 border-amber-500/25',
                                'confirmada' => 'bg-blue-500/15 text-blue-300 border-blue-500/25',
                                'completada' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/25',
                                'cancelada'  => 'bg-red-500/15 text-red-400 border-red-500/25',
                                'en_proceso' => 'bg-sky-500/15 text-sky-300 border-sky-500/25',
                            ];
                            $pill = $statusCfg[$appt->estado] ?? 'bg-white/8 text-white/50 border-white/10';
                        @endphp
                        <div class="flex items-center gap-4 p-3.5 rounded-2xl border border-white/5 hover:border-gold/20 hover:bg-white/[0.02] transition-all">
                            <div class="shrink-0 w-14 text-center">
                                <p class="text-base font-black text-white">{{ \Carbon\Carbon::parse($appt->fecha)->format('d') }}</p>
                                <p class="text-[10px] font-bold text-muted uppercase">{{ \Carbon\Carbon::parse($appt->fecha)->format('M Y') }}</p>
                            </div>
                            <div class="w-px h-8 bg-white/8 shrink-0"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-black text-white">{{ $appt->service?->nombre ?? '—' }}</p>
                                <p class="text-[10px] font-bold text-muted">Con {{ $appt->barber?->user?->name ?? '—' }} · {{ substr($appt->hora_inicio, 0, 5) }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <span class="text-[9px] font-black border rounded-full px-2.5 py-1 {{ $pill }}">{{ $appt->estado }}</span>
                                @if($pago = $appt->payments->first())
                                    <p class="text-[10px] font-black text-emerald-400 mt-1">${{ number_format($pago->monto + $pago->propina, 2) }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-6 pt-5 border-t border-white/5 flex items-center justify-between">
                <a href="{{ route('clients.index') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-gold transition flex items-center gap-1">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    Volver a Clientes
                </a>
                <a href="{{ route('appointments.create') }}" class="ui-btn py-2.5 px-6 text-[11px] tracking-widest">
                    Agendar Nueva Cita
                </a>
            </div>
        </section>
    </div>
</x-app-layout>
