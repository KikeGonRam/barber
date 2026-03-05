<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Configuracion</h2>
            <span class="ui-badge">Admin</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="ui-card px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif

            <section class="ui-surface">
                <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label" for="nombre">Nombre del negocio</label>
                            <input id="nombre" name="nombre" value="{{ old('nombre', $setting->nombre) }}" class="ui-input" required>
                            @error('nombre') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="telefono">Telefono</label>
                            <input id="telefono" name="telefono" value="{{ old('telefono', $setting->telefono) }}" class="ui-input">
                            @error('telefono') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="ui-label" for="direccion">Direccion</label>
                            <input id="direccion" name="direccion" value="{{ old('direccion', $setting->direccion) }}" class="ui-input">
                            @error('direccion') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="horario_apertura">Horario apertura</label>
                            <input id="horario_apertura" type="time" name="horario_apertura" value="{{ old('horario_apertura', $setting->horario_apertura?->format('H:i')) }}" class="ui-input">
                            @error('horario_apertura') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="horario_cierre">Horario cierre</label>
                            <input id="horario_cierre" type="time" name="horario_cierre" value="{{ old('horario_cierre', $setting->horario_cierre?->format('H:i')) }}" class="ui-input">
                            @error('horario_cierre') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="politica_cancelacion">Politica cancelacion (horas)</label>
                            <input id="politica_cancelacion" type="number" min="1" max="168" name="politica_cancelacion" value="{{ old('politica_cancelacion', $setting->politica_cancelacion) }}" class="ui-input" required>
                            @error('politica_cancelacion') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @php
                        $social = $setting->redes_sociales ?? [];
                    @endphp

                    <div class="ui-divider my-2"></div>
                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label" for="instagram">Instagram</label>
                            <input id="instagram" name="instagram" value="{{ old('instagram', $social['instagram'] ?? '') }}" class="ui-input">
                        </div>
                        <div>
                            <label class="ui-label" for="facebook">Facebook</label>
                            <input id="facebook" name="facebook" value="{{ old('facebook', $social['facebook'] ?? '') }}" class="ui-input">
                        </div>
                        <div>
                            <label class="ui-label" for="tiktok">Tiktok</label>
                            <input id="tiktok" name="tiktok" value="{{ old('tiktok', $social['tiktok'] ?? '') }}" class="ui-input">
                        </div>
                    </div>

                    <div class="ui-toolbar-group pt-2">
                        <button type="submit" class="ui-btn">Guardar configuracion</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
