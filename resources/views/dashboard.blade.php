<x-app-layout>
    <x-slot name="header">
        @php
            $dashboardMode = ($adminMode ?? false) ? 'admin' : (($isBarberMode ?? false) ? 'barber' : (($isReceptionMode ?? false) ? 'reception' : 'client'));
            $dashboardTitleClass = match ($dashboardMode) {
                'admin' => 'ui-profile-title ui-profile-title-admin',
                'barber' => 'ui-profile-title ui-profile-title-barber',
                'reception' => 'ui-profile-title ui-profile-title-reception',
                default => 'ui-profile-title ui-profile-title-client',
            };
        @endphp
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="{{ $dashboardTitleClass }}">Dashboard <span class="text-gold">{{ ($adminMode ?? false) ? 'Administrativo' : (($isBarberMode ?? false) ? 'Profesional' : (($isReceptionMode ?? false) ? 'Operativo' : 'Cliente')) }}</span></h2>
                <p class="ui-profile-subtitle mt-2">Vista ejecutiva del rendimiento y agenda de la barbería.</p>
            </div>
            <div class="flex items-center gap-4">
                @if($adminMode ?? false)
                <form method="POST" action="{{ route('settings.maintenance.toggle') }}">
                    @csrf
                    <button type="submit" 
                            class="flex items-center gap-2 px-4 py-2 rounded-xl border {{ ($maintenanceMode ?? false) ? 'bg-red-500/10 border-red-500/30 text-red-400' : 'bg-white/5 border-white/10 text-muted hover:text-white' }} transition-all text-[10px] font-black uppercase tracking-widest">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        <span>{{ ($maintenanceMode ?? false) ? 'Sistema en Mantenimiento' : 'Modo Mantenimiento' }}</span>
                    </button>
                </form>
                <a href="{{ route('backups.database.download') }}"
                   class="flex items-center gap-2 px-4 py-2 rounded-xl border bg-green-500/10 border-green-500/30 text-green-300 hover:text-white hover:bg-green-500/20 transition-all text-[10px] font-black uppercase tracking-widest">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.105.895-2 2-2h4a2 2 0 012 2v4a2 2 0 01-2 2h-4a2 2 0 01-2-2m0-4H8a2 2 0 00-2 2v4a2 2 0 002 2h4m0-8V7a2 2 0 00-2-2H6a2 2 0 00-2 2v4m8 0v4" /></svg>
                    <span>Backup BD</span>
                </a>
                @endif
                <span class="ui-badge bg-white shadow-sm ring-1 ring-white/10 border-white/5">
                    <span class="h-2 w-2 rounded-full bg-green-500 animate-pulse mr-2"></span>
                    Sistema Activo
                </span>
            </div>

        </div>
    </x-slot>

    <div class="space-y-6 py-4 sm:space-y-8 sm:py-6">
        @if ($adminMode ?? false)
            <!-- ========================================== -->
            <!-- ADMIN DASHBOARD                            -->
            <!-- ========================================== -->
            
            <!-- KPIs Row 1 -->
            <section class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <article class="ui-kpi-card group animate-slide-up" style="animation-delay: 0ms;">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="ui-kpi-label">Citas Hoy</p>
                            <p class="ui-kpi-value mt-1">{{ $kpis['appointments_today'] }}</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-[10px] text-muted font-bold">
                        <span class="bg-white/5 px-1.5 py-0.5 rounded border border-white/10 uppercase transition-all duration-300 group-hover:bg-blue-500/10 group-hover:border-blue-500/30">Sem: {{ $kpis['appointments_week'] }}</span>
                        <span class="bg-white/5 px-1.5 py-0.5 rounded border border-white/10 uppercase transition-all duration-300 group-hover:bg-blue-500/10 group-hover:border-blue-500/30">Mes: {{ $kpis['appointments_month'] }}</span>
                    </div>
                    @if($kpis['appointment_growth'] !== 0)
                    <div class="mt-3 text-xs font-bold {{ $kpis['appointment_growth'] >= 0 ? 'text-green-400' : 'text-red-400' }}">
                        {{ $kpis['appointment_growth'] >= 0 ? '↑' : '↓' }} {{ abs($kpis['appointment_growth']) }}% vs mes anterior
                    </div>
                    @endif
                </article>

                <a href="{{ route('payments.index') }}" class="ui-kpi-card group cursor-pointer hover:border-green-500/50 animate-slide-up" style="animation-delay: 100ms;">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="ui-kpi-label">Ingresos Hoy</p>
                            <p class="ui-kpi-value mt-1 text-green-400">${{ number_format($kpis['income_today'], 2) }}</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-green-500/10 text-green-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-[10px] text-muted font-bold">
                        <span class="bg-green-500/10 px-1.5 py-0.5 rounded text-green-400 border border-green-500/20 uppercase transition-all duration-300 group-hover:bg-green-500/20">Sem: ${{ number_format($kpis['income_week'], 2) }}</span>
                    </div>
                    @if($kpis['income_growth'] !== 0)
                    <div class="mt-3 text-xs font-bold {{ $kpis['income_growth'] >= 0 ? 'text-green-400' : 'text-red-400' }}">
                        {{ $kpis['income_growth'] >= 0 ? '↑' : '↓' }} {{ abs($kpis['income_growth']) }}% vs mes anterior
                    </div>
                    @endif
                </a>

                <article class="ui-kpi-card group animate-slide-up" style="animation-delay: 200ms;">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="ui-kpi-label">Clientes Activos</p>
                            <p class="ui-kpi-value mt-1">{{ $kpis['active_clients'] }}</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 12H9m6 0a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-[10px] text-muted font-bold uppercase">
                        <span class="bg-cyan-500/10 px-1.5 py-0.5 rounded text-cyan-400 border border-cyan-500/20 transition-all duration-300 group-hover:bg-cyan-500/20">Total: {{ $kpis['total_clients'] }}</span>
                    </div>
                </article>

                <article class="ui-kpi-card group animate-slide-up" style="animation-delay: 300ms;">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="ui-kpi-label">Tasa Retención</p>
                            <p class="ui-kpi-value mt-1 text-purple-400">{{ number_format($kpis['retention_rate'], 1) }}%</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-[10px] text-muted font-bold uppercase">
                        <span class="bg-purple-500/10 px-1.5 py-0.5 rounded text-purple-400 border border-purple-500/20 transition-all duration-300 group-hover:bg-purple-500/20">Recurrentes: {{ $kpis['recurring_clients'] }}</span>
                    </div>
                </article>
            </section>

            <!-- AI Predictions & Insights Section -->
            <section class="ui-card-premium p-6 sm:p-8 animate-slide-up" style="animation-delay: 350ms;">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-white uppercase tracking-widest">🤖 Predicciones con IA</h3>
                        <span class="text-[10px] text-muted font-bold uppercase mt-1">Análisis predictivo basado en Ollama</span>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-2.5 py-1 text-[9px] font-black uppercase text-indigo-400">
                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2z" clip-rule="evenodd"/></svg>
                        Beta
                    </span>
                </div>
                
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Income Forecast -->
                    <div class="rounded-2xl border border-white/5 bg-white/3 p-6 hover:border-green-500/30 hover:bg-green-500/5 transition-all duration-300">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-muted">Predicción Ingresos</p>
                                <p class="text-xs text-muted mt-1">Próximos 7 días</p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-green-500/10 text-green-400 flex items-center justify-center">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                            </div>
                        </div>
                        <p id="income-forecast" class="text-3xl font-black text-green-400 animate-pulse">--</p>
                        <p class="text-xs text-muted mt-3 italic">Cargando predicción...</p>
                    </div>

                    <!-- Appointment Forecast -->
                    <div class="rounded-2xl border border-white/5 bg-white/3 p-6 hover:border-blue-500/30 hover:bg-blue-500/5 transition-all duration-300">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-muted">Predicción Citas</p>
                                <p class="text-xs text-muted mt-1">Próximos 7 días</p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                        </div>
                        <p id="appointment-forecast" class="text-3xl font-black text-blue-400 animate-pulse">--</p>
                        <p class="text-xs text-muted mt-3 italic">Cargando predicción...</p>
                    </div>

                    <!-- AI Confidence & Status -->
                    <div class="rounded-2xl border border-white/5 bg-white/3 p-6 hover:border-indigo-500/30 hover:bg-indigo-500/5 transition-all duration-300">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-muted">Confianza IA</p>
                                <p class="text-xs text-muted mt-1">Precisión modelo</p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                            </div>
                        </div>
                        <p id="ai-confidence" class="text-3xl font-black text-indigo-400 animate-pulse">--</p>
                        <p class="text-xs text-muted mt-3 italic">Procesando análisis...</p>
                    </div>
                </div>

                <!-- AI Insights List -->
                <div class="mt-8 pt-6 border-t border-white/10">
                    <h4 class="text-xs font-black text-white uppercase tracking-widest mb-4">Insights Recomendados</h4>
                    <div id="ai-insights" class="space-y-3">
                        <div class="flex items-start gap-3 p-3 rounded-lg bg-white/5 border border-white/10 animate-pulse">
                            <div class="h-2 w-2 rounded-full bg-yellow-400 mt-1.5 flex-shrink-0"></div>
                            <p class="text-xs text-muted">Cargando análisis de inteligencia artificial...</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Visual Live Status Widget -->
            <section class="ui-card-premium p-6 sm:p-8 relative overflow-hidden">
                <div class="absolute right-0 top-0 h-full w-1/3 bg-gradient-to-l from-gold/5 to-transparent"></div>
                <div class="mb-6 sm:mb-8 flex items-center justify-between relative z-10">
                    <div>
                        <h3 class="text-sm font-black text-white uppercase tracking-[0.2em]">Estado de Estaciones en Vivo</h3>
                        <p class="text-[10px] text-muted font-bold uppercase mt-1">Monitoreo de ocupación en tiempo real por barbero.</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-[9px] font-black uppercase text-green-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse"></span> Live
                    </span>
                </div>

                @php
                    $barberStatuses = $kpis['barbers_status'] ?? [];
                @endphp
                @if(! empty($barberStatuses))
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 relative z-10">
                        @foreach($barberStatuses as $index => $status)
                            <div class="rounded-2xl border border-white/5 bg-white/5 p-4 hover:border-gold/30 transition-all duration-500 animate-slide-up" style="animation-delay: {{ $index * 100 }}ms;">
                                <div class="flex items-center gap-4">
                                    <div class="relative flex-shrink-0">
                                        <div class="h-12 w-12 rounded-xl bg-bg-accent border border-white/10 flex items-center justify-center text-gold font-black transition-transform duration-300 group-hover:scale-110">
                                            {{ substr($status['name'], 0, 2) }}
                                        </div>
                                        <div class="absolute -bottom-1 -right-1 h-4 w-4 rounded-full border-4 border-bg-card {{ $status['is_busy'] ? 'bg-red-500 animate-pulse-gold' : 'bg-green-500' }} transition-all duration-300"></div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="truncate text-sm font-bold text-white">{{ $status['name'] }}</p>
                                        <p class="text-[10px] uppercase font-black tracking-widest {{ $status['is_busy'] ? 'text-red-400' : 'text-green-400' }}">
                                            {{ $status['is_busy'] ? 'Ocupado' : 'Disponible' }}
                                        </p>
                                    </div>
                                </div>
                                @if($status['is_busy'])
                                    <div class="mt-4 space-y-1.5">
                                        <div class="flex justify-between text-[9px] uppercase font-black text-muted tracking-widest">
                                            <span>Progreso</span>
                                            <span class="text-white">{{ $status['progress'] }}%</span>
                                        </div>
                                        <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden">
                                            <div class="h-full bg-gold animate-shimmer" style="width: {{ $status['progress'] }}%"></div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="relative z-10 ui-empty-state">
                        <p class="text-xs font-black uppercase tracking-widest text-muted">Sin datos de estaciones en vivo</p>
                        <p class="mt-2 text-sm text-muted">No hay barberos con actividad registrada en este momento.</p>
                    </div>
                @endif
            </section>

            <!-- Charts Section -->
            <section class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <article class="ui-card-premium p-6 sm:p-8 animate-slide-up" style="animation-delay: 400ms;">
                    <div class="mb-6 flex items-center justify-between">
                        <h3 class="text-sm font-black text-white uppercase tracking-widest">Tendencia de Ingresos</h3>
                        <span class="text-[10px] text-muted font-bold uppercase">Últimos 7 días</span>
                    </div>
                    @php
                        $hasIncomeSeries = ! empty(array_filter($incomeChart['values'] ?? []));
                    @endphp
                    @if($hasIncomeSeries)
                        <div class="h-[280px]">
                            <canvas id="incomeChart"></canvas>
                        </div>
                    @else
                        <div class="h-[280px] ui-empty-state">
                            <p class="ui-empty-state-copy">Aún no hay ingresos en la ventana seleccionada.</p>
                        </div>
                    @endif
                </article>
                <article class="ui-card-premium p-6 sm:p-8 animate-slide-up" style="animation-delay: 500ms;">
                    <div class="mb-6 flex items-center justify-between">
                        <h3 class="text-sm font-black text-white uppercase tracking-widest">Demanda de Servicios</h3>
                        <span class="text-[10px] text-muted font-bold uppercase">Distribución %</span>
                    </div>
                    @php
                        $hasServiceSeries = ! empty(array_filter($servicesChart['values'] ?? []));
                    @endphp
                    @if($hasServiceSeries)
                        <div class="h-[280px]">
                            <canvas id="servicesChart"></canvas>
                        </div>
                    @else
                        <div class="h-[280px] ui-empty-state">
                            <p class="ui-empty-state-copy">Aún no hay servicios registrados para graficar.</p>
                        </div>
                    @endif
                </article>
            </section>

            <!-- Advanced Analytics Row -->
            <section class="grid grid-cols-1 gap-6 lg:grid-cols-2 animate-slide-up" style="animation-delay: 500ms;">
                <!-- Barber Performance Chart -->
                <article class="ui-card-premium p-6 sm:p-8">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Desempeño de Barberos</h3>
                            <span class="text-[10px] text-muted font-bold uppercase">Citas completadas este mes</span>
                        </div>
                    </div>
                    @php
                        $hasBarberData = ! empty(array_filter($barberPerformance['appointments'] ?? []));
                    @endphp
                    @if($hasBarberData)
                        <div class="h-[320px]">
                            <canvas id="barberPerformanceChart"></canvas>
                        </div>
                    @else
                        <div class="h-[320px] ui-empty-state">
                            <p class="ui-empty-state-copy">Sin datos de desempeño disponibles.</p>
                        </div>
                    @endif
                </article>

                <!-- Client Trends Chart -->
                <article class="ui-card-premium p-6 sm:p-8">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Tendencias de Clientes</h3>
                            <span class="text-[10px] text-muted font-bold uppercase">Citas completadas últimas 4 semanas</span>
                        </div>
                    </div>
                    @php
                        $hasClientTrends = ! empty(array_filter($clientTrends['values'] ?? []));
                    @endphp
                    @if($hasClientTrends)
                        <div class="h-[320px]">
                            <canvas id="clientTrendsChart"></canvas>
                        </div>
                    @else
                        <div class="h-[320px] ui-empty-state">
                            <p class="ui-empty-state-copy">Sin datos de tendencias disponibles.</p>
                        </div>
                    @endif
                </article>
            </section>

            <!-- Quick Reports Section -->
            <section class="ui-card-premium p-6 sm:p-8 animate-slide-up" style="animation-delay: 550ms;">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-white uppercase tracking-widest">Reportes Rápidos</h3>
                        <span class="text-[10px] text-muted font-bold uppercase mt-1">Exporta tus datos</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <a href="#" class="group rounded-2xl border border-white/10 bg-white/5 p-4 transition-all duration-300 hover:border-blue-500/50 hover:bg-blue-500/10 hover:shadow-lg cursor-pointer">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold text-white">Reporte Mensual</p>
                                <p class="text-xs text-muted mt-1">PDF con KPIs completos</p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                            </div>
                        </div>
                    </a>
                    <a href="#" class="group rounded-2xl border border-white/10 bg-white/5 p-4 transition-all duration-300 hover:border-green-500/50 hover:bg-green-500/10 hover:shadow-lg cursor-pointer">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold text-white">Reporte Ingresos</p>
                                <p class="text-xs text-muted mt-1">CSV de ventas y propinas</p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-green-500/10 text-green-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                        </div>
                    </a>
                    <a href="#" class="group rounded-2xl border border-white/10 bg-white/5 p-4 transition-all duration-300 hover:border-purple-500/50 hover:bg-purple-500/10 hover:shadow-lg cursor-pointer">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold text-white">Reporte Clientes</p>
                                <p class="text-xs text-muted mt-1">Análisis de retención y CRM</p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 0a1 1 0 11-2 0 1 1 0 012 0z" /></svg>
                            </div>
                        </div>
                    </a>
                    <a href="#" class="group rounded-2xl border border-white/10 bg-white/5 p-4 transition-all duration-300 hover:border-orange-500/50 hover:bg-orange-500/10 hover:shadow-lg cursor-pointer">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold text-white">Reporte Inventory</p>
                                <p class="text-xs text-muted mt-1">Stock y alertas de compra</p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-orange-500/10 text-orange-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.325 15.582l-3.819-5.209a2.25 2.25 0 00-3.612 0l-3.819 5.209m0 0a6 6 0 016-10.385m0 10.385l3.75 5.209a2.25 2.25 0 003.612 0l3.638-4.958" /></svg>
                            </div>
                        </div>
                    </a>
                </div>
            </section>

            <section class="ui-card-premium p-6 sm:p-8 animate-slide-up" style="animation-delay: 600ms;">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-white uppercase tracking-widest">Telemetria Chatbot</h3>
                        <p class="text-[10px] text-muted font-bold uppercase mt-1">Ventana: ultimos {{ $chatbotTelemetry['window_days'] ?? 7 }} dias</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[9px] font-black uppercase text-muted">
                        Operational
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-white/5 bg-white/5 p-4 transition-all duration-300 hover:border-blue-500/30 hover:bg-blue-500/5 hover:shadow-lg cursor-default">
                        <p class="text-[10px] font-black uppercase tracking-widest text-muted">Eventos</p>
                        <p class="mt-2 text-2xl font-black text-white">{{ $chatbotTelemetry['total_requests'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/5 bg-white/5 p-4 transition-all duration-300 hover:border-red-500/30 hover:bg-red-500/5 hover:shadow-lg cursor-default">
                        <p class="text-[10px] font-black uppercase tracking-widest text-muted">Error Rate</p>
                        <p class="mt-2 text-2xl font-black text-red-400">{{ number_format($chatbotTelemetry['error_rate_pct'] ?? 0, 2) }}%</p>
                    </div>
                    <div class="rounded-2xl border border-white/5 bg-white/5 p-4 transition-all duration-300 hover:border-blue-300/30 hover:bg-blue-300/5 hover:shadow-lg cursor-default">
                        <p class="text-[10px] font-black uppercase tracking-widest text-muted">Latencia Promedio</p>
                        <p class="mt-2 text-2xl font-black text-blue-300">{{ $chatbotTelemetry['avg_latency_ms'] ?? 0 }}ms</p>
                    </div>
                    <div class="rounded-2xl border border-white/5 bg-white/5 p-4 transition-all duration-300 hover:border-green-500/30 hover:bg-green-500/5 hover:shadow-lg cursor-default">
                        <p class="text-[10px] font-black uppercase tracking-widest text-muted">Costo Estimado</p>
                        <p class="mt-2 text-2xl font-black text-green-400">${{ number_format($chatbotTelemetry['estimated_cost_usd'] ?? 0, 4) }}</p>
                    </div>
                </div>

                <div class="mt-6 rounded-2xl border border-white/5 bg-bg-accent/40 p-4 transition-all duration-300 hover:border-gold/20">
                    <p class="text-[10px] font-black uppercase tracking-widest text-muted mb-3">Top Fuentes</p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        @forelse(($chatbotTelemetry['top_sources'] ?? []) as $source => $count)
                            <div class="rounded-xl border border-white/5 bg-white/5 px-3 py-2 flex items-center justify-between transition-all duration-300 hover:border-gold/30 hover:bg-gold/5 hover:shadow-md animate-slide-up" style="animation-delay: {{ $loop->index * 50 }}ms;">
                                <span class="text-xs font-bold text-white uppercase">{{ str_replace('_', ' ', $source) }}</span>
                                <span class="text-xs font-black text-gold">{{ $count }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-muted italic">Sin eventos de telemetria aun.</p>
                        @endforelse
                    </div>
                </div>
            </section>

        @elseif ($isBarberMode ?? false)
            <!-- ========================================== -->
            <!-- BARBER DASHBOARD                           -->
            <!-- ========================================== -->
            
            <!-- Welcome -->
            <section class="ui-card-premium p-6 sm:p-8 lg:p-10 relative overflow-hidden group">
                <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-gold/5 blur-3xl"></div>
                <div class="relative z-10 flex flex-col sm:flex-row items-center gap-8">
                    <div class="h-24 w-24 rounded-3xl bg-gradient-to-br from-gold to-gold-dim flex items-center justify-center text-black shadow-lg animate-float flex-shrink-0">
                        <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div>
                        <h3 class="ui-profile-title ui-profile-title-barber uppercase">¡Hola, Maestro <span class="text-gradient-gold">{{ explode(' ', auth()->user()->name)[0] }}</span>!</h3>
                        <p class="mt-2 text-muted max-w-xl text-lg leading-relaxed">Tu maestría define nuestro estándar. Tienes {{ $kpis['appointments_today'] }} servicios programados para hoy.</p>
                    </div>
                </div>
            </section>

            <!-- KPIs -->
            <section class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <article class="ui-kpi-card text-center border-white/5">
                    <p class="ui-kpi-label">Citas Hoy</p>
                    <p class="ui-kpi-value mt-1">{{ $kpis['appointments_today'] }}</p>
                </article>
                <article class="ui-kpi-card text-center border-white/5">
                    <p class="ui-kpi-label">Citas del Mes</p>
                    <p class="ui-kpi-value mt-1">{{ $kpis['appointments_month'] }}</p>
                </article>
                <article class="ui-kpi-card text-center border-white/5">
                    <p class="ui-kpi-label">Ingresos Mes</p>
                    <p class="ui-kpi-value mt-1 text-green-400">${{ number_format($kpis['income_month'], 2) }}</p>
                </article>
                <article class="ui-kpi-card text-center border-white/5">
                    <p class="ui-kpi-label">Rating</p>
                    <p class="ui-kpi-value mt-1 text-gold">{{ $kpis['rating'] }} <span class="text-xs">★</span></p>
                </article>
            </section>

            <!-- Charts -->
            <section class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <article class="ui-card-premium p-6 sm:p-8">
                    <h3 class="text-sm font-black text-white uppercase tracking-widest mb-6">Productividad Semanal</h3>
                    @php
                        $hasPerformanceSeries = ! empty(array_filter($performanceChart['values'] ?? []));
                    @endphp
                    @if($hasPerformanceSeries)
                        <div class="h-[280px]">
                            <canvas id="performanceChart"></canvas>
                        </div>
                    @else
                        <div class="h-[280px] ui-empty-state">
                            <p class="ui-empty-state-copy">Sin citas suficientes para mostrar productividad.</p>
                        </div>
                    @endif
                </article>
                <article class="ui-card-premium p-6 sm:p-8">
                    <h3 class="text-sm font-black text-white uppercase tracking-widest mb-6">Top de Especialidades</h3>
                    @php
                        $hasBarberServicesSeries = ! empty(array_filter($servicesChart['values'] ?? []));
                    @endphp
                    @if($hasBarberServicesSeries)
                        <div class="h-[280px]">
                            <canvas id="servicesChart"></canvas>
                        </div>
                    @else
                        <div class="h-[280px] ui-empty-state">
                            <p class="ui-empty-state-copy">Aún no hay especialidades suficientes para graficar.</p>
                        </div>
                    @endif
                </article>
            </section>

            <!-- Actions -->
            <section class="flex flex-wrap gap-4 pt-4">
                <a href="{{ route('barber.agenda') }}" class="ui-btn px-12 py-4">Gestionar Mi Agenda</a>
                <a href="{{ route('barber.profile.edit') }}" class="ui-btn-secondary px-12 py-4">Ver Mi Perfil</a>
            </section>

        @elseif ($isReceptionMode ?? false)
            <!-- ========================================== -->
            <!-- RECEPTIONIST DASHBOARD                     -->
            <!-- ========================================== -->
            
            <!-- Welcome & Highlights -->
            <section class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 ui-card-premium p-6 sm:p-8 lg:p-10 relative overflow-hidden group">
                    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-indigo-500/5 blur-3xl"></div>
                    <div class="relative z-10 flex flex-col sm:flex-row items-center gap-8">
                        <div class="h-20 w-20 rounded-3xl bg-indigo-600 flex items-center justify-center text-white shadow-lg animate-float flex-shrink-0">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21v-2a4 4 0 00-4-4H9a4 4 0 00-4 4v2" /><circle cx="12" cy="7" r="4" /></svg>
                        </div>
                        <div>
                            <h3 class="ui-profile-title ui-profile-title-reception uppercase">¡Hola, {{ explode(' ', auth()->user()->name)[0] }}!</h3>
                            <p class="mt-2 ui-profile-subtitle">Centro de mando activo. Gestiona el flujo de excelencia hoy.</p>
                        </div>
                    </div>
                </div>
                <div class="ui-card-premium p-6 sm:p-8 lg:p-10 flex flex-col justify-center text-center">
                    <p class="text-[10px] font-black uppercase text-gold tracking-widest mb-2">Cobros Pendientes</p>
                    <p class="text-4xl sm:text-5xl font-black text-white">{{ $kpis['pending_payments'] }}</p>
                    <a href="{{ route('payments.create') }}" class="mt-4 text-[9px] font-black text-gold hover:text-white transition uppercase tracking-widest">Resolver Ahora &rarr;</a>
                </div>
            </section>

            <!-- KPIs -->
            <section class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <article class="ui-kpi-card border-l-4 border-indigo-500">
                    <p class="ui-kpi-label">Citas Hoy</p>
                    <p class="ui-kpi-value mt-1">{{ $kpis['appointments_today'] }}</p>
                </article>
                <article class="ui-kpi-card border-l-4 border-green-500">
                    <p class="ui-kpi-label">Nuevos Clientes</p>
                    <p class="ui-kpi-value mt-1 text-green-400">{{ $kpis['new_clients_today'] }}</p>
                </article>
                <article class="ui-kpi-card border-l-4 border-red-500">
                    <p class="ui-kpi-label">Suministros Críticos</p>
                    <p class="ui-kpi-value mt-1 text-red-400">{{ $kpis['low_stock_count'] }}</p>
                </article>
            </section>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Next Appointments -->
                <section class="lg:col-span-7 ui-card-premium p-6 sm:p-8">
                    <div class="mb-8 flex items-center justify-between">
                        <h3 class="text-sm font-black text-white uppercase tracking-widest">Próximas Llegadas</h3>
                        <a href="{{ route('appointments.index') }}" class="text-[9px] font-black text-muted hover:text-gold transition uppercase tracking-widest">Agenda Full &rarr;</a>
                    </div>
                    <div class="space-y-4">
                        @forelse($nextAppointments as $appt)
                            <div class="flex items-center justify-between p-4 rounded-2xl bg-white/5 border border-white/5 hover:border-indigo-500/30 transition-all">
                                <div class="flex items-center gap-4">
                                    <div class="h-10 w-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-400 font-black text-xs">{{ substr($appt->hora_inicio, 0, 2) }}</div>
                                    <div>
                                        <p class="text-sm font-black text-white uppercase">{{ $appt->client?->user?->name }}</p>
                                        <p class="text-[9px] uppercase font-bold text-muted">{{ $appt->service?->nombre }} • Con {{ $appt->barber?->user?->name }}</p>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-xs font-black text-white">{{ substr($appt->hora_inicio, 0, 5) }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-center py-10 text-muted italic text-sm border border-dashed border-white/5 rounded-2xl">Sin llegadas próximas.</p>
                        @endforelse
                    </div>
                </section>

                <!-- Flow Chart -->
                <section class="lg:col-span-5 ui-card-premium p-6 sm:p-8">
                    <h3 class="text-sm font-black text-white uppercase tracking-widest mb-8">Flujo Operativo (Horas)</h3>
                    @php
                        $hasFlowSeries = ! empty(array_filter($flow_chart['values'] ?? []));
                    @endphp
                    @if($hasFlowSeries)
                        <div class="h-[300px]">
                            <canvas id="flowChart"></canvas>
                        </div>
                    @else
                        <div class="h-[300px] ui-empty-state">
                            <p class="ui-empty-state-copy">Todavía no hay flujo operativo para este día.</p>
                        </div>
                    @endif
                </section>
            </div>

            <!-- Global Actions -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-4">
                <a href="{{ route('appointments.create') }}" class="ui-btn py-4">Nueva Cita</a>
                <a href="{{ route('clients.create') }}" class="ui-btn-secondary py-4">Alta Cliente</a>
                <a href="{{ route('payments.create') }}" class="ui-btn-secondary py-4">Cobrar Servicio</a>
            </section>

        @elseif ($isClientMode ?? false)
            <!-- ========================================== -->
            <!-- CUSTOMER DASHBOARD                         -->
            <!-- ========================================== -->
            
            <!-- Welcome Elite -->
            <section class="ui-card-premium p-6 sm:p-8 lg:p-10 relative overflow-hidden group">
                <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-gold/5 blur-3xl group-hover:bg-gold/10 transition-all duration-700"></div>
                <div class="relative z-10 flex flex-col sm:flex-row items-center gap-8">
                    <div class="h-24 w-24 rounded-3xl bg-gradient-to-br from-gold to-gold-dim flex items-center justify-center text-black shadow-lg animate-float flex-shrink-0">
                        <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div>
                        <h3 class="ui-profile-title ui-profile-title-client uppercase leading-none">¡Bienvenido, <span class="text-gradient-gold">{{ explode(' ', auth()->user()->name)[0] }}</span>!</h3>
                        <p class="mt-2 ui-profile-subtitle max-w-xl">Tu estilo es nuestra prioridad. Hoy tienes el estatus de <span class="text-white font-black uppercase">{{ $kpis['membership_status'] }}</span> en BarberPro.</p>
                    </div>
                </div>
            </section>

            <!-- Style Metrics -->
            <section class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <article class="ui-kpi-card text-center group hover:border-gold/30 transition-all">
                    <p class="text-[10px] font-black uppercase text-gold tracking-widest mb-2">Visitas Totales</p>
                    <p class="text-4xl font-black text-white">{{ $kpis['total_appointments'] }}</p>
                </article>
                <article class="ui-kpi-card text-center group hover:border-gold/30 transition-all">
                    <p class="text-[10px] font-black uppercase text-gold tracking-widest mb-2">Estatus Elite</p>
                    <p class="text-2xl font-black text-white uppercase">{{ $kpis['membership_status'] }}</p>
                </article>
                <article class="ui-kpi-card text-center group hover:border-gold/30 transition-all">
                    <p class="text-[10px] font-black uppercase text-gold tracking-widest mb-2">Experto Favorito</p>
                    <p class="text-base font-black text-white truncate px-2">{{ $kpis['favorite_barber'] }}</p>
                </article>
                <article class="ui-kpi-card text-center group hover:border-gold/30 transition-all">
                    <p class="text-[10px] font-black uppercase text-gold tracking-widest mb-2">Puntos Acumulados</p>
                    <p class="text-4xl font-black text-white">{{ $kpis['completed_appointments'] * 10 }}</p>
                </article>
            </section>

            <!-- Spotlight: Next Appointment -->
            @if($nextAppointment)
                <section class="ui-card-premium p-0 overflow-hidden border-gold/30 gold-glow">
                    <div class="flex flex-col md:flex-row">
                        <div class="bg-gradient-to-br from-gold to-gold-dim p-8 md:w-72 flex flex-col justify-center items-center text-black">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-2 opacity-70">Tu Cita de Oro</p>
                            <p class="text-4xl font-black">{{ \Carbon\Carbon::parse($nextAppointment->fecha)->format('d M') }}</p>
                            <p class="text-xl font-bold mt-1">{{ substr($nextAppointment->hora_inicio, 0, 5) }}</p>
                        </div>
                        <div class="p-6 sm:p-8 lg:p-10 flex-1 flex flex-col sm:flex-row justify-between items-center gap-8 bg-white/5 backdrop-blur-xl">
                            <div>
                                <h3 class="text-2xl font-black text-white uppercase tracking-tight">{{ $nextAppointment->service?->nombre }}</h3>
                                <p class="text-gold font-bold uppercase tracking-widest text-xs mt-1">Con el Maestro {{ $nextAppointment->barber?->user?->name }}</p>
                            </div>
                            <a href="{{ route('client.appointments.index') }}" class="ui-btn px-12 py-4">Ver Detalles</a>
                        </div>
                    </div>
                </section>
            @endif

            <!-- Progress & Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <section class="lg:col-span-7 ui-card-premium p-6 sm:p-8">
                    <h3 class="text-sm font-black text-white uppercase tracking-widest mb-8">Frecuencia de Cuidado Personal</h3>
                    @php
                        $hasVisitSeries = ! empty(array_filter($visit_chart['values'] ?? []));
                    @endphp
                    @if($hasVisitSeries)
                        <div class="h-[280px]">
                            <canvas id="visitChart"></canvas>
                        </div>
                    @else
                        <div class="h-[280px] ui-empty-state">
                            <p class="ui-empty-state-copy">Aún no hay historial suficiente para graficar visitas.</p>
                        </div>
                    @endif
                </section>
                
                <section class="lg:col-span-5 ui-card-premium p-8 flex flex-col items-center justify-center relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gold/5 scale-0 group-hover:scale-150 transition-transform duration-1000 rounded-full"></div>
                    <div class="text-center relative z-10 w-full">
                        <p class="text-[10px] font-black text-gold uppercase tracking-[0.3em] mb-6">Camino a la Leyenda</p>
                        @php
                            $target = $kpis['completed_appointments'] < 5 ? 5 : 10;
                            $needed = max(0, $target - $kpis['completed_appointments']);
                            $progress = min(100, ($kpis['completed_appointments'] / $target) * 100);
                        @endphp
                        <p class="text-lg font-bold text-white mb-4 leading-tight">Faltan <span class="text-3xl font-black text-gradient-gold px-1">{{ $needed }}</span> citas <br> para ser {{ $target == 5 ? 'V.I.P' : 'Leyenda' }}</p>
                        <div class="w-full h-1.5 bg-white/5 rounded-full overflow-hidden border border-white/10">
                            <div class="h-full bg-gold shadow-[0_0_10px_rgba(212,175,55,0.5)] transition-all duration-1000" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Quick Access -->
            <section class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4">
                <a href="{{ route('client.appointments.create') }}" class="ui-card-premium p-8 flex items-center justify-between group hover:border-gold/50">
                    <div><h4 class="text-xl font-black text-white uppercase">Agendar Cita</h4><p class="text-xs text-muted mt-1 uppercase tracking-widest">Elige tu ritual</p></div>
                    <div class="h-12 w-12 rounded-2xl bg-gold/10 text-gold flex items-center justify-center group-hover:bg-gold group-hover:text-black transition-all"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></div>
                </a>
                <a href="{{ route('client.appointments.index') }}" class="ui-card-premium p-8 flex items-center justify-between group hover:border-white/30">
                    <div><h4 class="text-xl font-black text-white uppercase">Mis Citas</h4><p class="text-xs text-muted mt-1 uppercase tracking-widest">Historial Premium</p></div>
                    <div class="h-12 w-12 rounded-2xl bg-white/10 text-white flex items-center justify-center group-hover:bg-white group-hover:text-black transition-all"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                </a>
            </section>
        @else
            <section class="ui-card-premium p-8">
                <h3 class="text-lg font-black text-white uppercase tracking-widest">Panel no disponible</h3>
                <p class="mt-2 text-sm text-muted">
                    Tu perfil no tiene un modo de dashboard configurado todavia. Contacta a un administrador para completar la configuracion de rol/perfil.
                </p>
            </section>
        @endif
    </div>

    @if(($adminMode ?? false) || ($isBarberMode ?? false) || ($isReceptionMode ?? false) || ($isClientMode ?? false))
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            Chart.defaults.font.family = "'Figtree', sans-serif";
            Chart.defaults.color = '#737373';
            Chart.defaults.font.weight = 'bold';
            
            const chartScale = {
                ticks: { color: '#737373', font: { size: 10 } },
                grid: { color: 'rgba(255,255,255,0.03)', drawBorder: false }
            };

            @if($adminMode ?? false)
                const incomeCtx = document.getElementById('incomeChart');
                if (incomeCtx) {
                    new Chart(incomeCtx, {
                        type: 'line',
                        data: {
                            labels: @json($incomeChart['labels'] ?? []),
                            datasets: [{
                                label: 'Ingresos ($)',
                                data: @json($incomeChart['values'] ?? []),
                                borderColor: '#d4af37',
                                backgroundColor: 'rgba(212, 175, 55, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 4,
                                pointBackgroundColor: '#000',
                                pointBorderColor: '#d4af37'
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: chartScale, x: chartScale }
                        }
                    });
                }

                // Chatbot telemetry trend charts
                // (Preparado para UI/UX - datos calculados en backend en $chatbotTelemetry['trend_chart'])
            @endif

            @if($isBarberMode ?? false)
                const perfCtx = document.getElementById('performanceChart');
                if (perfCtx) {
                    new Chart(perfCtx, {
                        type: 'bar',
                        data: {
                            labels: @json($performanceChart['labels'] ?? []),
                            datasets: [{
                                label: 'Citas',
                                data: @json($performanceChart['values'] ?? []),
                                backgroundColor: '#d4af37',
                                borderRadius: 8,
                                barThickness: 20
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: chartScale, x: chartScale }
                        }
                    });
                }
            @endif

            @if($isReceptionMode ?? false)
                const flowCtx = document.getElementById('flowChart');
                if (flowCtx) {
                    new Chart(flowCtx, {
                        type: 'line',
                        data: {
                            labels: @json($flow_chart['labels'] ?? []),
                            datasets: [{
                                label: 'Citas',
                                data: @json($flow_chart['values'] ?? []),
                                borderColor: '#6366f1',
                                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 4,
                                pointBackgroundColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { ...chartScale, beginAtZero: true, ticks: { ...chartScale.ticks, stepSize: 1 } }, x: chartScale }
                        }
                    });
                }
            @endif

            @if($isClientMode ?? false)
                const visitCtx = document.getElementById('visitChart');
                if (visitCtx) {
                    new Chart(visitCtx, {
                        type: 'line',
                        data: {
                            labels: @json($visit_chart['labels'] ?? []),
                            datasets: [{
                                label: 'Visitas',
                                data: @json($visit_chart['values'] ?? []),
                                borderColor: '#d4af37',
                                backgroundColor: 'rgba(212, 175, 55, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 5,
                                pointBackgroundColor: '#d4af37'
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { ...chartScale, beginAtZero: true, ticks: { ...chartScale.ticks, stepSize: 1 } }, x: chartScale }
                        }
                    });
                }
            @endif

            const servicesCtx = document.getElementById('servicesChart');
            if (servicesCtx) {
                new Chart(servicesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: @json($servicesChart['labels'] ?? []),
                        datasets: [{
                            data: @json($servicesChart['values'] ?? []),
                            backgroundColor: ['#d4af37', '#ffffff', '#444444', '#888888', '#222222'],
                            borderWidth: 0,
                            hoverOffset: 15
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        cutout: '80%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: '#b0b0b0', usePointStyle: true, padding: 20, font: { size: 11, weight: 'bold' } }
                            }
                        }
                    }
                });
            }

            @if($adminMode ?? false)
                // Barber Performance Chart
                const barberPerfCtx = document.getElementById('barberPerformanceChart');
                if (barberPerfCtx) {
                    new Chart(barberPerfCtx, {
                        type: 'bar',
                        data: {
                            labels: @json($barberPerformance['labels'] ?? []),
                            datasets: [
                                {
                                    label: 'Citas Completadas',
                                    data: @json($barberPerformance['appointments'] ?? []),
                                    backgroundColor: '#3b82f6',
                                    borderRadius: 8,
                                    barThickness: 30,
                                    order: 2
                                },
                                {
                                    label: 'Ingresos ($)',
                                    data: @json($barberPerformance['revenue'] ?? []),
                                    backgroundColor: '#10b981',
                                    borderRadius: 8,
                                    barThickness: 30,
                                    order: 2
                                }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { 
                                legend: { 
                                    display: true,
                                    labels: { color: '#b0b0b0', usePointStyle: true, padding: 15, font: { size: 11, weight: 'bold' } }
                                } 
                            },
                            scales: { 
                                y: chartScale, 
                                x: chartScale 
                            }
                        }
                    });
                }

                // Client Trends Chart
                const clientTrendsCtx = document.getElementById('clientTrendsChart');
                if (clientTrendsCtx) {
                    new Chart(clientTrendsCtx, {
                        type: 'line',
                        data: {
                            labels: @json($clientTrends['labels'] ?? []),
                            datasets: [{
                                label: 'Citas Completadas',
                                data: @json($clientTrends['values'] ?? []),
                                borderColor: '#a78bfa',
                                backgroundColor: 'rgba(167, 139, 250, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 5,
                                pointBackgroundColor: '#a78bfa',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { 
                                y: { ...chartScale, beginAtZero: true, ticks: { ...chartScale.ticks, stepSize: Math.ceil(Math.max(...(@json($clientTrends['values'] ?? []) || [0])) / 5) || 1 } }, 
                                x: chartScale 
                            }
                        }
                    });
                }
            @endif

            @if($adminMode ?? false)
                // Load API Token for Dashboard
                let apiToken = null;

                async function getApiToken() {
                    try {
                        const response = await fetch('/api/v1/auth/get-api-token', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            }
                        });

                        if (response.ok) {
                            const data = await response.json();
                            apiToken = data.token;
                            return true;
                        }
                    } catch (error) {
                        console.error('Error getting API token:', error);
                    }
                    return false;
                }

                // Load AI Predictions
                async function loadPredictions() {
                    // Get API token first if we don't have one
                    if (!apiToken) {
                        const hasToken = await getApiToken();
                        if (!hasToken) {
                            console.error('Failed to obtain API token');
                            document.getElementById('income-forecast').textContent = 'Error';
                            document.getElementById('appointment-forecast').textContent = 'Error';
                            return;
                        }
                    }

                    try {
                        const headers = {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${apiToken}`,
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        };

                        const [incomeRes, appointmentsRes, insightsRes] = await Promise.all([
                            fetch('/api/v1/admin/predictions/income/7', { headers }),
                            fetch('/api/v1/admin/predictions/appointments/7', { headers }),
                            fetch('/api/v1/admin/predictions/insights', { headers })
                        ]);

                        if (incomeRes.ok) {
                            const incomeData = await incomeRes.json();
                            document.getElementById('income-forecast').textContent = 
                                incomeData.data?.predicted_income ? `$${incomeData.data.predicted_income.toFixed(2)}` : 'N/A';
                        }

                        if (appointmentsRes.ok) {
                            const appointmentData = await appointmentsRes.json();
                            document.getElementById('appointment-forecast').textContent = 
                                appointmentData.data?.predicted_appointments ?? 'N/A';
                        }

                        if (insightsRes.ok) {
                            const insightsData = await insightsRes.json();
                            const insights = insightsData.data || {};
                            
                            let insightsHtml = '';
                            Object.entries(insights).forEach(([key, insight]) => {
                                const statusColors = {
                                    positive: 'border-green-500/30 bg-green-500/5',
                                    warning: 'border-yellow-500/30 bg-yellow-500/5',
                                    neutral: 'border-blue-500/30 bg-blue-500/5'
                                };
                                const color = statusColors[insight.status] || statusColors.neutral;
                                
                                insightsHtml += `
                                    <div class="flex items-start gap-3 p-4 rounded-lg border ${color} animate-slide-up">
                                        <div class="h-2 w-2 rounded-full ${insight.status === 'positive' ? 'bg-green-400' : insight.status === 'warning' ? 'bg-yellow-400' : 'bg-blue-400'} mt-1.5 flex-shrink-0"></div>
                                        <div>
                                            <p class="text-xs font-bold text-white">${insight.message}</p>
                                            ${insight.avg_daily ? `<p class="text-[10px] text-muted mt-1">Promedio: ${insight.avg_daily}</p>` : ''}
                                        </div>
                                    </div>
                                `;
                            });
                            
                            document.getElementById('ai-insights').innerHTML = insightsHtml || '<p class="text-xs text-muted">Sin insights disponibles.</p>';
                        }

                        // Update confidence (mock for now)
                        document.getElementById('ai-confidence').textContent = '72%';
                    } catch (error) {
                        console.error('Error loading predictions:', error);
                        document.getElementById('income-forecast').textContent = 'Error';
                        document.getElementById('appointment-forecast').textContent = 'Error';
                    }
                }

                // Load predictions when page loads
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', loadPredictions);
                } else {
                    loadPredictions();
                }
            @endif
        </script>
    @endif
</x-app-layout>
