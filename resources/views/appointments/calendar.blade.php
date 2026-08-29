<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Calendario de <span class="text-gold">Citas</span></h2>
                <p class="ui-subtitle">Vista mensual, semanal y diaria de la agenda.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('appointments.index') }}" class="ui-btn-secondary px-5 text-[11px] tracking-widest">
                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    Vista Lista
                </a>
                <a href="{{ route('appointments.create') }}" class="ui-btn">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nueva Cita
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">

        {{-- Leyenda + Filtro de barbero --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            {{-- Leyenda de colores --}}
            <div class="flex flex-wrap gap-3">
                @foreach([
                    ['color'=>'#d97706','label'=>'Pendiente'],
                    ['color'=>'#3b82f6','label'=>'Confirmada'],
                    ['color'=>'#06b6d4','label'=>'En Proceso'],
                    ['color'=>'#10b981','label'=>'Completada'],
                    ['color'=>'#ef4444','label'=>'Cancelada'],
                    ['color'=>'#6b7280','label'=>'No Asistió'],
                ] as $leg)
                    <span class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-muted">
                        <span class="h-2.5 w-2.5 rounded-full" style="background:{{ $leg['color'] }}"></span>
                        {{ $leg['label'] }}
                    </span>
                @endforeach
            </div>

            {{-- Filtro barbero --}}
            <div class="flex items-center gap-3">
                <label class="text-[10px] font-black uppercase tracking-widest text-muted">Barbero:</label>
                <select id="barber-filter" class="ui-input py-2 text-sm min-w-[180px]">
                    <option value="">Todos los barberos</option>
                    @foreach($barbers as $b)
                        <option value="{{ $b->id }}">{{ $b->user?->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Calendario --}}
        <div class="ui-card-premium p-4 sm:p-6">
            <div id="calendar"></div>
        </div>

        {{-- Modal detalle de cita --}}
        <div id="event-modal"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm"
             style="display:none !important;">
            <div class="relative w-full max-w-md rounded-3xl border border-ink/10 bg-card p-8 shadow-[0_30px_80px_rgba(0,0,0,0.7)]">
                <button id="modal-close" aria-label="Cerrar" class="absolute top-4 right-4 text-muted hover:text-ink transition">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-gold/30 to-transparent rounded-t-3xl"></div>

                <div id="modal-status-dot" class="h-3 w-3 rounded-full mb-4"></div>
                <h3 id="modal-title" class="text-xl font-black text-ink uppercase tracking-tight mb-1"></h3>
                <p id="modal-time" class="text-[10px] font-bold text-muted uppercase tracking-widest mb-6"></p>

                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-lg bg-gold/10 text-gold flex items-center justify-center shrink-0">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <div>
                            <p class="text-[9px] text-muted uppercase font-bold tracking-widest">Cliente</p>
                            <p id="modal-cliente" class="text-sm font-bold text-ink"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-lg bg-gold/10 text-gold flex items-center justify-center shrink-0">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 9a3 3 0 100-6 3 3 0 000 6zm0 12a3 3 0 100-6 3 3 0 000 6zm14-15L8.5 15M9 6l11 12"/></svg>
                        </div>
                        <div>
                            <p class="text-[9px] text-muted uppercase font-bold tracking-widest">Servicio</p>
                            <p id="modal-servicio" class="text-sm font-bold text-ink"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-lg bg-gold/10 text-gold flex items-center justify-center shrink-0">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-[9px] text-muted uppercase font-bold tracking-widest">Barbero</p>
                            <p id="modal-barbero" class="text-sm font-bold text-ink"></p>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex gap-3">
                    <a id="modal-edit-btn" href="#"
                       class="flex-1 ui-btn py-3 text-[11px] tracking-widest justify-center">
                        Editar Cita
                    </a>
                    <button id="modal-close-btn"
                            class="px-5 py-3 rounded-xl border border-ink/10 bg-ink/5 text-[11px] font-black uppercase tracking-widest text-muted hover:text-ink transition-all">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- FullCalendar --}}
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales/es.global.min.js'></script>

    <style>
        /* FullCalendar dark theme overrides */
        :root {
            --fc-border-color: rgba(255,255,255,0.06);
            --fc-button-bg-color: #1a1a1a;
            --fc-button-border-color: rgba(255,255,255,0.1);
            --fc-button-text-color: #b0b0b0;
            --fc-button-hover-bg-color: #d4af37;
            --fc-button-hover-border-color: #d4af37;
            --fc-button-hover-text-color: #000;
            --fc-button-active-bg-color: #d4af37;
            --fc-button-active-border-color: #d4af37;
            --fc-button-active-text-color: #000;
            --fc-today-bg-color: rgba(212,175,55,0.06);
            --fc-page-bg-color: transparent;
            --fc-neutral-bg-color: rgba(255,255,255,0.02);
            --fc-list-event-hover-bg-color: rgba(212,175,55,0.08);
        }
        .fc { color: #b0b0b0; font-family: 'Figtree', sans-serif; }
        .fc-col-header-cell { background: rgba(255,255,255,0.02); }
        .fc-col-header-cell-cushion,
        .fc-daygrid-day-number,
        .fc-list-event-title { color: #b0b0b0 !important; text-decoration: none !important; }
        .fc-toolbar-title { font-weight: 900 !important; font-size: 1.1rem !important; text-transform: uppercase; letter-spacing: .1em; color: #fff; }
        .fc-event { cursor: pointer; border-radius: 6px; font-size: 11px; font-weight: 700; padding: 1px 4px; border: none !important; }
        .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { color: #d4af37 !important; font-weight: 900; }
        .fc-button { border-radius: 10px !important; font-size: 11px !important; font-weight: 900 !important; text-transform: uppercase; letter-spacing: .1em; transition: all .2s !important; }
        .fc-h-event .fc-event-main { color: #fff; }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const calendarEl  = document.getElementById('calendar');
        const modal       = document.getElementById('event-modal');
        const closeBtn    = document.getElementById('modal-close');
        const closeBtn2   = document.getElementById('modal-close-btn');
        const barberFilter = document.getElementById('barber-filter');

        function getEvents(info, successCb, failureCb) {
            fetch(`{{ route('appointments.calendar.data') }}?start=${info.startStr}&end=${info.endStr}&barber_id=${barberFilter.value}`)
                .then(r => r.json())
                .then(successCb)
                .catch(failureCb);
        }

        const calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'es',
            initialView: 'dayGridMonth',
            headerToolbar: {
                left:   'prev,next today',
                center: 'title',
                right:  'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            buttonText: {
                today:    'Hoy',
                month:    'Mes',
                week:     'Semana',
                day:      'Día',
                list:     'Lista',
            },
            height: 'auto',
            slotMinTime: '08:00:00',
            slotMaxTime: '22:00:00',
            events: getEvents,
            eventClick: function (info) {
                const p = info.event.extendedProps;
                const start = info.event.start;
                const end   = info.event.end;

                document.getElementById('modal-status-dot').style.background = info.event.backgroundColor;
                document.getElementById('modal-title').textContent = info.event.title;
                document.getElementById('modal-time').textContent =
                    start ? start.toLocaleDateString('es', { weekday:'long', day:'2-digit', month:'short' }) +
                             ' · ' + start.toLocaleTimeString('es', { hour:'2-digit', minute:'2-digit' }) +
                             (end ? ' – ' + end.toLocaleTimeString('es', { hour:'2-digit', minute:'2-digit' }) : '')
                    : '';
                document.getElementById('modal-cliente').textContent  = p.cliente;
                document.getElementById('modal-servicio').textContent = p.servicio;
                document.getElementById('modal-barbero').textContent  = p.barbero;
                document.getElementById('modal-edit-btn').href        = p.edit_url;

                modal.style.removeProperty('display');
            },
            eventDidMount: function (info) {
                // Tooltip nativo
                info.el.title = `${info.event.extendedProps.cliente} · ${info.event.extendedProps.servicio}`;
            },
            dayCellDidMount: function (info) {
                info.el.style.minHeight = '80px';
            },
        });

        calendar.render();

        // Refetch on barber filter change
        barberFilter.addEventListener('change', () => calendar.refetchEvents());

        // Close modal
        [closeBtn, closeBtn2].forEach(btn => btn.addEventListener('click', () => {
            modal.style.display = 'none';
        }));
        modal.addEventListener('click', e => {
            if (e.target === modal) modal.style.display = 'none';
        });
    });
    </script>
</x-app-layout>
