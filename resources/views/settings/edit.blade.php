<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Configuración del <span class="text-gold">Negocio</span></h2>
                <p class="ui-subtitle">Personaliza los parámetros operativos y la identidad de tu barbería.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl space-y-8 px-4 sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <section class="ui-surface">
                <form method="POST" action="{{ route('settings.update') }}" class="space-y-10">
                    @csrf
                    @method('PUT')

                    <!-- General Info -->
                    <div>
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Identidad & Contacto</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label class="ui-label" for="nombre">Nombre Comercial</label>
                                <input id="nombre" name="nombre" value="{{ old('nombre', $setting->nombre) }}" class="ui-input !bg-panel border-white/10 text-white" required>
                                @error('nombre') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="ui-label" for="telefono">Teléfono de Contacto</label>
                                <input id="telefono" name="telefono" value="{{ old('telefono', $setting->telefono) }}" class="ui-input !bg-panel border-white/10 text-white">
                                @error('telefono') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="ui-label" for="direccion">Dirección Física</label>
                                <input id="direccion" name="direccion" value="{{ old('direccion', $setting->direccion) }}" class="ui-input !bg-panel border-white/10 text-white">
                                @error('direccion') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Operative Hours -->
                    <div class="pt-8 border-t border-white/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Horarios & Políticas</h3>
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                            <div>
                                <label class="ui-label" for="horario_apertura">Apertura</label>
                                <input id="horario_apertura" type="time" name="horario_apertura" value="{{ old('horario_apertura', $setting->horario_apertura) }}" class="ui-input !bg-panel border-white/10 text-white">
                                @error('horario_apertura') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="ui-label" for="horario_cierre">Cierre</label>
                                <input id="horario_cierre" type="time" name="horario_cierre" value="{{ old('horario_cierre', $setting->horario_cierre) }}" class="ui-input !bg-panel border-white/10 text-white">
                                @error('horario_cierre') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="ui-label" for="politica_cancelacion">Antelación Cancelación (Horas)</label>
                                <input id="politica_cancelacion" type="number" min="1" max="168" name="politica_cancelacion" value="{{ old('politica_cancelacion', $setting->politica_cancelacion) }}" class="ui-input !bg-panel border-white/10 text-white" required>
                                @error('politica_cancelacion') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Social Networks -->
                    <div class="pt-8 border-t border-white/5">
                        @php $social = $setting->redes_sociales ?? []; @endphp
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Presencia Digital</h3>
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                            <div>
                                <label class="ui-label" for="instagram">Instagram</label>
                                <input id="instagram" name="instagram" value="{{ old('instagram', $social['instagram'] ?? '') }}" class="ui-input !bg-panel border-white/10 text-white" placeholder="@usuario">
                            </div>
                            <div>
                                <label class="ui-label" for="facebook">Facebook</label>
                                <input id="facebook" name="facebook" value="{{ old('facebook', $social['facebook'] ?? '') }}" class="ui-input !bg-panel border-white/10 text-white" placeholder="url de página">
                            </div>
                            <div>
                                <label class="ui-label" for="tiktok">Tiktok</label>
                                <input id="tiktok" name="tiktok" value="{{ old('tiktok', $social['tiktok'] ?? '') }}" class="ui-input !bg-panel border-white/10 text-white" placeholder="@usuario">
                            </div>
                        </div>
                    </div>

                    <div class="pt-10 border-t border-white/5 flex justify-end">
                        <button type="submit" class="ui-btn px-16 py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                            Actualizar Configuración Maestro
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
