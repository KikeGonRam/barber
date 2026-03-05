<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Barberos</h2>
            <span class="ui-badge">Equipo</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="ui-card px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif

            <section class="ui-surface">
                <form method="GET" action="{{ route('barbers.index') }}" class="ui-form-grid mb-4">
                    <div>
                        <label class="ui-label" for="q">Buscar</label>
                        <input id="q" name="q" value="{{ $search }}" class="ui-input" placeholder="Nombre o email">
                    </div>
                    <div>
                        <label class="ui-label" for="activo">Estado</label>
                        <select id="activo" name="activo" class="ui-input">
                            <option value="">Todos</option>
                            <option value="1" @selected($status === '1')>Activos</option>
                            <option value="0" @selected($status === '0')>Inactivos</option>
                        </select>
                    </div>
                    <div class="ui-toolbar-group md:col-span-2">
                        <button type="submit" class="ui-btn">Filtrar</button>
                        <a href="{{ route('barbers.index') }}" class="ui-btn-secondary">Limpiar</a>
                    </div>
                </form>

                <div class="ui-list">
                    @forelse($barbers as $barber)
                        <article class="ui-list-item">
                            <div class="ui-list-item-head">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-[#141414]">{{ $barber->user?->name ?? 'Sin usuario' }}</h3>
                                    <span class="ui-badge">{{ $barber->activo ? 'Activo' : 'Inactivo' }}</span>
                                </div>
                                <a href="{{ route('barbers.edit', $barber) }}" class="ui-btn-secondary">Editar</a>
                            </div>
                            <div class="ui-meta-grid">
                                <div><strong>Email:</strong> {{ $barber->user?->email ?: '-' }}</div>
                                <div><strong>Especialidades:</strong> {{ $barber->especialidades ?: '-' }}</div>
                            </div>
                        </article>
                    @empty
                        <div class="ui-empty">No hay barberos para los filtros seleccionados.</div>
                    @endforelse
                </div>

                <div class="mt-4">{{ $barbers->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
