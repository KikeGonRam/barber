<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Clientes</h2>
            <span class="ui-badge">Atencion</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="ui-card px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif

            <section class="ui-surface">
                <form method="GET" action="{{ route('clients.index') }}" class="ui-form-grid mb-4">
                    <div>
                        <label class="ui-label" for="q">Buscar</label>
                        <input id="q" name="q" value="{{ $search }}" class="ui-input" placeholder="Nombre o email">
                    </div>
                    <div class="ui-toolbar-group md:col-span-2">
                        <button type="submit" class="ui-btn">Filtrar</button>
                        <a href="{{ route('clients.index') }}" class="ui-btn-secondary">Limpiar</a>
                        <a href="{{ route('clients.create') }}" class="ui-btn">Nuevo cliente</a>
                    </div>
                </form>

                <div class="ui-list">
                    @forelse($clients as $client)
                        <article class="ui-list-item">
                            <div class="ui-list-item-head">
                                <div>
                                    <h3 class="text-sm font-semibold text-[#141414]">{{ $client->user?->name ?? 'Sin usuario' }}</h3>
                                    <p class="text-xs text-[#666]">{{ $client->user?->email }}</p>
                                </div>
                                <a href="{{ route('clients.edit', $client) }}" class="ui-btn-secondary">Editar</a>
                            </div>
                            <div class="ui-meta-grid">
                                <div><strong>Telefono:</strong> {{ $client->telefono ?: '-' }}</div>
                                <div><strong>Nacimiento:</strong> {{ $client->fecha_nacimiento?->format('d/m/Y') ?: '-' }}</div>
                            </div>
                        </article>
                    @empty
                        <div class="ui-empty">No hay clientes para los filtros seleccionados.</div>
                    @endforelse
                </div>

                <div class="mt-4">{{ $clients->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
