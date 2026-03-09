<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Centro de <span class="text-gold">Reportes</span></h2>
                <p class="ui-subtitle">Analiza el rendimiento y exporta datos operativos en alta resolución.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="ui-badge border-gold/20 bg-gold/5 text-gold">
                    <svg class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Datos en tiempo real
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <section class="space-y-8">
                <!-- Global Filters -->
                <div class="ui-card-premium p-8 border-white/5 bg-black/20 backdrop-blur-md">
                    <div class="mb-6 border-b border-white/10 pb-4">
                        <h3 class="text-xs font-black text-white uppercase tracking-[0.2em]">Configuración de Filtros</h3>
                        <p class="text-[10px] text-muted font-bold uppercase mt-1">Define el rango y criterios para la generación de inteligencia de negocio.</p>
                    </div>
                    <form method="GET" action="{{ route('reports.index') }}" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="ui-label">Fecha Inicio</label>
                            <input type="date" name="start_date" value="{{ request('start_date') }}" class="ui-input !bg-panel border-white/10 focus:border-gold/50 transition-all text-white">
                        </div>
                        <div>
                            <label class="ui-label">Fecha Fin</label>
                            <input type="date" name="end_date" value="{{ request('end_date') }}" class="ui-input !bg-panel border-white/10 focus:border-gold/50 transition-all text-white">
                        </div>
                        <div>
                            <label class="ui-label">Maestro Barbero</label>
                            <select name="barber_id" class="ui-input !bg-panel border-white/10 focus:border-gold/50 transition-all text-white">
                                <option value="">Todos los barberos</option>
                                @foreach($barbers as $barber)
                                    <option value="{{ $barber->id }}" @selected(request('barber_id') == $barber->id)>{{ $barber->user?->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Método de Pago</label>
                            <select name="metodo_pago" class="ui-input !bg-panel border-white/10 focus:border-gold/50 transition-all text-white">
                                <option value="">Todos los métodos</option>
                                @foreach(['efectivo','tarjeta','transferencia','qr'] as $metodo)
                                    <option value="{{ $metodo }}" @selected(request('metodo_pago') === $metodo)>{{ ucfirst($metodo) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-4 flex items-center justify-end gap-6 mt-4 pt-4 border-t border-white/5">
                            @if(request()->anyFilled(['start_date', 'end_date', 'barber_id', 'metodo_pago']))
                                <a href="{{ route('reports.index') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-white transition">Limpiar Filtros</a>
                            @endif
                            <button type="submit" class="ui-btn px-10 py-3 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/10">
                                Aplicar Inteligencia
                            </button>
                        </div>
                    </form>
                </div>

                @php
                    $filterQuery = request()->only(['start_date', 'end_date', 'barber_id', 'metodo_pago', 'estado', 'categoria', 'tipo']);
                @endphp

                <!-- Export Cards -->
                <div class="grid grid-cols-1 gap-8 sm:grid-cols-2">
                    <!-- Ingresos -->
                    <div class="ui-card-premium p-8 group border-l-4 border-l-green-500 hover:border-gold/50">
                        <div class="flex items-start justify-between">
                            <div class="max-w-[70%]">
                                <h4 class="text-lg font-black text-white uppercase tracking-tight">Finanzas & Ingresos</h4>
                                <p class="text-xs text-muted mt-2 leading-relaxed font-medium">Detalle exhaustivo de cobros, propinas acumuladas y desglose por método de pago.</p>
                            </div>
                            <div class="bg-green-500/10 p-3 rounded-2xl border border-green-500/20 group-hover:scale-110 transition-transform">
                                <svg class="h-7 w-7 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-8 flex gap-4">
                            <a href="{{ route('reports.export', ['type' => 'ingresos', 'format' => 'pdf'] + $filterQuery) }}" class="flex-1 rounded-xl border border-white/5 bg-white/5 py-3 text-[10px] font-black text-white uppercase tracking-widest text-center hover:bg-white/10 transition-all">PDF Executive</a>
                            <a href="{{ route('reports.export', ['type' => 'ingresos', 'format' => 'excel'] + $filterQuery) }}" class="flex-1 rounded-xl border border-gold/30 bg-gold/10 py-3 text-[10px] font-black text-gold uppercase tracking-widest text-center hover:bg-gold hover:text-black transition-all">Excel Data</a>
                        </div>
                    </div>

                    <!-- Citas -->
                    <div class="ui-card-premium p-8 group border-l-4 border-l-blue-500 hover:border-gold/50">
                        <div class="flex items-start justify-between">
                            <div class="max-w-[70%]">
                                <h4 class="text-lg font-black text-white uppercase tracking-tight">Agenda Operativa</h4>
                                <p class="text-xs text-muted mt-2 leading-relaxed font-medium">Resumen métrico de servicios realizados, tasas de cancelación y flujo de agenda.</p>
                            </div>
                            <div class="bg-blue-500/10 p-3 rounded-2xl border border-blue-500/20 group-hover:scale-110 transition-transform">
                                <svg class="h-7 w-7 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-8 flex gap-4">
                            <a href="{{ route('reports.export', ['type' => 'citas', 'format' => 'pdf'] + $filterQuery) }}" class="flex-1 rounded-xl border border-white/5 bg-white/5 py-3 text-[10px] font-black text-white uppercase tracking-widest text-center hover:bg-white/10 transition-all">PDF Executive</a>
                            <a href="{{ route('reports.export', ['type' => 'citas', 'format' => 'excel'] + $filterQuery) }}" class="flex-1 rounded-xl border border-gold/30 bg-gold/10 py-3 text-[10px] font-black text-gold uppercase tracking-widest text-center hover:bg-gold hover:text-black transition-all">Excel Data</a>
                        </div>
                    </div>

                    <!-- Inventario -->
                    <div class="ui-card-premium p-8 group border-l-4 border-l-purple-500 hover:border-gold/50">
                        <div class="flex items-start justify-between">
                            <div class="max-w-[70%]">
                                <h4 class="text-lg font-black text-white uppercase tracking-tight">Stock & Suministros</h4>
                                <p class="text-xs text-muted mt-2 leading-relaxed font-medium">Auditoría de movimientos de productos, alertas de stock crítico y valor de inventario.</p>
                            </div>
                            <div class="bg-purple-500/10 p-3 rounded-2xl border border-purple-500/20 group-hover:scale-110 transition-transform">
                                <svg class="h-7 w-7 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-8 flex gap-4">
                            <a href="{{ route('reports.export', ['type' => 'inventario', 'format' => 'pdf'] + $filterQuery) }}" class="flex-1 rounded-xl border border-white/5 bg-white/5 py-3 text-[10px] font-black text-white uppercase tracking-widest text-center hover:bg-white/10 transition-all">PDF Executive</a>
                            <a href="{{ route('reports.export', ['type' => 'inventario', 'format' => 'excel'] + $filterQuery) }}" class="flex-1 rounded-xl border border-gold/30 bg-gold/10 py-3 text-[10px] font-black text-gold uppercase tracking-widest text-center hover:bg-gold hover:text-black transition-all">Excel Data</a>
                        </div>
                    </div>

                    <!-- Clientes -->
                    <div class="ui-card-premium p-8 group border-l-4 border-l-orange-500 hover:border-gold/50">
                        <div class="flex items-start justify-between">
                            <div class="max-w-[70%]">
                                <h4 class="text-lg font-black text-white uppercase tracking-tight">Fidelidad & CRM</h4>
                                <p class="text-xs text-muted mt-2 leading-relaxed font-medium">Ranking de clientes VIP, frecuencia de visita y captación de nuevos perfiles.</p>
                            </div>
                            <div class="bg-orange-500/10 p-3 rounded-2xl border border-orange-500/20 group-hover:scale-110 transition-transform">
                                <svg class="h-7 w-7 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 15.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-8 flex gap-4">
                            <a href="{{ route('reports.export', ['type' => 'clientes', 'format' => 'pdf'] + $filterQuery) }}" class="flex-1 rounded-xl border border-white/5 bg-white/5 py-3 text-[10px] font-black text-white uppercase tracking-widest text-center hover:bg-white/10 transition-all">PDF Executive</a>
                            <a href="{{ route('reports.export', ['type' => 'clientes', 'format' => 'excel'] + $filterQuery) }}" class="flex-1 rounded-xl border border-gold/30 bg-gold/10 py-3 text-[10px] font-black text-gold uppercase tracking-widest text-center hover:bg-gold hover:text-black transition-all">Excel Data</a>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
