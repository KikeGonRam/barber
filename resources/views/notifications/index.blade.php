<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Centro de <span class="text-gold">Notificaciones</span></h2>
                <p class="ui-subtitle">Mantente al tanto de tus citas, pagos y eventos importantes.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('notifications.preferences') }}" class="ui-btn-secondary text-[10px] uppercase tracking-widest px-6">
                    Preferencias
                </a>
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="ui-btn-secondary text-[10px] uppercase tracking-widest px-6">
                        Marcar todo como leído
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

            <section class="space-y-4">
                @forelse($notifications as $notification)
                    @php
                        $isUnread = is_null($notification->read_at);
                        $type = $notification->data['type'] ?? '';
                        $icon = match(true) {
                            str_contains($notification->type, 'AppointmentNotification') => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                            str_contains($notification->type, 'PaymentReceipt')          => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                            default                                                       => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                        };
                    @endphp
                    <article class="ui-card-premium p-5 group {{ $isUnread ? 'border-gold/20' : 'border-ink/5 bg-ink/[0.01]' }}">
                        <div class="flex items-start gap-5">
                            <!-- Icon -->
                            <div class="h-11 w-11 rounded-xl flex items-center justify-center flex-shrink-0
                                {{ $isUnread ? 'bg-gold/10 text-gold shadow-[0_0_16px_rgba(212,175,55,0.1)]' : 'bg-ink/5 text-muted' }}">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $icon }}"/>
                                </svg>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-3 mb-1">
                                    <h3 class="text-sm font-black text-ink uppercase tracking-tight leading-tight">
                                        {{ $notification->data['title'] ?? $notification->data['subject'] ?? 'Actualización del Sistema' }}
                                    </h3>
                                    <span class="text-[9px] font-black uppercase tracking-[0.2em] text-muted flex-shrink-0">
                                        {{ $notification->created_at->diffForHumans() }}
                                    </span>
                                </div>

                                <p class="text-xs text-muted/80 leading-relaxed">
                                    {{ $notification->data['message'] ?? '' }}
                                </p>

                                @if($type === 'appointment' && !empty($notification->data['fecha']))
                                    <p class="mt-1.5 text-[10px] text-muted font-bold">
                                        {{ \Carbon\Carbon::parse($notification->data['fecha'])->translatedFormat('d M, Y') }}
                                        @if(!empty($notification->data['hora_inicio']))
                                            · {{ substr($notification->data['hora_inicio'], 0, 5) }}
                                        @endif
                                    </p>
                                @endif

                                @if($type === 'payment' && !empty($notification->data['monto']))
                                    <p class="mt-1.5 text-[10px] text-gold font-black">
                                        ${{ number_format((float) $notification->data['monto'], 2) }}
                                        @if(!empty($notification->data['metodo_pago']))
                                            · {{ ucfirst($notification->data['metodo_pago']) }}
                                        @endif
                                    </p>
                                @endif

                                <div class="mt-3 flex items-center gap-3">
                                    @if($isUnread)
                                        <span class="flex items-center gap-1.5 text-[9px] font-black text-gold uppercase tracking-widest">
                                            <span class="h-1.5 w-1.5 rounded-full bg-gold animate-pulse"></span>
                                            Nuevo
                                        </span>
                                        <form method="POST" action="{{ route('notifications.read-one', $notification->id) }}">
                                            @csrf
                                            <button type="submit"
                                                class="px-4 py-2 rounded-lg border border-ink/10 text-[9px] font-black uppercase tracking-widest text-muted hover:text-ink hover:border-ink/20 transition-all">
                                                Marcar como leída
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="ui-card-premium py-20 text-center border-dashed border-ink/10 bg-transparent">
                        <svg class="h-12 w-12 text-ink/5 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <p class="text-sm font-black text-muted/50 uppercase tracking-widest">Bandeja vacía</p>
                        <p class="text-xs text-muted/30 mt-2 max-w-xs mx-auto">Recibirás notificaciones cuando cambien el estado de tus citas o se registren pagos.</p>
                    </div>
                @endforelse

                <div class="mt-8">
                    {{ $notifications->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
