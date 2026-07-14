<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="ui-title">Tu <span class="text-gold">carrito</span></h2>
            <p class="ui-subtitle">Revisa tu pedido. Pagas y recoges en sucursal.</p>
        </div>
    </x-slot>

    <div class="py-4 max-w-3xl space-y-5">
        @if(session('status'))
            <div class="rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm font-bold text-emerald-400">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-xl border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm font-bold text-red-400">{{ $errors->first() }}</div>
        @endif

        @if(empty($items))
            <div class="ui-card-premium p-12 text-center">
                <p class="text-sm font-bold text-white">Tu carrito está vacío</p>
                <p class="text-xs text-muted mt-1">Explora la tienda y agrega productos.</p>
                <a href="{{ route('client.tienda.index') }}" class="ui-btn px-8 py-3 mt-5 inline-block text-[11px] tracking-widest">Ir a la tienda</a>
            </div>
        @else
            <div class="ui-card-premium divide-y divide-white/5">
                @foreach($items as $item)
                    <div class="p-4 flex items-center gap-4">
                        <div class="h-16 w-16 rounded-xl bg-[#0f0f0f] overflow-hidden shrink-0 flex items-center justify-center">
                            @if($item['imagen'])
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($item['imagen']) }}" alt="" class="h-full w-full object-cover">
                            @else
                                <svg class="h-7 w-7 text-white/10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7"/></svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-black text-white line-clamp-1">{{ $item['nombre'] }}</h3>
                            <p class="text-xs text-gold font-bold mt-0.5">${{ number_format($item['precio'], 2) }} c/u</p>
                        </div>
                        <form method="POST" action="{{ route('client.carrito.update') }}" class="flex items-center gap-2 shrink-0">
                            @csrf @method('PATCH')
                            <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                            <input type="number" name="cantidad" value="{{ $item['cantidad'] }}" min="1"
                                   class="ui-input w-16 text-center px-2 py-1.5" onchange="this.form.submit()">
                        </form>
                        <div class="w-20 text-right shrink-0">
                            <p class="text-sm font-black text-white">${{ number_format($item['precio'] * $item['cantidad'], 2) }}</p>
                        </div>
                        <form method="POST" action="{{ route('client.carrito.remove', $item['product_id']) }}" class="shrink-0">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-muted hover:text-red-400 transition p-1" aria-label="Quitar">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>

            <div class="ui-card-premium p-5 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-muted">Total</p>
                    <p class="text-2xl font-black text-gold">${{ number_format($total, 2) }}</p>
                </div>
                <form method="POST" action="{{ route('client.carrito.checkout') }}">
                    @csrf
                    <button type="submit" class="ui-btn px-8 py-4 text-[12px] tracking-widest">Confirmar pedido</button>
                </form>
            </div>
            <p class="text-[11px] text-muted text-center">El pedido queda reservado. Pagas y recoges en sucursal.</p>
        @endif
    </div>
</x-app-layout>
