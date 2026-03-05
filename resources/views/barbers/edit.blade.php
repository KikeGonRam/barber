<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Editar barbero</h2>
            <span class="ui-badge">Equipo</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('barbers.update', $barber) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label" for="name">Nombre</label>
                            <input id="name" name="name" value="{{ old('name', $barber->user?->name) }}" class="ui-input" required>
                            @error('name') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="email">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $barber->user?->email) }}" class="ui-input" required>
                            @error('email') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="ui-label" for="especialidades">Especialidades</label>
                        <textarea id="especialidades" name="especialidades" class="ui-input">{{ old('especialidades', $barber->especialidades) }}</textarea>
                        @error('especialidades') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="ui-label" for="descripcion">Descripcion</label>
                        <textarea id="descripcion" name="descripcion" class="ui-input">{{ old('descripcion', $barber->descripcion) }}</textarea>
                        @error('descripcion') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                    </div>

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label" for="foto">Foto (ruta)</label>
                            <input id="foto" name="foto" value="{{ old('foto', $barber->foto) }}" class="ui-input">
                            @error('foto') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="activo" value="1" class="rounded border-[#bfbfbf]" @checked(old('activo', $barber->activo))>
                            Activo
                        </label>
                    </div>

                    <div class="ui-toolbar-group pt-2">
                        <button type="submit" class="ui-btn">Actualizar barbero</button>
                        <a href="{{ route('barbers.index') }}" class="ui-btn-secondary">Volver</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
