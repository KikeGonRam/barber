<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Editar producto</h2>
            <span class="ui-badge">Inventario</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('inventory.products.update', $product) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label">Nombre</label>
                            <input name="nombre" value="{{ old('nombre', $product->nombre) }}" class="ui-input" required>
                            @error('nombre') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label">Categoria</label>
                            <input name="categoria" value="{{ old('categoria', $product->categoria) }}" class="ui-input" required>
                            @error('categoria') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label">Tipo</label>
                            <select name="tipo" class="ui-input" required>
                                <option value="venta_cliente" @selected(old('tipo', $product->tipo) === 'venta_cliente')>venta_cliente</option>
                                <option value="insumo_trabajo" @selected(old('tipo', $product->tipo) === 'insumo_trabajo')>insumo_trabajo</option>
                            </select>
                            @error('tipo') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label">Precio compra</label>
                            <input type="number" step="0.01" min="0" name="precio_compra" value="{{ old('precio_compra', $product->precio_compra) }}" class="ui-input" required>
                            @error('precio_compra') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label">Precio venta</label>
                            <input type="number" step="0.01" min="0" name="precio_venta" value="{{ old('precio_venta', $product->precio_venta) }}" class="ui-input" required>
                            @error('precio_venta') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label">Stock actual</label>
                            <input type="number" min="0" name="stock_actual" value="{{ old('stock_actual', $product->stock_actual) }}" class="ui-input" required>
                            @error('stock_actual') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label">Stock minimo</label>
                            <input type="number" min="0" name="stock_minimo" value="{{ old('stock_minimo', $product->stock_minimo) }}" class="ui-input" required>
                            @error('stock_minimo') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="ui-label">Descripcion</label>
                        <textarea name="descripcion" class="ui-input">{{ old('descripcion', $product->descripcion) }}</textarea>
                        @error('descripcion') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                    </div>

                    <div class="ui-toolbar-group pt-2">
                        <button type="submit" class="ui-btn">Actualizar producto</button>
                        <a href="{{ route('inventory.products.index') }}" class="ui-btn-secondary">Volver</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
