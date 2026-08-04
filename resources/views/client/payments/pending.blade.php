<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Pagos <span class="text-gold">Pendientes</span></h2>
                <p class="ui-subtitle">Citas aprobadas que aún no tienen un pago registrado.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />

            @if($appointments->isEmpty())
                <div class="py-24 text-center rounded-3xl border border-dashed border-white/5">
                    <svg class="h-12 w-12 text-white/10 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm font-black text-muted/50 uppercase tracking-widest">No tienes pagos pendientes</p>
                    <p class="text-xs text-muted/30 mt-2">Cuando una cita esté aprobada por el barbero, aparecerá aquí si aún no se ha pagado.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($appointments as $appointment)
                        @php
                            $rechazado = $appointment->payments->firstWhere('estado', \App\Models\Payment::ESTADO_RECHAZADO);
                        @endphp
                        <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-black text-white uppercase leading-tight">{{ $appointment->service?->nombre ?? 'Servicio' }}</p>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-muted font-bold mt-1">
                                    @if($appointment->barber?->user)
                                        <span>{{ $appointment->barber->user->name }}</span>
                                    @endif
                                    @if($appointment->fecha)
                                        <span>{{ $appointment->fecha->translatedFormat('d M Y') }}</span>
                                    @endif
                                    <span class="text-gold">${{ number_format($appointment->service?->precio ?? 0, 2) }}</span>
                                </div>
                                @if($rechazado)
                                    <div class="mt-2 inline-flex items-center gap-1.5 rounded-full border border-red-500/25 bg-red-500/8 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-red-300">
                                        Comprobante rechazado: {{ $rechazado->motivo_rechazo ?? 'sube uno nuevo' }}
                                    </div>
                                @endif
                            </div>
                            <a href="{{ route('client.payments.upload', $appointment) }}"
                               class="flex-shrink-0 inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl border border-gold/30 bg-gold/10 text-[10px] font-black uppercase tracking-widest text-gold hover:bg-gold hover:text-black transition-all">
                                Subir comprobante &rarr;
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
