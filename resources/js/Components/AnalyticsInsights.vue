<script setup>
/**
 * Puerto a Vue de resources/views/components/analytics-insights.blade.php,
 * PERO solo la variante SIN gráfica: los 4 dashboards por rol siempre la
 * invocan sin showCharts (default false en el Blade original), así que la
 * rama con matriz/heatmap/canvas de Chart.js nunca se ejecutaba ahí — solo
 * la usa resources/views/analytics/index.blade.php (showCharts=true), que
 * todavía no está migrada. Si esa página se migra en el futuro, esa rama
 * (matrix/heatmap/factor-list/canvas) se agrega aquí como extensión, no se
 * reimplementa desde cero.
 *
 * `insights` ya viene con los campos de presentación precalculados en PHP
 * (ver App\Models\AnalyticsInsight::toDashboardCardArray()) — este
 * componente solo pinta, no reimplementa reglas de truncado/porcentaje.
 */
defineProps({
  insights: { type: Array, default: () => [] },
  titulo: { type: String, default: 'Analítica avanzada' },
});

const PALETTE = {
  gold: {
    border: 'border-gold/20',
    bg: 'bg-[linear-gradient(180deg,rgba(212,175,55,.07),rgba(255,255,255,.025))]',
    texto: 'text-gold',
    dot: 'bg-gold',
  },
  success: {
    border: 'border-emerald-400/20',
    bg: 'bg-[linear-gradient(180deg,rgba(52,211,153,.07),rgba(255,255,255,.025))]',
    texto: 'text-emerald-300',
    dot: 'bg-emerald-400',
  },
  warning: {
    border: 'border-amber-400/20',
    bg: 'bg-[linear-gradient(180deg,rgba(245,158,11,.075),rgba(255,255,255,.025))]',
    texto: 'text-amber-300',
    dot: 'bg-amber-400',
  },
  danger: {
    border: 'border-rose-400/20',
    bg: 'bg-[linear-gradient(180deg,rgba(251,113,133,.07),rgba(255,255,255,.025))]',
    texto: 'text-rose-300',
    dot: 'bg-rose-400',
  },
  info: {
    border: 'border-sky-400/20',
    bg: 'bg-[linear-gradient(180deg,rgba(56,189,248,.07),rgba(255,255,255,.025))]',
    texto: 'text-sky-300',
    dot: 'bg-sky-400',
  },
};

function tone(color) {
  return PALETTE[color] ?? PALETTE.gold;
}
</script>

<template>
  <section v-if="insights.length" :aria-label="titulo">
    <div class="mb-3 flex items-center justify-between gap-3 px-1">
      <div class="flex items-center gap-2">
        <span class="h-2 w-2 rounded-full bg-gold shadow-[0_0_16px_rgba(212,175,55,.45)]"></span>
        <h3 class="text-[11px] font-black uppercase tracking-[0.18em] text-gold">{{ titulo }}</h3>
      </div>
      <span class="hidden text-[10px] font-bold text-ink/35 sm:inline"
        >{{ insights.length }} resultados</span
      >
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
      <article
        v-for="(insight, index) in insights"
        :key="insight.titulo + index"
        class="ub-analytics-card group overflow-hidden rounded-2xl border p-4 transition duration-300 hover:-translate-y-0.5 hover:border-ink/15 sm:p-5"
        :class="[tone(insight.color).border, tone(insight.color).bg]"
      >
        <div class="space-y-4">
          <div class="min-w-0">
            <div class="mb-4 flex items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                  <span
                    class="rounded-full border border-ink/[0.08] bg-ink/[0.04] px-2 py-0.5 text-[8px] font-black uppercase tracking-[0.16em] text-ink/42"
                    >{{ insight.visual_label }}</span
                  >
                </div>
                <p
                  class="text-[10px] font-black uppercase tracking-[0.18em]"
                  :class="tone(insight.color).texto"
                >
                  {{ insight.titulo }}
                </p>
                <p class="mt-1 text-2xl font-black leading-tight text-ink sm:text-3xl">
                  {{ insight.dato }}
                </p>
              </div>
              <span
                class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-ink/[0.08] bg-ink/[0.035]"
              >
                <span class="h-2 w-2 rounded-full" :class="tone(insight.color).dot"></span>
              </span>
            </div>

            <div class="rounded-xl border border-ink/[0.06] bg-black/15 p-4">
              <div
                v-if="insight.progress_value !== null"
                class="mb-4 h-2 overflow-hidden rounded-full bg-ink/[0.07]"
              >
                <span
                  class="block h-full rounded-full"
                  :class="tone(insight.color).dot"
                  :style="{ width: insight.progress_value + '%' }"
                ></span>
              </div>
              <div v-else class="mb-4 flex gap-1">
                <span class="h-1.5 flex-1 rounded-full" :class="tone(insight.color).dot"></span>
                <span
                  class="h-1.5 flex-1 rounded-full opacity-70"
                  :class="tone(insight.color).dot"
                ></span>
                <span
                  class="h-1.5 flex-1 rounded-full opacity-40"
                  :class="tone(insight.color).dot"
                ></span>
              </div>
              <p class="max-w-2xl text-sm leading-relaxed text-ink/58">{{ insight.brief }}</p>
            </div>
          </div>

          <details
            v-if="insight.is_truncated"
            class="group/details rounded-xl border border-ink/[0.07] bg-ink/[0.025] px-3 py-2"
          >
            <summary
              class="flex cursor-pointer list-none items-center justify-between gap-3 text-[10px] font-black uppercase tracking-[0.16em] text-ink/45 transition-colors hover:text-gold"
            >
              <span>Ver hallazgo</span>
              <svg
                class="h-3.5 w-3.5 transition-transform group-open/details:rotate-180"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
              >
                <path
                  fill-rule="evenodd"
                  d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z"
                  clip-rule="evenodd"
                />
              </svg>
            </summary>
            <p class="mt-3 text-[12px] leading-relaxed text-ink/62">{{ insight.mensaje }}</p>
          </details>
        </div>
      </article>
    </div>
  </section>
</template>
