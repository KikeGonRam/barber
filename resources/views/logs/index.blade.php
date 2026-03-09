<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Auditoría de <span class="text-gold">Actividad</span></h2>
                <p class="ui-subtitle">Registro cronológico de todas las operaciones del sistema.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
            
            <!-- Filter Panel -->
            <section class="ui-card-premium p-6 border-white/5 bg-black/20">
                <form method="GET" action="{{ route('logs.index') }}" class="grid grid-cols-1 gap-6 md:grid-cols-3 items-end">
                    <div>
                        <label class="ui-label" for="q">Buscar Evento</label>
                        <input id="q" name="q" value="{{ $search }}" class="ui-input !bg-panel border-white/10" placeholder="Descripción...">
                    </div>
                    <div>
                        <label class="ui-label" for="log_name">Módulo del Sistema</label>
                        <select id="log_name" name="log_name" class="ui-input !bg-panel border-white/10 text-white">
                            <option value="">Todos los módulos</option>
                            @foreach($logNames as $name)
                                <option value="{{ $name }}" @selected($logName === $name)>{{ ucfirst($name) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="ui-btn flex-1 py-3 text-[10px] uppercase tracking-widest">Filtrar Historial</button>
                        <a href="{{ route('logs.index') }}" class="ui-btn-secondary px-6 py-3 text-[10px] uppercase tracking-widest">Limpiar</a>
                    </div>
                </form>
            </section>

            <!-- Activity List -->
            <section class="space-y-4">
                @forelse($logs as $log)
                    <article class="ui-card-premium p-6 hover:border-gold/30 transition-all group">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                            <div class="flex items-start gap-5">
                                <div class="h-12 w-12 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center text-gold group-hover:bg-gold group-hover:text-black transition-all">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-3 mb-1">
                                        <h3 class="text-base font-black text-white uppercase tracking-tight">{{ $log->description ?: 'Operación del Sistema' }}</h3>
                                        <span class="ui-badge text-[8px] px-2 py-0.5 border-white/10 bg-white/5">{{ $log->log_name ?: 'general' }}</span>
                                    </div>
                                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                                        <p class="text-[10px] font-bold text-muted uppercase tracking-widest">
                                            <span class="text-gold/50">Por:</span> {{ $log->causer?->name ?: 'Sistema Autónomo' }}
                                        </p>
                                        <p class="text-[10px] font-bold text-muted uppercase tracking-widest">
                                            <span class="text-gold/50">Evento:</span> {{ strtoupper($log->event ?: 'update') }}
                                        </p>
                                        <p class="text-[10px] font-bold text-muted uppercase tracking-widest">
                                            <span class="text-gold/50">Objeto:</span> {{ class_basename((string) $log->subject_type) ?: 'N/A' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-xs font-black text-white">{{ $log->created_at?->format('H:i:s') }}</p>
                                <p class="text-[10px] font-bold text-muted uppercase tracking-widest mt-1">{{ $log->created_at?->format('d M, Y') }}</p>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="ui-card-premium py-20 text-center border-dashed border-white/10 bg-transparent">
                        <p class="text-muted italic">No hay registros de actividad para los criterios seleccionados.</p>
                    </div>
                @endforelse

                <div class="mt-8">
                    {{ $logs->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
