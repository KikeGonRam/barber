<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Registrar pago</h2>
            <span class="ui-badge">Cobro</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('payments.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="ui-label" for="appointment_id">Cita</label>
                        <select id="appointment_id" name="appointment_id" class="ui-input" required>
                            <option value="">Seleccionar cita</option>
                            @foreach($appointments as $appointment)
                                <option value="{{ $appointment->id }}" @selected(old('appointment_id') == $appointment->id)>
                                    {{ $appointment->fecha }} {{ $appointment->hora_inicio }} - {{ $appointment->client?->user?->name }} / {{ $appointment->service?->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @error('appointment_id') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                    </div>

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label" for="monto">Monto</label>
                            <input id="monto" type="number" step="0.01" min="0.01" name="monto" value="{{ old('monto') }}" class="ui-input" required>
                            @error('monto') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="ui-label" for="propina">Propina (opcional)</label>
                            <input id="propina" type="number" step="0.01" min="0" name="propina" value="{{ old('propina', 0) }}" class="ui-input">
                            @error('propina') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="md:max-w-[50%]">
                        <label class="ui-label" for="metodo_pago">Metodo de pago</label>
                        <select id="metodo_pago" name="metodo_pago" class="ui-input" required>
                            @foreach(['efectivo', 'tarjeta', 'transferencia', 'qr'] as $metodo)
                                <option value="{{ $metodo }}" @selected(old('metodo_pago') === $metodo)>{{ ucfirst($metodo) }}</option>
                            @endforeach
                        </select>
                        @error('metodo_pago') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                    </div>

                    <div class="ui-toolbar-group pt-2">
                        <button type="submit" class="ui-btn">Guardar pago</button>
                        <a href="{{ route('payments.index') }}" class="ui-btn-secondary">Volver</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
