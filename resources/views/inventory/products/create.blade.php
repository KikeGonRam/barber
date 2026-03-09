<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Nuevo <span class="text-gold">Producto de Inventario</span></h2>
                <p class="ui-subtitle">Añade nuevos suministros o productos para venta al por menor.</p>
            </div>
            <a href="{{ route('inventory.products.index') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-white transition">
                &larr; Volver al inventario
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('inventory.products.store') }}" enctype="multipart/form-data" class="space-y-10">
                    @csrf

                    <!-- Section: Product Identity -->
                    <div>
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Identidad del Producto</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="ui-label" for="imagen">Imagen del Producto</label>
                                <input type="file" name="imagen" id="imagen" class="ui-input !bg-panel border-white/10 text-white" accept="image/*">
                                @error('imagen') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="ui-label" for="nombre">Nombre Comercial</label>
                                <input name="nombre" value="{{ old('nombre') }}" class="ui-input !bg-panel border-white/10 text-white" required placeholder="Ej: Cera Mate Premium">
                                @error('nombre') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="ui-label" for="categoria">Categoría</label>
                                <input name="categoria" value="{{ old('categoria') }}" class="ui-input !bg-panel border-white/10 text-white" required placeholder="Ej: Estilizado">
                                @error('categoria') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="ui-label" for="tipo">Tipo de Uso</label>
                                <select name="tipo" class="ui-input !bg-panel border-white/10 text-white" required>
                                    <option value="venta_cliente" @selected(old('tipo') === 'venta_cliente')>Venta a Cliente</option>
                                    <option value="insumo_trabajo" @selected(old('tipo') === 'insumo_trabajo')>Insumo de Trabajo</option>
                                </select>
                                @error('tipo') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Section: Pricing & Stock -->
                    <div class="pt-8 border-t border-white/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Finanzas & Existencias</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label class="ui-label">Precio Compra</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-muted font-bold">$</span>
                                    <input type="number" step="0.01" min="0" name="precio_compra" value="{{ old('precio_compra') }}" class="ui-input !pl-10 !bg-panel border-white/10 text-white" required>
                                </div>
                            </div>
                            <div>
                                <label class="ui-label">Precio Venta</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gold font-bold">$</span>
                                    <input type="number" step="0.01" min="0" name="precio_venta" value="{{ old('precio_venta') }}" class="ui-input !pl-10 !bg-panel border-white/10 text-white" required>
                                </div>
                            </div>
                            <div>
                                <label class="ui-label">Stock Inicial</label>
                                <input type="number" min="0" name="stock_actual" value="{{ old('stock_actual', 0) }}" class="ui-input !bg-panel border-white/10 text-white" required>
                            </div>
                            <div>
                                <label class="ui-label">Mínimo Crítico</label>
                                <input type="number" min="0" name="stock_minimo" value="{{ old('stock_minimo', 0) }}" class="ui-input !bg-panel border-white/10 text-white" required title="Alertar cuando el stock baje de este valor">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Details -->
                    <div class="pt-8 border-t border-white/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Descripción Técnica</h3>
                        </div>

                        <div>
                            <textarea name="description" rows="4" class="ui-input !bg-panel border-white/10 text-white leading-relaxed" placeholder="Especificaciones, marca o modo de uso...">{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <div class="pt-10 border-t border-white/5 flex justify-end">
                        <button type="submit" class="ui-btn px-16 py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                            Registrar en Inventario <span class="ml-2 opacity-50">&rarr;</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
