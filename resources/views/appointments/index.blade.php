<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Citas y Reservas</h2>
                <p class="ui-subtitle">Gestiona la agenda y el seguimiento de servicios.</p>
            </div>
            <a href="{{ route('appointments.create') }}" class="ui-btn">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nueva cita
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <section class="space-y-6">
                <!-- Desktop Table -->
                <div class="hidden md:block ui-table-container">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Servicio</th>
                                <th>Barbero</th>
                                <th>Fecha y Hora</th>
                                <th>Estado</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($appointments as $appointment)
                                <tr>
                                    <td class="font-bold text-white">
                                        {{ $appointment->client?->user?->name ?? 'Cliente Desconocido' }}
                                    </td>
                                    <td>
                                        {{ $appointment->service?->nombre ?? 'Servicio no especificado' }}
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            @if($appointment->barber?->user)
                                                <div class="h-6 w-6 rounded-full bg-white/10 flex items-center justify-center text-[10px] font-bold text-gold border border-white/5">
                                                    {{ substr($appointment->barber->user->name, 0, 2) }}
                                                </div>
                                                {{ $appointment->barber->user->name }}
                                            @else
                                                <span class="text-muted italic">Sin asignar</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col">
                                            <span class="font-bold text-white">{{ \Carbon\Carbon::parse($appointment->fecha)->format('d M, Y') }}</span>
                                            <span class="text-xs text-muted">{{ substr($appointment->hora_inicio, 0, 5) }} - {{ substr($appointment->hora_fin, 0, 5) }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $statusColors = [
                                                'pendiente' => 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20',
                                                'confirmada' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                                'completada' => 'bg-green-500/10 text-green-400 border-green-500/20',
                                                'cancelada' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                            ];
                                            $statusColor = $statusColors[strtolower($appointment->estado)] ?? 'bg-white/5 text-muted border-white/10';
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-widest border {{ $statusColor }}">
                                            {{ $appointment->estado }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-3">
                                            <a href="{{ route('appointments.edit', $appointment) }}" class="text-muted hover:text-gold transition-colors" title="Editar">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                            <form action="{{ route('appointments.destroy', $appointment) }}" method="POST" onsubmit="return confirm('¿Cancelar esta cita?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-muted hover:text-red-500 transition-colors" title="Cancelar">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-16 text-center text-muted">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="h-12 w-12 text-white/5 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <p class="text-sm font-medium">No hay citas registradas próximamente.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="space-y-4 md:hidden">
                    @forelse ($appointments as $appointment)
                        <div class="ui-card p-5">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-xl bg-white/5 flex items-center justify-center text-gold border border-white/10">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-black text-white">{{ $appointment->client?->user?->name ?? 'Cliente' }}</h3>
                                        <p class="text-[10px] uppercase font-bold tracking-widest text-gold">{{ $appointment->service?->nombre ?? 'Servicio' }}</p>
                                    </div>
                                </div>
                                @php
                                    $statusColors = [
                                        'pendiente' => 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20',
                                        'confirmada' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                        'completada' => 'bg-green-500/10 text-green-400 border-green-500/20',
                                        'cancelada' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                    ];
                                    $statusColor = $statusColors[strtolower($appointment->estado)] ?? 'bg-white/5 text-muted border-white/10';
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-widest border {{ $statusColor }}">
                                    {{ $appointment->estado }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-4 text-xs border-t border-white/5 pt-4">
                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-widest text-muted mb-1">Fecha y Hora</span>
                                    <span class="font-bold text-white">{{ \Carbon\Carbon::parse($appointment->fecha)->format('d/m/Y') }}</span>
                                    <p class="text-muted mt-0.5">{{ substr($appointment->hora_inicio, 0, 5) }} - {{ substr($appointment->hora_fin, 0, 5) }}</p>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-widest text-muted mb-1">Barbero</span>
                                    <span class="text-white">{{ $appointment->barber?->user?->name ?? 'Sin asignar' }}</span>
                                </div>
                            </div>

                            <div class="mt-5 flex justify-end gap-4 border-t border-white/5 pt-4">
                                <a href="{{ route('appointments.edit', $appointment) }}" class="text-[10px] font-black uppercase tracking-widest text-gold">Editar</a>
                                <form action="{{ route('appointments.destroy', $appointment) }}" method="POST" onsubmit="return confirm('¿Cancelar cita?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[10px] font-black uppercase tracking-widest text-red-500">Cancelar</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 ui-card bg-panel/20 border-dashed">
                            <p class="text-sm text-muted">No hay citas.</p>
                        </div>
                    @endforelse
                </div>

                <div class="mt-6">
                    {{ $appointments->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
