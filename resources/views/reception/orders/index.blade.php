<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="ui-title">Bandeja de <span class="text-gold">pedidos</span></h2>
            <p class="ui-subtitle">Prepara, cobra y entrega los pedidos de la tienda.</p>
        </div>
    </x-slot>

    <div class="py-4 space-y-5">
        @if(session('status'))
            <div class="rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm font-bold text-emerald-400">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-xl border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm font-bold text-red-400">{{ $errors->first() }}</div>
        @endif

        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-4">
            <div class="ui-card-premium p-5 text-center">
                <p class="text-3xl font-black text-amber-300">{{ $stats['pendientes'] }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-muted mt-1">Pendientes</p>
            </div>
            <div class="ui-card-premium p-5 text-center">
                <p class="text-2xl font-black text-gold">${{ number_format($stats['por_cobrar'], 2) }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-muted mt-1">Por cobrar</p>
            </div>
            <div class="ui-card-premium p-5 text-center">
                <p class="text-3xl font-black text-emerald-400">{{ $stats['entregados'] }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-muted mt-1">Entregados</p>
            </div>
        </div>

        {{-- Filtros --}}
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="q" value="{{ $search }}" placeholder="Buscar por folio..." class="ui-input flex-1">
            <select name="estado" class="ui-input sm:w-52" onchange="this.form.submit()">
                <option value="">Todos los estados</option>
                <option value="pendiente" @selected($estado==='pendiente')>Pendientes</option>
                <option value="entregado" @selected($estado==='entregado')>Entregados</option>
                <option value="cancelado" @selected($estado==='cancelado')>Cancelados</option>
            </select>
            {{-- Sin tabla en esta pantalla (tarjetas), asi que el orden se
                 elige aqui en vez de columnas clicables. Cada opcion trae su
                 propia direccion "natural" (data-dir) para que cambiar de
                 columna no arrastre el `dir` de la columna anterior. --}}
            <select name="sort" class="ui-input sm:w-52"
                    onchange="document.getElementById('order-dir').value = this.options[this.selectedIndex].dataset.dir; this.form.submit();">
                <option value="created_at" data-dir="desc" @selected(request('sort', 'created_at') === 'created_at')>Más recientes</option>
                <option value="total" data-dir="desc" @selected(request('sort') === 'total')>Monto (mayor a menor)</option>
                <option value="folio" data-dir="asc" @selected(request('sort') === 'folio')>Folio (alfabético)</option>
            </select>
            <input type="hidden" id="order-dir" name="dir" value="{{ request('dir', 'desc') }}">
            <button type="submit" class="ui-btn px-8 py-3 text-[11px] tracking-widest">Filtrar</button>
        </form>

        {{-- Lista --}}
        @forelse($orders as $order)
            @php
                $estadoCfg = [
                    'pendiente' => ['Pendiente', 'text-amber-300 border-amber-500/30 bg-amber-500/10'],
                    'entregado' => ['Entregado', 'text-emerald-300 border-emerald-500/25 bg-emerald-500/10'],
                    'cancelado' => ['Cancelado', 'text-red-400 border-red-500/25 bg-red-500/10'],
                ][$order->estado] ?? [$order->estado, 'text-ink/50 border-ink/10'];
            @endphp
            <div class="ui-card-premium p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-black text-ink">{{ $order->folio }}
                            @if($order->tipo === 'cita')<span class="text-[9px] text-gold uppercase tracking-wider ml-1">· add-on de cita</span>@endif
                        </p>
                        <p class="text-[11px] text-muted mt-0.5">{{ $order->client?->user?->name ?? 'Cliente' }} · {{ optional($order->created_at)->translatedFormat('d M Y, H:i') }}</p>
                    </div>
                    <span class="text-[9px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full border {{ $estadoCfg[1] }}">{{ $estadoCfg[0] }}</span>
                </div>

                <div class="mt-3 border-t border-ink/5 pt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1">
                    @foreach($order->items ?? [] as $it)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted"><span class="text-ink font-bold">{{ $it['cantidad'] }}×</span> {{ $it['nombre'] }}</span>
                            <span class="text-ink font-bold">${{ number_format($it['subtotal'] ?? ($it['precio'] * $it['cantidad']), 2) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-ink/5 pt-3">
                    <div class="flex items-baseline gap-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-muted">Total</span>
                        <span class="text-lg font-black text-gold">${{ number_format($order->total, 2) }}</span>
                        @if($order->estado === 'entregado' && $order->metodo_pago)
                            <span class="text-[10px] text-muted">· {{ ucfirst($order->metodo_pago) }}</span>
                        @endif
                    </div>

                    @if($order->estado === 'pendiente')
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('orders.deliver', $order) }}" class="flex items-center gap-2">
                                @csrf @method('PATCH')
                                <select name="metodo_pago" required class="ui-input py-2 px-3 text-xs w-32">
                                    <option value="efectivo">Efectivo</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="qr">QR</option>
                                </select>
                                <button type="submit" class="ui-btn px-5 py-2 text-[10px] tracking-widest">Entregar y cobrar</button>
                            </form>
                            <form method="POST" action="{{ route('orders.cancel', $order) }}" onsubmit="return confirm('¿Cancelar el pedido y devolver el stock?')">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-red-400 transition px-2">Cancelar</button>
                            </form>
                        </div>
                    @elseif($order->estado === 'entregado')
                        <a href="{{ route('orders.receipt', $order) }}" target="_blank" rel="noopener"
                           class="text-[10px] font-black uppercase tracking-widest text-gold hover:text-ink transition inline-flex items-center gap-1.5">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Descargar recibo
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="ui-card-premium p-12 text-center">
                <p class="text-sm font-bold text-ink">Sin pedidos</p>
                <p class="text-xs text-muted mt-1">Cuando los clientes compren en la tienda, aparecerán aquí.</p>
            </div>
        @endforelse

        @if($orders->hasPages())
            <div>{{ $orders->links() }}</div>
        @endif
    </div>
</x-app-layout>
