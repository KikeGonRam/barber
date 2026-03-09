<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Gestión de Inventario</h2>
                <p class="ui-subtitle">Control de stock, productos y suministros de la barbería.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('inventory.movements.index') }}" class="ui-btn-secondary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    Movimientos
                </a>
                <a href="{{ route('inventory.products.create') }}" class="ui-btn">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo producto
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            @if ($lowStockCount > 0)
                <div class="mb-6 rounded-lg border-l-4 border-yellow-400 bg-yellow-50 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <span class="font-bold">Alerta de Stock:</span>
                                Tienes {{ $lowStockCount }} producto(s) por debajo del stock mínimo.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <section class="space-y-6">
                <!-- Desktop Table -->
                <div class="hidden md:block ui-table-container">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Tipo</th>
                                <th>Stock Actual</th>
                                <th>Precios</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr>
                                    <td class="font-medium text-white">
                                        <div class="flex items-center gap-4">
                                            <div class="h-12 w-12 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center overflow-hidden flex-shrink-0">
                                                @if($product->imagen)
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($product->imagen) }}" alt="{{ $product->nombre }}" class="h-full w-full object-cover">
                                                @else
                                                    <svg class="h-6 w-6 text-white/5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="1.5"/></svg>
                                                @endif
                                            </div>
                                            {{ $product->nombre }}
                                        </div>
                                    </td>

                                    <td>
                                        <span class="inline-flex items-center rounded-md bg-white/10 px-2 py-1 text-xs font-medium ring-1 ring-inset ring-white/10">
                                            {{ $product->categoria }}
                                        </span>
                                    </td>
                                    <td class="text-xs uppercase tracking-wider">
                                        {{ $product->tipo }}
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            @php
                                                $isLow = $product->stock_actual <= $product->stock_minimo;
                                            @endphp
                                            <span class="text-sm font-semibold {{ $isLow ? 'text-red-500' : '' }}">
                                                {{ $product->stock_actual }}
                                            </span>
                                            <span class="text-[10px] text-muted uppercase">Min: {{ $product->stock_minimo }}</span>
                                            @if($isLow)
                                                <span class="flex h-2 w-2 rounded-full bg-red-500" title="Bajo stock"></span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col text-xs">
                                            <span>Compra: <span class="font-medium">${{ number_format($product->precio_compra, 2) }}</span></span>
                                            <span>Venta: <span class="font-medium text-green-500">${{ number_format($product->precio_venta, 2) }}</span></span>
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('inventory.products.edit', $product) }}" class="text-muted hover:text-blue-600 transition-colors" title="Editar">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                            <form action="{{ route('inventory.products.destroy', $product) }}" method="POST" onsubmit="return confirm('¿Eliminar producto?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-muted hover:text-red-600 transition-colors" title="Eliminar">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center">
                                        No hay productos registrados en el inventario.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="space-y-4 md:hidden">
                    @forelse ($products as $product)
                        <div class="ui-card p-4">
                            <div class="flex items-start gap-4">
                                <div class="h-16 w-16 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center overflow-hidden flex-shrink-0">
                                    @if($product->imagen)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($product->imagen) }}" alt="{{ $product->nombre }}" class="h-full w-full object-cover">
                                    @else
                                        <svg class="h-8 w-8 text-white/5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="1.5"/></svg>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h3 class="text-sm font-bold text-white truncate">{{ $product->nombre }}</h3>
                                            <p class="text-[10px] text-muted uppercase tracking-widest">{{ $product->categoria }} • {{ $product->tipo }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-black text-green-400">${{ number_format($product->precio_venta, 2) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between border-t border-white/5 pt-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs">Stock:</span>
                                    <span class="text-sm font-bold {{ $product->stock_actual <= $product->stock_minimo ? 'text-red-500' : '' }}">
                                        {{ $product->stock_actual }}
                                    </span>
                                    <span class="text-[10px] text-muted">/ {{ $product->stock_minimo }} min</span>
                                </div>
                                <div class="flex gap-3">
                                    <a href="{{ route('inventory.products.edit', $product) }}" class="text-xs font-medium text-blue-600">Editar</a>
                                    <form action="{{ route('inventory.products.destroy', $product) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-red-600">Eliminar</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8">No hay productos.</div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $products->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
