<template>
  <div class="bg-white rounded-lg shadow-lg p-6">
    <!-- Header -->
    <h3 class="text-2xl font-bold text-gray-900 mb-6">Segmentación de Clientes</h3>

    <!-- Segmentos -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <!-- VIP -->
      <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-6 border-2 border-purple-300">
        <div class="flex items-center justify-between mb-4">
          <h4 class="text-lg font-bold text-purple-900">VIP</h4>
          <span class="text-3xl">{{ segments.vip.count }}</span>
        </div>
        <p class="text-sm text-purple-700 mb-4">Clientes frecuentes (>10 citas)</p>
        <div class="bg-purple-200 h-2 rounded-full overflow-hidden">
          <div
            class="bg-purple-600 h-full"
            :style="{ width: (segments.vip.percentage * 100) + '%' }"
          ></div>
        </div>
        <p class="text-xs text-purple-600 mt-2">{{ (segments.vip.percentage * 100).toFixed(1) }}% del total</p>
        <div class="mt-4 space-y-2">
          <p class="text-sm"><strong>Gasto promedio:</strong> ${{ segments.vip.avgSpent }}</p>
          <p class="text-sm"><strong>Calificación:</strong> {{ segments.vip.rating }}</p>
        </div>
        <button
          @click="viewSegment('vip')"
          class="w-full mt-4 px-3 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 transition font-semibold text-sm"
        >
          Ver Clientes
        </button>
      </div>

      <!-- Nuevos -->
      <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-6 border-2 border-green-300">
        <div class="flex items-center justify-between mb-4">
          <h4 class="text-lg font-bold text-green-900">Nuevos</h4>
          <span class="text-3xl">{{ segments.new.count }}</span>
        </div>
        <p class="text-sm text-green-700 mb-4">Últimas 2 semanas</p>
        <div class="bg-green-200 h-2 rounded-full overflow-hidden">
          <div
            class="bg-green-600 h-full"
            :style="{ width: (segments.new.percentage * 100) + '%' }"
          ></div>
        </div>
        <p class="text-xs text-green-600 mt-2">{{ (segments.new.percentage * 100).toFixed(1) }}% del total</p>
        <div class="mt-4 space-y-2">
          <p class="text-sm"><strong>Gasto promedio:</strong> ${{ segments.new.avgSpent }}</p>
          <p class="text-sm"><strong>Citas promedio:</strong> {{ segments.new.avgAppointments }}</p>
        </div>
        <button
          @click="viewSegment('new')"
          class="w-full mt-4 px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition font-semibold text-sm"
        >
          Ver Clientes
        </button>
      </div>

      <!-- Inactivos -->
      <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg p-6 border-2 border-gray-300">
        <div class="flex items-center justify-between mb-4">
          <h4 class="text-lg font-bold text-gray-900">Inactivos</h4>
          <span class="text-3xl">{{ segments.inactive.count }}</span>
        </div>
        <p class="text-sm text-gray-700 mb-4">Sin citas >30 días</p>
        <div class="bg-gray-200 h-2 rounded-full overflow-hidden">
          <div
            class="bg-gray-600 h-full"
            :style="{ width: (segments.inactive.percentage * 100) + '%' }"
          ></div>
        </div>
        <p class="text-xs text-gray-600 mt-2">{{ (segments.inactive.percentage * 100).toFixed(1) }}% del total</p>
        <div class="mt-4 space-y-2">
          <p class="text-sm"><strong>Días sin cita:</strong> {{ segments.inactive.avgDaysSinceAppointment }}</p>
          <p class="text-sm"><strong>Gasto total:</strong> ${{ segments.inactive.totalSpent }}</p>
        </div>
        <button
          @click="viewSegment('inactive')"
          class="w-full mt-4 px-3 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition font-semibold text-sm"
        >
          Recuperar
        </button>
      </div>

      <!-- Deudores -->
      <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-lg p-6 border-2 border-red-300">
        <div class="flex items-center justify-between mb-4">
          <h4 class="text-lg font-bold text-red-900">Deudores</h4>
          <span class="text-3xl">{{ segments.debtors.count }}</span>
        </div>
        <p class="text-sm text-red-700 mb-4">Con pagos pendientes</p>
        <div class="bg-red-200 h-2 rounded-full overflow-hidden">
          <div
            class="bg-red-600 h-full"
            :style="{ width: (segments.debtors.percentage * 100) + '%' }"
          ></div>
        </div>
        <p class="text-xs text-red-600 mt-2">{{ (segments.debtors.percentage * 100).toFixed(1) }}% del total</p>
        <div class="mt-4 space-y-2">
          <p class="text-sm"><strong>Deuda total:</strong> ${{ segments.debtors.totalDebt }}</p>
          <p class="text-sm"><strong>Deuda promedio:</strong> ${{ segments.debtors.avgDebt }}</p>
        </div>
        <button
          @click="viewSegment('debtors')"
          class="w-full mt-4 px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition font-semibold text-sm"
        >
          Cobrar
        </button>
      </div>
    </div>

    <!-- Gráfico de distribución -->
    <div class="mt-8 pt-6 border-t">
      <h4 class="text-lg font-bold mb-4">Distribución por Segmento</h4>
      <div class="bg-gray-50 rounded-lg p-6">
        <canvas ref="segmentationChart" class="h-80"></canvas>
      </div>
    </div>

    <!-- Acciones por segmento -->
    <div class="mt-8 pt-6 border-t">
      <h4 class="text-lg font-bold mb-4">Acciones Recomendadas</h4>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- VIP Actions -->
        <div class="bg-purple-50 rounded-lg p-4 border-l-4 border-purple-600">
          <h5 class="font-bold text-purple-900 mb-3">Para Clientes VIP:</h5>
          <ul class="space-y-2 text-sm text-purple-800">
            <li>Enviar ofertas exclusivas</li>
            <li>Ofrecer descuentos por volumen</li>
            <li>Agendar citas de cortesía</li>
            <li>Solicitar referidos</li>
          </ul>
          <button class="w-full mt-3 px-3 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 text-sm font-semibold">
            Enviar Campaña
          </button>
        </div>

        <!-- New Actions -->
        <div class="bg-green-50 rounded-lg p-4 border-l-4 border-green-600">
          <h5 class="font-bold text-green-900 mb-3">Para Clientes Nuevos:</h5>
          <ul class="space-y-2 text-sm text-green-800">
            <li>Bienvenida personalizada</li>
            <li>Encuesta de satisfacción</li>
            <li>Descuento primer corte</li>
            <li>Invitar a agendar siguiente</li>
          </ul>
          <button class="w-full mt-3 px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm font-semibold">
            Activar Campaña
          </button>
        </div>

        <!-- Inactive Actions -->
        <div class="bg-gray-50 rounded-lg p-4 border-l-4 border-gray-600">
          <h5 class="font-bold text-gray-900 mb-3">Para Clientes Inactivos:</h5>
          <ul class="space-y-2 text-sm text-gray-800">
            <li>Enviar recordatorio</li>
            <li>Oferta de retorno (descuento)</li>
            <li>Encuesta: ¿Por qué se fueron?</li>
            <li>Promoción especial</li>
          </ul>
          <button class="w-full mt-3 px-3 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 text-sm font-semibold">
            Recuperar
          </button>
        </div>

        <!-- Debtors Actions -->
        <div class="bg-red-50 rounded-lg p-4 border-l-4 border-red-600">
          <h5 class="font-bold text-red-900 mb-3">Para Deudores:</h5>
          <ul class="space-y-2 text-sm text-red-800">
            <li>Recordatorio de pago</li>
            <li>Plan de pago flexible</li>
            <li>Confirmación de deuda</li>
            <li>Bloquear nuevas citas</li>
          </ul>
          <button class="w-full mt-3 px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm font-semibold">
            Cobrar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

export default {
  name: 'ClientSegmentation',
  data() {
    return {
      segments: {
        vip: {
          count: 12,
          percentage: 0.24,
          avgSpent: 450,
          rating: 4.8,
        },
        new: {
          count: 8,
          percentage: 0.16,
          avgSpent: 90,
          avgAppointments: 2,
        },
        inactive: {
          count: 15,
          percentage: 0.30,
          avgDaysSinceAppointment: 45,
          totalSpent: 1200,
        },
        debtors: {
          count: 5,
          percentage: 0.10,
          totalDebt: 450,
          avgDebt: 90,
        },
      },
      chart: null,
    };
  },
  mounted() {
    this.initChart();
  },
  methods: {
    initChart() {
      const ctx = this.$refs.segmentationChart;
      if (!ctx) return;

      this.chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: ['VIP', 'Nuevos', 'Inactivos', 'Deudores'],
          datasets: [
            {
              data: [
                this.segments.vip.count,
                this.segments.new.count,
                this.segments.inactive.count,
                this.segments.debtors.count,
              ],
              backgroundColor: ['#a855f7', '#16a34a', '#6b7280', '#dc2626'],
              borderColor: '#fff',
              borderWidth: 2,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                padding: 15,
                font: {
                  size: 12,
                  weight: 'bold',
                },
              },
            },
          },
        },
      });
    },
    viewSegment(segment) {
      console.log('View segment:', segment);
    },
  },
};
</script>

<style scoped>
/* Estilos adicionales si es necesario */
</style>
