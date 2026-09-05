<script setup>
/**
 * Migración del dashboard de Administrador a Inertia+Vue (ver
 * .claude/skills/inertia-vue-migration/SKILL.md, Fase 7 — última rama de
 * dashboard.blade.php, que se borra en este mismo commit junto con los
 * componentes Blade que ya nadie más usa).
 *
 * Todos los enlaces son <a> normales (no <Link>): ninguno de sus destinos
 * está migrado todavía. El toggle de mantenimiento usa un <form> nativo por
 * la misma razón que Aprobar/Rechazar en Barbero.vue: su destino
 * (settings.maintenance.toggle) sigue siendo una redirección Blade clásica.
 *
 * La sección "Analítica avanzada" es plegable. La versión Blade necesitaba
 * un truco (colapsar con max-height, NUNCA display:none) para que los
 * canvas de Chart.js no se inicializaran a 0x0 estando ocultos. Aquí no
 * hace falta: con v-if, vue-chartjs monta el <canvas> recién cuando el
 * contenedor YA es visible, así que mide el tamaño real desde el principio
 * — una simplificación real, no solo una preferencia de estilo.
 */
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import { route } from 'ziggy-js';
import { chartScale, fmtInt, fmtMoney, UB_CATEGORICAL } from '../../chart-theme';
import AppLayout from '@/Layouts/AppLayout.vue';
import AnalyticsInsights from '@/Components/AnalyticsInsights.vue';
import AnalyticsCta from '@/Components/AnalyticsCta.vue';
import DashboardHeader from '@/Components/DashboardHeader.vue';

const props = defineProps({
  todayLabel: { type: String, required: true },
  kpis: { type: Object, required: true },
  incomeChart: { type: Object, required: true },
  servicesChart: { type: Object, required: true },
  barberPerformance: { type: Object, required: true },
  clientTrends: { type: Object, required: true },
  chatbotTelemetry: { type: Object, default: () => ({}) },
  maintenanceMode: { type: Boolean, default: false },
  todayAppointments: { type: Array, required: true },
  recentAppointments: { type: Array, required: true },
  insights: { type: Array, default: () => [] },
  sparkHighlights: { type: Array, default: () => [] },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const quickActions = [
  {
    href: route('appointments.create'),
    label: 'Nueva Cita',
    link: 'hover:border-blue-500/30 hover:bg-blue-500/[0.04]',
    box: 'bg-blue-500/10',
    text: 'text-blue-400',
    icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
  },
  {
    href: route('clients.create'),
    label: 'Nuevo Cliente',
    link: 'hover:border-cyan-500/30 hover:bg-cyan-500/[0.04]',
    box: 'bg-cyan-500/10',
    text: 'text-cyan-400',
    icon: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',
  },
  {
    href: route('payments.create'),
    label: 'Cobrar',
    link: 'hover:border-emerald-500/30 hover:bg-emerald-500/[0.04]',
    box: 'bg-emerald-500/10',
    text: 'text-emerald-400',
    icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
  },
  {
    href: route('reports.index'),
    label: 'Reportes',
    link: 'hover:border-purple-500/30 hover:bg-purple-500/[0.04]',
    box: 'bg-purple-500/10',
    text: 'text-purple-400',
    icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  },
];

const STATUS_STYLE = {
  completada: {
    cls: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300',
    dot: 'bg-emerald-400',
    label: 'Completada',
  },
  pendiente: {
    cls: 'border-amber-500/25 bg-amber-500/10 text-amber-300',
    dot: 'bg-amber-400',
    label: 'Pendiente',
  },
  en_proceso: {
    cls: 'border-blue-500/25 bg-blue-500/10 text-blue-300',
    dot: 'bg-blue-400',
    label: 'En proceso',
  },
  cancelada: {
    cls: 'border-red-500/25 bg-red-500/10 text-red-400',
    dot: 'bg-red-400',
    label: 'Cancelada',
  },
};
function statusStyle(estado) {
  return (
    STATUS_STYLE[estado] ?? {
      cls: 'border-ink/10 bg-ink/5 text-ink/40',
      dot: 'bg-ink/30',
      label: '—',
    }
  );
}

// Puerto de la función PHP $adminSparkline de dashboard.blade.php: arma un
// path SVG en miniatura a partir de una serie de valores. Aritmética pura,
// no reglas de negocio ni i18n — segura de portar tal cual a JS.
function sparklinePath(values, w = 80, h = 22) {
  const vals = (values ?? []).filter((v) => v !== null && v !== undefined);
  if (vals.length < 2) return '';

  const max = Math.max(...vals) || 1;
  const min = Math.min(...vals);
  const rng = max - min || 1;
  const step = w / (vals.length - 1);
  const pts = vals.map(
    (v, i) => `${(i * step).toFixed(2)},${(h - ((v - min) / rng) * h).toFixed(2)}`,
  );

  return `M ${pts.join(' L ')}`;
}

const incomeSpark = computed(() => sparklinePath(props.incomeChart.values));
const ratioActiveClients = computed(() =>
  props.kpis.total_clients > 0
    ? Math.round((props.kpis.active_clients / props.kpis.total_clients) * 100)
    : 0,
);

const busyStatuses = computed(() => (props.kpis.barbers_status ?? []).filter((s) => s.is_busy));
const freeStatuses = computed(() => (props.kpis.barbers_status ?? []).filter((s) => !s.is_busy));
const showFree = ref(false);

const activeTab = ref('activity');
const TABS = [
  { id: 'activity', label: 'Actividad' },
  { id: 'stations', label: 'Estaciones' },
  { id: 'topbarber', label: 'Top Mes' },
];

// Igual que la versión Blade: recuerda si el panel de analítica avanzada
// estaba abierto en localStorage, misma clave ('adminAnalytics').
const analyticsOpen = ref(false);
onMounted(() => {
  try {
    analyticsOpen.value = localStorage.getItem('adminAnalytics') === 'true';
  } catch {
    analyticsOpen.value = false;
  }
});
function toggleAnalytics() {
  analyticsOpen.value = !analyticsOpen.value;
  try {
    localStorage.setItem('adminAnalytics', String(analyticsOpen.value));
  } catch {
    // localStorage puede fallar en modo privado — solo se pierde el "recordar", no es crítico.
  }
}

const hasIncome = computed(() => (props.incomeChart.values ?? []).some((v) => v));
const incomeData = computed(() => ({
  labels: props.incomeChart.labels ?? [],
  datasets: [
    {
      label: 'Ingresos ($)',
      data: props.incomeChart.values ?? [],
      borderColor: '#34d399',
      backgroundColor: (context) => {
        const { ctx, chartArea } = context.chart;
        if (!chartArea) return 'rgba(52,211,153,0.05)';
        const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
        gradient.addColorStop(0, 'rgba(52,211,153,0.24)');
        gradient.addColorStop(1, 'rgba(52,211,153,0.01)');
        return gradient;
      },
      borderWidth: 2.5,
      fill: true,
      tension: 0.35,
      pointRadius: 3,
      pointHoverRadius: 6,
      pointBackgroundColor: '#0d0d0d',
      pointBorderColor: '#34d399',
      pointBorderWidth: 2,
    },
  ],
}));
const incomeOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { intersect: false, mode: 'index' },
  plugins: {
    legend: { display: false },
    tooltip: {
      displayColors: false,
      callbacks: { label: (ctx) => `Ingresos: ${fmtMoney(ctx.parsed.y)}` },
    },
  },
  scales: {
    y: {
      ...chartScale,
      beginAtZero: true,
      ticks: { ...chartScale.ticks, callback: (v) => fmtMoney(v) },
    },
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
      hoverOffset: 10,
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
        padding: 16,
        font: { size: 10, weight: 'bold' },
      },
    },
    tooltip: {
      displayColors: true,
      callbacks: { label: (ctx) => `${ctx.label}: ${fmtInt(ctx.parsed)}` },
    },
  },
};

const hasBarberPerformance = computed(
  () =>
    (props.barberPerformance.appointments ?? []).some((v) => v) ||
    (props.barberPerformance.revenue ?? []).some((v) => v),
);
const barberCitasData = computed(() => ({
  labels: props.barberPerformance.labels ?? [],
  datasets: [
    {
      label: 'Citas',
      data: props.barberPerformance.appointments ?? [],
      backgroundColor: 'rgba(59,130,246,0.75)',
      hoverBackgroundColor: '#3b82f6',
      borderRadius: 4,
      barThickness: 18,
    },
  ],
}));
const barberCitasOptions = {
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
const barberIngresosData = computed(() => ({
  labels: props.barberPerformance.labels ?? [],
  datasets: [
    {
      label: 'Ingresos ($)',
      data: props.barberPerformance.revenue ?? [],
      backgroundColor: 'rgba(16,185,129,0.75)',
      hoverBackgroundColor: '#10b981',
      borderRadius: 4,
      barThickness: 18,
    },
  ],
}));
const barberIngresosOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      displayColors: false,
      callbacks: { label: (ctx) => `Ingresos: ${fmtMoney(ctx.parsed.y)}` },
    },
  },
  scales: {
    y: {
      ...chartScale,
      beginAtZero: true,
      ticks: { ...chartScale.ticks, callback: (v) => fmtMoney(v) },
    },
    x: chartScale,
  },
};

const hasClientTrends = computed(() => (props.clientTrends.values ?? []).some((v) => v));
const clientTrendsData = computed(() => ({
  labels: props.clientTrends.labels ?? [],
  datasets: [
    {
      label: 'Citas Completadas',
      data: props.clientTrends.values ?? [],
      borderColor: '#c084fc',
      backgroundColor: 'rgba(192,132,252,0.1)',
      borderWidth: 2.5,
      fill: true,
      cubicInterpolationMode: 'monotone',
      pointRadius: 3,
      pointHoverRadius: 6,
      pointBackgroundColor: '#0d0d0d',
      pointBorderColor: '#c084fc',
      pointBorderWidth: 2,
    },
  ],
}));
const clientTrendsOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { intersect: false, mode: 'index' },
  plugins: {
    legend: { display: false },
    tooltip: {
      displayColors: false,
      callbacks: { label: (ctx) => `${fmtInt(ctx.parsed.y)} cita${ctx.parsed.y === 1 ? '' : 's'}` },
    },
  },
  scales: {
    y: { ...chartScale, beginAtZero: true, ticks: { ...chartScale.ticks, precision: 0 } },
    x: chartScale,
  },
};

// Predicciones IA: puerto directo del IIFE async de dashboard.blade.php.
// Pide un token de Sanctum fresco (la sesión web no sirve para /api/v1/*,
// tratado como contrato externo — ver guardrails) y llama 3 endpoints en
// paralelo. Igual que el original: '72%' de confianza es un valor fijo,
// no una regresión introducida aquí.
const incomeForecast = ref(null);
const appointmentForecast = ref(null);
const aiConfidence = ref(null);
const aiInsights = ref(null);
const AI_STATUS_STYLE = {
  positive: { cls: 'border-emerald-500/20 bg-emerald-500/[0.04]', dot: 'bg-emerald-400' },
  warning: { cls: 'border-amber-500/20 bg-amber-500/[0.04]', dot: 'bg-amber-400' },
  neutral: { cls: 'border-blue-500/20 bg-blue-500/[0.04]', dot: 'bg-blue-400' },
};

onMounted(async () => {
  try {
    const tokenRes = await fetch('/api/v1/auth/get-api-token', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
    });
    if (!tokenRes.ok) return;

    const { token } = await tokenRes.json();
    const headers = {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
      'X-CSRF-TOKEN': csrfToken,
    };

    const [incomeRes, apptRes, insightsRes] = await Promise.all([
      fetch('/api/v1/admin/predictions/income/7', { headers }),
      fetch('/api/v1/admin/predictions/appointments/7', { headers }),
      fetch('/api/v1/admin/predictions/insights', { headers }),
    ]);

    if (incomeRes.ok) {
      const d = await incomeRes.json();
      incomeForecast.value = d.data?.predicted_income ? fmtMoney(d.data.predicted_income) : 'N/A';
    }
    if (apptRes.ok) {
      const d = await apptRes.json();
      appointmentForecast.value = d.data?.predicted_appointments ?? 'N/A';
    }
    aiConfidence.value = '72%';

    if (insightsRes.ok) {
      const { data = {} } = await insightsRes.json();
      aiInsights.value = Object.values(data);
    }
  } catch {
    incomeForecast.value = '—';
    appointmentForecast.value = '—';
    aiConfidence.value = '—';
  }
});
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout>
    <template #header>
      <DashboardHeader label="Administrativo" color="text-gold" :today-label="todayLabel">
        <div class="flex items-center gap-1.5 ml-auto sm:ml-0">
          <form method="POST" :action="route('settings.maintenance.toggle')">
            <input type="hidden" name="_token" :value="csrfToken" />
            <button
              type="submit"
              :title="maintenanceMode ? 'Mantenimiento activado' : 'Activar modo mantenimiento'"
              aria-label="Modo mantenimiento"
              class="flex h-8 w-8 items-center justify-center rounded-xl border transition-all"
              :class="
                maintenanceMode
                  ? 'bg-red-500/10 border-red-500/30 text-red-400'
                  : 'bg-ink/[0.03] border-ink/8 text-ink/35 hover:text-ink hover:border-ink/20'
              "
            >
              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                />
                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </button>
          </form>
          <a
            :href="route('backups.database.download')"
            title="Descargar backup de la base de datos"
            aria-label="Backup"
            class="flex h-8 w-8 items-center justify-center rounded-xl border border-emerald-500/25 bg-emerald-500/[0.06] text-emerald-400 hover:bg-emerald-500/10 transition-all"
          >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
              />
            </svg>
          </a>
        </div>
      </DashboardHeader>
    </template>

    <div class="space-y-5">
      <!-- Acciones rápidas -->
      <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <a
          v-for="a in quickActions"
          :key="a.label"
          :href="a.href"
          class="group flex items-center gap-3 rounded-[8px] border border-ink/[0.06] bg-card px-4 py-3.5 transition-all"
          :class="a.link"
        >
          <div
            class="h-8 w-8 rounded-[8px] flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform"
            :class="a.box"
          >
            <svg
              class="h-4 w-4"
              :class="a.text"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="a.icon" />
            </svg>
          </div>
          <span class="text-[11px] font-black text-ink uppercase tracking-wide">{{ a.label }}</span>
        </a>
      </section>

      <div class="flex items-center gap-3 px-1 pt-1">
        <span class="text-[10px] font-black uppercase tracking-[0.22em] text-gold">Resumen</span>
        <span class="h-px flex-1 bg-ink/[0.06]"></span>
      </div>

      <!-- KPIs -->
      <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-ink/[0.06] bg-card p-5 relative overflow-hidden">
          <div
            class="absolute top-0 left-0 h-0.5 w-full bg-gradient-to-r from-blue-500/60 to-transparent"
          ></div>
          <div class="flex items-start justify-between mb-4">
            <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/35">Citas Hoy</p>
            <div class="h-7 w-7 rounded-lg bg-blue-500/10 flex items-center justify-center">
              <svg
                class="h-3.5 w-3.5 text-blue-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                />
              </svg>
            </div>
          </div>
          <p class="text-3xl font-black text-ink leading-none">{{ kpis.appointments_today }}</p>
          <div class="mt-3 flex items-center gap-2 text-[9px] font-black text-ink/50">
            <span class="text-blue-400/80">Sem {{ kpis.appointments_week }}</span>
            <span>·</span>
            <span>Mes {{ kpis.appointments_month }}</span>
            <span
              v-if="kpis.appointment_growth != 0"
              class="ml-auto"
              :class="kpis.appointment_growth >= 0 ? 'text-emerald-400' : 'text-red-400'"
            >
              {{ kpis.appointment_growth >= 0 ? '▲' : '▼' }}{{ Math.abs(kpis.appointment_growth) }}%
            </span>
          </div>
        </div>

        <a
          :href="route('payments.index')"
          class="rounded-2xl border border-ink/[0.06] bg-card p-5 relative overflow-hidden hover:border-emerald-500/25 transition-all group"
        >
          <div
            class="absolute top-0 left-0 h-0.5 w-full bg-gradient-to-r from-emerald-500/60 to-transparent"
          ></div>
          <div class="flex items-start justify-between mb-4">
            <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/35">
              Ingresos Hoy
            </p>
            <div class="h-7 w-7 rounded-lg bg-emerald-500/10 flex items-center justify-center">
              <svg
                class="h-3.5 w-3.5 text-emerald-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
          </div>
          <p class="text-3xl font-black text-emerald-400 leading-none">
            ${{ Number(kpis.income_today).toLocaleString('es-MX', { maximumFractionDigits: 0 }) }}
          </p>
          <svg
            v-if="incomeSpark"
            viewBox="0 0 80 22"
            class="w-full h-5 my-2"
            preserveAspectRatio="none"
          >
            <path
              :d="incomeSpark"
              fill="none"
              stroke="rgba(52,211,153,0.45)"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
          <div v-else class="h-5 my-2"></div>
          <div class="flex items-center gap-2 text-[9px] font-black text-ink/50">
            <span class="text-emerald-400/80"
              >Sem ${{
                Number(kpis.income_week).toLocaleString('es-MX', { maximumFractionDigits: 0 })
              }}</span
            >
            <span>·</span>
            <span
              >Mes ${{
                Number(kpis.income_month).toLocaleString('es-MX', { maximumFractionDigits: 0 })
              }}</span
            >
            <span
              v-if="kpis.income_growth != 0"
              class="ml-auto"
              :class="kpis.income_growth >= 0 ? 'text-emerald-400' : 'text-red-400'"
            >
              {{ kpis.income_growth >= 0 ? '▲' : '▼' }}{{ Math.abs(kpis.income_growth) }}%
            </span>
          </div>
        </a>

        <a
          :href="route('clients.index')"
          class="rounded-2xl border border-ink/[0.06] bg-card p-5 relative overflow-hidden hover:border-cyan-500/25 transition-all"
        >
          <div
            class="absolute top-0 left-0 h-0.5 w-full bg-gradient-to-r from-cyan-500/60 to-transparent"
          ></div>
          <div class="flex items-start justify-between mb-4">
            <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/35">Clientes</p>
            <div class="h-7 w-7 rounded-lg bg-cyan-500/10 flex items-center justify-center">
              <svg
                class="h-3.5 w-3.5 text-cyan-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                />
              </svg>
            </div>
          </div>
          <p class="text-3xl font-black text-ink leading-none">{{ kpis.active_clients }}</p>
          <div class="mt-3 h-1 w-full bg-ink/5 rounded-full overflow-hidden">
            <div
              class="h-full bg-cyan-400 rounded-full"
              :style="{ width: `${ratioActiveClients}%` }"
            ></div>
          </div>
          <div class="mt-2 flex items-center gap-2 text-[9px] font-black text-ink/50">
            <span class="text-cyan-400/80">{{ ratioActiveClients }}% activos</span>
            <span>de {{ kpis.total_clients }} totales</span>
          </div>
        </a>

        <div class="rounded-2xl border border-ink/[0.06] bg-card p-5 relative overflow-hidden">
          <div
            class="absolute top-0 left-0 h-0.5 w-full bg-gradient-to-r from-purple-500/60 to-transparent"
          ></div>
          <div class="flex items-start justify-between mb-4">
            <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/35">Retención</p>
            <div class="h-7 w-7 rounded-lg bg-purple-500/10 flex items-center justify-center">
              <svg
                class="h-3.5 w-3.5 text-purple-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                />
              </svg>
            </div>
          </div>
          <p class="text-3xl font-black text-purple-400 leading-none">
            {{ kpis.retention_rate.toFixed(1) }}<span class="text-lg text-ink/40">%</span>
          </p>
          <div class="mt-3 h-1 w-full bg-ink/5 rounded-full overflow-hidden">
            <div
              class="h-full bg-purple-400 rounded-full"
              :style="{ width: `${Math.min(100, kpis.retention_rate)}%` }"
            ></div>
          </div>
          <div class="mt-2 flex items-center gap-2 text-[9px] font-black text-ink/50">
            <span class="text-purple-400/80">{{ kpis.recurring_clients }} recurrentes</span>
            <a
              v-if="(kpis.low_stock_count ?? 0) > 0"
              :href="route('inventory.products.index')"
              class="ml-auto flex items-center gap-1 text-amber-400/80 hover:text-amber-400 transition-colors"
            >
              <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                />
              </svg>
              {{ kpis.low_stock_count }} stock bajo
            </a>
          </div>
        </div>
      </section>

      <!-- Insights del análisis (hallazgos en vivo, precomputados en el controlador) -->
      <section v-if="insights.length" aria-label="Insights del análisis de datos">
        <div class="flex items-center gap-2 mb-3 px-1">
          <svg class="h-4 w-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0013 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
            />
          </svg>
          <h3 class="text-[11px] font-black uppercase tracking-widest text-gold">
            Insights del análisis de datos
          </h3>
          <span class="text-[9px] text-muted">· UrbanBlade Analytics</span>
          <a
            :href="route('analytics.index')"
            class="ml-auto flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-ink/50 hover:text-gold transition-colors"
          >
            Ver todo
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
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <article
            v-for="(insight, index) in insights"
            :key="index"
            class="rounded-2xl border border-gold/15 bg-gold/[0.03] p-4"
          >
            <p class="text-[9px] font-black uppercase tracking-widest text-gold/70">
              {{ insight.titulo }}
            </p>
            <p class="text-2xl font-black text-ink mt-1">{{ insight.dato }}</p>
            <p class="text-[11px] text-muted mt-1.5 leading-snug">{{ insight.detalle }}</p>
          </article>
        </div>
      </section>

      <AnalyticsInsights :insights="sparkHighlights" titulo="Prioridades detectadas" />
      <AnalyticsCta
        titulo="Analítica avanzada completa"
        descripcion="Preparación de datos, predicciones (supervisado), patrones ocultos (no supervisado) y gráficas — todo explicado en lenguaje simple, con datos reales de tu barbería."
        cta="Explorar"
      />

      <div class="flex items-center gap-3 px-1 pt-2">
        <span class="text-[10px] font-black uppercase tracking-[0.22em] text-gold"
          >Operación de hoy</span
        >
        <span class="h-px flex-1 bg-ink/[0.06]"></span>
      </div>

      <!-- Agenda + panel con tabs -->
      <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-7 rounded-2xl border border-ink/[0.06] bg-card p-5">
          <div class="flex items-center justify-between mb-5">
            <div>
              <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/50">Agenda</p>
              <h3 class="text-sm font-black text-ink uppercase mt-0.5">Citas de Hoy</h3>
            </div>
            <a
              :href="route('appointments.index')"
              class="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-ink/50 hover:text-gold transition-colors"
            >
              Ver todo
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
            v-if="!todayAppointments.length"
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
            <a
              :href="route('appointments.create')"
              class="mt-3 text-[9px] font-black uppercase tracking-widest text-gold/60 hover:text-gold transition-colors"
              >+ Crear cita</a
            >
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="appt in todayAppointments"
              :key="appt.id"
              class="flex items-center gap-3 p-3 rounded-xl border border-ink/[0.05] hover:border-ink/10 hover:bg-ink/[0.02] transition-all"
            >
              <div class="w-10 text-center shrink-0">
                <p class="text-[11px] font-black text-ink">
                  {{ appt.hora_inicio?.slice(0, 5) ?? '--:--' }}
                </p>
                <p class="text-[8px] text-ink/45 font-bold">{{ appt.hora_fin?.slice(0, 5) }}</p>
              </div>
              <div class="w-px h-7 bg-ink/[0.06] shrink-0"></div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-black text-ink truncate">{{ appt.cliente }}</p>
                <p class="text-[9px] text-ink/35 font-bold truncate">
                  {{ appt.servicio }} · {{ appt.barbero }}
                </p>
              </div>
              <span
                class="shrink-0 flex items-center gap-1 text-[8px] font-black uppercase tracking-wider border rounded-full px-2 py-0.5"
                :class="statusStyle(appt.estado).cls"
              >
                <span class="h-1.5 w-1.5 rounded-full" :class="statusStyle(appt.estado).dot"></span>
                {{ statusStyle(appt.estado).label }}
              </span>
            </div>
          </div>
        </div>

        <div class="lg:col-span-5">
          <div
            class="rounded-2xl border border-ink/[0.06] bg-card overflow-hidden h-full flex flex-col"
          >
            <div class="flex border-b border-ink/[0.06]">
              <button
                v-for="tab in TABS"
                :key="tab.id"
                type="button"
                class="flex-1 py-3 text-[9px] font-black uppercase tracking-[0.2em] transition-all"
                :class="
                  activeTab === tab.id
                    ? 'text-gold border-b-2 border-gold -mb-px'
                    : 'text-ink/50 hover:text-ink/60'
                "
                @click="activeTab = tab.id"
              >
                {{ tab.label }}
              </button>
            </div>

            <div class="flex-1 p-5">
              <div v-if="activeTab === 'activity'" class="space-y-3">
                <div
                  v-for="appt in recentAppointments.slice(0, 6)"
                  :key="appt.id"
                  class="flex items-center gap-3"
                >
                  <div
                    class="h-8 w-8 rounded-lg bg-ink/[0.04] border border-ink/[0.06] flex items-center justify-center text-[10px] font-black text-gold shrink-0"
                  >
                    {{ appt.barberoInicial }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-bold text-ink truncate">{{ appt.cliente }}</p>
                    <p class="text-[9px] text-ink/50 truncate">
                      {{ appt.fecha }} · {{ appt.hora_inicio?.slice(0, 5) }}
                    </p>
                  </div>
                  <span
                    class="shrink-0 text-[8px] font-black uppercase border rounded-full px-2 py-0.5"
                    :class="statusStyle(appt.estado).cls"
                    >{{ statusStyle(appt.estado).label }}</span
                  >
                </div>
                <p
                  v-if="!recentAppointments.length"
                  class="text-xs text-ink/45 italic text-center py-8"
                >
                  Sin actividad reciente
                </p>
              </div>

              <div v-else-if="activeTab === 'stations'">
                <div class="flex items-center justify-between mb-4">
                  <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/50">
                    Ocupación en tiempo real
                  </p>
                  <span
                    class="flex items-center gap-1 text-[8px] font-black uppercase text-emerald-400"
                  >
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>Live
                  </span>
                </div>
                <template v-if="(kpis.barbers_status ?? []).length">
                  <div class="mb-4 flex gap-3">
                    <div
                      class="flex-1 rounded-xl border border-red-500/20 bg-red-500/[0.04] p-3 text-center"
                    >
                      <p class="text-lg font-black text-red-400">{{ busyStatuses.length }}</p>
                      <p class="text-[8px] font-black uppercase text-red-400/80">Ocupados</p>
                    </div>
                    <div
                      class="flex-1 rounded-xl border border-emerald-500/15 bg-emerald-500/[0.04] p-3 text-center"
                    >
                      <p class="text-lg font-black text-emerald-400">{{ freeStatuses.length }}</p>
                      <p class="text-[8px] font-black uppercase text-emerald-400/80">Libres</p>
                    </div>
                  </div>

                  <div v-if="busyStatuses.length" class="grid grid-cols-2 gap-3">
                    <div
                      v-for="st in busyStatuses"
                      :key="st.name"
                      class="rounded-xl border border-red-500/20 bg-red-500/[0.04] p-3 text-center"
                    >
                      <div class="relative inline-flex mb-2">
                        <div
                          class="h-9 w-9 rounded-lg bg-ink/[0.05] border border-ink/[0.08] flex items-center justify-center text-[11px] font-black text-gold"
                        >
                          {{ st.name.slice(0, 2).toUpperCase() }}
                        </div>
                        <span
                          class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-[#111] bg-red-500"
                        ></span>
                      </div>
                      <p class="text-[10px] font-black text-ink truncate">
                        {{ st.name.split(' ')[0] }}
                      </p>
                      <p class="text-[8px] font-black uppercase text-red-400">Ocupado</p>
                      <div class="mt-1.5 h-0.5 w-full bg-ink/5 rounded-full overflow-hidden">
                        <div
                          class="h-full bg-gold rounded-full"
                          :style="{ width: `${st.progress}%` }"
                        ></div>
                      </div>
                    </div>
                  </div>
                  <p v-else class="text-xs text-ink/45 italic text-center py-4">
                    Nadie está atendiendo ahora mismo.
                  </p>

                  <template v-if="freeStatuses.length">
                    <button
                      type="button"
                      class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-ink/[0.06] bg-ink/[0.02] py-2.5 text-[9px] font-black uppercase tracking-widest text-ink/50 hover:text-ink/70 transition"
                      @click="showFree = !showFree"
                    >
                      <span>{{
                        showFree ? 'Ocultar libres' : `Ver ${freeStatuses.length} libres`
                      }}</span>
                      <svg
                        class="h-3 w-3 transition-transform"
                        :class="showFree ? 'rotate-180' : ''"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M19 9l-7 7-7-7"
                        />
                      </svg>
                    </button>
                    <div v-if="showFree" class="mt-3 grid grid-cols-2 gap-3">
                      <div
                        v-for="st in freeStatuses"
                        :key="st.name"
                        class="rounded-xl border border-emerald-500/15 bg-emerald-500/[0.04] p-3 text-center"
                      >
                        <div class="relative inline-flex mb-2">
                          <div
                            class="h-9 w-9 rounded-lg bg-ink/[0.05] border border-ink/[0.08] flex items-center justify-center text-[11px] font-black text-gold"
                          >
                            {{ st.name.slice(0, 2).toUpperCase() }}
                          </div>
                          <span
                            class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-[#111] bg-emerald-500"
                          ></span>
                        </div>
                        <p class="text-[10px] font-black text-ink truncate">
                          {{ st.name.split(' ')[0] }}
                        </p>
                        <p class="text-[8px] font-black uppercase text-emerald-400">Libre</p>
                      </div>
                    </div>
                  </template>
                </template>
                <p v-else class="text-xs text-ink/45 italic text-center py-8">
                  Sin barberos activos
                </p>
              </div>

              <div v-else-if="activeTab === 'topbarber'">
                <div
                  v-if="kpis.top_barber_name"
                  class="flex flex-col items-center text-center py-4"
                >
                  <div
                    class="h-16 w-16 rounded-2xl bg-gradient-to-br from-gold/20 to-gold/5 border border-gold/20 flex items-center justify-center text-2xl font-black text-gold mb-3"
                  >
                    {{ kpis.top_barber_name.slice(0, 2).toUpperCase() }}
                  </div>
                  <p class="text-base font-black text-ink uppercase">{{ kpis.top_barber_name }}</p>
                  <p class="text-[9px] text-ink/50 font-bold uppercase tracking-widest mt-0.5">
                    Mejor del mes
                  </p>
                  <div
                    class="mt-3 inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gold/15 bg-gold/[0.06]"
                  >
                    <svg
                      class="h-3.5 w-3.5 text-gold"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                      />
                    </svg>
                    <p class="text-sm font-black text-gold">{{ kpis.top_barber_total }}</p>
                    <p class="text-[9px] text-ink/50 font-bold uppercase tracking-wider">citas</p>
                  </div>
                  <div class="flex gap-0.5 mt-3">
                    <svg
                      v-for="s in 5"
                      :key="s"
                      class="h-3.5 w-3.5 text-gold"
                      viewBox="0 0 20 20"
                      fill="currentColor"
                    >
                      <path
                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                      />
                    </svg>
                  </div>
                </div>
                <p v-else class="text-xs text-ink/45 italic text-center py-8">Sin datos este mes</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Analítica avanzada (plegable) -->
      <section class="space-y-5">
        <button
          type="button"
          class="w-full flex items-center gap-3 rounded-2xl border border-ink/[0.06] bg-card px-5 py-4 hover:border-ink/12 transition-all"
          :aria-expanded="analyticsOpen"
          @click="toggleAnalytics"
        >
          <svg
            class="h-4 w-4 text-gold shrink-0 transition-transform duration-200"
            :class="analyticsOpen ? 'rotate-90' : ''"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2.5"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
          <div class="text-left">
            <p class="text-[11px] font-black uppercase tracking-widest text-ink">
              Analítica avanzada
            </p>
            <p class="text-[9px] text-ink/45 font-bold">
              4 gráficas · predicciones IA · telemetría chatbot
            </p>
          </div>
          <span class="ml-auto text-[9px] font-black uppercase tracking-widest text-gold/70">{{
            analyticsOpen ? 'Ocultar' : 'Ver'
          }}</span>
        </button>

        <div v-if="analyticsOpen" class="space-y-5">
          <section class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="rounded-2xl border border-ink/[0.06] bg-card p-5">
              <div class="flex items-center justify-between mb-5">
                <div>
                  <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/50">
                    Últimas 8 semanas
                  </p>
                  <h3 class="text-sm font-black text-ink uppercase mt-0.5">
                    Tendencia de Ingresos
                  </h3>
                </div>
                <div class="h-2 w-2 rounded-full bg-emerald-400" title="Ingresos ($)"></div>
              </div>
              <div v-if="hasIncome" class="h-52">
                <Line :data="incomeData" :options="incomeOptions" />
              </div>
              <div
                v-else
                class="h-52 flex items-center justify-center border border-dashed border-ink/[0.06] rounded-xl"
              >
                <p class="text-xs text-ink/45 uppercase tracking-widest font-bold">
                  Sin ingresos aún
                </p>
              </div>
            </div>

            <div class="rounded-2xl border border-ink/[0.06] bg-card p-5">
              <div class="flex items-center justify-between mb-5">
                <div>
                  <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/50">
                    Distribución
                  </p>
                  <h3 class="text-sm font-black text-ink uppercase mt-0.5">Demanda de Servicios</h3>
                </div>
              </div>
              <div v-if="hasServices" class="h-52">
                <Doughnut :data="servicesData" :options="servicesOptions" />
              </div>
              <div
                v-else
                class="h-52 flex items-center justify-center border border-dashed border-ink/[0.06] rounded-xl"
              >
                <p class="text-xs text-ink/45 uppercase tracking-widest font-bold">
                  Sin servicios registrados
                </p>
              </div>
            </div>

            <div class="rounded-2xl border border-ink/[0.06] bg-card p-5">
              <div class="flex items-center justify-between mb-5">
                <div>
                  <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/50">
                    Este mes
                  </p>
                  <h3 class="text-sm font-black text-ink uppercase mt-0.5">Desempeño Barberos</h3>
                </div>
                <div class="flex gap-3 text-[8px] font-black uppercase text-ink/45">
                  <span class="flex items-center gap-1"
                    ><span class="h-2 w-2 rounded-sm bg-blue-500"></span>Citas</span
                  >
                  <span class="flex items-center gap-1"
                    ><span class="h-2 w-2 rounded-sm bg-emerald-500"></span>Ingresos</span
                  >
                </div>
              </div>
              <div v-if="hasBarberPerformance" class="grid grid-cols-2 gap-4 h-52">
                <div class="min-w-0">
                  <Bar :data="barberCitasData" :options="barberCitasOptions" />
                </div>
                <div class="min-w-0">
                  <Bar :data="barberIngresosData" :options="barberIngresosOptions" />
                </div>
              </div>
              <div
                v-else
                class="h-52 flex items-center justify-center border border-dashed border-ink/[0.06] rounded-xl"
              >
                <p class="text-xs text-ink/45 uppercase tracking-widest font-bold">
                  Sin datos de desempeño
                </p>
              </div>
            </div>

            <div class="rounded-2xl border border-ink/[0.06] bg-card p-5">
              <div class="flex items-center justify-between mb-5">
                <div>
                  <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/50">
                    Mes actual
                  </p>
                  <h3 class="text-sm font-black text-ink uppercase mt-0.5">
                    Tendencia de Clientes
                  </h3>
                </div>
                <div class="h-2 w-2 rounded-full bg-purple-400"></div>
              </div>
              <div v-if="hasClientTrends" class="h-52">
                <Line :data="clientTrendsData" :options="clientTrendsOptions" />
              </div>
              <div
                v-else
                class="h-52 flex items-center justify-center border border-dashed border-ink/[0.06] rounded-xl"
              >
                <p class="text-xs text-ink/45 uppercase tracking-widest font-bold">
                  Sin datos de tendencias
                </p>
              </div>
            </div>
          </section>

          <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-5 rounded-2xl border border-ink/[0.06] bg-card p-5">
              <div class="flex items-center justify-between mb-5">
                <div>
                  <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/50">
                    Próximos 7 días
                  </p>
                  <h3 class="text-sm font-black text-ink uppercase mt-0.5 flex items-center gap-2">
                    Predicciones IA
                    <span
                      class="text-[8px] font-black uppercase tracking-widest border border-indigo-500/30 bg-indigo-500/10 text-indigo-400 px-1.5 py-0.5 rounded-full"
                      >Beta</span
                    >
                  </h3>
                </div>
              </div>

              <div class="grid grid-cols-3 gap-3 mb-5">
                <div class="rounded-[8px] border border-ink/[0.05] bg-ink/[0.02] p-3 text-center">
                  <p class="text-[8px] font-black uppercase tracking-wider text-ink/50 mb-2">
                    Ingresos Est.
                  </p>
                  <p class="text-lg font-black text-emerald-400">
                    <span
                      v-if="incomeForecast === null"
                      class="inline-block w-8 h-1 bg-emerald-500/25 rounded animate-pulse"
                    ></span>
                    <template v-else>{{ incomeForecast }}</template>
                  </p>
                </div>
                <div class="rounded-[8px] border border-ink/[0.05] bg-ink/[0.02] p-3 text-center">
                  <p class="text-[8px] font-black uppercase tracking-wider text-ink/50 mb-2">
                    Citas Est.
                  </p>
                  <p class="text-lg font-black text-blue-400">
                    <span
                      v-if="appointmentForecast === null"
                      class="inline-block w-8 h-1 bg-blue-500/25 rounded animate-pulse"
                    ></span>
                    <template v-else>{{ appointmentForecast }}</template>
                  </p>
                </div>
                <div class="rounded-[8px] border border-ink/[0.05] bg-ink/[0.02] p-3 text-center">
                  <p class="text-[8px] font-black uppercase tracking-wider text-ink/50 mb-2">
                    Confianza
                  </p>
                  <p class="text-lg font-black text-indigo-400">
                    <span
                      v-if="aiConfidence === null"
                      class="inline-block w-8 h-1 bg-indigo-500/25 rounded animate-pulse"
                    ></span>
                    <template v-else>{{ aiConfidence }}</template>
                  </p>
                </div>
              </div>

              <div>
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-ink/45 mb-3">
                  Insights
                </p>
                <div class="space-y-2">
                  <div
                    v-if="aiInsights === null"
                    class="flex items-start gap-2 p-3 rounded-xl border border-ink/[0.05] bg-ink/[0.02] animate-pulse"
                  >
                    <div class="h-1.5 w-1.5 rounded-full bg-amber-400 mt-1.5 shrink-0"></div>
                    <p class="text-[10px] text-ink/45">Cargando análisis...</p>
                  </div>
                  <p v-else-if="!aiInsights.length" class="text-[10px] text-ink/45 italic">
                    Sin insights disponibles.
                  </p>
                  <div
                    v-for="(insight, index) in aiInsights ?? []"
                    :key="index"
                    class="flex items-start gap-2 p-3 rounded-xl border"
                    :class="(AI_STATUS_STYLE[insight.status] ?? AI_STATUS_STYLE.neutral).cls"
                  >
                    <div
                      class="h-1.5 w-1.5 rounded-full mt-1.5 shrink-0"
                      :class="(AI_STATUS_STYLE[insight.status] ?? AI_STATUS_STYLE.neutral).dot"
                    ></div>
                    <p class="text-[10px] text-ink/60">{{ insight.message }}</p>
                  </div>
                </div>
              </div>
            </div>

            <div class="lg:col-span-7 rounded-2xl border border-ink/[0.06] bg-card p-5">
              <div class="flex items-center justify-between mb-5">
                <div>
                  <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/50">
                    Últimos {{ chatbotTelemetry.window_days ?? 7 }} días
                  </p>
                  <h3 class="text-sm font-black text-ink uppercase mt-0.5 flex items-center gap-2">
                    Telemetría Chatbot
                    <span
                      class="flex items-center gap-1 text-[8px] font-black uppercase text-emerald-400"
                    >
                      <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>OK
                    </span>
                  </h3>
                </div>
              </div>

              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                <div class="rounded-[8px] border border-ink/[0.05] bg-ink/[0.02] p-3">
                  <p class="text-[8px] font-black uppercase tracking-wider text-ink/50 mb-1.5">
                    Eventos
                  </p>
                  <p class="text-lg font-black text-blue-400">
                    {{ chatbotTelemetry.total_requests ?? 0 }}
                  </p>
                </div>
                <div class="rounded-[8px] border border-ink/[0.05] bg-ink/[0.02] p-3">
                  <p class="text-[8px] font-black uppercase tracking-wider text-ink/50 mb-1.5">
                    Error Rate
                  </p>
                  <p class="text-lg font-black text-red-400">
                    {{ (chatbotTelemetry.error_rate_pct ?? 0).toFixed(1) }}%
                  </p>
                </div>
                <div class="rounded-[8px] border border-ink/[0.05] bg-ink/[0.02] p-3">
                  <p class="text-[8px] font-black uppercase tracking-wider text-ink/50 mb-1.5">
                    Latencia Prom.
                  </p>
                  <p class="text-lg font-black text-sky-400">
                    {{ chatbotTelemetry.avg_latency_ms ?? 0 }}ms
                  </p>
                </div>
                <div class="rounded-[8px] border border-ink/[0.05] bg-ink/[0.02] p-3">
                  <p class="text-[8px] font-black uppercase tracking-wider text-ink/50 mb-1.5">
                    Costo Est.
                  </p>
                  <p class="text-lg font-black text-emerald-400">
                    ${{ (chatbotTelemetry.estimated_cost_usd ?? 0).toFixed(4) }}
                  </p>
                </div>
              </div>

              <div
                v-if="
                  chatbotTelemetry.top_sources && Object.keys(chatbotTelemetry.top_sources).length
                "
              >
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-ink/45 mb-3">
                  Top Fuentes
                </p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  <div
                    v-for="(count, source) in chatbotTelemetry.top_sources"
                    :key="source"
                    class="rounded-xl border border-ink/[0.05] bg-ink/[0.02] px-3 py-2 flex items-center justify-between"
                  >
                    <span class="text-[9px] font-bold text-ink uppercase truncate">{{
                      source.replace(/_/g, ' ')
                    }}</span>
                    <span class="text-[9px] font-black text-gold ml-2 shrink-0">{{ count }}</span>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
