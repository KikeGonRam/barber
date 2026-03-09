<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Control de <span class="text-gold">Movimientos</span></h2>
                <p class="ui-subtitle">Historial detallado de entradas y salidas de inventario.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('inventory.movements.create') }}" class="ui-btn">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    Registrar Movimiento
                </a>
            </div>
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
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Responsable</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $movement)
                                <tr>
                                    <td class="text-muted text-xs">
                                        {{ $movement->fecha }}
                                    </td>
                                    <td class="font-bold text-white">
                                        {{ $movement->product?->nombre ?? 'Producto' }}
                                    </td>
                                    <td>
                                        @php
                                            $isEntry = strtolower($movement->tipo) === 'entrada';
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-widest border {{ $isEntry ? 'bg-green-500/10 text-green-400 border-green-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20' }}">
                                            {{ $movement->tipo }}
                                        </span>
                                    </td>
                                    <td class="font-bold {{ $isEntry ? 'text-green-400' : 'text-red-400' }}">
                                        {{ $isEntry ? '+' : '-' }}{{ $movement->cantidad }}
                                    </td>
                                    <td class="text-white">
                                        {{ $movement->user?->name ?? '-' }}
                                    </td>
                                    <td class="text-muted italic text-xs">
                                        {{ $movement->motivo ?: '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-16 text-center text-muted">No hay movimientos registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="space-y-4 md:hidden">
                    @forelse($movements as $movement)
                        <div class="ui-card p-5">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="text-sm font-bold text-white">{{ $movement->product?->nombre ?? 'Producto' }}</h3>
                                    <p class="text-[10px] uppercase font-bold tracking-widest text-muted">{{ $movement->fecha }}</p>
                                </div>
                                <span class="font-black {{ strtolower($movement->tipo) === 'entrada' ? 'text-green-400' : 'text-red-400' }}">
                                    {{ strtolower($movement->tipo) === 'entrada' ? '+' : '-' }}{{ $movement->cantidad }}
                                </span>
                            </div>
                            <div class="border-t border-white/5 pt-3">
                                <p class="text-xs text-muted"><span class="font-bold text-white">Motivo:</span> {{ $movement->motivo ?: '-' }}</p>
                                <p class="text-[10px] text-muted uppercase mt-1">Por: {{ $movement->user?->name ?? '-' }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 ui-card bg-panel/20 border-dashed">
                            <p class="text-sm text-muted">No hay movimientos.</p>
                        </div>
                    @endforelse
                </div>

                <div class="mt-6">
                    {{ $movements->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
