<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Detalle de <span class="text-gold">Suministro</span></h2>
                <p class="ui-subtitle">Ficha técnica y estado actual en el almacén central.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('almacen.edit', $inventory) }}" class="ui-btn px-8">Editar Ficha</a>
                <a href="{{ route('almacen.index') }}" class="ui-btn-secondary">Volver</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="ui-card-premium p-10 relative overflow-hidden">
                <div class="absolute right-0 top-0 h-full w-1/3 bg-gradient-to-l from-gold/5 to-transparent"></div>
                
                <div class="relative z-10">
                    <div class="flex flex-col md:flex-row justify-between items-start gap-8">
                        <div>
                            <span class="ui-badge mb-4">ID: #{{ str_pad($inventory->id, 4, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="text-4xl font-black text-white uppercase tracking-tighter">{{ $inventory->name }}</h3>
                            <p class="mt-4 text-muted max-w-xl text-sm leading-relaxed font-medium">Control operativo de existencias para este artículo esencial.</p>
                        </div>
                        <div class="text-center md:text-right">
                            <p class="text-[10px] font-black uppercase text-gold tracking-widest mb-1">Stock Actual</p>
                            <p class="text-6xl font-black text-white">{{ $inventory->quantity }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-16 border-t border-white/5 pt-10">
                        <article class="p-6 rounded-2xl bg-white/5 border border-white/5">
                            <p class="text-[10px] font-black uppercase text-muted tracking-widest mb-2">Valor Unitario</p>
                            <p class="text-2xl font-black text-gold">${{ number_format($inventory->price, 2) }}</p>
                        </article>
                        <article class="p-6 rounded-2xl bg-white/5 border border-white/5">
                            <p class="text-[10px] font-black uppercase text-muted tracking-widest mb-2">Mínimo Crítico</p>
                            <p class="text-2xl font-black text-white">{{ $inventory->min_stock }} Unid.</p>
                        </article>
                        <article class="p-6 rounded-2xl bg-white/5 border border-white/5">
                            <p class="text-[10px] font-black uppercase text-muted tracking-widest mb-2">Estatus Sistema</p>
                            @if ($inventory->active)
                                <p class="text-lg font-black text-green-400 uppercase">Activo</p>
                            @else
                                <p class="text-lg font-black text-red-400 uppercase">Inactivo</p>
                            @endif
                        </article>
                    </div>

                    <div class="mt-10 p-6 rounded-2xl border border-white/5 bg-panel/30">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-2 w-2 rounded-full bg-gold animate-pulse"></div>
                            <h4 class="text-[10px] font-black text-white uppercase tracking-widest">Información Operativa</h4>
                        </div>
                        <p class="text-xs text-muted leading-relaxed italic">Última actualización de inventario: {{ $inventory->updated_at->format('d M, Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
