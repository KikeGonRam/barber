<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Tienda <span class="text-gold">UrbanBlade</span></h2>
                <p class="ui-subtitle">Productos premium para llevar tu estilo a casa.</p>
            </div>
            <a href="{{ route('client.carrito.index') }}" class="relative ui-btn-secondary px-6 py-3 text-[10px] uppercase tracking-widest inline-flex items-center gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Carrito
                @php $cartCount = app(\App\Services\Cart\CartService::class)->count(); @endphp
                @if($cartCount > 0)
                    <span class="absolute -top-2 -right-2 h-5 min-w-[20px] px-1 rounded-full bg-gold text-black text-[10px] font-black flex items-center justify-center">{{ $cartCount }}</span>
                @endif
            </a>
        </div>
    </x-slot>

    <div class="py-4 space-y-6">
        @if(session('status'))
            <div class="rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm font-bold text-emerald-400">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-xl border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm font-bold text-red-400">{{ $errors->first() }}</div>
        @endif

        {{-- Filtros --}}
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="q" value="{{ $search }}" placeholder="Buscar producto..." class="ui-input flex-1">
            <select name="categoria" class="ui-input sm:w-56" onchange="this.form.submit()">
                <option value="">Todas las categorías</option>
                @foreach($categorias as $cat)
                    <option value="{{ $cat }}" @selected($categoria === $cat)>{{ $cat }}</option>
                @endforeach
            </select>
            <button type="submit" class="ui-btn px-8 py-3 text-[11px] tracking-widest">Buscar</button>
        </form>

        {{-- Catálogo --}}
        @if($products->isEmpty())
            <div class="rounded-2xl border border-dashed border-white/10 p-12 text-center">
                <p class="text-sm font-bold text-muted">No hay productos disponibles con esos filtros.</p>
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($products as $product)
                    <article class="ui-card-premium p-0 overflow-hidden group flex flex-col">
                        <div class="aspect-square bg-[#0f0f0f] relative overflow-hidden">
                            @if($product->imagen)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($product->imagen) }}" alt="{{ $product->nombre }}" loading="lazy"
                                     class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-500">
                            @else
                                <div class="h-full w-full flex items-center justify-center">
                                    <svg class="h-12 w-12 text-white/10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                </div>
                            @endif
                            @if($product->categoria)
                                <span class="absolute top-2 left-2 text-[8px] font-black uppercase tracking-wider bg-black/60 text-gold px-2 py-1 rounded-full">{{ $product->categoria }}</span>
                            @endif
                        </div>
                        <div class="p-4 flex flex-col flex-1">
                            <h3 class="text-sm font-black text-white line-clamp-1">{{ $product->nombre }}</h3>
                            <p class="text-[11px] text-muted mt-0.5 line-clamp-2 flex-1">{{ $product->descripcion ?: 'Producto premium de barbería.' }}</p>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="text-lg font-black text-gold">${{ number_format($product->precio_venta, 2) }}</span>
                                <span class="text-[9px] font-bold uppercase tracking-wider text-muted">{{ $product->stock_actual }} disp.</span>
                            </div>
                            <form method="POST" action="{{ route('client.carrito.add', $product) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="w-full ui-btn py-2.5 text-[10px] tracking-widest">Agregar</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
