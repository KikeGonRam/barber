<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Refinar <span class="text-gold">Servicio Signature</span></h2>
                <p class="ui-subtitle">Ajusta los parámetros de esta experiencia premium.</p>
            </div>
            <a href="{{ route('services.index') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-ink transition">
                &larr; Volver al catálogo
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('services.update', $service) }}" enctype="multipart/form-data" class="space-y-10">
                    @csrf
                    @method('PUT')

                    <!-- Section: Service Definition -->
                    <div>
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758L5 19m0-14l4.121 4.121" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-ink uppercase tracking-widest">Definición del Servicio</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="ui-label" for="nombre">Nombre del Servicio</label>
                                <input id="nombre" name="nombre" value="{{ old('nombre', $service->nombre) }}" 
                                       class="ui-input !bg-panel border-ink/10 text-ink" required>
                                @error('nombre') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="ui-label" for="categoria">Categoría</label>
                                <input id="categoria" name="categoria" value="{{ old('categoria', $service->categoria) }}" 
                                       class="ui-input !bg-panel border-ink/10 text-ink" required>
                                @error('categoria') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Section: Pricing & Duration -->
                    <div class="pt-8 border-t border-ink/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-ink uppercase tracking-widest">Valor & Tiempo</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="ui-label" for="precio">Precio de Venta</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gold font-bold">$</span>
                                    <input id="precio" type="number" step="0.01" min="0" name="precio" value="{{ old('precio', $service->precio) }}" 
                                           class="ui-input !pl-10 !bg-panel border-ink/10 text-ink" required>
                                </div>
                                @error('precio') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="ui-label" for="duracion_min">Duración Estimada (Minutos)</label>
                                <div class="relative">
                                    <input id="duracion_min" type="number" min="5" name="duracion_min" value="{{ old('duracion_min', $service->duracion_min) }}" 
                                           class="ui-input !bg-panel border-ink/10 text-ink" required>
                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-muted text-[10px] font-bold uppercase">Min</span>
                                </div>
                                @error('duracion_min') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Section: Details -->
                    <div class="pt-8 border-t border-ink/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-ink uppercase tracking-widest">Detalles Adicionales</h3>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <label class="ui-label" for="descripcion">Descripción del Servicio</label>
                                <textarea id="descripcion" name="descripcion" rows="4" 
                                          class="ui-input !bg-panel border-ink/10 text-ink leading-relaxed">{{ old('descripcion', $service->descripcion) }}</textarea>
                                @error('descripcion') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>

                            <label class="relative inline-flex cursor-pointer items-center group">
                                <input type="checkbox" name="activo" value="1" class="sr-only peer" @checked(old('activo', $service->activo))>
                                <div class="h-6 w-11 rounded-full bg-ink/5 border border-ink/10 peer-checked:bg-gold/20 peer-checked:border-gold transition-all"></div>
                                <div class="absolute left-1 top-1 h-4 w-4 rounded-full bg-muted peer-checked:bg-gold peer-checked:translate-x-5 transition-all"></div>
                                <span class="ms-3 text-[10px] font-black uppercase tracking-widest text-muted group-hover:text-ink transition-colors">Servicio Activo en el Catálogo</span>
                            </label>
                        </div>
                    </div>

                    <!-- Section: Image -->
                    <div class="pt-8 border-t border-ink/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-ink uppercase tracking-widest">Imagen del Servicio</h3>
                        </div>

                        <div class="space-y-4">
                            @if($service->imagen)
                                <div class="flex items-center gap-4">
                                    <img src="{{ Storage::url($service->imagen) }}" alt="{{ $service->nombre }}"
                                         class="h-24 w-24 rounded-xl object-cover border border-ink/10">
                                    <p class="text-[10px] font-bold text-muted uppercase">Imagen actual — sube una nueva para reemplazarla</p>
                                </div>
                            @endif
                            <div>
                                <label class="ui-label" for="imagen">{{ $service->imagen ? 'Reemplazar Imagen' : 'Subir Imagen' }} <span class="text-muted">(opcional, máx. 2 MB)</span></label>
                                <input id="imagen" type="file" name="imagen" accept="image/jpeg,image/png,image/jpg,image/webp"
                                       class="ui-input !bg-panel border-ink/10 text-ink file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-gold/10 file:text-gold hover:file:bg-gold/20 cursor-pointer">
                                @error('imagen') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="pt-10 border-t border-ink/5 flex justify-end">
                        <button type="submit" class="ui-btn px-16 py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                            Guardar Cambios en Servicio <span class="ml-2 opacity-50">&rarr;</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
