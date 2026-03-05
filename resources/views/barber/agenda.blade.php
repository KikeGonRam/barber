<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Mi agenda</h2>
            <span class="ui-badge">Portal barbero</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="ui-card px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif

            <section class="ui-surface">
                <div class="ui-toolbar">
                    <div class="ui-toolbar-group">
                        <a class="{{ $period === 'day' ? 'ui-btn' : 'ui-btn-secondary' }}" href="{{ route('barber.agenda', ['period' => 'day']) }}">Hoy</a>
                        <a class="{{ $period === 'week' ? 'ui-btn' : 'ui-btn-secondary' }}" href="{{ route('barber.agenda', ['period' => 'week']) }}">Semana</a>
                        <a class="ui-btn-secondary" href="{{ route('barber.profile.edit') }}">Editar perfil</a>
                    </div>
                </div>

                <div class="ui-list-item mb-4">
                    <div class="ui-meta-grid">
                        <div><strong>Completadas:</strong> {{ $stats['completed_count'] }}</div>
                        <div><strong>Ingresos:</strong> ${{ number_format($stats['income_total'], 2) }}</div>
                    </div>
                </div>

                <div class="ui-list">
                    @forelse($agenda as $appointment)
                        <article class="ui-list-item">
                            <div class="ui-list-item-head">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-[#141414]">
                                        {{ $appointment->fecha?->format('Y-m-d') }} {{ $appointment->hora_inicio }} - {{ $appointment->hora_fin }}
                                    </h3>
                                    <span class="ui-badge">{{ $appointment->estado }}</span>
                                </div>
                            </div>

                            <div class="ui-meta-grid mb-3">
                                <div><strong>Cliente:</strong> {{ $appointment->client?->user?->name }}</div>
                                <div><strong>Servicio:</strong> {{ $appointment->service?->nombre }}</div>
                            </div>

                            <form method="POST" action="{{ route('barber.appointments.status', $appointment) }}" class="ui-form-grid">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label class="ui-label">Estado</label>
                                    <select name="estado" class="ui-input">
                                        @foreach(['pendiente','en_proceso','completada'] as $estado)
                                            <option value="{{ $estado }}" @selected($appointment->estado === $estado)>{{ $estado }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="ui-label">Notas</label>
                                    <input type="text" name="notas" class="ui-input" value="{{ $appointment->notas }}">
                                </div>
                                <div class="md:col-span-2">
                                    <button class="ui-btn" type="submit">Actualizar estado</button>
                                </div>
                            </form>
                        </article>
                    @empty
                        <div class="ui-empty">No hay citas para el periodo seleccionado.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
