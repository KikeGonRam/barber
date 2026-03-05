<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Notificaciones</h2>
            <span class="ui-badge">Bandeja</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="ui-card px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif

            <section class="ui-surface">
                <div class="ui-toolbar">
                    <div>
                        <p class="text-sm font-semibold text-[#1f1f1f]">Centro de notificaciones</p>
                        <p class="text-xs text-[#707070]">Alertas de citas, pagos y eventos del sistema.</p>
                    </div>
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="ui-btn">Marcar todas como leidas</button>
                    </form>
                </div>

                <div class="ui-list">
                    @forelse($notifications as $notification)
                        <article class="ui-list-item">
                            <div class="ui-list-item-head">
                                <h3 class="text-sm font-semibold text-[#0d0d0d]">{{ $notification->data['title'] ?? $notification->data['subject'] ?? 'Notificacion' }}</h3>
                                <span class="ui-badge">{{ $notification->read_at ? 'Leida' : 'No leida' }}</span>
                            </div>
                            <p class="text-sm text-[#4f4f4f]">{{ $notification->data['message'] ?? '' }}</p>
                            <p class="mt-2 text-xs text-[#7a7a7a]">{{ $notification->created_at->format('d/m/Y H:i') }}</p>
                        </article>
                    @empty
                        <div class="ui-empty">No tienes notificaciones.</div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $notifications->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
