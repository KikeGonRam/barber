<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Mis <span class="text-gold">pedidos</span></h2>
                <p class="ui-subtitle">Tus compras de productos y su estado.</p>
            </div>
            <a href="{{ route('client.tienda.index') }}" class="ui-btn-secondary px-6 py-3 text-[10px] uppercase tracking-widest">Ir a la tienda</a>
        </div>
    </x-slot>

    <div class="py-4 space-y-4 max-w-3xl">
        @if(session('status'))
            <div class="rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm font-bold text-emerald-400">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-xl border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm font-bold text-red-400">{{ $errors->first() }}</div>
        @endif

        @forelse($orders as $order)
            @php
                $estadoCfg = [
                    'pendiente' => ['Pendiente', 'text-amber-300 border-amber-500/30 bg-amber-500/10'],
                    'entregado' => ['Entregado', 'text-emerald-300 border-emerald-500/25 bg-emerald-500/10'],
                    'cancelado' => ['Cancelado', 'text-red-400 border-red-500/25 bg-red-500/10'],
                ][$order->estado] ?? [$order->estado, 'text-white/50 border-white/10'];
            @endphp
            <div class="ui-card-premium p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-black text-white">{{ $order->folio }}
                            @if($order->tipo === 'cita')<span class="text-[9px] text-gold uppercase tracking-wider ml-1">· con tu cita</span>@endif
                        </p>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted mt-0.5">{{ optional($order->created_at)->translatedFormat('d M Y, H:i') }}</p>
                    </div>
                    <span class="text-[9px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full border {{ $estadoCfg[1] }}">{{ $estadoCfg[0] }}</span>
                </div>

                <div class="mt-3 space-y-1.5 border-t border-white/5 pt-3">
                    @foreach($order->items ?? [] as $it)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted"><span class="text-white font-bold">{{ $it['cantidad'] }}×</span> {{ $it['nombre'] }}</span>
                            <span class="text-white font-bold">${{ number_format($it['subtotal'] ?? ($it['precio'] * $it['cantidad']), 2) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 flex items-center justify-between border-t border-white/5 pt-3">
                    <span class="text-[10px] font-black uppercase tracking-widest text-muted">Total</span>
                    <span class="text-lg font-black text-gold">${{ number_format($order->total, 2) }}</span>
                </div>

                @if($order->estado === 'pendiente')
                    <form method="POST" action="{{ route('client.pedidos.cancel', $order) }}" class="mt-3 text-right"
                          onsubmit="return confirm('¿Cancelar este pedido?')">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-red-400 transition">Cancelar pedido</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="ui-card-premium p-12 text-center">
                <p class="text-sm font-bold text-white">Aún no tienes pedidos</p>
                <p class="text-xs text-muted mt-1">Cuando compres en la tienda, aparecerán aquí.</p>
            </div>
        @endforelse

        @if($orders->hasPages())
            <div>{{ $orders->links() }}</div>
        @endif
    </div>
</x-app-layout>
