<script setup>
import { route } from 'ziggy-js';

defineProps({
  titulo: { type: String, default: 'Analítica avanzada' },
  descripcion: {
    type: String,
    default: 'Patrones, predicciones y recomendaciones calculadas sobre tu historial.',
  },
  cta: { type: String, default: 'Ver analítica' },
});
</script>

<template>
  <a
    :href="route('analytics.index')"
    class="group grid gap-4 rounded-[8px] border border-ink/[0.08] bg-card p-4 shadow-[0_18px_45px_rgba(0,0,0,.18)] transition-all hover:border-gold/30 hover:bg-gold/[0.035] sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center"
  >
    <div
      class="flex h-11 w-11 items-center justify-center rounded-[8px] border border-gold/20 bg-gold/10 text-gold"
    >
      <svg
        class="h-5 w-5"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="1.9"
        aria-hidden="true"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18" />
        <path stroke-linecap="round" stroke-linejoin="round" d="m18.7 8.3-4.2 4.2-2.8-2.8L7 14.4" />
      </svg>
    </div>

    <div class="min-w-0">
      <p class="text-[9px] font-black uppercase tracking-[0.22em] text-gold/70">
        UrbanBlade Analytics
      </p>
      <h3 class="mt-1 text-sm font-black uppercase tracking-tight text-ink">{{ titulo }}</h3>
      <p class="mt-1 max-w-2xl text-[12px] leading-relaxed text-ink/48">{{ descripcion }}</p>
    </div>

    <span
      class="inline-flex h-10 items-center justify-center gap-2 rounded-[8px] bg-gold px-4 text-[10px] font-black uppercase tracking-widest text-black transition-all group-hover:bg-[#f0cc55]"
    >
      {{ cta }}
      <svg
        class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2.5"
        aria-hidden="true"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
      </svg>
    </span>
  </a>
</template>
