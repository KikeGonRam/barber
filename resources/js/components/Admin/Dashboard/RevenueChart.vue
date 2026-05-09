<template>
  <div class="bg-white rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-6">
      <h3 class="text-xl font-bold text-gray-800">Ingresos Últimos 7 Días</h3>
      <select v-model="timeFrame" class="px-3 py-1 border rounded text-sm">
        <option value="week">Esta Semana</option>
        <option value="month">Este Mes</option>
        <option value="year">Este Año</option>
      </select>
    </div>

    <div class="h-80">
      <canvas ref="revenueChart"></canvas>
    </div>

    <!-- Estadísticas bajo el gráfico -->
    <div class="grid grid-cols-3 gap-4 mt-6 pt-6 border-t">
      <div>
        <p class="text-gray-600 text-sm">Ingresos Totales</p>
        <p class="text-2xl font-bold text-green-600">${{ totalRevenue.toLocaleString() }}</p>
      </div>
      <div>
        <p class="text-gray-600 text-sm">Promedio Diario</p>
        <p class="text-2xl font-bold text-blue-600">${{ averageRevenue.toLocaleString() }}</p>
      </div>
      <div>
        <p class="text-gray-600 text-sm">Día Mayor</p>
        <p class="text-2xl font-bold text-purple-600">${{ maxRevenue.toLocaleString() }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

export default {
  name: 'RevenueChart',
  data() {
    return {
      timeFrame: 'week',
      chart: null,
      revenueData: [1200, 1400, 1100, 1600, 1300, 1700, 1250],
      labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
    };
  },
  computed: {
    totalRevenue() {
      return this.revenueData.reduce((a, b) => a + b, 0);
    },
    averageRevenue() {
      return Math.round(this.totalRevenue / this.revenueData.length);
    },
    maxRevenue() {
      return Math.max(...this.revenueData);
    },
  },
  watch: {
    timeFrame() {
      this.updateChart();
    },
  },
  mounted() {
    this.initChart();
  },
  methods: {
    initChart() {
      const ctx = this.$refs.revenueChart;
      if (!ctx) return;

      this.chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: this.labels,
          datasets: [
            {
              label: 'Ingresos ($)',
              data: this.revenueData,
              borderColor: '#10b981',
              backgroundColor: 'rgba(16, 185, 129, 0.1)',
              borderWidth: 3,
              fill: true,
              tension: 0.4,
              pointBackgroundColor: '#10b981',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              pointRadius: 5,
              pointHoverRadius: 7,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false,
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: 'rgba(0, 0, 0, 0.05)',
              },
              ticks: {
                callback: function (value) {
                  return '$' + value.toLocaleString();
                },
              },
            },
            x: {
              grid: {
                display: false,
              },
            },
          },
        },
      });
    },
    updateChart() {
      if (!this.chart) return;

      // En una app real, aquí iría una llamada API
      if (this.timeFrame === 'month') {
        this.labels = ['Semana 1', 'Semana 2', 'Semana 3', 'Semana 4'];
        this.revenueData = [5200, 6400, 5100, 6600];
      } else if (this.timeFrame === 'year') {
        this.labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        this.revenueData = [15000, 18000, 16000, 19000, 21000, 20000, 22000, 19000, 18000, 17000, 16000, 24000];
      } else {
        this.labels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        this.revenueData = [1200, 1400, 1100, 1600, 1300, 1700, 1250];
      }

      this.chart.data.labels = this.labels;
      this.chart.data.datasets[0].data = this.revenueData;
      this.chart.update();
    },
  },
};
</script>

<style scoped>
/* Estilos adicionales si es necesario */
</style>
