<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Ajustar <span class="text-gold">Cita Maestro</span></h2>
                <p class="ui-subtitle">Modifica los detalles de la reserva seleccionada.</p>
            </div>
            <a href="{{ route('appointments.index') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-ink transition">
                &larr; Volver a la agenda
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('appointments.update', $appointment) }}" class="space-y-10">
                    @csrf
                    @method('PUT')

                    <!-- Section: Client & Service -->
                    <div>
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-ink uppercase tracking-widest">Asignación de Servicio</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="ui-label" for="client_id">Cliente</label>
                                <select id="client_id" name="client_id" class="ui-input !bg-panel border-ink/10 text-ink" required>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}" @selected((string) old('client_id', $appointment->client_id) === (string) $client->id)>{{ $client->user?->name }}</option>
                                    @endforeach
                                </select>
                                @error('client_id') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="ui-label" for="service_id">Servicio</label>
                                <select id="service_id" name="service_id" class="ui-input !bg-panel border-ink/10 text-ink" required>
                                    @foreach($services as $service)
                                        <option value="{{ $service->id }}" data-duracion="{{ $service->duracion_min }}" @selected((string) old('service_id', $appointment->service_id) === (string) $service->id)>
                                            {{ $service->nombre }} ({{ $service->duracion_min }} min)
                                        </option>
                                    @endforeach
                                </select>
                                @error('service_id') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Section: Professional & Schedule -->
                    <div class="pt-8 border-t border-ink/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-ink uppercase tracking-widest">Horario & Barbero</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="ui-label" for="barber_id">Maestro Barbero</label>
                                <select id="barber_id" name="barber_id" class="ui-input !bg-panel border-ink/10 text-ink" required>
                                    @foreach($barbers as $barber)
                                        <option value="{{ $barber->id }}" @selected((string) old('barber_id', $appointment->barber_id) === (string) $barber->id)>{{ $barber->user?->name }}</option>
                                    @endforeach
                                </select>
                                @error('barber_id') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="ui-label" for="fecha">Fecha</label>
                                <input id="fecha" type="date" name="fecha" value="{{ old('fecha', optional($appointment->fecha)->format('Y-m-d')) }}" class="ui-input !bg-panel border-ink/10 text-ink" required>
                                @error('fecha') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="ui-label" for="estado">Estado Actual</label>
                                <select id="estado" name="estado" class="ui-input !bg-panel border-ink/10 text-ink">
                                    @foreach (['pendiente', 'confirmada', 'en_proceso', 'completada', 'cancelada', 'no_asistio'] as $estado)
                                        <option value="{{ $estado }}" @selected(old('estado', $appointment->estado) === $estado)>{{ ucfirst($estado) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label class="ui-label" for="hora_inicio">Hora de Inicio</label>
                                <input id="hora_inicio" type="time" name="hora_inicio" value="{{ old('hora_inicio', \Illuminate\Support\Str::of($appointment->hora_inicio)->substr(0, 5)) }}" class="ui-input !bg-panel border-ink/10 text-ink" required>
                                @error('hora_inicio') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="ui-label" for="hora_fin">Hora de Finalización</label>
                                <input id="hora_fin" type="time" name="hora_fin" value="{{ old('hora_fin', \Illuminate\Support\Str::of($appointment->hora_fin)->substr(0, 5)) }}" class="ui-input !bg-panel border-ink/10 text-ink" required>
                                @error('hora_fin') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="pt-10 border-t border-ink/5 flex justify-end">
                        <button type="submit" class="ui-btn px-16 py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                            Actualizar Detalles de Reserva <span class="ml-2 opacity-50">&rarr;</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <script>
        // Sugiere hora_fin a partir de la duracion del servicio elegido, para
        // que recepcion/admin no tenga que calcularla a mano (y evitar citas
        // con una duracion que no corresponde al servicio real). Solo actua
        // si el staff cambia servicio/hora_inicio; no toca el valor precargado
        // si no los toca. Sigue siendo un input normal editable.
        (function () {
            const serviceSelect = document.getElementById('service_id');
            const startInput = document.getElementById('hora_inicio');
            const endInput = document.getElementById('hora_fin');

            function autoFillEnd() {
                const opt = serviceSelect.options[serviceSelect.selectedIndex];
                const duracion = parseInt(opt?.dataset.duracion || '', 10);
                if (!duracion || !startInput.value) return;

                const [h, m] = startInput.value.split(':').map(Number);
                const totalMin = h * 60 + m + duracion;
                const endH = String(Math.floor(totalMin / 60) % 24).padStart(2, '0');
                const endM = String(totalMin % 60).padStart(2, '0');
                endInput.value = `${endH}:${endM}`;
            }

            serviceSelect.addEventListener('change', autoFillEnd);
            startInput.addEventListener('change', autoFillEnd);
        })();
    </script>
</x-app-layout>
