<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Servicios</h2>
            <span class="ui-badge">Catalogo</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="ui-card px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif

            <section class="ui-surface">
                <form method="GET" action="{{ route('services.index') }}" class="ui-form-grid mb-4">
                    <div>
                        <label class="ui-label" for="categoria">Categoria</label>
                        <select id="categoria" name="categoria" class="ui-input">
                            <option value="">Todas</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" @selected(($filters['categoria'] ?? '') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="ui-label" for="activo">Estado</label>
                        <select id="activo" name="activo" class="ui-input">
                            <option value="">Todos</option>
                            <option value="1" @selected(($filters['activo'] ?? '') === '1')>Activos</option>
                            <option value="0" @selected(($filters['activo'] ?? '') === '0')>Inactivos</option>
                        </select>
                    </div>

                    <div class="ui-toolbar-group md:col-span-2">
                        <button type="submit" class="ui-btn">Filtrar</button>
                        <a href="{{ route('services.index') }}" class="ui-btn-secondary">Limpiar</a>
                        <a href="{{ route('services.create') }}" class="ui-btn">Nuevo servicio</a>
                    </div>
                </form>

                <div class="ui-list">
                    @forelse ($services as $service)
                        <article class="ui-list-item">
                            <div class="ui-list-item-head">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-[#141414]">{{ $service->nombre }}</h3>
                                    <span class="ui-badge">{{ $service->activo ? 'Activo' : 'Inactivo' }}</span>
                                </div>
                                <div class="ui-toolbar-group">
                                    <a href="{{ route('services.edit', $service) }}" class="ui-btn-secondary">Editar</a>
                                    <form action="{{ route('services.destroy', $service) }}" method="POST" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn">Eliminar</button>
                                    </form>
                                </div>
                            </div>

                            <div class="ui-meta-grid">
                                <div><strong>Categoria:</strong> {{ $service->categoria }}</div>
                                <div><strong>Duracion:</strong> {{ $service->duracion_min }} min</div>
                                <div><strong>Precio:</strong> ${{ number_format((float) $service->precio, 2) }}</div>
                                <div><strong>Descripcion:</strong> {{ $service->descripcion ?: '-' }}</div>
                            </div>
                        </article>
                    @empty
                        <div class="ui-empty">No hay servicios registrados.</div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $services->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
