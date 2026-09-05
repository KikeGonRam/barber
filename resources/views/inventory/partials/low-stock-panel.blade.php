{{--
    Panel de productos con stock bajo pendientes de atención, con acción para
    marcarlos como "ya pedido" y silenciar así la alerta diaria por unos días.
    Espera una variable $lowStockProducts (colección de App\Models\Product).
--}}
@if($lowStockProducts->isNotEmpty())
    <section class="rounded-2xl border border-amber-500/25 bg-amber-500/5 overflow-hidden">
        <div class="px-6 py-4 border-b border-amber-500/15 flex items-center gap-3">
            <div class="h-8 w-8 rounded-lg bg-amber-500/15 text-amber-400 flex items-center justify-center shrink-0">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>
            <p class="text-sm font-black text-amber-300">Productos con stock bajo pendientes de atención</p>
        </div>
        <ul class="divide-y divide-amber-500/10">
            @foreach($lowStockProducts as $product)
                @php $pending = $product->hasPendingRestockOrder(); @endphp
                <li class="flex flex-col gap-2 px-6 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-bold text-ink text-sm">{{ $product->nombre }}</p>
                        <p class="text-[10px] text-muted">{{ $product->stock_actual }} / mín {{ $product->stock_minimo }}</p>
                    </div>
                    @if($pending)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-400 w-fit">
                            Pedido hace {{ (int) $product->reabastecimiento_pedido_en->diffInDays(now()) }} día(s)
                        </span>
                    @else
                        <form action="{{ route('inventory.products.mark-ordered', $product) }}" method="POST" class="w-fit">
                            @csrf
                            <button type="submit" class="text-[10px] font-black uppercase tracking-widest text-amber-300 hover:text-ink border border-amber-500/30 rounded-lg px-3 py-1.5 hover:bg-amber-500/10 transition-all">
                                Marcar como pedido
                            </button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
