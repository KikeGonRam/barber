<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Mis citas</h2>
            <span class="ui-badge">Portal cliente</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="ui-card px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif
            @error('general')
                <div class="ui-card px-4 py-2 text-sm">{{ $message }}</div>
            @enderror

            <section class="ui-surface">
                <div class="ui-toolbar">
                    <div>
                        <p class="text-sm font-semibold text-[#1f1f1f]">Historial de citas</p>
                        <p class="text-xs text-[#707070]">Reagenda o cancela segun politica vigente.</p>
                    </div>
                    <a href="{{ route('client.appointments.create') }}" class="ui-btn">Agendar cita</a>
                </div>

                <div class="ui-list">
                    @forelse($appointments as $appointment)
                        <article class="ui-list-item">
                            <div class="ui-list-item-head">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-[#141414]">
                                        {{ $appointment->fecha?->format('Y-m-d') }} {{ $appointment->hora_inicio }} - {{ $appointment->hora_fin }}
                                    </h3>
                                    <span class="ui-badge">{{ $appointment->estado }}</span>
                                </div>
                                <div class="ui-toolbar-group">
                                    <a class="ui-btn-secondary" href="{{ route('client.appointments.edit', $appointment) }}">Reprogramar</a>
                                    <form action="{{ route('client.appointments.destroy', $appointment) }}" method="POST" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button class="ui-btn" type="submit">Cancelar</button>
                                    </form>
                                </div>
                            </div>

                            <div class="ui-meta-grid">
                                <div><strong>Barbero:</strong> {{ $appointment->barber?->user?->name }}</div>
                                <div><strong>Servicio:</strong> {{ $appointment->service?->nombre }}</div>
                            </div>
                        </article>
                    @empty
                        <div class="ui-empty">No tienes citas registradas.</div>
                    @endforelse
                </div>

                <div class="mt-4">{{ $appointments->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
