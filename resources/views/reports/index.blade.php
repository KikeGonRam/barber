<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Centro de <span class="text-gold">Reportes</span></h2>
                <p class="ui-subtitle">Analiza el rendimiento y exporta datos operativos en alta resolución.</p>
            </div>
            <span class="ui-badge border-gold/20 bg-gold/5 text-gold self-start sm:self-auto">
                <svg class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Datos en tiempo real
            </span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <!-- Filtros -->
            <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-5">
                <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/30 mb-4">Filtros de reporte</p>
                <form method="GET" action="{{ route('reports.index') }}"
                      class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                      onsubmit="const s=this.start_date.value,e=this.end_date.value;if(s&&e&&e<s){alert('La fecha fin no puede ser anterior a la fecha inicio.');return false;}">
                    <div>
                        <label class="ui-label">Fecha inicio</label>
                        <input type="date" name="start_date" value="{{ request('start_date') }}"
                               class="ui-input !bg-black/30 border-white/10 focus:border-gold/50 transition-all text-white">
                    </div>
                    <div>
                        <label class="ui-label">Fecha fin</label>
                        <input type="date" name="end_date" value="{{ request('end_date') }}"
                               class="ui-input !bg-black/30 border-white/10 focus:border-gold/50 transition-all text-white">
                    </div>
                    <div>
                        <label class="ui-label">Barbero</label>
                        <select name="barber_id" class="ui-input !bg-black/30 border-white/10 focus:border-gold/50 transition-all text-white">
                            <option value="">Todos</option>
                            @foreach($barbers as $barber)
                                <option value="{{ $barber->id }}" @selected(request('barber_id') == $barber->id)>{{ $barber->user?->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="ui-label">Método de pago</label>
                        <select name="metodo_pago" class="ui-input !bg-black/30 border-white/10 focus:border-gold/50 transition-all text-white">
                            <option value="">Todos</option>
                            @foreach(['efectivo','tarjeta','transferencia','qr','stripe'] as $metodo)
                                <option value="{{ $metodo }}" @selected(request('metodo_pago') === $metodo)>{{ ucfirst($metodo) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4 flex items-center justify-end gap-4 pt-2 border-t border-white/[0.06]">
                        @if(request()->anyFilled(['start_date', 'end_date', 'barber_id', 'metodo_pago']))
                            <a href="{{ route('reports.index') }}"
                               class="text-[10px] font-black uppercase tracking-widest text-white/30 hover:text-white transition">
                                Limpiar
                            </a>
                        @endif
                        <button type="submit" class="ui-btn px-8 py-2.5 text-[10px] uppercase tracking-[0.2em]">
                            Aplicar filtros
                        </button>
                    </div>
                </form>
            </div>

            @php
                $filterQuery = request()->only(['start_date', 'end_date', 'barber_id', 'metodo_pago', 'estado', 'categoria', 'tipo']);

                $exportCards = [
                    [
                        'type'  => 'ingresos',
                        'title' => 'Finanzas & Ingresos',
                        'desc'  => 'Detalle de cobros, propinas acumuladas y desglose por método de pago.',
                        'color' => 'emerald',
                        'icon'  => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                    ],
                    [
                        'type'  => 'citas',
                        'title' => 'Agenda Operativa',
                        'desc'  => 'Servicios realizados, tasas de cancelación y flujo de agenda.',
                        'color' => 'blue',
                        'icon'  => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                    ],
                    [
                        'type'  => 'inventario',
                        'title' => 'Stock & Suministros',
                        'desc'  => 'Movimientos de productos, alertas de stock crítico y valor de inventario.',
                        'color' => 'purple',
                        'icon'  => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                    ],
                    [
                        'type'  => 'clientes',
                        'title' => 'Fidelidad & CRM',
                        'desc'  => 'Ranking de clientes VIP, frecuencia de visita y captación de nuevos perfiles.',
                        'color' => 'orange',
                        'icon'  => 'M12 4.354a4 4 0 110 15.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                    ],
                ];

                $colorMap = [
                    'emerald' => ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/20', 'text' => 'text-emerald-400', 'grad' => 'from-emerald-500/30'],
                    'blue'    => ['bg' => 'bg-blue-500/10',    'border' => 'border-blue-500/20',    'text' => 'text-blue-400',    'grad' => 'from-blue-500/30'],
                    'purple'  => ['bg' => 'bg-purple-500/10',  'border' => 'border-purple-500/20',  'text' => 'text-purple-400',  'grad' => 'from-purple-500/30'],
                    'orange'  => ['bg' => 'bg-orange-500/10',  'border' => 'border-orange-500/20',  'text' => 'text-orange-400',  'grad' => 'from-orange-500/30'],
                ];
            @endphp

            <!-- Export Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach($exportCards as $card)
                    @php $c = $colorMap[$card['color']]; @endphp
                    <div class="relative rounded-2xl border border-white/[0.06] bg-[#111] overflow-hidden group"
                         x-data="{ loading: null }">
                        <!-- Top accent -->
                        <div class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r {{ $c['grad'] }} to-transparent"></div>

                        <div class="p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-black text-white uppercase tracking-tight leading-tight">{{ $card['title'] }}</h4>
                                    <p class="text-[10px] text-white/40 font-medium mt-1.5 leading-relaxed">{{ $card['desc'] }}</p>
                                </div>
                                <div class="{{ $c['bg'] }} p-2.5 rounded-xl {{ $c['border'] }} border flex-shrink-0 group-hover:scale-105 transition-transform">
                                    <svg class="h-5 w-5 {{ $c['text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}" />
                                    </svg>
                                </div>
                            </div>

                            <div class="mt-4 flex gap-2">
                                <a href="{{ route('reports.export', ['type' => $card['type'], 'format' => 'pdf'] + $filterQuery) }}"
                                   @click="loading='pdf'"
                                   :class="loading==='pdf' ? 'opacity-50 pointer-events-none' : ''"
                                   class="flex-1 rounded-xl border border-white/[0.06] bg-white/[0.03] py-2.5 text-[9px] font-black text-white/60 uppercase tracking-widest text-center hover:bg-white/[0.07] hover:text-white transition-all">
                                    <span x-text="loading==='pdf' ? 'Generando…' : 'PDF'">PDF</span>
                                </a>
                                <a href="{{ route('reports.export', ['type' => $card['type'], 'format' => 'excel'] + $filterQuery) }}"
                                   @click="loading='excel'"
                                   :class="loading==='excel' ? 'opacity-50 pointer-events-none' : ''"
                                   class="flex-1 rounded-xl border border-gold/20 bg-gold/[0.06] py-2.5 text-[9px] font-black text-gold/80 uppercase tracking-widest text-center hover:bg-gold hover:text-black transition-all">
                                    <span x-text="loading==='excel' ? 'Generando…' : 'Excel'">Excel</span>
                                </a>
                                <a href="{{ route('reports.export', ['type' => $card['type'], 'format' => 'csv'] + $filterQuery) }}"
                                   @click="loading='csv'"
                                   :class="loading==='csv' ? 'opacity-50 pointer-events-none' : ''"
                                   class="flex-1 rounded-xl border border-emerald-500/20 bg-emerald-500/[0.06] py-2.5 text-[9px] font-black text-emerald-400/80 uppercase tracking-widest text-center hover:bg-emerald-500 hover:text-black transition-all">
                                    <span x-text="loading==='csv' ? 'Generando…' : 'CSV'">CSV</span>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</x-app-layout>
