<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Agendar cita</h2>
            <span class="ui-badge">Portal cliente</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('client.appointments.store') }}" class="space-y-4">
                    @csrf

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label">Barbero</label>
                            <select name="barber_id" class="ui-input" required>
                                <option value="">Seleccionar</option>
                                @foreach($barbers as $barber)
                                    <option value="{{ $barber->id }}" @selected(old('barber_id') == $barber->id)>{{ $barber->user?->name }}</option>
                                @endforeach
                            </select>
                            @error('barber_id')<p class="text-sm text-[#525252]">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="ui-label">Servicio</label>
                            <select name="service_id" class="ui-input" required>
                                <option value="">Seleccionar</option>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->nombre }} (${{ number_format((float)$service->precio,2) }})</option>
                                @endforeach
                            </select>
                            @error('service_id')<p class="text-sm text-[#525252]">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="ui-label">Fecha</label>
                            <input type="date" name="fecha" class="ui-input" value="{{ old('fecha') }}" required>
                            @error('fecha')<p class="text-sm text-[#525252]">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="ui-label">Hora inicio</label>
                            <input type="time" name="hora_inicio" class="ui-input" value="{{ old('hora_inicio') }}" required>
                            @error('hora_inicio')<p class="text-sm text-[#525252]">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="ui-label">Hora fin</label>
                            <input type="time" name="hora_fin" class="ui-input" value="{{ old('hora_fin') }}" required>
                            @error('hora_fin')<p class="text-sm text-[#525252]">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="ui-label">Notas</label>
                        <textarea name="notas" class="ui-input">{{ old('notas') }}</textarea>
                    </div>

                    <div class="ui-toolbar-group pt-2">
                        <button type="submit" class="ui-btn">Guardar cita</button>
                        <a href="{{ route('client.appointments.index') }}" class="ui-btn-secondary">Volver</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
