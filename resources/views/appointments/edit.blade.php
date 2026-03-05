<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Editar cita</h2>
            <span class="ui-badge">Ajuste de agenda</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('appointments.update', $appointment) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label" for="client_id">Cliente</label>
                            <select id="client_id" name="client_id" class="ui-input" required>
                                <option value="">Seleccionar cliente</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" @selected((int) old('client_id', $appointment->client_id) === $client->id)>{{ $client->user?->name }}</option>
                                @endforeach
                            </select>
                            @error('client_id') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="barber_id">Barbero</label>
                            <select id="barber_id" name="barber_id" class="ui-input" required>
                                <option value="">Seleccionar barbero</option>
                                @foreach($barbers as $barber)
                                    <option value="{{ $barber->id }}" @selected((int) old('barber_id', $appointment->barber_id) === $barber->id)>{{ $barber->user?->name }}</option>
                                @endforeach
                            </select>
                            @error('barber_id') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="service_id">Servicio</label>
                            <select id="service_id" name="service_id" class="ui-input" required>
                                <option value="">Seleccionar servicio</option>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}" @selected((int) old('service_id', $appointment->service_id) === $service->id)>
                                        {{ $service->nombre }} ({{ $service->duracion_min }} min)
                                    </option>
                                @endforeach
                            </select>
                            @error('service_id') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="estado">Estado</label>
                            <select id="estado" name="estado" class="ui-input">
                                @foreach (['pendiente', 'confirmada', 'en_proceso', 'completada', 'cancelada', 'no_asistio'] as $estado)
                                    <option value="{{ $estado }}" @selected(old('estado', $appointment->estado) === $estado)>{{ $estado }}</option>
                                @endforeach
                            </select>
                            @error('estado') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label" for="fecha">Fecha</label>
                            <input id="fecha" type="date" name="fecha" value="{{ old('fecha', optional($appointment->fecha)->format('Y-m-d')) }}" class="ui-input" required>
                            @error('fecha') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="hora_inicio">Hora inicio</label>
                            <input id="hora_inicio" type="time" name="hora_inicio" value="{{ old('hora_inicio', \Illuminate\Support\Str::of($appointment->hora_inicio)->substr(0, 5)) }}" class="ui-input" required>
                            @error('hora_inicio') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="md:max-w-[50%]">
                        <label class="ui-label" for="hora_fin">Hora fin</label>
                        <input id="hora_fin" type="time" name="hora_fin" value="{{ old('hora_fin', \Illuminate\Support\Str::of($appointment->hora_fin)->substr(0, 5)) }}" class="ui-input" required>
                        @error('hora_fin') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                    </div>

                    <div class="ui-toolbar-group pt-2">
                        <button type="submit" class="ui-btn">Actualizar cita</button>
                        <a href="{{ route('appointments.index') }}" class="ui-btn-secondary">Volver</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
