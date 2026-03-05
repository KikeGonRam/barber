<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Registrar movimiento de inventario</h2>
            <span class="ui-badge">Operacion</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('inventory.movements.store') }}" class="space-y-4">
                    @csrf

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label">Producto</label>
                            <select name="product_id" class="ui-input" required>
                                <option value="">Seleccionar</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->nombre }} (stock: {{ $product->stock_actual }})</option>
                                @endforeach
                            </select>
                            @error('product_id') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="ui-label">Tipo</label>
                            <select name="tipo" class="ui-input" required>
                                @if(auth()->user()?->hasRole('recepcionista'))
                                    <option value="salida" @selected(old('tipo') === 'salida')>salida</option>
                                @else
                                    <option value="entrada" @selected(old('tipo') === 'entrada')>entrada</option>
                                    <option value="salida" @selected(old('tipo') === 'salida')>salida</option>
                                @endif
                            </select>
                            @error('tipo') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="ui-label">Cantidad</label>
                            <input type="number" min="1" name="cantidad" value="{{ old('cantidad', 1) }}" class="ui-input" required>
                            @error('cantidad') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                        <label class="ui-label">Cita (opcional)</label>
                        <select name="appointment_id" class="ui-input">
                            <option value="">Ninguna</option>
                            @foreach($appointments as $appointment)
                                    <option value="{{ $appointment->id }}" @selected(old('appointment_id') == $appointment->id)>{{ $appointment->fecha }} {{ $appointment->hora_inicio }} - {{ $appointment->client?->user?->name }} / {{ $appointment->barber?->user?->name }}</option>
                            @endforeach
                        </select>
                            @error('appointment_id') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="ui-label">Motivo</label>
                        <input name="motivo" value="{{ old('motivo') }}" class="ui-input">
                        @error('motivo') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                    </div>

                    <div class="ui-toolbar-group pt-2">
                        <button type="submit" class="ui-btn">Guardar movimiento</button>
                        <a href="{{ route('inventory.movements.index') }}" class="ui-btn-secondary">Volver</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
