<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="ui-title">Dashboard de Barberia</h2>
                <p class="ui-subtitle">Control diario de citas, ingresos y operacion en una vista ejecutiva.</p>
            </div>
            <span class="ui-badge">Actualizado hoy</span>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (!($adminMode ?? false))
            @php
                $authUser = auth()->user();
                $isReception = $authUser?->hasRole('recepcionista');
                $isBarber = $authUser?->hasRole('barbero');
                $isClient = $authUser?->hasRole('cliente');
            @endphp

            <section class="ui-card-premium p-6">
                <h3 class="text-lg font-semibold text-[#0d0d0d]">Panel por perfil</h3>
                <p class="mt-1 text-sm text-[#6d6d6d]">Accesos rapidos disponibles segun tu rol actual.</p>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if($isReception)
                        <a href="{{ route('clients.index') }}" class="ui-btn-secondary">Clientes</a>
                        <a href="{{ route('appointments.index') }}" class="ui-btn">Citas</a>
                        <a href="{{ route('payments.index') }}" class="ui-btn-secondary">Pagos</a>
                        <a href="{{ route('inventory.movements.index') }}" class="ui-btn-secondary">Movimientos</a>
                    @endif

                    @if($isBarber)
                        <a href="{{ route('barber.agenda') }}" class="ui-btn">Mi agenda</a>
                        <a href="{{ route('barber.profile.edit') }}" class="ui-btn-secondary">Mi perfil</a>
                    @endif

                    @if($isClient)
                        <a href="{{ route('client.appointments.index') }}" class="ui-btn">Mis citas</a>
                        <a href="{{ route('client.appointments.create') }}" class="ui-btn-secondary">Agendar cita</a>
                    @endif
                </div>
            </section>
        @else
            <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="ui-kpi-card">
                    <div class="mb-3 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#c9c9c9] bg-[#ececec]">
                        <svg class="h-4 w-4 text-[#2a2a2a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
                    </div>
                    <p class="ui-kpi-label">Citas hoy</p>
                    <p class="ui-kpi-value mt-2">{{ $kpis['appointments_today'] }}</p>
                    <p class="mt-1 text-xs text-[#6d6d6d]">Semana: {{ $kpis['appointments_week'] }} | Mes: {{ $kpis['appointments_month'] }}</p>
                </article>
                <article class="ui-kpi-card">
                    <div class="mb-3 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#c9c9c9] bg-[#ececec]">
                        <svg class="h-4 w-4 text-[#2a2a2a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2.5" y="6" width="19" height="12" rx="2"/><path d="M2.5 10h19M7 14h3"/></svg>
                    </div>
                    <p class="ui-kpi-label">Ingresos del dia</p>
                    <p class="ui-kpi-value mt-2">${{ number_format($kpis['income_today'], 2) }}</p>
                    <p class="mt-1 text-xs text-[#6d6d6d]">Semana: ${{ number_format($kpis['income_week'], 2) }} | Mes: ${{ number_format($kpis['income_month'], 2) }}</p>
                </article>
                <article class="ui-kpi-card">
                    <div class="mb-3 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#c9c9c9] bg-[#ececec]">
                        <svg class="h-4 w-4 text-[#2a2a2a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="3"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>
                    </div>
                    <p class="ui-kpi-label">Clientes nuevos</p>
                    <p class="ui-kpi-value mt-2">{{ $kpis['new_clients'] }}</p>
                    <p class="mt-1 text-xs text-[#6d6d6d]">Recurrentes: {{ $kpis['recurring_clients'] }}</p>
                </article>
                <article class="ui-kpi-card">
                    <div class="mb-3 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#c9c9c9] bg-[#ececec]">
                        <svg class="h-4 w-4 text-[#2a2a2a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 20h16M7 16V8M12 16V4M17 16v-6"/></svg>
                    </div>
                    <p class="ui-kpi-label">Servicios mas reservados</p>
                    <p class="mt-2 text-xl font-semibold text-[#0d0d0d]">{{ $kpis['top_barber_name'] ?? 'Sin datos' }}</p>
                    <p class="mt-1 text-xs text-[#6d6d6d]">Stock bajo: {{ $kpis['low_stock_count'] }}</p>
                </article>
            </section>

            <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <article class="ui-card-premium p-5">
                    <h3 class="text-base font-semibold text-[#0d0d0d]">Ingresos por semana</h3>
                    <p class="mt-1 text-xs text-[#7a7a7a]">Grafico comparativo con color para lectura rapida.</p>
                    <div class="mt-4">
                        <canvas id="incomeChart" height="130"></canvas>
                    </div>
                </article>
                <article class="ui-card-premium p-5">
                    <h3 class="text-base font-semibold text-[#0d0d0d]">Servicios mas reservados</h3>
                    <p class="mt-1 text-xs text-[#7a7a7a]">Distribucion visual por categoria de servicio.</p>
                    <div class="mt-4">
                        <canvas id="servicesChart" height="130"></canvas>
                    </div>
                </article>
            </section>

            @php
                $today = \Illuminate\Support\Carbon::today();
                $start = $today->copy()->startOfMonth()->startOfWeek(\Illuminate\Support\Carbon::MONDAY);
                $end = $today->copy()->endOfMonth()->endOfWeek(\Illuminate\Support\Carbon::SUNDAY);
                $days = [];
                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $days[] = $date->copy();
                }
                $weekDays = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'];
            @endphp

            <section class="ui-card-premium p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-[#0d0d0d]">Calendario de citas</h3>
                        <p class="text-xs text-[#7a7a7a]">{{ $today->translatedFormat('F Y') }}</p>
                    </div>
                    <a href="{{ route('appointments.index') }}" class="ui-btn-secondary">Ver agenda</a>
                </div>

                <div class="grid grid-cols-7 gap-2 text-center text-xs text-[#6f6f6f]">
                    @foreach($weekDays as $label)
                        <div class="rounded-md border border-[#cfcfcf] bg-[#ebebeb] py-2 font-medium">{{ $label }}</div>
                    @endforeach
                </div>

                <div class="mt-2 grid grid-cols-7 gap-2">
                    @foreach($days as $date)
                        @php
                            $isCurrentMonth = $date->month === $today->month;
                            $isToday = $date->isSameDay($today);
                        @endphp
                        <div class="min-h-20 rounded-md border px-2 py-2 text-xs {{ $isCurrentMonth ? 'border-[#bfbfbf] bg-[#f6f6f6]' : 'border-[#d7d7d7] bg-[#eeeeee] text-[#9a9a9a]' }}">
                            <div class="font-medium {{ $isToday ? 'text-[#0d0d0d]' : '' }}">
                                {{ $date->day }}
                            </div>
                            <div class="mt-2 space-y-1">
                                @if($isToday)
                                    <div class="rounded bg-[#404040] px-1.5 py-0.5 text-[10px] text-[#f2f2f2]">Hoy</div>
                                @elseif($isCurrentMonth && in_array($date->dayOfWeekIso, [2,4,6], true))
                                    <div class="rounded border border-[#c8c8c8] px-1.5 py-0.5 text-[10px] text-[#666]">Disponible</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="pb-2">
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('appointments.create') }}" class="ui-btn">Nueva cita</a>
                    <a href="{{ route('payments.create') }}" class="ui-btn-secondary">Registrar pago</a>
                    <a href="{{ route('reports.index') }}" class="ui-btn-secondary">Reportes exportables</a>
                </div>
            </section>
        @endif
    </div>

    @if(($adminMode ?? false))
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const chartScale = {
                ticks: { color: '#5f5f5f', font: { size: 11 } },
                grid: { color: '#d4d4d4' }
            };

            const incomeCtx = document.getElementById('incomeChart');
            const servicesCtx = document.getElementById('servicesChart');

            if (incomeCtx) {
                new Chart(incomeCtx, {
                    type: 'bar',
                    data: {
                        labels: @json($incomeChart['labels'] ?? []),
                        datasets: [{
                            label: 'Ingresos',
                            data: @json($incomeChart['values'] ?? []),
                            backgroundColor: ['#2563eb', '#3b82f6', '#60a5fa', '#0ea5e9', '#0284c7', '#14b8a6', '#10b981'],
                            borderColor: '#1e3a8a',
                            borderWidth: 1,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        plugins: { legend: { labels: { color: '#404040' } } },
                        scales: { y: chartScale, x: chartScale }
                    }
                });
            }

            if (servicesCtx) {
                new Chart(servicesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: @json($servicesChart['labels'] ?? []),
                        datasets: [{
                            label: 'Servicios',
                            data: @json($servicesChart['values'] ?? []),
                            backgroundColor: ['#f59e0b', '#ef4444', '#8b5cf6', '#22c55e', '#06b6d4', '#3b82f6', '#ec4899'],
                            borderColor: '#f2f2f2',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        plugins: { legend: { labels: { color: '#404040' } } }
                    }
                });
            }
        </script>
    @endif
</x-app-layout>
