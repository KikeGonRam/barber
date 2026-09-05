<script setup>
/**
 * Migración de resources/views/appointments/calendar.blade.php a Inertia+Vue
 * (ver .claude/skills/inertia-vue-migration/SKILL.md, Fase 3). calendarData()
 * sigue siendo un endpoint JSON plano sin cambios — se consume igual que
 * antes lo hacía el <script> vanilla, solo que ahora el modal de detalle es
 * estado reactivo de Vue en vez de manipulación directa del DOM.
 */
import { reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import esLocale from '@fullcalendar/core/locales/es';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
  barbers: { type: Array, required: true },
});

const fc = ref(null);
const selectedBarber = ref('');

const legend = [
  { color: '#d97706', label: 'Pendiente' },
  { color: '#3b82f6', label: 'Confirmada' },
  { color: '#06b6d4', label: 'En Proceso' },
  { color: '#10b981', label: 'Completada' },
  { color: '#ef4444', label: 'Cancelada' },
  { color: '#6b7280', label: 'No Asistió' },
];

const modal = reactive({
  open: false,
  color: '',
  title: '',
  time: '',
  cliente: '',
  servicio: '',
  barbero: '',
  editUrl: '#',
});

function refetchEvents() {
  fc.value?.getApi().refetchEvents();
}

function fetchEvents(info, successCallback, failureCallback) {
  const params = new URLSearchParams({
    start: info.startStr,
    end: info.endStr,
    barber_id: selectedBarber.value,
  });

  fetch(`${route('appointments.calendar.data')}?${params}`)
    .then((r) => r.json())
    .then(successCallback)
    .catch(failureCallback);
}

function onEventClick(info) {
  const p = info.event.extendedProps;
  const start = info.event.start;
  const end = info.event.end;

  modal.color = info.event.backgroundColor;
  modal.title = info.event.title;
  modal.time = start
    ? start.toLocaleDateString('es', { weekday: 'long', day: '2-digit', month: 'short' }) +
      ' · ' +
      start.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }) +
      (end ? ' – ' + end.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }) : '')
    : '';
  modal.cliente = p.cliente;
  modal.servicio = p.servicio;
  modal.barbero = p.barbero;
  modal.editUrl = p.edit_url;
  modal.open = true;
}

const calendarOptions = reactive({
  plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
  locale: esLocale,
  initialView: 'dayGridMonth',
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
  },
  buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día', list: 'Lista' },
  height: 'auto',
  slotMinTime: '08:00:00',
  slotMaxTime: '22:00:00',
  events: fetchEvents,
  eventClick: onEventClick,
  eventDidMount(info) {
    info.el.title = `${info.event.extendedProps.cliente} · ${info.event.extendedProps.servicio}`;
  },
  dayCellDidMount(info) {
    info.el.style.minHeight = '80px';
  },
});
</script>

<template>
  <Head title="Calendario de Citas" />

  <AppLayout>
    <template #header>
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="ui-title">Calendario de <span class="text-gold">Citas</span></h2>
          <p class="ui-subtitle">Vista mensual, semanal y diaria de la agenda.</p>
        </div>
        <div class="flex items-center gap-3">
          <!--
            <a> normal, NO <Link> de Inertia: appointments.index sigue siendo
            una vista Blade (no migrada todavía). Un <Link> hacia una ruta
            no-Inertia dispara el modal de depuración "respuesta inesperada"
            de Inertia en vez de navegar — ver SKILL.md, Fase 3, gotcha.
          -->
          <a
            :href="route('appointments.index')"
            class="ui-btn-secondary px-5 text-[11px] tracking-widest"
          >
            <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 6h16M4 10h16M4 14h16M4 18h16"
              />
            </svg>
            Vista Lista
          </a>
          <a :href="route('appointments.create')" class="ui-btn">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4v16m8-8H4"
              />
            </svg>
            Nueva Cita
          </a>
        </div>
      </div>
    </template>

    <div class="space-y-5">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex flex-wrap gap-3">
          <span
            v-for="item in legend"
            :key="item.label"
            class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-muted"
          >
            <span class="h-2.5 w-2.5 rounded-full" :style="{ background: item.color }"></span>
            {{ item.label }}
          </span>
        </div>

        <div class="flex items-center gap-3">
          <label class="text-[10px] font-black uppercase tracking-widest text-muted"
            >Barbero:</label
          >
          <select
            v-model="selectedBarber"
            class="ui-input py-2 text-sm min-w-[180px]"
            @change="refetchEvents"
          >
            <option value="">Todos los barberos</option>
            <option v-for="b in barbers" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>
      </div>

      <div class="ui-card-premium p-4 sm:p-6">
        <FullCalendar ref="fc" :options="calendarOptions" />
      </div>

      <div
        v-if="modal.open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm"
        @click.self="modal.open = false"
      >
        <div
          class="relative w-full max-w-md rounded-3xl border border-ink/10 bg-card p-8 shadow-[0_30px_80px_rgba(0,0,0,0.7)]"
        >
          <button
            aria-label="Cerrar"
            class="absolute top-4 right-4 text-muted hover:text-ink transition"
            @click="modal.open = false"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
          <div
            class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-gold/30 to-transparent rounded-t-3xl"
          ></div>

          <div class="h-3 w-3 rounded-full mb-4" :style="{ background: modal.color }"></div>
          <h3 class="text-xl font-black text-ink uppercase tracking-tight mb-1">
            {{ modal.title }}
          </h3>
          <p class="text-[10px] font-bold text-muted uppercase tracking-widest mb-6">
            {{ modal.time }}
          </p>

          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <div
                class="h-8 w-8 rounded-lg bg-gold/10 text-gold flex items-center justify-center shrink-0"
              >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                  />
                </svg>
              </div>
              <div>
                <p class="text-[9px] text-muted uppercase font-bold tracking-widest">Cliente</p>
                <p class="text-sm font-bold text-ink">{{ modal.cliente }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <div
                class="h-8 w-8 rounded-lg bg-gold/10 text-gold flex items-center justify-center shrink-0"
              >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M6 9a3 3 0 100-6 3 3 0 000 6zm0 12a3 3 0 100-6 3 3 0 000 6zm14-15L8.5 15M9 6l11 12"
                  />
                </svg>
              </div>
              <div>
                <p class="text-[9px] text-muted uppercase font-bold tracking-widest">Servicio</p>
                <p class="text-sm font-bold text-ink">{{ modal.servicio }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <div
                class="h-8 w-8 rounded-lg bg-gold/10 text-gold flex items-center justify-center shrink-0"
              >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z"
                  />
                </svg>
              </div>
              <div>
                <p class="text-[9px] text-muted uppercase font-bold tracking-widest">Barbero</p>
                <p class="text-sm font-bold text-ink">{{ modal.barbero }}</p>
              </div>
            </div>
          </div>

          <div class="mt-8 flex gap-3">
            <!-- <a> normal: appointments.edit tampoco está migrada (ver nota arriba). -->
            <a
              :href="modal.editUrl"
              class="flex-1 ui-btn py-3 text-[11px] tracking-widest justify-center"
              >Editar Cita</a
            >
            <button
              class="px-5 py-3 rounded-xl border border-ink/10 bg-ink/5 text-[11px] font-black uppercase tracking-widest text-muted hover:text-ink transition-all"
              @click="modal.open = false"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style>
/* FullCalendar dark theme overrides — idénticos a los de calendar.blade.php.
   Sin scoped: los selectores :root / .fc-* deben aplicar globalmente. */
:root {
  --fc-border-color: rgba(255, 255, 255, 0.06);
  --fc-button-bg-color: #1a1a1a;
  --fc-button-border-color: rgba(255, 255, 255, 0.1);
  --fc-button-text-color: #b0b0b0;
  --fc-button-hover-bg-color: #d4af37;
  --fc-button-hover-border-color: #d4af37;
  --fc-button-hover-text-color: #000;
  --fc-button-active-bg-color: #d4af37;
  --fc-button-active-border-color: #d4af37;
  --fc-button-active-text-color: #000;
  --fc-today-bg-color: rgba(212, 175, 55, 0.06);
  --fc-page-bg-color: transparent;
  --fc-neutral-bg-color: rgba(255, 255, 255, 0.02);
  --fc-list-event-hover-bg-color: rgba(212, 175, 55, 0.08);
}
.fc {
  color: #b0b0b0;
  font-family: 'Figtree', sans-serif;
}
.fc-col-header-cell {
  background: rgba(255, 255, 255, 0.02);
}
.fc-col-header-cell-cushion,
.fc-daygrid-day-number,
.fc-list-event-title {
  color: #b0b0b0 !important;
  text-decoration: none !important;
}
.fc-toolbar-title {
  font-weight: 900 !important;
  font-size: 1.1rem !important;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #fff;
}
.fc-event {
  cursor: pointer;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  padding: 1px 4px;
  border: none !important;
}
.fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
  color: #d4af37 !important;
  font-weight: 900;
}
.fc-button {
  border-radius: 10px !important;
  font-size: 11px !important;
  font-weight: 900 !important;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  transition: all 0.2s !important;
}
.fc-h-event .fc-event-main {
  color: #fff;
}
</style>
