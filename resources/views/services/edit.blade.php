<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Editar servicio</h2>
            <span class="ui-badge">Catalogo</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('services.update', $service) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label" for="nombre">Nombre</label>
                            <input id="nombre" name="nombre" value="{{ old('nombre', $service->nombre) }}" class="ui-input" required>
                            @error('nombre') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="ui-label" for="categoria">Categoria</label>
                            <input id="categoria" name="categoria" value="{{ old('categoria', $service->categoria) }}" class="ui-input" required>
                            @error('categoria') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="ui-label" for="precio">Precio</label>
                            <input id="precio" type="number" step="0.01" min="0" name="precio" value="{{ old('precio', $service->precio) }}" class="ui-input" required>
                            @error('precio') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="ui-label" for="duracion_min">Duracion (min)</label>
                            <input id="duracion_min" type="number" min="5" name="duracion_min" value="{{ old('duracion_min', $service->duracion_min) }}" class="ui-input" required>
                            @error('duracion_min') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="ui-label" for="descripcion">Descripcion</label>
                        <textarea id="descripcion" name="descripcion" class="ui-input">{{ old('descripcion', $service->descripcion) }}</textarea>
                        @error('descripcion') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="inline-flex items-center gap-2 text-sm text-[#2d2d2d]">
                            <input type="checkbox" name="activo" value="1" class="rounded border-[#bfbfbf] text-[#404040] focus:ring-[#8c8c8c]" @checked(old('activo', $service->activo))>
                            Activo
                        </label>
                    </div>

                    <div class="ui-toolbar-group pt-2">
                        <button type="submit" class="ui-btn">Actualizar servicio</button>
                        <a href="{{ route('services.index') }}" class="ui-btn-secondary">Volver</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
