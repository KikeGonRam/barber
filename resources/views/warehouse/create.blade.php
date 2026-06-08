<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Incorporar <span class="text-gold">Suministro</span></h2>
                <p class="ui-subtitle">Registra nuevos artículos para el control operativo del almacén.</p>
            </div>
            <a href="{{ route('warehouse.index') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-white transition">
                &larr; Volver al almacén
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('warehouse.store') }}" enctype="multipart/form-data" class="space-y-10">
                    @csrf

                    <!-- Section: Item Identity -->
                    <div>
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Identidad del Artículo</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="ui-label" for="imagen">Imagen del Suministro</label>
                                <input type="file" name="imagen" id="imagen" class="ui-input !bg-panel border-white/10 text-white" accept="image/*">
                                @error('imagen') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="ui-label" for="name">Nombre del Suministro</label>
                                <input id="name" name="name" value="{{ old('name') }}" 
                                       class="ui-input !bg-panel border-white/10 text-white" 
                                       placeholder="Ej: Toallas de Algodón Egipcio" required>
                                @error('name') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Section: Inventory & Value -->
                    <div class="pt-8 border-t border-white/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Stock & Valoración</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="ui-label" for="quantity">Cantidad Inicial</label>
                                <input id="quantity" type="number" min="0" name="quantity" value="{{ old('quantity', 0) }}" 
                                       class="ui-input !bg-panel border-white/10 text-white" required>
                                @error('quantity') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="ui-label" for="min_stock">Mínimo de Alerta</label>
                                <input id="min_stock" type="number" min="0" name="min_stock" value="{{ old('min_stock', 5) }}" 
                                       class="ui-input !bg-panel border-white/10 text-white" required>
                                @error('min_stock') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="ui-label" for="price">Precio Unitario</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gold font-bold">$</span>
                                    <input id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price', 0) }}" 
                                           class="ui-input !pl-10 !bg-panel border-white/10 text-white" required>
                                </div>
                                @error('price') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Section: Status -->
                    <div class="pt-8 border-t border-white/5">
                        <label class="relative inline-flex cursor-pointer items-center group">
                            <input type="checkbox" name="active" value="1" class="sr-only peer" @checked(old('active', true))>
                            <div class="h-6 w-11 rounded-full bg-white/5 border border-white/10 peer-checked:bg-gold/20 peer-checked:border-gold transition-all"></div>
                            <div class="absolute left-1 top-1 h-4 w-4 rounded-full bg-muted peer-checked:bg-gold peer-checked:translate-x-5 transition-all"></div>
                            <span class="ms-3 text-[10px] font-black uppercase tracking-widest text-muted group-hover:text-white transition-colors">Suministro Activo para Operaciones</span>
                        </label>
                    </div>

                    <div class="pt-10 border-t border-white/5 flex justify-end">
                        <button type="submit" class="ui-btn px-16 py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                            Registrar en Almacén <span class="ml-2 opacity-50">&rarr;</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
