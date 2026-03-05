<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Perfil de barbero</h2>
            <span class="ui-badge">Portal barbero</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="ui-card px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif

            <section class="ui-surface">
                <form method="POST" action="{{ route('barber.profile.update') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="ui-label">Especialidades</label>
                        <textarea name="especialidades" class="ui-input">{{ old('especialidades', $barber->especialidades) }}</textarea>
                        @error('especialidades') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="ui-label">Descripcion</label>
                        <textarea name="descripcion" class="ui-input">{{ old('descripcion', $barber->descripcion) }}</textarea>
                        @error('descripcion') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="ui-label">Foto de perfil</label>
                        <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" class="ui-input">
                        @if(!empty($barber->foto))
                            <div class="mt-2">
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($barber->foto) }}" alt="Foto actual" class="h-24 w-24 rounded-lg border border-[#cfcfcf] object-cover">
                            </div>
                        @endif
                        <p class="ui-field-help">Selecciona imagen JPG, PNG o WEBP. Se guarda por usuario/dia/mes/anio.</p>
                        @error('foto') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                    </div>

                    <div class="ui-toolbar-group pt-2">
                        <button type="submit" class="ui-btn">Guardar cambios</button>
                        <a href="{{ route('barber.agenda') }}" class="ui-btn-secondary">Volver</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
