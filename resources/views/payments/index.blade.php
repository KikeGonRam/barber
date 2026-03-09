<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Centro de <span class="text-gold">Facturación</span></h2>
                <p class="ui-subtitle">Gestión de comprobantes fiscales, ingresos y trazabilidad financiera.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('reports.index') }}" class="ui-btn-secondary px-6">
                    <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Reporte Fiscal
                </a>
                <a href="{{ route('payments.create') }}" class="ui-btn shadow-gold/20">
                    <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    Emitir Factura
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <!-- Billing Summary Cards -->
            <section class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div class="ui-card-premium p-6 border-l-4 border-l-gold">
                    <p class="text-[10px] font-black uppercase tracking-widest text-muted">Facturación Hoy</p>
                    <p class="text-2xl font-black text-white mt-1">
                        ${{ number_format($payments->where('created_at', '>=', now()->startOfDay())->sum(fn($p) => $p->monto + $p->propina), 2) }}
                    </p>
                </div>
                <div class="ui-card-premium p-6 border-l-4 border-l-green-500">
                    <p class="text-[10px] font-black uppercase tracking-widest text-muted">Comprobantes Emitidos</p>
                    <p class="text-2xl font-black text-white mt-1">{{ $payments->total() }}</p>
                </div>
                <div class="ui-card-premium p-6 border-l-4 border-l-blue-500">
                    <p class="text-[10px] font-black uppercase tracking-widest text-muted">Método Preferido</p>
                    <p class="text-2xl font-black text-white mt-1 uppercase">Efectivo</p>
                </div>
            </section>

            <!-- Invoices Table -->
            <section class="ui-table-container">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Folio / Fecha</th>
                            <th>Cliente / Servicio</th>
                            <th>Método</th>
                            <th>Monto Total</th>
                            <th class="text-right">Documentos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr class="group">
                                <td>
                                    <div class="flex flex-col">
                                        <span class="font-black text-white text-xs">#{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</span>
                                        <span class="text-[10px] text-muted uppercase font-bold">{{ $payment->created_at?->format('d M, Y H:i') }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <span class="text-white font-bold text-sm">{{ $payment->appointment?->client?->user?->name ?? 'N/A' }}</span>
                                        <span class="text-[10px] uppercase tracking-widest text-gold font-black">{{ $payment->appointment?->service?->nombre ?? 'Servicio General' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        @php
                                            $methodIcons = [
                                                'efectivo' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                                                'tarjeta' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[9px] font-black uppercase tracking-widest border border-white/10 bg-white/5 text-muted">
                                            {{ $payment->metodo_pago }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <span class="font-black text-green-400 text-base">${{ number_format($payment->monto + $payment->propina, 2) }}</span>
                                        @if($payment->propina > 0)
                                            <span class="text-[9px] text-gold font-bold uppercase">+ ${{ number_format($payment->propina, 2) }} propina</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <a href="{{ route('payments.receipt.download', $payment) }}" 
                                           class="ui-btn py-1.5 px-4 text-[9px] uppercase tracking-widest shadow-none hover:bg-gold hover:text-black transition-all"
                                           title="Descargar Factura PDF">
                                            Descargar PDF
                                        </a>
                                        <form action="{{ route('payments.destroy', $payment) }}" method="POST" onsubmit="return confirm('¿Anular este comprobante?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 rounded-lg border border-red-900/20 bg-red-950/10 text-red-500 hover:bg-red-500 hover:text-white transition-all">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-20 text-center text-muted">
                                    <svg class="h-12 w-12 text-white/5 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    <p class="text-sm font-bold uppercase tracking-widest">No se han emitido facturas aún.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <div class="mt-6">
                {{ $payments->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
