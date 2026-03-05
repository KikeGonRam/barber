<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Logs de actividad</h2>
            <span class="ui-badge">Admin</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="GET" action="{{ route('logs.index') }}" class="ui-form-grid mb-4">
                    <div>
                        <label class="ui-label" for="q">Buscar</label>
                        <input id="q" name="q" value="{{ $search }}" class="ui-input" placeholder="Evento o descripcion">
                    </div>
                    <div>
                        <label class="ui-label" for="log_name">Modulo</label>
                        <select id="log_name" name="log_name" class="ui-input">
                            <option value="">Todos</option>
                            @foreach($logNames as $name)
                                <option value="{{ $name }}" @selected($logName === $name)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ui-toolbar-group md:col-span-2">
                        <button type="submit" class="ui-btn">Filtrar</button>
                        <a href="{{ route('logs.index') }}" class="ui-btn-secondary">Limpiar</a>
                    </div>
                </form>

                <div class="ui-list">
                    @forelse($logs as $log)
                        <article class="ui-list-item">
                            <div class="ui-list-item-head">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-[#141414]">{{ $log->description ?: 'evento' }}</h3>
                                    <span class="ui-badge">{{ $log->log_name ?: 'general' }}</span>
                                </div>
                            </div>
                            <div class="ui-meta-grid">
                                <div><strong>Evento:</strong> {{ $log->event ?: '-' }}</div>
                                <div><strong>Usuario:</strong> {{ $log->causer?->name ?: 'Sistema' }}</div>
                                <div><strong>Fecha:</strong> {{ $log->created_at?->format('d/m/Y H:i') }}</div>
                                <div><strong>Asunto:</strong> {{ class_basename((string) $log->subject_type) }}</div>
                            </div>
                        </article>
                    @empty
                        <div class="ui-empty">No hay registros para los filtros seleccionados.</div>
                    @endforelse
                </div>

                <div class="mt-4">{{ $logs->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
