<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Publicar <span class="text-gold">Nuevo Trabajo</span></h2>
                <p class="ui-subtitle">Muestra tu talento a la comunidad y atrae nuevos clientes.</p>
            </div>
                    <a href="{{ route('barber.portfolio.index') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-white transition">
                &larr; Volver al portafolio
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('barber.portfolio.store') }}" enctype="multipart/form-data" class="space-y-8">
                    @csrf

                    <div class="space-y-6">
                        <div>
                            <label class="ui-label" for="title">Título del Trabajo</label>
                            <input id="title" name="title" value="{{ old('title') }}" 
                                   class="ui-input !bg-panel border-white/10 text-white" 
                                   placeholder="Ej: Fade Clásico con Diseño" required>
                            @error('title') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="ui-label" for="description">Descripción (Opcional)</label>
                            <textarea id="description" name="description" rows="4" 
                                      class="ui-input !bg-panel border-white/10 text-white leading-relaxed" 
                                      placeholder="Describe la técnica utilizada o los productos aplicados...">{{ old('description') }}</textarea>
                            @error('description') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="ui-label">Imágenes del Trabajo (Máx. 5)</label>
                            <div class="mt-2 flex items-center justify-center w-full">
                                <label for="images" class="flex flex-col items-center justify-center w-full h-64 border-2 border-dashed border-white/10 rounded-2xl cursor-pointer bg-white/5 hover:bg-white/10 hover:border-gold/30 transition-all">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <svg class="w-10 h-10 mb-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        <p class="mb-2 text-sm text-white font-bold uppercase tracking-widest">Haz clic para subir imágenes</p>
                                        <p class="text-xs text-muted uppercase font-bold tracking-tighter">PNG, JPG o WEBP (MAX. 5MB cada una)</p>
                                    </div>
                                    <input id="images" name="images[]" type="file" class="hidden" multiple required accept="image/*" />
                                </label>
                            </div>
                            @error('images') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            @error('images.*') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="pt-8 border-t border-white/5 flex justify-end">
                        <button type="submit" class="ui-btn px-16 py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                            Publicar en mi Muro Professional <span class="ml-2 opacity-50">&rarr;</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
