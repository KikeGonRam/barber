<script setup>
/**
 * Header compartido de los 4 dashboards por rol — puerto del bloque
 * <x-slot name="header"> al inicio de resources/views/dashboard.blade.php
 * (título "Panel {rol}", fecha, badge "Sistema Activo"). Faltó en la Fase 4
 * (Recepcion.vue se migró sin este header) — se retroactivó al construir
 * este componente en la Fase 5. `todayLabel` viene precalculado del
 * controlador (Carbon::translatedFormat) para no reimplementar formateo de
 * fechas en español en JS.
 *
 * El slot por defecto es para el botón extra que solo ve administrador
 * (mantenimiento/backup) — vacío en los otros 3 roles.
 */
defineProps({
  label: { type: String, required: true },
  color: { type: String, default: 'text-gold' },
  todayLabel: { type: String, required: true },
});
</script>

<template>
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <p class="text-[9px] font-black uppercase tracking-[0.3em] text-ink/50 mb-1">
        UrbanBlade · Dashboard
      </p>
      <h2 class="text-xl font-black text-ink uppercase tracking-tight">
        Panel <span :class="color">{{ label }}</span>
      </h2>
      <p class="text-[10px] text-ink/50 font-bold mt-0.5 uppercase tracking-wider">
        {{ todayLabel }}
      </p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <span
        class="flex items-center gap-1.5 px-3 py-2 rounded-xl border border-ink/8 bg-ink/[0.03] text-[9px] font-black uppercase tracking-widest text-ink/40"
      >
        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
        Sistema Activo
      </span>
      <slot />
    </div>
  </div>
</template>
