@php
    use App\Helpers\SmartImageHelper;
    $activeFilters = array_filter($filters ?? [], fn($v) => $v !== '' && $v !== null);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Gestión de <span class="text-gold">Inventario</span></h2>
                <p class="ui-subtitle">Control de stock, productos y suministros.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('inventory.movements.index') }}" class="ui-btn-secondary px-5 text-[11px] tracking-widest">
                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    Movimientos
                </a>
                <a href="{{ route('inventory.products.create') }}" class="ui-btn">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nuevo Producto
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">
        <x-auth-session-status :status="session('status')" />

        {{-- ── ALERTA STOCK BAJO ────────────────────────── --}}
        @if($stats['bajo_stock'] > 0)
            <div class="flex items-center gap-4 rounded-2xl border border-amber-500/25 bg-amber-500/8 px-5 py-4">
                <div class="h-9 w-9 rounded-xl bg-amber-500/15 text-amber-400 flex items-center justify-center shrink-0">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-black text-amber-300">{{ $stats['bajo_stock'] }} producto{{ $stats['bajo_stock'] !== 1 ? 's' : '' }} con stock bajo</p>
                    <p class="text-[10px] text-amber-400/70 mt-0.5">Usa el filtro "Stock bajo" para verlos todos</p>
                </div>
                <a href="{{ route('inventory.products.index', ['bajo_stock' => '1']) }}"
                   class="text-[10px] font-black uppercase tracking-widest text-amber-300 hover:text-white border border-amber-500/30 rounded-lg px-3 py-1.5 hover:bg-amber-500/10 transition-all shrink-0">
                    Ver ahora
                </a>
            </div>
        @endif

        {{-- ── STATS ──────────────────────────────────── --}}
        <section class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-white/8 bg-[#111] p-5">
                <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-2">Total Productos</p>
                <p class="text-3xl font-black text-white">{{ $stats['total'] }}</p>
            </div>
            <div class="rounded-2xl border {{ $stats['bajo_stock'] > 0 ? 'border-amber-500/25 bg-amber-500/5' : 'border-emerald-500/20 bg-emerald-500/5' }} p-5">
                <p class="text-[9px] font-black uppercase tracking-widest {{ $stats['bajo_stock'] > 0 ? 'text-amber-400/70' : 'text-emerald-400/70' }} mb-2">Bajo Stock</p>
                <p class="text-3xl font-black {{ $stats['bajo_stock'] > 0 ? 'text-amber-400' : 'text-emerald-400' }}">{{ $stats['bajo_stock'] }}</p>
            </div>
            <div class="rounded-2xl border border-gold/20 bg-gold/5 p-5">
                <p class="text-[9px] font-black uppercase tracking-widest text-gold/60 mb-2">Valor Inventario</p>
                <p class="text-3xl font-black text-gold">${{ number_format($stats['valor_total'], 0) }}</p>
            </div>
            <div class="rounded-2xl border border-white/8 bg-[#111] p-5">
                <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-2">Categorías</p>
                <p class="text-3xl font-black text-white">{{ count($categorias) }}</p>
            </div>
        </section>

        {{-- ── FILTROS ─────────────────────────────────── --}}
        <section x-data="{ open: {{ count($activeFilters) > 0 ? 'true' : 'false' }} }" class="ui-card-premium overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 cursor-pointer border-b border-white/6" @click="open = !open">
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span class="text-sm font-black text-white uppercase tracking-widest">Filtros</span>
                    @if(count($activeFilters) > 0)
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gold text-[9px] font-black text-black">{{ count($activeFilters) }}</span>
                    @endif
                </div>
                <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 text-muted transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>

            <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-6 py-5">
                <form method="GET" action="{{ route('inventory.products.index') }}">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="lg:col-span-2">
                            <label class="ui-label">Búsqueda</label>
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="ui-input pl-10" placeholder="Nombre del producto...">
                            </div>
                        </div>
                        <div>
                            <label class="ui-label">Categoría</label>
                            <select name="categoria" class="ui-input mt-1">
                                <option value="">Todas</option>
                                @foreach($categorias as $cat)
                                    <option value="{{ $cat }}" @selected(($filters['categoria'] ?? '') === $cat)>{{ ucfirst($cat) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Tipo</label>
                            <select name="tipo" class="ui-input mt-1">
                                <option value="">Todos</option>
                                @foreach($tipos as $tipo)
                                    <option value="{{ $tipo }}" @selected(($filters['tipo'] ?? '') === $tipo)>{{ ucfirst($tipo) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="flex items-center gap-3 cursor-pointer w-fit group">
                            <input type="checkbox" name="bajo_stock" value="1" @checked(!empty($filters['bajo_stock']))
                                   class="h-4 w-4 rounded border-white/20 bg-black/40 text-gold focus:ring-gold/30 focus:ring-offset-0">
                            <span class="text-[11px] font-bold uppercase tracking-widest text-muted group-hover:text-amber-400 transition-colors">
                                Solo productos con stock bajo
                            </span>
                        </label>
                    </div>
                    <div class="mt-5 flex items-center gap-3">
                        <button type="submit" class="ui-btn py-2.5 px-6 text-[11px] tracking-widest">Aplicar Filtros</button>
                        @if(count($activeFilters) > 0)
                            <a href="{{ route('inventory.products.index') }}" class="flex items-center gap-1.5 rounded-xl border border-white/10 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-muted hover:text-white transition-all">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>
            {{-- Active filter chips --}}
            @if(count($activeFilters) > 0)
                <div class="flex flex-wrap gap-2 px-6 pb-4">
                    @foreach($activeFilters as $key => $val)
                        @php $labels = ['q'=>'Búsqueda','categoria'=>'Categoría','tipo'=>'Tipo','bajo_stock'=>'Stock bajo']; @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold/25 bg-gold/8 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-gold">
                            {{ $labels[$key] ?? $key }}{{ $key !== 'bajo_stock' ? ': '.$val : '' }}
                        </span>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ── TABLA ─────────────────────────────────────── --}}
        <section>
            <div class="mb-3 px-1">
                <p class="text-[11px] font-bold text-muted uppercase tracking-wider">{{ $products->total() }} producto{{ $products->total() !== 1 ? 's' : '' }}</p>
            </div>

            {{-- Desktop --}}
            <div class="hidden md:block ui-table-container">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Stock</th>
                            <th>Precio Compra</th>
                            <th>Precio Venta</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            @php
                                $isLow    = $product->stock_actual <= $product->stock_minimo;
                                $prodImg  = $product->imagen
                                    ? \Illuminate\Support\Facades\Storage::url($product->imagen)
                                    : SmartImageHelper::forProduct($product->nombre, $product->categoria ?? '', 'sm');
                            @endphp
                            <tr class="group">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="h-12 w-12 rounded-xl overflow-hidden shrink-0 border border-white/8">
                                            <img src="{{ $prodImg }}"
                                                 alt="{{ $product->nombre }}"
                                                 class="h-full w-full object-cover"
                                                 loading="lazy"
                                                 onerror="this.src='https://images.unsplash.com/photo-1626806819282-2c1dc01a5e0c?w=120&h=120&auto=format&fit=crop&q=70'">
                                        </div>
                                        <div>
                                            <p class="font-bold text-white text-sm">{{ $product->nombre }}</p>
                                            <p class="text-[10px] text-muted uppercase tracking-wider">{{ $product->tipo }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-muted">
                                        {{ $product->categoria }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-sm {{ $isLow ? 'text-amber-400' : 'text-white' }}">
                                                {{ $product->stock_actual }}
                                            </span>
                                            <span class="text-[10px] text-muted">/ mín {{ $product->stock_minimo }}</span>
                                            @if($isLow)
                                                <span class="text-[9px] font-black border border-amber-500/25 bg-amber-500/10 text-amber-400 rounded-full px-1.5 py-0.5 uppercase">bajo</span>
                                            @endif
                                        </div>
                                        {{-- Mini barra de stock --}}
                                        @php $pct = $product->stock_minimo > 0 ? min(100, ($product->stock_actual / ($product->stock_minimo * 2)) * 100) : 100; @endphp
                                        <div class="h-1 w-24 bg-white/5 rounded-full overflow-hidden">
                                            <div class="h-full {{ $isLow ? 'bg-amber-400' : 'bg-emerald-400' }} rounded-full transition-all" style="width: {{ $pct }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-muted text-sm">${{ number_format($product->precio_compra, 2) }}</td>
                                <td class="font-black text-emerald-400 text-base">${{ number_format($product->precio_venta, 2) }}</td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                        <a href="{{ route('inventory.products.edit', $product) }}"
                                           class="h-8 w-8 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center text-muted hover:text-gold hover:border-gold/30 transition-all">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <form action="{{ route('inventory.products.destroy', $product) }}" method="POST"
                                              onsubmit="return confirm('¿Eliminar producto?');" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="h-8 w-8 rounded-lg border border-red-500/20 bg-red-500/5 flex items-center justify-center text-red-500/70 hover:text-red-400 hover:bg-red-500/10 transition-all">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <p class="text-sm font-bold text-muted uppercase tracking-widest">Sin productos</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile --}}
            <div class="space-y-3 md:hidden">
                @forelse($products as $product)
                    @php
                        $isLow   = $product->stock_actual <= $product->stock_minimo;
                        $mProdImg = $product->imagen
                            ? \Illuminate\Support\Facades\Storage::url($product->imagen)
                            : SmartImageHelper::forProduct($product->nombre, $product->categoria ?? '', 'sm');
                    @endphp
                    <div class="rounded-2xl border {{ $isLow ? 'border-amber-500/20' : 'border-white/8' }} bg-[#111] p-4">
                        <div class="flex items-start gap-4 mb-3">
                            <div class="h-16 w-16 rounded-xl overflow-hidden shrink-0 border border-white/8">
                                <img src="{{ $mProdImg }}"
                                     alt="{{ $product->nombre }}"
                                     class="h-full w-full object-cover"
                                     loading="lazy"
                                     onerror="this.src='https://images.unsplash.com/photo-1626806819282-2c1dc01a5e0c?w=120&h=120&auto=format&fit=crop&q=70'">
                            </div>
                            <div class="flex-1">
                                <p class="font-bold text-white text-sm">{{ $product->nombre }}</p>
                                <p class="text-[10px] text-muted uppercase">{{ $product->categoria }} · {{ $product->tipo }}</p>
                            </div>
                            <span class="font-black text-emerald-400 text-base shrink-0">${{ number_format($product->precio_venta, 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-white/5 pt-3">
                            <div class="flex items-center gap-2 text-xs">
                                <span class="font-black {{ $isLow ? 'text-amber-400' : 'text-white' }}">{{ $product->stock_actual }}</span>
                                <span class="text-muted">/ {{ $product->stock_minimo }} mín</span>
                                @if($isLow)<span class="text-[9px] font-black text-amber-400 border border-amber-500/25 rounded-full px-1.5">bajo</span>@endif
                            </div>
                            <div class="flex items-center gap-4">
                                <a href="{{ route('inventory.products.edit', $product) }}" class="text-[10px] font-black uppercase tracking-widest text-gold hover:text-white transition">Editar</a>
                                <form action="{{ route('inventory.products.destroy', $product) }}" method="POST"
                                      onsubmit="return confirm('¿Eliminar {{ addslashes($product->nombre) }}?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-[10px] font-black uppercase tracking-widest text-red-500 hover:text-red-400 transition">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/10 p-10 text-center"><p class="text-sm text-muted">Sin productos.</p></div>
                @endforelse
            </div>

            <div class="mt-6">{{ $products->links() }}</div>
        </section>
    </div>
</x-app-layout>
