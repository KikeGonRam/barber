<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Mi <span class="text-gold">Espacio</span></h2>
                <p class="ui-subtitle">Bienvenido de vuelta, {{ auth()->user()->name }}.</p>
            </div>
            <a href="{{ route('client.appointments.create') }}" class="ui-btn shadow-gold/10">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Agendar Cita
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-10 px-4 sm:px-6 lg:px-8">

            <x-auth-session-status :status="session('status')" />

            @if ($errors->any())
                <div class="rounded-xl border border-red-900/50 bg-red-950/20 p-4 text-sm text-red-400">
                    {{ $errors->first() }}
                </div>
            @endif

            <!-- Stats Row -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-5 text-center">
                    <p class="text-3xl font-black text-white">{{ $stats['total'] }}</p>
                    <p class="text-[9px] font-black text-muted uppercase tracking-[0.2em] mt-1">Total Citas</p>
                </div>
                <div class="rounded-2xl border border-gold/20 bg-gold/[0.03] p-5 text-center">
                    <p class="text-3xl font-black text-gold">{{ $stats['proximas'] }}</p>
                    <p class="text-[9px] font-black text-muted uppercase tracking-[0.2em] mt-1">Próximas</p>
                </div>
                <div class="rounded-2xl border border-green-900/30 bg-green-950/10 p-5 text-center">
                    <p class="text-3xl font-black text-green-400">{{ $stats['completadas'] }}</p>
                    <p class="text-[9px] font-black text-muted uppercase tracking-[0.2em] mt-1">Completadas</p>
                </div>
                <div class="rounded-2xl border border-red-900/30 bg-red-950/10 p-5 text-center">
                    <p class="text-3xl font-black text-red-400">{{ $stats['canceladas'] }}</p>
                    <p class="text-[9px] font-black text-muted uppercase tracking-[0.2em] mt-1">Canceladas</p>
                </div>
            </div>

            <!-- Próxima Cita Hero -->
            @if($nextAppointment)
            <div
                x-data="countdown('{{ $nextAppointment->fecha->format('Y-m-d') }}', '{{ $nextAppointment->hora_inicio }}')"
                class="relative overflow-hidden rounded-3xl border border-gold/20 bg-gradient-to-br from-gold/[0.06] via-transparent to-transparent p-8 shadow-xl"
            >
                <div class="absolute top-0 left-1/2 -translate-x-1/2 h-px w-3/4 bg-gradient-to-r from-transparent via-gold/40 to-transparent"></div>
                <div class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-gold/5 blur-3xl pointer-events-none"></div>

                <div class="relative grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[0.35em] text-gold mb-4">Tu Próxima Cita</p>

                        <h3 class="text-3xl font-black text-white uppercase tracking-tight leading-tight">
                            {{ $nextAppointment->service?->nombre }}
                        </h3>
                        <p class="mt-2 text-muted font-bold text-sm">
                            con <span class="text-white font-black">{{ $nextAppointment->barber?->user?->name }}</span>
                        </p>

                        <div class="mt-6 flex items-center gap-6">
                            <div>
                                <p class="text-[9px] font-black text-muted uppercase tracking-widest">Fecha</p>
                                <p class="text-sm font-black text-white mt-0.5">
                                    {{ \Carbon\Carbon::parse($nextAppointment->fecha)->translatedFormat('d F, Y') }}
                                </p>
                            </div>
                            <div class="h-8 w-px bg-white/10"></div>
                            <div>
                                <p class="text-[9px] font-black text-muted uppercase tracking-widest">Hora</p>
                                <p class="text-sm font-black text-white mt-0.5">{{ substr($nextAppointment->hora_inicio, 0, 5) }}</p>
                            </div>
                            @if($nextAppointment->service?->duracion_min)
                            <div class="h-8 w-px bg-white/10"></div>
                            <div>
                                <p class="text-[9px] font-black text-muted uppercase tracking-widest">Duración</p>
                                <p class="text-sm font-black text-white mt-0.5">{{ $nextAppointment->service->duracion_min }} min</p>
                            </div>
                            @endif
                        </div>

                        <div class="mt-8 flex items-center gap-3">
                            @php
                                $nextStartsAt = \Carbon\Carbon::parse($nextAppointment->fecha->format('Y-m-d').' '.$nextAppointment->hora_inicio);
                                $nextStatus = strtolower((string) $nextAppointment->estado);
                                $canManageNext = in_array($nextStatus, ['pendiente', 'confirmada'], true) && $nextStartsAt->isFuture();
                                $canCancel = $canManageNext && now()->diffInHours($nextStartsAt, false) >= ($policyHours ?? 24);
                            @endphp
                            @if($canManageNext)
                                <a href="{{ route('client.appointments.edit', $nextAppointment) }}"
                                   class="rounded-xl border border-white/10 bg-white/5 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-muted hover:border-gold/30 hover:text-gold transition-all">
                                    Reagendar
                                </a>
                            @endif
                            @if($canCancel)
                            <form action="{{ route('client.appointments.destroy', $nextAppointment) }}" method="POST"
                                  onsubmit="return confirm('¿Cancelar esta cita?');">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    class="rounded-xl border border-red-900/30 bg-transparent px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-red-500/50 hover:bg-red-500/10 hover:text-red-400 hover:border-red-500/30 transition-all">
                                    Cancelar cita
                                </button>
                            </form>
                            @elseif($canManageNext)
                                <span class="rounded-xl border border-amber-500/15 bg-amber-500/[0.04] px-4 py-2.5 text-[10px] font-bold text-amber-300/70">
                                    Cancelación con {{ $policyHours ?? 24 }}h mín.
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Countdown -->
                    <div class="text-center md:border-l md:border-white/5 md:pl-8">
                        <p class="text-[9px] font-black uppercase tracking-[0.35em] text-muted mb-4">Faltan</p>
                        <div x-show="daysLeft > 0" class="space-y-1">
                            <span class="text-7xl font-black text-gold block leading-none" x-text="daysLeft"></span>
                            <span class="text-[10px] font-black text-muted uppercase tracking-widest">
                                <span x-text="daysLeft === 1 ? 'día' : 'días'"></span>
                            </span>
                            <p class="text-[9px] text-muted/50 mt-2" x-show="hoursLeft > 0">
                                y <span x-text="hoursLeft"></span>h más
                            </p>
                        </div>
                        <div x-show="daysLeft === 0 && hoursLeft > 0" class="space-y-1">
                            <span class="text-7xl font-black text-gold block leading-none" x-text="hoursLeft"></span>
                            <span class="text-[10px] font-black text-muted uppercase tracking-widest">horas</span>
                        </div>
                        <div x-show="daysLeft === 0 && hoursLeft === 0" class="space-y-1">
                            <span class="text-4xl font-black text-gold block animate-pulse">¡Hoy!</span>
                            <span class="text-[10px] font-black text-muted uppercase tracking-widest">es tu sesión</span>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @php
                $todayStr     = now()->toDateString();
                $statusConfig = [
                    'pendiente'  => ['badge' => 'border-yellow-900/50 bg-yellow-950/20 text-yellow-400', 'bar' => 'bg-yellow-400'],
                    'confirmada' => ['badge' => 'border-blue-900/50 bg-blue-950/20 text-blue-400',       'bar' => 'bg-blue-400'],
                    'en_proceso' => ['badge' => 'border-sky-900/50 bg-sky-950/20 text-sky-400',          'bar' => 'bg-sky-400'],
                    'completada' => ['badge' => 'border-green-900/50 bg-green-950/20 text-green-400',    'bar' => 'bg-green-400'],
                    'cancelada'  => ['badge' => 'border-red-900/50 bg-red-950/20 text-red-400',         'bar' => 'bg-red-500'],
                    'no_asistio' => ['badge' => 'border-orange-900/50 bg-orange-950/20 text-orange-400', 'bar' => 'bg-orange-400'],
                ];
                // Etiquetas claras para el cliente (una cita nace "pendiente de aprobacion").
                $statusLabels = [
                    'pendiente'  => 'Pendiente de aprobación',
                    'confirmada' => 'Confirmada',
                    'en_proceso' => 'En proceso',
                    'completada' => 'Completada',
                    'cancelada'  => 'Cancelada',
                    'no_asistio' => 'No asististe',
                ];
                $upcoming = $appointments->filter(fn($a) =>
                    $a->fecha->toDateString() >= $todayStr &&
                    !in_array($a->estado, ['cancelada', 'completada', 'no_asistio'], true)
                );
                $history = $appointments->filter(fn($a) =>
                    $a->fecha->toDateString() < $todayStr ||
                    in_array($a->estado, ['cancelada', 'completada', 'no_asistio'], true)
                );
            @endphp

            @if($appointments->isEmpty())
                <!-- Empty state -->
                <div class="py-20 text-center rounded-3xl border border-dashed border-white/5 bg-white/[0.01]">
                    <div class="h-16 w-16 rounded-3xl bg-gold/5 border border-gold/10 flex items-center justify-center mx-auto mb-6">
                        <svg class="h-8 w-8 text-gold/30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <p class="text-sm font-black text-muted uppercase tracking-widest">Sin citas registradas</p>
                    <p class="text-xs text-muted/50 mt-2 max-w-xs mx-auto">Tu historial aparecerá aquí una vez que reserves tu primera sesión.</p>
                    <a href="{{ route('client.appointments.create') }}"
                       class="mt-8 inline-block ui-btn px-10 py-3 text-[11px] uppercase tracking-[0.2em]">
                        Reservar ahora &rarr;
                    </a>
                </div>
            @else
                <!-- Upcoming section -->
                @if($upcoming->isNotEmpty())
                <section class="space-y-5">
                    <div class="flex items-center gap-3">
                        <div class="h-px flex-1 bg-white/5"></div>
                        <span class="text-[9px] font-black uppercase tracking-[0.35em] text-gold">Próximas</span>
                        <div class="h-px flex-1 bg-white/5"></div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        @foreach($upcoming as $appointment)
                            @php
                                $estado = strtolower((string) $appointment->estado);
                                $sc = $statusConfig[$estado] ?? ['badge' => 'border-white/10 bg-white/5 text-muted', 'bar' => 'bg-muted'];
                                $startsAt = \Carbon\Carbon::parse($appointment->fecha->format('Y-m-d').' '.$appointment->hora_inicio);
                                $canManage = in_array($estado, ['pendiente', 'confirmada'], true) && $startsAt->isFuture();
                                $canCancelAppointment = $canManage && now()->diffInHours($startsAt, false) >= ($policyHours ?? 24);
                            @endphp
                            <article class="group relative overflow-hidden rounded-2xl border border-white/5 bg-white/[0.02] transition-all hover:border-gold/20 hover:bg-white/[0.04]">
                                <div class="absolute left-0 top-0 h-full w-1 {{ $sc['bar'] }} opacity-70 rounded-l-2xl"></div>
                                <div class="p-6 pl-8">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <p class="text-base font-black text-white uppercase leading-tight">{{ $appointment->service?->nombre }}</p>
                                            <p class="text-[11px] font-bold text-muted mt-0.5">con {{ $appointment->barber?->user?->name }}</p>
                                        </div>
                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[8px] font-black uppercase tracking-wider {{ $sc['badge'] }}">
                                            {{ $statusLabels[strtolower($appointment->estado)] ?? ucfirst($appointment->estado) }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-5 text-xs">
                                        <div class="flex items-center gap-1.5 text-muted">
                                            <svg class="h-3.5 w-3.5 text-gold/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <span class="font-bold">{{ \Carbon\Carbon::parse($appointment->fecha)->translatedFormat('d M, Y') }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-muted">
                                            <svg class="h-3.5 w-3.5 text-gold/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="font-bold">{{ substr($appointment->hora_inicio, 0, 5) }} – {{ substr($appointment->hora_fin, 0, 5) }}</span>
                                        </div>
                                    </div>

                                    @if(!empty($appointment->productos))
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        @foreach($appointment->productos as $prod)
                                            <span class="inline-flex items-center gap-1 rounded-lg border border-white/5 bg-white/[0.03] px-2 py-0.5 text-[9px] font-bold text-muted">
                                                {{ $prod['nombre'] ?? '' }} ×{{ $prod['cantidad'] ?? 1 }}
                                            </span>
                                        @endforeach
                                    </div>
                                    @endif

                                    <div class="mt-5 flex items-center gap-2 pt-4 border-t border-white/5">
                                        @if($canManage)
                                            <a href="{{ route('client.appointments.edit', $appointment) }}"
                                               class="flex-1 text-center rounded-xl border border-white/10 bg-white/5 py-2 text-[9px] font-black uppercase tracking-widest text-muted hover:border-gold/30 hover:text-gold transition-all">
                                                Reagendar
                                            </a>
                                        @endif

                                        @if($canCancelAppointment)
                                            <form action="{{ route('client.appointments.destroy', $appointment) }}" method="POST"
                                                  class="flex-1" onsubmit="return confirm('¿Cancelar esta cita?');">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                    class="w-full rounded-xl border border-red-900/30 bg-transparent py-2 text-[9px] font-black uppercase tracking-widest text-red-500/50 hover:bg-red-500/10 hover:text-red-400 hover:border-red-500/30 transition-all">
                                                    Cancelar
                                                </button>
                                            </form>
                                        @elseif($canManage)
                                            <div class="flex-1 rounded-xl border border-amber-500/15 bg-amber-500/[0.04] px-3 py-2 text-center text-[9px] font-bold text-amber-300/70">
                                                Cancelación: {{ $policyHours ?? 24 }}h mín.
                                            </div>
                                        @else
                                            <div class="w-full rounded-xl border border-white/[0.06] bg-white/[0.025] px-3 py-2 text-center text-[9px] font-bold text-white/35">
                                                Solo seguimiento
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
                @endif

                <!-- History section -->
                @if($history->isNotEmpty())
                <section class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="h-px flex-1 bg-white/5"></div>
                        <span class="text-[9px] font-black uppercase tracking-[0.35em] text-muted/60">Historial</span>
                        <div class="h-px flex-1 bg-white/5"></div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach($history as $appointment)
                            @php $sc = $statusConfig[strtolower($appointment->estado)] ?? ['badge' => 'border-white/10 bg-white/5 text-muted', 'bar' => 'bg-muted']; @endphp
                            <article class="group relative overflow-hidden rounded-2xl border border-white/[0.04] bg-white/[0.01] transition-all hover:opacity-80">
                                <div class="absolute left-0 top-0 h-full w-1 {{ $sc['bar'] }} opacity-25 rounded-l-2xl"></div>
                                <div class="p-5 pl-8">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <p class="text-sm font-black text-white/60 uppercase">{{ $appointment->service?->nombre }}</p>
                                            <p class="text-[10px] font-bold text-muted/60 mt-0.5">con {{ $appointment->barber?->user?->name }}</p>
                                        </div>
                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-[8px] font-black uppercase tracking-wider opacity-60 {{ $sc['badge'] }}">
                                            {{ $statusLabels[strtolower($appointment->estado)] ?? ucfirst($appointment->estado) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3 text-[10px] text-muted/50 font-bold">
                                        <span>{{ \Carbon\Carbon::parse($appointment->fecha)->translatedFormat('d M, Y') }}</span>
                                        <span>·</span>
                                        <span>{{ substr($appointment->hora_inicio, 0, 5) }}</span>
                                        @if($appointment->precio_cobrado)
                                        <span>·</span>
                                        <span class="text-gold/40">${{ number_format($appointment->precio_cobrado, 2) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
                @endif

                <div class="mt-2">{{ $appointments->links() }}</div>
            @endif
        </div>
    </div>

    <script>
        function countdown(dateStr, timeStr) {
            return {
                daysLeft: 0,
                hoursLeft: 0,
                init() {
                    const target = new Date(`${dateStr}T${timeStr}`);
                    const now    = new Date();
                    const diff   = target - now;
                    if (diff <= 0) return;
                    this.daysLeft  = Math.floor(diff / (1000 * 60 * 60 * 24));
                    this.hoursLeft = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                }
            };
        }
    </script>
</x-app-layout>
