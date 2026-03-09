<x-app-layout>
    <x-slot name="header">
        <h2 class="ui-title">Subir nuevo trabajo</h2>
        <p class="ui-subtitle">Agrega un nuevo corte o estilo a tu galería.</p>
    </x-slot>
    <div class="py-8">
        <div class="max-w-xl mx-auto">
            <form method="POST" action="{{ route('barbers.works.store', $barber) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <div>
                    <label for="title" class="ui-label">Título</label>
                    <input id="title" name="title" type="text" class="ui-input" required maxlength="255">
                </div>
                <div>
                    <label for="description" class="ui-label">Descripción</label>
                    <textarea id="description" name="description" class="ui-input" rows="2"></textarea>
                </div>
                <div>
                    <label for="images" class="ui-label">Imágenes</label>
                    <input id="images" name="images[]" type="file" class="ui-input" multiple accept="image/*" required>
                    <small class="text-muted">Puedes subir varias fotos.</small>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="ui-btn">Guardar trabajo</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
