<template>
  <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-lg shadow-lg p-6 text-white">
    <!-- Header con ícono -->
    <div class="flex items-center justify-between mb-4">
      <span class="text-3xl">{{ icon }}</span>
      <span v-if="trendUp" class="text-green-300 text-sm font-semibold">{{ trend }}</span>
      <span v-else class="text-red-300 text-sm font-semibold">{{ trend }}</span>
    </div>

    <!-- Título y valor -->
    <div>
      <p class="text-blue-100 text-sm font-medium">{{ title }}</p>
      <h3 class="text-2xl font-bold mt-1">{{ value }}</h3>
    </div>

    <!-- Indicador de tendencia -->
    <div class="mt-4 pt-4 border-t border-blue-400 border-opacity-30">
      <p class="text-xs text-blue-100">
        <span v-if="trendUp" class="text-green-300">📈</span>
        <span v-else class="text-red-300">📉</span>
        {{ trendUp ? 'Crecimiento' : 'Disminución' }} respecto a ayer
      </p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'KPICard',
  props: {
    title: {
      type: String,
      required: true,
    },
    value: {
      type: [String, Number],
      required: true,
    },
    icon: {
      type: String,
      default: '📊',
    },
    trend: {
      type: String,
      default: '+0%',
    },
    trendUp: {
      type: Boolean,
      default: true,
    },
  },
};
</script>

<style scoped>
/* Estilos adicionales si es necesario */
</style>
