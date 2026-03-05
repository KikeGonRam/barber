<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Citas</h2>
            <span class="ui-badge">Agenda operativa</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="ui-card px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif

            <section class="ui-surface">
                <div class="ui-toolbar">
                    <div>
                        <p class="text-sm font-semibold text-[#1f1f1f]">Listado de citas</p>
                        <p class="text-xs text-[#707070]">Gestion diaria de horarios, clientes y estado de servicio.</p>
                    </div>
                    <div class="ui-toolbar-group">
                        <a href="{{ route('appointments.create') }}" class="ui-btn">Nueva cita</a>
                    </div>
                </div>

                <div class="ui-list">
                    @forelse ($appointments as $appointment)
                        <article class="ui-list-item">
                            <div class="ui-list-item-head">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-[#141414]">
                                        {{ $appointment->client?->user?->name ?? 'Sin cliente' }}
                                    </h3>
                                    <span class="ui-badge">{{ $appointment->estado }}</span>
                                </div>
                                <div class="ui-toolbar-group">
                                    <a href="{{ route('appointments.edit', $appointment) }}" class="ui-btn-secondary">Editar</a>
                                    <form action="{{ route('appointments.destroy', $appointment) }}" method="POST" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn">Cancelar</button>
                                    </form>
                                </div>
                            </div>

                            <div class="ui-meta-grid">
                                <div><strong>Fecha:</strong> {{ $appointment->fecha }}</div>
                                <div><strong>Horario:</strong> {{ $appointment->hora_inicio }} - {{ $appointment->hora_fin }}</div>
                                <div><strong>Barbero:</strong> {{ $appointment->barber?->user?->name ?? '-' }}</div>
                                <div><strong>Servicio:</strong> {{ $appointment->service?->nombre ?? '-' }}</div>
                            </div>
                        </article>
                    @empty
                        <div class="ui-empty">No hay citas registradas.</div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $appointments->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
