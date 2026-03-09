<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Centro de <span class="text-gold">Notificaciones</span></h2>
                <p class="ui-subtitle">Mantente al tanto de tus citas, pagos y eventos importantes.</p>
            </div>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="ui-btn-secondary text-[10px] uppercase tracking-widest px-6">
                    Marcar todo como leído
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <section class="space-y-4">
                @forelse($notifications as $notification)
                    <article class="ui-card-premium p-6 group {{ $notification->read_at ? 'opacity-60' : 'border-gold/30' }}">
                        <div class="flex items-start gap-6">
                            <div class="h-12 w-12 rounded-2xl flex items-center justify-center flex-shrink-0 {{ $notification->read_at ? 'bg-white/5 text-muted' : 'bg-gold/10 text-gold shadow-[0_0_20px_rgba(212,175,55,0.1)]' }}">
                                @php
                                    $icon = match($notification->type) {
                                        'App\Notifications\AppointmentNotification' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                                        'App\Notifications\PaymentReceiptNotification' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2',
                                        default => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
                                    };
                                @endphp
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $icon }}" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <h3 class="text-base font-black text-white uppercase tracking-tight truncate">
                                        {{ $notification->data['title'] ?? $notification->data['subject'] ?? 'Actualización del Sistema' }}
                                    </h3>
                                    <span class="text-[9px] font-black uppercase tracking-[0.2em] text-muted">{{ $notification->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-sm text-muted leading-relaxed">{{ $notification->data['message'] ?? '' }}</p>
                                
                                @if(!$notification->read_at)
                                    <div class="mt-4 flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gold animate-pulse"></span>
                                        <span class="text-[9px] font-black text-gold uppercase tracking-widest">Nuevo Mensaje</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="ui-card-premium py-20 text-center border-dashed border-white/10 bg-transparent">
                        <svg class="h-12 w-12 text-white/5 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <p class="text-muted italic">Bandeja de entrada vacía por ahora.</p>
                    </div>
                @endforelse

                <div class="mt-8">
                    {{ $notifications->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
