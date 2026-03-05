<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Inventario | Movimientos</h2>
            <span class="ui-badge">Trazabilidad</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="ui-card px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif

            <section class="ui-surface">
                <div class="ui-toolbar">
                    <div>
                        <p class="text-sm font-semibold text-[#1f1f1f]">Historial de movimientos</p>
                        <p class="text-xs text-[#707070]">Entradas y salidas por producto con responsable.</p>
                    </div>
                    <div class="ui-toolbar-group">
                        <a href="{{ route('inventory.movements.create') }}" class="ui-btn">Registrar movimiento</a>
                        @if(auth()->user()?->hasRole('administrador'))
                            <a href="{{ route('inventory.products.index') }}" class="ui-btn-secondary">Ver productos</a>
                        @endif
                    </div>
                </div>

                <div class="ui-list">
                    @forelse($movements as $movement)
                        <article class="ui-list-item">
                            <div class="ui-list-item-head">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-[#141414]">{{ $movement->product?->nombre ?? 'Producto' }}</h3>
                                    <span class="ui-badge">{{ $movement->tipo }}</span>
                                </div>
                            </div>
                            <div class="ui-meta-grid">
                                <div><strong>Fecha:</strong> {{ $movement->fecha }}</div>
                                <div><strong>Cantidad:</strong> {{ $movement->cantidad }}</div>
                                <div><strong>Responsable:</strong> {{ $movement->user?->name ?? '-' }}</div>
                                <div><strong>Motivo:</strong> {{ $movement->motivo ?: '-' }}</div>
                                <div><strong>Cita:</strong> {{ $movement->appointment?->client?->user?->name ? ($movement->appointment?->fecha.' '.$movement->appointment?->hora_inicio.' - '.$movement->appointment?->client?->user?->name) : '-' }}</div>
                            </div>
                        </article>
                    @empty
                        <div class="ui-empty">No hay movimientos registrados.</div>
                    @endforelse
                </div>

                <div class="mt-4">{{ $movements->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
