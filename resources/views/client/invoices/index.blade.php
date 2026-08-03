<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Mis <span class="text-gold">Facturas</span></h2>
                <p class="ui-subtitle">Historial de pagos registrados por tus servicios completados.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-8">

            <!-- Stats row -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-5">
                    <p class="text-[9px] font-black uppercase tracking-[0.25em] text-muted">Facturas emitidas</p>
                    <p class="text-3xl font-black text-white mt-2">{{ $payments->total() }}</p>
                </div>
                <div class="rounded-2xl border border-gold/10 bg-gold/[0.03] p-5">
                    <p class="text-[9px] font-black uppercase tracking-[0.25em] text-gold/70">Total pagado</p>
                    <p class="text-3xl font-black text-gold mt-2">${{ number_format($totalPagado, 2) }}</p>
                </div>
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-5 col-span-2 sm:col-span-1">
                    <p class="text-[9px] font-black uppercase tracking-[0.25em] text-muted">Total de citas</p>
                    <p class="text-3xl font-black text-white mt-2">{{ $totalCitas }}</p>
                </div>
            </div>

            @if($payments->isEmpty())
                <div class="py-24 text-center rounded-3xl border border-dashed border-white/5">
                    <svg class="h-12 w-12 text-white/10 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm font-black text-muted/50 uppercase tracking-widest">No tienes facturas aún</p>
                    <p class="text-xs text-muted/30 mt-2">Las facturas se generan una vez que el equipo registra tu pago tras la sesión.</p>
                    <a href="{{ route('client.appointments.create') }}"
                       class="mt-6 inline-flex items-center gap-2 px-8 py-3 rounded-xl border border-gold/30 bg-gold/10 text-[10px] font-black uppercase tracking-widest text-gold hover:bg-gold hover:text-black transition-all">
                        Reservar una cita &rarr;
                    </a>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($payments as $payment)
                        @php
                            $appt    = $payment->appointment;
                            $service = $appt?->service;
                            $barber  = $appt?->barber?->user;
                            $monto   = (float) $payment->monto;
                            $propina = (float) $payment->propina;
                            $total   = $monto + $propina;

                            $metodoBadge = match($payment->metodo_pago) {
                                'efectivo'      => ['label' => 'Efectivo',      'cls' => 'border-emerald-500/20 bg-emerald-950/20 text-emerald-400'],
                                'tarjeta'       => ['label' => 'Tarjeta',       'cls' => 'border-blue-500/20 bg-blue-950/20 text-blue-400'],
                                'transferencia' => ['label' => 'Transferencia', 'cls' => 'border-violet-500/20 bg-violet-950/20 text-violet-400'],
                                'qr'            => ['label' => 'QR',            'cls' => 'border-orange-500/20 bg-orange-950/20 text-orange-400'],
                                'stripe'        => ['label' => 'Stripe',        'cls' => 'border-indigo-500/20 bg-indigo-950/20 text-indigo-400'],
                                default         => ['label' => ucfirst($payment->metodo_pago ?? '—'), 'cls' => 'border-white/10 bg-white/5 text-muted'],
                            };
                        @endphp

                        <div class="group rounded-2xl border border-white/5 bg-white/[0.02] overflow-hidden hover:border-gold/15 transition-all">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-5 p-5">

                                <!-- Left: receipt icon + number -->
                                <div class="flex-shrink-0 flex items-center gap-4">
                                    <div class="h-12 w-12 rounded-xl bg-gold/[0.06] border border-gold/15 flex items-center justify-center">
                                        <svg class="h-5 w-5 text-gold/60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black text-muted uppercase tracking-widest">Factura</p>
                                        <p class="text-xs font-black text-white font-mono mt-0.5">#{{ strtoupper(mb_substr((string)$payment->id, -8)) }}</p>
                                    </div>
                                </div>

                                <!-- Center: service + barber + date -->
                                <div class="flex-1 min-w-0 space-y-1">
                                    <p class="text-sm font-black text-white uppercase leading-tight truncate">
                                        {{ $service?->nombre ?? 'Servicio' }}
                                    </p>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-muted font-bold">
                                        @if($barber)
                                            <span class="flex items-center gap-1">
                                                <svg class="h-3 w-3 text-gold/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                                {{ $barber->name }}
                                            </span>
                                        @endif
                                        @if($appt?->fecha)
                                            <span class="flex items-center gap-1">
                                                <svg class="h-3 w-3 text-gold/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                {{ $appt->fecha->translatedFormat('d M Y') }}
                                                @if($appt->hora_inicio) · {{ substr($appt->hora_inicio, 0, 5) }} @endif
                                            </span>
                                        @endif
                                        <span class="flex items-center gap-1">
                                            <svg class="h-3 w-3 text-gold/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ $payment->created_at?->translatedFormat('d M Y, H:i') }}
                                        </span>
                                    </div>

                                    <!-- Método de pago -->
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[8px] font-black uppercase tracking-wider {{ $metodoBadge['cls'] }}">
                                        {{ $metodoBadge['label'] }}
                                    </span>
                                </div>

                                <!-- Right: amounts + download -->
                                <div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-center gap-3 sm:gap-2 flex-shrink-0">
                                    <div class="text-right">
                                        <p class="text-xl font-black text-gold">${{ number_format($total, 2) }}</p>
                                        @if($propina > 0)
                                            <p class="text-[9px] text-muted font-bold mt-0.5">
                                                ${{ number_format($monto, 2) }} + ${{ number_format($propina, 2) }} propina
                                            </p>
                                        @endif
                                    </div>

                                    <a href="{{ route('client.facturas.download', $payment) }}"
                                       class="flex items-center gap-1.5 px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-[9px] font-black uppercase text-muted hover:border-gold/30 hover:text-gold hover:bg-gold/[0.04] transition-all group/dl min-h-[44px]">
                                        <svg class="h-3.5 w-3.5 group-hover/dl:translate-y-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        PDF
                                    </a>
                                </div>
                            </div>

                            @if($appt && $appt->productos && count($appt->productos) > 0)
                                <div class="px-5 pb-4 flex flex-wrap gap-2">
                                    @foreach($appt->productos as $prod)
                                        <span class="px-2 py-0.5 rounded-full bg-white/[0.04] border border-white/5 text-[8px] font-black text-muted uppercase">
                                            {{ $prod['nombre'] ?? '' }} ×{{ $prod['cantidad'] ?? 1 }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                @if($payments->hasPages())
                    <div class="pt-4">
                        {{ $payments->links() }}
                    </div>
                @endif
            @endif

        </div>
    </div>
</x-app-layout>
