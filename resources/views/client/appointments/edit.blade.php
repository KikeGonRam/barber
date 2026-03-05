<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Reprogramar cita</h2>
            <span class="ui-badge">Portal cliente</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('client.appointments.update', $appointment) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label">Barbero</label>
                            <select name="barber_id" class="ui-input" required>
                                @foreach($barbers as $barber)
                                    <option value="{{ $barber->id }}" @selected(old('barber_id', $appointment->barber_id) == $barber->id)>{{ $barber->user?->name }}</option>
                                @endforeach
                            </select>
                            @error('barber_id')<p class="text-sm text-[#525252]">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="ui-label">Servicio</label>
                            <select name="service_id" class="ui-input" required>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}" @selected(old('service_id', $appointment->service_id) == $service->id)>{{ $service->nombre }}</option>
                                @endforeach
                            </select>
                            @error('service_id')<p class="text-sm text-[#525252]">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="ui-label">Fecha</label>
                            <input type="date" name="fecha" class="ui-input" value="{{ old('fecha', $appointment->fecha?->format('Y-m-d')) }}" required>
                            @error('fecha')<p class="text-sm text-[#525252]">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="ui-label">Hora inicio</label>
                            <input type="time" name="hora_inicio" class="ui-input" value="{{ old('hora_inicio', \Illuminate\Support\Str::of($appointment->hora_inicio)->substr(0,5)) }}" required>
                            @error('hora_inicio')<p class="text-sm text-[#525252]">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="ui-label">Hora fin</label>
                            <input type="time" name="hora_fin" class="ui-input" value="{{ old('hora_fin', \Illuminate\Support\Str::of($appointment->hora_fin)->substr(0,5)) }}" required>
                            @error('hora_fin')<p class="text-sm text-[#525252]">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="ui-label">Motivo reprogramacion</label>
                        <input name="motivo_reagendamiento" class="ui-input" value="{{ old('motivo_reagendamiento', $appointment->motivo_reagendamiento) }}">
                    </div>

                    <div class="ui-toolbar-group pt-2">
                        <button type="submit" class="ui-btn">Actualizar cita</button>
                        <a href="{{ route('client.appointments.index') }}" class="ui-btn-secondary">Volver</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
