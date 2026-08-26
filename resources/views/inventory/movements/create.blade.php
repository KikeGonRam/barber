<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Auditoría de <span class="text-gold">Inventario</span></h2>
                <p class="ui-subtitle">Registra el flujo de productos y suministros operativos.</p>
            </div>
            <a href="{{ route('inventory.movements.index') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-white transition">
                &larr; Volver al historial
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('inventory.movements.store') }}" class="space-y-10">
                    @csrf

                    <!-- Section: Product & Type -->
                    <div>
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Identificación del Movimiento</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="ui-label">Producto / Suministro</label>
                                <select name="product_id" class="ui-input !bg-panel border-white/10 text-white" required>
                                    <option value="">Seleccionar artículo...</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->nombre }} (Stock: {{ $product->stock_actual }})</option>
                                    @endforeach
                                </select>
                                @error('product_id') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="ui-label">Dirección del Flujo</label>
                                <div class="grid grid-cols-2 gap-4">
                                    @php $canEntry = !auth()->user()?->hasRole('recepcionista'); @endphp
                                    @if($canEntry)
                                        <label class="relative flex cursor-pointer items-center justify-center rounded-xl border border-white/5 bg-white/5 p-3 transition-all hover:border-green-500/30 has-[:checked]:border-green-500 has-[:checked]:bg-green-500/10 group">
                                            <input type="radio" name="tipo" value="entrada" class="sr-only" @checked(old('tipo') === 'entrada')>
                                            <span class="text-[10px] font-black uppercase text-muted group-hover:text-green-400">Entrada</span>
                                        </label>
                                    @endif
                                    <label class="relative flex cursor-pointer items-center justify-center rounded-xl border border-white/5 bg-white/5 p-3 transition-all hover:border-red-500/30 has-[:checked]:border-red-500 has-[:checked]:bg-red-500/10 group">
                                        <input type="radio" name="tipo" value="salida" class="sr-only" @checked(old('tipo', auth()->user()?->hasRole('recepcionista') ? 'salida' : '') === 'salida')>
                                        <span class="text-[10px] font-black uppercase text-muted group-hover:text-red-400">Salida</span>
                                    </label>
                                </div>
                                @error('tipo') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Section: Quantity & Reference -->
                    <div class="pt-8 border-t border-white/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Volumen & Referencia</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="ui-label">Cantidad</label>
                                <input type="number" min="1" name="cantidad" value="{{ old('cantidad', 1) }}" 
                                       class="ui-input !bg-panel border-white/10 text-white" required>
                                @error('cantidad') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="ui-label">Cita Relacionada (Opcional)</label>
                                <select name="appointment_id" class="ui-input !bg-panel border-white/10 text-white">
                                    <option value="">Sin vinculación a cita</option>
                                    @foreach($appointments as $appointment)
                                        <option value="{{ $appointment->id }}" @selected(old('appointment_id') == $appointment->id)>
                                            {{ $appointment->fecha?->translatedFormat('d M, Y') }} - {{ $appointment->client?->user?->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Reasoning -->
                    <div class="pt-8 border-t border-white/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Justificación del Cambio</h3>
                        </div>

                        <input name="motivo" value="{{ old('motivo') }}" 
                               class="ui-input !bg-panel border-white/10 text-white" 
                               placeholder="Ej: Reposición de stock, Uso en servicio #123, Producto dañado...">
                        @error('motivo') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                    </div>

                    <div class="pt-10 border-t border-white/5 flex justify-end">
                        <button type="submit" class="ui-btn px-16 py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                            Validar Movimiento de Stock <span class="ml-2 opacity-50">&rarr;</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
