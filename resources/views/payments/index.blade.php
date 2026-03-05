<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Pagos</h2>
            <span class="ui-badge">Caja diaria</span>
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
                        <p class="text-sm font-semibold text-[#1f1f1f]">Registro de pagos</p>
                        <p class="text-xs text-[#707070]">Control de monto, propina y comprobante por cita.</p>
                    </div>
                    <a href="{{ route('payments.create') }}" class="ui-btn">Registrar pago</a>
                </div>

                <div class="ui-list">
                    @forelse ($payments as $payment)
                        <article class="ui-list-item">
                            <div class="ui-list-item-head">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-[#141414]">Pago de {{ $payment->appointment?->fecha }} {{ $payment->appointment?->hora_inicio }}</h3>
                                    <span class="ui-badge">{{ ucfirst($payment->metodo_pago) }}</span>
                                </div>
                                <div class="ui-toolbar-group">
                                    @if($payment->comprobante_pdf)
                                        <a class="ui-btn-secondary" href="{{ route('payments.receipt.download', $payment) }}">Comprobante</a>
                                    @endif
                                    <form action="{{ route('payments.destroy', $payment) }}" method="POST" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn">Eliminar</button>
                                    </form>
                                </div>
                            </div>

                            <div class="ui-meta-grid">
                                <div><strong>Cita:</strong> {{ $payment->appointment?->fecha }} {{ $payment->appointment?->hora_inicio }}</div>
                                <div><strong>Cliente:</strong> {{ $payment->appointment?->client?->user?->name ?? '-' }}</div>
                                <div><strong>Barbero:</strong> {{ $payment->appointment?->barber?->user?->name ?? '-' }}</div>
                                <div><strong>Monto:</strong> ${{ number_format((float) $payment->monto, 2) }}</div>
                                <div><strong>Propina:</strong> ${{ number_format((float) $payment->propina, 2) }}</div>
                                <div><strong>Total:</strong> ${{ number_format((float) $payment->monto + (float) $payment->propina, 2) }}</div>
                            </div>
                        </article>
                    @empty
                        <div class="ui-empty">No hay pagos registrados.</div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $payments->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
