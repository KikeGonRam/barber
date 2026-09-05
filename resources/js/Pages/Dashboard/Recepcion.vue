<script setup>
/**
 * Migración del dashboard de Recepcionista a Inertia+Vue (ver
 * .claude/skills/inertia-vue-migration/SKILL.md, Fase 4). Los otros 3 roles
 * (administrador/barbero/cliente) siguen renderizados por
 * resources/views/dashboard.blade.php hasta su propia fase — DashboardController
 * elige entre Blade e Inertia según el rol autenticado.
 *
 * Todos los enlaces de esta página son <a> normales (no <Link> de Inertia):
 * ninguno de sus destinos (citas, pagos, pedidos, clientes, inventario) está
 * migrado todavía — ver el gotcha "<Link> hacia ruta todavía-Blade" en el
 * SKILL.md.
 */
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { Line } from 'vue-chartjs';
import { route } from 'ziggy-js';
import { chartScale, fmtInt } from '../../chart-theme';
import AppLayout from '@/Layouts/AppLayout.vue';
import AnalyticsInsights from '@/Components/AnalyticsInsights.vue';
import AnalyticsCta from '@/Components/AnalyticsCta.vue';
import DashboardHeader from '@/Components/DashboardHeader.vue';

const props = defineProps({
  todayLabel: { type: String, required: true },
  kpis: { type: Object, required: true },
  nextAppointments: { type: Array, required: true },
  pendingOrders: { type: Array, required: true },
  flowChart: { type: Object, required: true },
  sparkHighlights: { type: Array, default: () => [] },
});

const page = usePage();
const firstName = computed(() => (page.props.auth?.user?.name ?? 'Recepción').split(' ')[0]);

const kpiCards = computed(() => [
  {
    label: 'Citas Hoy',
    val: props.kpis.appointments_today,
    text: 'text-indigo-400',
    line: 'from-indigo-500/60',
    hover: 'hover:border-indigo-500/25',
    href: route('appointments.index'),
  },
  {
    label: 'Cobrado Hoy',
    val: `$${Number(props.kpis.collected_today ?? 0).toLocaleString('es-MX', { maximumFractionDigits: 0 })}`,
    text: 'text-emerald-400',
    line: 'from-emerald-500/60',
    hover: 'hover:border-emerald-500/25',
    href: route('payments.index'),
  },
  {
    label: 'Cobros Pend.',
    val: props.kpis.pending_payments,
    text: 'text-amber-400',
    line: 'from-amber-500/60',
    hover: 'hover:border-amber-500/25',
    href: route('payments.create'),
  },
  {
    label: 'Pedidos',
    val: props.kpis.pending_orders ?? 0,
    text: 'text-cyan-400',
    line: 'from-cyan-500/60',
    hover: 'hover:border-cyan-500/25',
    href: route('orders.index'),
  },
  {
    label: 'Nuevos Clientes',
    val: props.kpis.new_clients_today,
    text: 'text-indigo-400',
    line: 'from-indigo-500/60',
    hover: 'hover:border-indigo-500/25',
    href: route('clients.index'),
  },
  {
    label: 'Stock Crítico',
    val: props.kpis.low_stock_count,
    text: 'text-red-400',
    line: 'from-red-500/60',
    hover: 'hover:border-red-500/25',
    href: route('inventory.products.index'),
  },
]);

const hasFlow = computed(() => (props.flowChart.values ?? []).some((v) => v));

const flowChartData = computed(() => ({
  labels: props.flowChart.labels ?? [],
  datasets: [
    {
      label: 'Citas',
      data: props.flowChart.values ?? [],
      borderColor: '#6366f1',
      backgroundColor: 'rgba(99,102,241,0.1)',
      borderWidth: 2.5,
      fill: true,
      cubicInterpolationMode: 'monotone',
      pointRadius: 3,
      pointHoverRadius: 6,
      pointBackgroundColor: '#0d0d0d',
      pointBorderColor: '#6366f1',
      pointBorderWidth: 2,
    },
  ],
}));

const flowChartOptions = {
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
    y: {
      ...chartScale,
      beginAtZero: true,
      ticks: { ...chartScale.ticks, stepSize: 1, precision: 0 },
    },
    x: chartScale,
  },
};
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout>
    <template #header>
      <DashboardHeader label="Operativo" color="text-indigo-400" :today-label="todayLabel" />
    </template>

    <div class="space-y-5">
      <!-- Bienvenida -->
      <section class="rounded-2xl border border-ink/[0.06] bg-card p-5 relative overflow-hidden">
        <div
          class="absolute -right-8 -bottom-8 h-32 w-32 rounded-full bg-indigo-500/5 blur-2xl"
        ></div>
        <div class="relative flex flex-col sm:flex-row sm:items-center gap-4">
          <div>
            <p class="text-[9px] font-black uppercase tracking-[0.3em] text-ink/50">Recepción</p>
            <h3 class="text-base font-black text-ink uppercase mt-0.5">
              Hola, <span class="text-indigo-400">{{ firstName }}</span>
            </h3>
            <p class="text-[10px] text-ink/50 mt-1">Centro de mando activo.</p>
          </div>
          <div class="sm:ml-auto flex flex-wrap gap-2">
            <a :href="route('appointments.create')" class="ui-btn px-4 py-2 text-[9px]"
              >+ Cita (walk-in)</a
            >
            <a
              :href="route('payments.create')"
              class="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-ink/10 bg-ink/[0.03] text-[9px] font-black uppercase tracking-widest text-ink/50 hover:text-ink transition-all"
              >Cobrar</a
            >
            <a
              :href="route('orders.index')"
              class="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-ink/10 bg-ink/[0.03] text-[9px] font-black uppercase tracking-widest text-ink/50 hover:text-ink transition-all"
              >Pedidos</a
            >
          </div>
        </div>
      </section>

      <!-- KPIs -->
      <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <a
          v-for="kpi in kpiCards"
          :key="kpi.label"
          :href="kpi.href"
          class="rounded-[8px] border border-ink/[0.06] bg-card p-5 relative overflow-hidden transition-all group"
          :class="kpi.hover"
        >
          <div
            class="absolute top-0 left-0 h-0.5 w-full bg-gradient-to-r to-transparent"
            :class="kpi.line"
          ></div>
          <p class="text-[9px] font-black uppercase tracking-[0.2em] text-ink/50 mb-3">
            {{ kpi.label }}
          </p>
          <p class="text-2xl font-black" :class="kpi.text">{{ kpi.val }}</p>
        </a>
      </section>

      <AnalyticsInsights :insights="sparkHighlights" titulo="Prioridades del turno" />
      <AnalyticsCta
        titulo="Analítica operativa"
        descripcion="Horarios de mayor demanda, clientes por reactivar y productos por reabastecer — para planear mejor el día a día."
        cta="Ver analítica"
      />

      <!-- Próximas llegadas + Flujo -->
      <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-7 rounded-2xl border border-ink/[0.06] bg-card p-5">
          <div class="flex items-center justify-between mb-5">
            <div>
              <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/50">Hoy</p>
              <h3 class="text-sm font-black text-ink uppercase mt-0.5">Próximas Llegadas</h3>
            </div>
            <a
              :href="route('appointments.index')"
              class="text-[9px] font-black uppercase tracking-widest text-ink/50 hover:text-gold transition-colors"
              >Agenda completa →</a
            >
          </div>
          <div class="space-y-2">
            <div
              v-for="appt in nextAppointments"
              :key="appt.id"
              class="flex items-center gap-3 p-3 rounded-xl border border-ink/[0.05] hover:border-indigo-500/20 hover:bg-indigo-500/[0.03] transition-all"
            >
              <div
                class="h-9 w-9 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400 font-black text-xs shrink-0"
              >
                {{ appt.hora_inicio?.slice(0, 2) }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-black text-ink truncate uppercase">{{ appt.cliente }}</p>
                <p class="text-[9px] text-ink/50 truncate">
                  {{ appt.servicio }} · {{ appt.barbero }}
                </p>
              </div>
              <p class="text-xs font-black text-ink shrink-0">
                {{ appt.hora_inicio?.slice(0, 5) }}
              </p>
            </div>
            <div
              v-if="!nextAppointments.length"
              class="py-12 flex items-center justify-center border border-dashed border-ink/[0.06] rounded-xl"
            >
              <p class="text-xs text-ink/45 uppercase tracking-widest font-bold">
                Sin llegadas próximas
              </p>
            </div>
          </div>
        </div>

        <div class="lg:col-span-5 rounded-2xl border border-ink/[0.06] bg-card p-5">
          <div class="mb-5">
            <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/50">
              Distribución horaria
            </p>
            <h3 class="text-sm font-black text-ink uppercase mt-0.5">Flujo Operativo</h3>
          </div>
          <div v-if="hasFlow" class="h-52">
            <Line :data="flowChartData" :options="flowChartOptions" />
          </div>
          <div
            v-else
            class="h-52 flex items-center justify-center border border-dashed border-ink/[0.06] rounded-xl"
          >
            <p class="text-xs text-ink/45 uppercase tracking-widest font-bold">
              Sin flujo registrado hoy
            </p>
          </div>
        </div>
      </section>

      <!-- Pedidos por entregar -->
      <section class="rounded-2xl border border-ink/[0.06] bg-card p-5">
        <div class="flex items-center justify-between mb-5">
          <div>
            <p class="text-[9px] font-black uppercase tracking-[0.25em] text-ink/50">Tienda</p>
            <h3 class="text-sm font-black text-ink uppercase mt-0.5">Pedidos por Entregar</h3>
          </div>
          <a
            :href="route('orders.index')"
            class="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-ink/50 hover:text-gold transition-colors"
          >
            Ir a la bandeja
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
        <a
          v-for="order in pendingOrders"
          :key="order.id"
          :href="route('orders.index')"
          class="flex items-center gap-3 p-3 rounded-xl border border-ink/[0.05] hover:border-cyan-500/20 hover:bg-cyan-500/[0.03] transition-all mb-2 last:mb-0"
        >
          <div
            class="h-9 w-9 rounded-lg bg-cyan-500/10 flex items-center justify-center text-cyan-400 shrink-0"
          >
            <svg
              class="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="1.8"
            >
              <path
                d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"
              />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-xs font-black text-ink truncate">
              {{ order.folio }} · {{ order.cliente }}
            </p>
            <p class="text-[9px] text-ink/45 font-bold truncate">
              {{ order.creadoEn }} · {{ order.itemsCount }} artículo{{
                order.itemsCount !== 1 ? 's' : ''
              }}
            </p>
          </div>
          <p class="text-sm font-black text-gold shrink-0">${{ order.total.toFixed(2) }}</p>
        </a>
        <div
          v-if="!pendingOrders.length"
          class="py-12 flex items-center justify-center border border-dashed border-ink/[0.06] rounded-xl"
        >
          <p class="text-xs text-ink/45 uppercase tracking-widest font-bold">
            Sin pedidos pendientes
          </p>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
