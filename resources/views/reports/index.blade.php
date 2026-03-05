<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Reportes</h2>
            <span class="ui-badge">Exportacion</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <section class="ui-surface">
                <h3 class="mb-4 text-base font-semibold text-[#0d0d0d]">Filtros globales</h3>
                <form method="GET" action="{{ route('reports.index') }}" class="ui-form-grid">
                    <div>
                        <label class="ui-label">Fecha inicio</label>
                        <input type="date" name="start_date" value="{{ request('start_date') }}" class="ui-input">
                    </div>
                    <div>
                        <label class="ui-label">Fecha fin</label>
                        <input type="date" name="end_date" value="{{ request('end_date') }}" class="ui-input">
                    </div>
                    <div>
                        <label class="ui-label">Barbero</label>
                        <select name="barber_id" class="ui-input">
                            <option value="">Todos</option>
                            @foreach($barbers as $barber)
                                <option value="{{ $barber->id }}" @selected(request('barber_id') == $barber->id)>{{ $barber->user?->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="ui-label">Metodo pago</label>
                        <select name="metodo_pago" class="ui-input">
                            <option value="">Todos</option>
                            @foreach(['efectivo','tarjeta','transferencia','qr'] as $metodo)
                                <option value="{{ $metodo }}" @selected(request('metodo_pago') === $metodo)>{{ ucfirst($metodo) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="ui-btn">Aplicar filtros</button>
                    </div>
                </form>
            </section>

            @php
                $filterQuery = request()->only(['start_date', 'end_date', 'barber_id', 'metodo_pago', 'estado', 'categoria', 'tipo']);
            @endphp

            <section class="ui-surface">
                <h3 class="mb-3 text-base font-semibold text-[#0d0d0d]">Exportar reportes</h3>
                <div class="ui-list">
                    <div class="ui-list-item">
                        <div class="ui-list-item-head">
                            <h4 class="text-sm font-semibold text-[#1f1f1f]">Ingresos</h4>
                            <div class="ui-toolbar-group">
                                <a class="ui-btn-secondary" href="{{ route('reports.export', ['type' => 'ingresos', 'format' => 'pdf'] + $filterQuery) }}">PDF</a>
                                <a class="ui-btn" href="{{ route('reports.export', ['type' => 'ingresos', 'format' => 'excel'] + $filterQuery) }}">Excel</a>
                            </div>
                        </div>
                    </div>
                    <div class="ui-list-item">
                        <div class="ui-list-item-head">
                            <h4 class="text-sm font-semibold text-[#1f1f1f]">Citas</h4>
                            <div class="ui-toolbar-group">
                                <a class="ui-btn-secondary" href="{{ route('reports.export', ['type' => 'citas', 'format' => 'pdf'] + $filterQuery) }}">PDF</a>
                                <a class="ui-btn" href="{{ route('reports.export', ['type' => 'citas', 'format' => 'excel'] + $filterQuery) }}">Excel</a>
                            </div>
                        </div>
                    </div>
                    <div class="ui-list-item">
                        <div class="ui-list-item-head">
                            <h4 class="text-sm font-semibold text-[#1f1f1f]">Inventario</h4>
                            <div class="ui-toolbar-group">
                                <a class="ui-btn-secondary" href="{{ route('reports.export', ['type' => 'inventario', 'format' => 'pdf'] + $filterQuery) }}">PDF</a>
                                <a class="ui-btn" href="{{ route('reports.export', ['type' => 'inventario', 'format' => 'excel'] + $filterQuery) }}">Excel</a>
                            </div>
                        </div>
                    </div>
                    <div class="ui-list-item">
                        <div class="ui-list-item-head">
                            <h4 class="text-sm font-semibold text-[#1f1f1f]">Clientes</h4>
                            <div class="ui-toolbar-group">
                                <a class="ui-btn-secondary" href="{{ route('reports.export', ['type' => 'clientes', 'format' => 'pdf'] + $filterQuery) }}">PDF</a>
                                <a class="ui-btn" href="{{ route('reports.export', ['type' => 'clientes', 'format' => 'excel'] + $filterQuery) }}">Excel</a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
