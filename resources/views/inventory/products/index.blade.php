<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Inventario | Productos</h2>
            <span class="ui-badge">Stock</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="ui-card px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif

            @if ($lowStockCount > 0)
                <div class="ui-card px-4 py-2 text-sm">
                    Hay {{ $lowStockCount }} producto(s) en stock minimo o por debajo.
                </div>
            @endif

            <section class="ui-surface">
                <div class="ui-toolbar">
                    <div>
                        <p class="text-sm font-semibold text-[#1f1f1f]">Catalogo de productos</p>
                        <p class="text-xs text-[#707070]">Control de costos, venta y stock minimo.</p>
                    </div>
                    <div class="ui-toolbar-group">
                        <a href="{{ route('inventory.products.create') }}" class="ui-btn">Nuevo producto</a>
                        <a href="{{ route('inventory.movements.index') }}" class="ui-btn-secondary">Ver movimientos</a>
                    </div>
                </div>

                <div class="ui-list">
                    @forelse ($products as $product)
                        <article class="ui-list-item">
                            <div class="ui-list-item-head">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-[#141414]">{{ $product->nombre }}</h3>
                                    <span class="ui-badge">{{ $product->tipo }}</span>
                                </div>
                                <div class="ui-toolbar-group">
                                    <a href="{{ route('inventory.products.edit', $product) }}" class="ui-btn-secondary">Editar</a>
                                    <form action="{{ route('inventory.products.destroy', $product) }}" method="POST" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn">Eliminar</button>
                                    </form>
                                </div>
                            </div>

                            <div class="ui-meta-grid">
                                <div><strong>Categoria:</strong> {{ $product->categoria }}</div>
                                <div><strong>Stock:</strong> {{ $product->stock_actual }} (minimo: {{ $product->stock_minimo }})</div>
                                <div><strong>Precio compra:</strong> ${{ number_format((float) $product->precio_compra, 2) }}</div>
                                <div><strong>Precio venta:</strong> ${{ number_format((float) $product->precio_venta, 2) }}</div>
                            </div>
                        </article>
                    @empty
                        <div class="ui-empty">No hay productos registrados.</div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $products->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
