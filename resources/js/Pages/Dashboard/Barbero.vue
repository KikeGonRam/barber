<script setup>
/**
 * Migración del dashboard de Barbero a Inertia+Vue (ver
 * .claude/skills/inertia-vue-migration/SKILL.md, Fase 5). Los otros 2 roles
 * (administrador/cliente) siguen renderizados por
 * resources/views/dashboard.blade.php hasta su propia fase.
 *
 * Todos los enlaces son <a> normales (no <Link>): ninguno de sus destinos
 * (agenda, perfil) está migrado todavía — ver el gotcha "<Link> hacia ruta
 * todavía-Blade" en el SKILL.md.
 *
 * Aprobar/Rechazar usan <form> nativos (POST real del navegador, no
 * router.patch()/useForm() de Inertia): el destino
 * (barber.appointments.status) sigue devolviendo una redirección Blade
 * clásica, y disparar eso vía el layer XHR de Inertia arriesga el mismo
 * problema de "respuesta inesperada" que un <Link> — un <form> nativo
 * simplemente navega la página completa, exactamente como ya hacía Blade.
 */
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { Bar, Doughnut } from 'vue-chartjs';
import { route } from 'ziggy-js';
import { chartScale, UB_CATEGORICAL, fmtInt } from '../../chart-theme';
import AppLayout from '@/Layouts/AppLayout.vue';
import AnalyticsInsights from '@/Components/AnalyticsInsights.vue';
import AnalyticsCta from '@/Components/AnalyticsCta.vue';
import DashboardHeader from '@/Components/DashboardHeader.vue';

const props = defineProps({
  todayLabel: { type: String, required: true },
  kpis: { type: Object, required: true },
  performanceChart: { type: Object, required: true },
  servicesChart: { type: Object, required: true },
  barberToday: { type: Array, required: true },
  barberPending: { type: Array, required: true },
  sparkHighlights: { type: Array, default: () => [] },
});

const page = usePage();
const firstName = computed(() => (page.props.auth?.user?.name ?? 'Barbero').split(' ')[0]);
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const kpiCards = computed(() => [
  { label: 'Citas Hoy', val: props.kpis.appointments_today, text: 'text-gold' },
  { label: 'Por Aprobar', val: props.barberPending.length, text: 'text-amber-300' },
  {
    label: 'Ingresos Mes',
    val: `$${Number(props.kpis.income_month ?? 0).toLocaleString('es-MX', { maximumFractionDigits: 0 })}`,
    text: 'text-emerald-400',
  },
  {
    label: 'Propinas Mes',
    val: `$${Number(props.kpis.tips_month ?? 0).toLocaleString('es-MX', { maximumFractionDigits: 0 })}`,
    text: 'text-gold',
  },
  { label: 'Rating', val: props.kpis.rating, text: 'text-ink' },
]);

const STATUS_STYLE = {
  completada: ['border-emerald-500/25 bg-emerald-500/10 text-emerald-300', 'Completada'],
  pendiente: ['border-amber-500/25 bg-amber-500/10 text-amber-300', 'Pendiente'],
  en_proceso: ['border-blue-500/25 bg-blue-500/10 text-blue-300', 'En proceso'],
  confirmada: ['border-gold/25 bg-gold/10 text-gold', 'Confirmada'],
  cancelada: ['border-red-500/25 bg-red-500/10 text-red-400', 'Cancelada'],
  no_asistio: ['border-ink/10 bg-ink/5 text-ink/40', 'No asistió'],
};

function statusStyle(estado) {
  return STATUS_STYLE[estado] ?? ['border-ink/10 bg-ink/5 text-ink/40', '—'];
}

function confirmReject(event) {
  if (!confirm('¿Rechazar esta solicitud de cita?')) {
    event.preventDefault();
  }
}

const hasPerformance = computed(() => (props.performanceChart.values ?? []).some((v) => v));
const performanceData = computed(() => ({
  labels: props.performanceChart.labels ?? [],
  datasets: [
    {
      label: 'Citas',
      data: props.performanceChart.values ?? [],
      backgroundColor: 'rgba(212,175,55,0.75)',
      hoverBackgroundColor: '#d4af37',
      borderRadius: 6,
      barThickness: 18,
    },
  ],
}));
const performanceOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      displayColors: false,
      callbacks: { label: (ctx) => `Citas: ${fmtInt(ctx.parsed.y)}` },
    },
  },
  scales: {
    y: { ...chartScale, beginAtZero: true, ticks: { ...chartScale.ticks, precision: 0 } },
    x: chartScale,
  },
};

const hasServices = computed(() => (props.servicesChart.values ?? []).some((v) => v));
const servicesData = computed(() => ({
  labels: props.servicesChart.labels ?? [],
  datasets: [
    {
      data: props.servicesChart.values ?? [],
      backgroundColor: UB_CATEGORICAL,
      borderColor: '#111111',
      borderWidth: 3,
      hoverOffset: 8,
    },
  ],
}));
const servicesOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '72%',
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        color: 'rgba(255,255,255,0.45)',
        usePointStyle: true,
        pointStyle: 'circle',
        padding: 14,
        font: { size: 10, weight: 'bold' },
      },
    },
    tooltip: {
      displayColors: true,
      callbacks: { label: (ctx) => `${ctx.label}: ${fmtInt(ctx.parsed)}` },
    },
  },
};
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout>
    <template #header>
      <DashboardHeader label="Profesional" color="text-amber-400" :today-label="todayLabel" />
    </template>

    <div class="space-y-5">
      <!-- Bienvenida -->
      <section class="rounded-2xl border border-ink/[0.06] bg-card p-6 relative overflow-hidden">
        <div
          class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-gold/5 blur-3xl pointer-events-none"
        ></div>
        <div class="relative flex flex-col sm:flex-row items-center gap-6">
          <div
            class="h-16 w-16 rounded-2xl bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center text-black shrink-0"
          >
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div>
            <p class="text-[9px] font-black uppercase tracking-[0.3em] text-ink/50">
              Bienvenido de vuelta
            </p>
            <h3 class="text-xl font-black text-ink uppercase mt-0.5">
              Maestro <span class="text-gold">{{ firstName }}</span>
            </h3>
            <p class="text-xs text-ink/40 mt-1">
              Tienes <strong class="text-ink">{{ kpis.appointments_today }}</strong> servicio{{
                kpis.appointments_today !== 1 ? 's' : ''
              }}
              hoy<template v-if="barberPending.length">
                · <strong class="text-amber-300">{{ barberPending.length }}</strong> por
                aprobar</template
              >.
            </p>
          </div>
          <div class="sm:ml-auto flex gap-3">
            <a :href="route('barber.agenda')" class="ui-btn px-6 py-3 text-[10px]">Mi Agenda</a>
            <a
              :href="route('barber.profile.edit')"
              class="flex items-center gap-2 px-6 py-3 rounded-xl border border-ink/10 bg-ink/[0.03] text-[10px] font-black uppercase tracking-widest text-ink/60 hover:text-ink hover:border-ink/20 transition-all"
              >Mi Perfil</a
            >
          </div>
        </div>
      </section>

      <!-- KPIs -->
      <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div
          v-for="kpi in kpiCards"
          :key="kpi.label"
          class="rounded-[8px] border border-ink/[0.06] bg-card p-5 text-center"
        >
          <p class="text-[9px] font-black uppercase tracking-[0.22em] text-ink/50 mb-3">
            {{ kpi.label }}
          </p>
          <p class="text-2xl font-black" :class="kpi.text">{{ kpi.val }}</p>
        </div>
      </section>

      <AnalyticsInsights :insights="sparkHighlights" titulo="Tus oportunidades" />
      <AnalyticsCta
        titulo="Tu analítica personal"
        descripcion="Descubre a qué horas tienes más demanda y cómo le está yendo a tus publicaciones — solo tus datos, en lenguaje simple."
        cta="Ver mi analítica"
      />

      <!-- Por aprobar -->
      <section
        v-if="barberPending.length"
        class="rounded-2xl border border-amber-500/25 bg-amber-500/[0.04] p-5"
      >
        <div class="flex items-center gap-2 mb-4">
          <svg
            class="h-4 w-4 text-amber-300"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          <h3 class="text-[11px] font-black uppercase tracking-widest text-amber-300">
            Esperando tu aprobación
          </h3>
          <span class="ml-auto text-[9px] font-black text-amber-300/70"
            >{{ barberPending.length }} solicitud{{ barberPending.length !== 1 ? 'es' : '' }}</span
          >
        </div>
        <div class="space-y-2">
          <div
            v-for="appt in barberPending"
            :key="appt.id"
            class="flex flex-wrap items-center gap-3 p-3 rounded-xl border border-amber-500/10 bg-black/20"
          >
            <div class="w-14 text-center shrink-0">
              <p class="text-[11px] font-black text-ink">
                {{ appt.hora_inicio?.slice(0, 5) ?? '--:--' }}
              </p>
              <p class="text-[8px] text-ink/45 font-bold">{{ appt.fecha }}</p>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-black text-ink truncate">{{ appt.cliente }}</p>
              <p class="text-[9px] text-ink/40 font-bold truncate">{{ appt.servicio }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <form method="POST" :action="appt.statusUrl">
                <input type="hidden" name="_token" :value="csrfToken" />
                <input type="hidden" name="_method" value="PATCH" />
                <input type="hidden" name="estado" value="confirmada" />
                <button type="submit" class="ui-btn px-4 py-2 text-[9px] tracking-widest">
                  Aprobar
                </button>
              </form>
              <form method="POST" :action="appt.statusUrl" @submit="confirmReject">
                <input type="hidden" name="_token" :value="csrfToken" />
                <input type="hidden" name="_method" value="PATCH" />
                <input type="hidden" name="estado" value="cancelada" />
                <button
                  type="submit"
                  class="text-[9px] font-black uppercase tracking-widest text-ink/40 hover:text-red-400 transition px-2"
                >
                  Rechazar
                </button>
              </form>
            </div>
          </div>
        </div>
      </section>

      <!-- Citas de hoy -->
      <section class="rounded-2xl border border-ink/[0.06] bg-card p-5">
        <div class="flex items-center justify-between mb-5">
          <div>
            <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/50">Agenda</p>
            <h3 class="text-sm font-black text-ink uppercase mt-0.5">Citas de Hoy</h3>
          </div>
          <a
            :href="route('barber.agenda')"
            class="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-ink/50 hover:text-gold transition-colors"
          >
            Ver agenda
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2.5"
                d="M9 5l7 7-7 7"
              />
            </svg>
          </a>
        </div>
        <div
          v-if="!barberToday.length"
          class="flex flex-col items-center justify-center py-12 border border-dashed border-ink/[0.06] rounded-xl"
        >
          <svg
            class="h-8 w-8 text-ink/10 mb-2"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.2"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
          </svg>
          <p class="text-xs font-bold text-ink/45 uppercase tracking-widest">Sin citas hoy</p>
        </div>
        <div v-else class="space-y-2">
          <div
            v-for="appt in barberToday"
            :key="appt.id"
            class="flex items-center gap-3 p-3 rounded-xl border transition-all"
            :class="
              appt.isNext
                ? 'border-gold/30 bg-gold/[0.04]'
                : 'border-ink/[0.05] hover:border-ink/10'
            "
          >
            <div class="w-12 text-center shrink-0">
              <p class="text-[11px] font-black" :class="appt.isNext ? 'text-gold' : 'text-ink'">
                {{ appt.hora_inicio?.slice(0, 5) ?? '--:--' }}
              </p>
              <p class="text-[8px] text-ink/45 font-bold">{{ appt.hora_fin?.slice(0, 5) }}</p>
            </div>
            <div class="w-px h-7 bg-ink/[0.06] shrink-0"></div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-black text-ink truncate">
                {{ appt.cliente }}
                <span
                  v-if="appt.isNext"
                  class="ml-1 text-[8px] font-black uppercase tracking-wider text-gold"
                  >· Siguiente</span
                >
              </p>
              <p class="text-[9px] text-ink/35 font-bold truncate">{{ appt.servicio }}</p>
            </div>
            <span
              class="shrink-0 text-[8px] font-black uppercase tracking-wider border rounded-full px-2 py-0.5"
              :class="statusStyle(appt.estado)[0]"
              >{{ statusStyle(appt.estado)[1] }}</span
            >
          </div>
        </div>
      </section>

      <!-- Gráficas -->
      <section class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="rounded-2xl border border-ink/[0.06] bg-card p-5">
          <div class="mb-5">
            <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/50">
              Últimos 7 días
            </p>
            <h3 class="text-sm font-black text-ink uppercase mt-0.5">Productividad Semanal</h3>
          </div>
          <div v-if="hasPerformance" class="h-52">
            <Bar :data="performanceData" :options="performanceOptions" />
          </div>
          <div
            v-else
            class="h-52 flex items-center justify-center border border-dashed border-ink/[0.06] rounded-xl"
          >
            <p class="text-xs text-ink/45 uppercase tracking-widest font-bold">
              Sin datos suficientes
            </p>
          </div>
        </div>
        <div class="rounded-2xl border border-ink/[0.06] bg-card p-5">
          <div class="mb-5">
            <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/50">Último año</p>
            <h3 class="text-sm font-black text-ink uppercase mt-0.5">Top Especialidades</h3>
          </div>
          <div v-if="hasServices" class="h-52">
            <Doughnut :data="servicesData" :options="servicesOptions" />
          </div>
          <div
            v-else
            class="h-52 flex items-center justify-center border border-dashed border-ink/[0.06] rounded-xl"
          >
            <p class="text-xs text-ink/45 uppercase tracking-widest font-bold">
              Sin especialidades aún
            </p>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
