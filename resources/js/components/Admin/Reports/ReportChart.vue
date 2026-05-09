<template>
  <div class="bg-white rounded-lg shadow-lg p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h3 class="text-2xl font-bold text-gray-900">📈 Visualización de Reportes</h3>
      <select v-model="selectedChart" class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="revenue">💰 Ingresos</option>
        <option value="appointments">📅 Citas</option>
        <option value="inventory">📦 Inventario</option>
        <option value="clients">👥 Clientes</option>
      </select>
    </div>

    <!-- Selector de período -->
    <div class="flex gap-4 mb-6 pb-6 border-b">
      <select v-model="selectedPeriod" class="px-4 py-2 border rounded-lg">
        <option value="semana">Esta Semana</option>
        <option value="mes">Este Mes</option>
        <option value="trimestre">Este Trimestre</option>
        <option value="año">Este Año</option>
      </select>
      <button
        @click="refreshChart"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold"
      >
        🔄 Actualizar
      </button>
    </div>

    <!-- Gráfico principal -->
    <div class="mb-6 bg-gray-50 rounded-lg p-4">
      <canvas ref="mainChart" class="h-80"></canvas>
    </div>

    <!-- Métricas key -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-blue-50 rounded-lg p-4 border-l-4 border-blue-600">
        <p class="text-gray-600 text-sm">Total</p>
        <p class="text-2xl font-bold text-blue-600">${{ metrics.total }}</p>
        <p class="text-xs text-gray-600 mt-1">{{ metrics.totalChange }}</p>
      </div>
      <div class="bg-green-50 rounded-lg p-4 border-l-4 border-green-600">
        <p class="text-gray-600 text-sm">Promedio</p>
        <p class="text-2xl font-bold text-green-600">${{ metrics.average }}</p>
        <p class="text-xs text-gray-600 mt-1">Por día</p>
      </div>
      <div class="bg-purple-50 rounded-lg p-4 border-l-4 border-purple-600">
        <p class="text-gray-600 text-sm">Máximo</p>
        <p class="text-2xl font-bold text-purple-600">${{ metrics.max }}</p>
        <p class="text-xs text-gray-600 mt-1">Pico más alto</p>
      </div>
      <div class="bg-orange-50 rounded-lg p-4 border-l-4 border-orange-600">
        <p class="text-gray-600 text-sm">Variación</p>
        <p class="text-2xl font-bold text-orange-600">{{ metrics.variance }}%</p>
        <p class="text-xs text-gray-600 mt-1">Volatilidad</p>
      </div>
    </div>

    <!-- Tabla de detalles -->
    <div class="mb-6">
      <h4 class="text-lg font-bold mb-4">📋 Detalle por Período</h4>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-100 border-b-2 border-gray-300">
              <th class="px-4 py-3 text-left font-bold">Período</th>
              <th class="px-4 py-3 text-right font-bold">Cantidad</th>
              <th class="px-4 py-3 text-right font-bold">Valor</th>
              <th class="px-4 py-3 text-right font-bold">% del Total</th>
              <th class="px-4 py-3 text-center font-bold">Variación</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in detailRows" :key="row.id" class="border-b hover:bg-gray-50">
              <td class="px-4 py-3 font-semibold">{{ row.period }}</td>
              <td class="px-4 py-3 text-right">{{ row.quantity }}</td>
              <td class="px-4 py-3 text-right font-bold">${{ row.value }}</td>
              <td class="px-4 py-3 text-right">{{ row.percentage }}%</td>
              <td class="px-4 py-3 text-center">
                <span :class="row.change >= 0 ? 'text-green-600' : 'text-red-600'" class="font-bold">
                  {{ row.change >= 0 ? '+' : '' }}{{ row.change }}%
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Comparación períodos -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Gráfico de comparación -->
      <div class="bg-gray-50 rounded-lg p-4">
        <h5 class="font-bold mb-3">📊 Comparación Períodos</h5>
        <canvas ref="comparisonChart" class="h-64"></canvas>
      </div>

      <!-- Top 5 -->
      <div class="bg-gray-50 rounded-lg p-4">
        <h5 class="font-bold mb-3">🏆 Top 5</h5>
        <div class="space-y-3">
          <div v-for="(item, idx) in topItems" :key="idx" class="flex items-center justify-between p-2 bg-white rounded border-l-4 border-blue-500">
            <div>
              <p class="font-semibold">{{ idx + 1 }}. {{ item.name }}</p>
              <p class="text-xs text-gray-600">{{ item.details }}</p>
            </div>
            <p class="text-lg font-bold text-blue-600">${{ item.value }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Análisis textual -->
    <div class="mt-6 pt-6 border-t">
      <h4 class="text-lg font-bold mb-4">📝 Análisis</h4>
      <div class="bg-blue-50 rounded-lg p-4 border-l-4 border-blue-600">
        <p class="text-gray-700 leading-relaxed">
          {{ analysisText }}
        </p>
      </div>
    </div>

    <!-- Opciones de exportación -->
    <div class="mt-6 flex gap-3">
      <button
        @click="exportChart('png')"
        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-semibold"
      >
        🖼️ Exportar como Imagen
      </button>
      <button
        @click="exportChart('pdf')"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold"
      >
        📄 Exportar como PDF
      </button>
      <button
        @click="exportChart('excel')"
        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold"
      >
        📊 Exportar como Excel
      </button>
    </div>
  </div>
</template>

<script>
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

export default {
  name: 'ReportChart',
  data() {
    return {
      selectedChart: 'revenue',
      selectedPeriod: 'mes',
      mainChart: null,
      comparisonChart: null,
      metrics: {
        total: 8450,
        average: 282,
        max: 1200,
        variance: 35,
        totalChange: '+12.5% vs mes anterior',
      },
      detailRows: [
        { id: 1, period: 'Semana 1', quantity: 45, value: 1850, percentage: 22, change: 5 },
        { id: 2, period: 'Semana 2', quantity: 52, value: 2150, percentage: 25, change: 8 },
        { id: 3, period: 'Semana 3', quality: 48, value: 1980, percentage: 23, change: -2 },
        { id: 4, period: 'Semana 4', quantity: 50, value: 2470, percentage: 30, change: 12 },
      ],
      topItems: [
        { name: 'Corte + Barba', details: '120 citas', value: 6000 },
        { name: 'Corte Moderno', details: '95 citas', value: 3325 },
        { name: 'Diseño Especial', details: '35 citas', value: 2100 },
        { name: 'Barba Profesional', details: '78 citas', value: 1950 },
        { name: 'Tratamiento Capilar', details: '25 citas', value: 875 },
      ],
      analysisText: 'El mes actual ha mostrado un desempeño positivo con un crecimiento del 12.5% respecto al mes anterior. La semana 4 fue la más productiva con $2,470 en ingresos. El servicio más popular es "Corte + Barba" representando el 71% de los ingresos totales. Se recomienda mantener los niveles actuales de promoción y considerar ofertas especiales para servicios de menor demanda como "Tratamiento Capilar".',
    };
  },
  mounted() {
    this.initMainChart();
    this.initComparisonChart();
  },
  methods: {
    initMainChart() {
      const ctx = this.$refs.mainChart;
      if (!ctx) return;

      this.mainChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
          datasets: [
            {
              label: 'Ingresos ($)',
              data: [650, 750, 800, 900, 1050, 1200, 950],
              borderColor: '#3b82f6',
              backgroundColor: 'rgba(59, 130, 246, 0.1)',
              fill: true,
              tension: 0.4,
              pointRadius: 6,
              pointBackgroundColor: '#3b82f6',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              labels: { font: { size: 12, weight: 'bold' } },
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { callback: (value) => '$' + value },
            },
          },
        },
      });
    },

    initComparisonChart() {
      const ctx = this.$refs.comparisonChart;
      if (!ctx) return;

      this.comparisonChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: ['Mes Anterior', 'Mes Actual'],
          datasets: [
            {
              label: 'Ingresos',
              data: [7500, 8450],
              backgroundColor: ['#93c5fd', '#3b82f6'],
              borderRadius: 5,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              labels: { font: { size: 12, weight: 'bold' } },
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { callback: (value) => '$' + value },
            },
          },
        },
      });
    },

    refreshChart() {
      console.log('Refreshing chart...');
      alert('✅ Gráfico actualizado');
    },

    exportChart(format) {
      console.log(`Exporting chart as ${format}`);
      alert(`📥 Exportando gráfico en formato ${format.toUpperCase()}`);
    },
  },
};
</script>

<style scoped>
/* Estilos adicionales si es necesario */
</style>
