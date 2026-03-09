<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Gestión de <span class="text-gold">Almacén Central</span></h2>
                <p class="ui-subtitle">Control maestro de suministros y productos operativos.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('almacen.create') }}" class="ui-btn shadow-gold/10">
                    <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo Suministro
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            @if ($lowStockCount > 0)
                <div class="ui-card-premium border-yellow-500/30 bg-yellow-500/5 p-6 flex items-center gap-4">
                    <div class="h-12 w-12 rounded-2xl bg-yellow-500/10 flex items-center justify-center text-yellow-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                    <div>
                        <h4 class="text-white font-black uppercase text-xs tracking-widest">Alerta de Stock Crítico</h4>
                        <p class="text-muted text-[11px] font-bold uppercase mt-1">Hay {{ $lowStockCount }} producto(s) por debajo del mínimo de seguridad.</p>
                    </div>
                </div>
            @endif

            <section class="ui-table-container">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Identificación / Nombre</th>
                            <th>Existencias</th>
                            <th>Stock Mínimo</th>
                            <th>Valor Unitario</th>
                            <th>Estatus</th>
                            <th class="text-right">Gestión</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($inventories as $inventory)
                            <tr class="group">
                                <td class="font-black text-white uppercase tracking-tight">
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center overflow-hidden flex-shrink-0">
                                            @if($inventory->imagen)
                                                <img src="{{ \Illuminate\Support\Facades\Storage::url($inventory->imagen) }}" alt="{{ $inventory->name }}" class="h-full w-full object-cover">
                                            @else
                                                <svg class="h-6 w-6 text-white/5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" stroke-width="1.5"/></svg>
                                            @endif
                                        </div>
                                        {{ $inventory->name }}
                                    </div>
                                </td>
                                <td>
                                    <span class="text-lg font-black {{ $inventory->quantity <= $inventory->min_stock ? 'text-red-400' : 'text-green-400' }}">
                                        {{ $inventory->quantity }}
                                    </span>
                                </td>
                                <td class="text-muted font-bold text-xs uppercase tracking-widest">
                                    {{ $inventory->min_stock }}
                                </td>
                                <td class="text-white font-black">
                                    ${{ number_format($inventory->price, 2) }}
                                </td>
                                <td>
                                    @if ($inventory->active)
                                        <span class="ui-badge border-green-500/20 bg-green-500/5 text-green-400">Activo</span>
                                    @else
                                        <span class="ui-badge border-red-500/20 bg-red-500/5 text-red-400">Inactivo</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <a href="{{ route('almacen.show', $inventory) }}" class="text-muted hover:text-gold transition-colors">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-width="1.5"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c3.477 0 6.517 1.76 8.542 4.458a1.99 1.99 0 010 2.542C18.517 14.76 15.477 16.5 12 16.5c-4.477 0-7.523-2.943-9.542-5.542z" stroke-width="1.5"/></svg>
                                        </a>
                                        @can('update', $inventory)
                                            <a href="{{ route('almacen.edit', $inventory) }}" class="text-muted hover:text-blue-400 transition-colors">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" stroke-width="1.5"/></svg>
                                            </a>
                                        @endcan
                                        @can('delete', $inventory)
                                            <form method="POST" action="{{ route('almacen.destroy', $inventory) }}" onsubmit="return confirm('¿Eliminar suministro permanentemente?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-muted hover:text-red-500 transition-colors">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="1.5"/></svg>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-20 text-center text-muted">
                                    <svg class="h-12 w-12 text-white/5 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                    <p class="text-sm font-bold uppercase tracking-widest">El almacén está vacío.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <div class="mt-6">
                {{ $inventories->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
