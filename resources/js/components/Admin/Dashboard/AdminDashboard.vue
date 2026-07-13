<template>
  <div class="space-y-6 p-6">
    <!-- Página Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">Dashboard Administrativo</h1>
      <p class="text-gray-600 mt-2">Bienvenido de vuelta, {{ userName }}. Aquí está tu resumen de hoy.</p>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <KPICard
        title="Ingresos Hoy"
        :value="`$${stats.revenueToday.toLocaleString()}`"
        icon=""
        trend="+12%"
        :trend-up="true"
      />
      <KPICard
        title="Citas Completadas"
        :value="stats.appointmentsCompleted"
        icon=""
        trend="+8%"
        :trend-up="true"
      />
      <KPICard
        title="Ocupación Promedio"
        :value="`${stats.occupancyRate}%`"
        icon=""
        trend="+5%"
        :trend-up="true"
      />
      <KPICard
        title="Clientes Nuevos"
        :value="stats.newClients"
        icon=""
        trend="+15%"
        :trend-up="true"
      />
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Revenue Chart -->
      <RevenueChart />

      <!-- Appointments Chart -->
      <div class="bg-white rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-6">Citas por Estado</h3>
        <canvas ref="appointmentsChart" class="h-80"></canvas>
      </div>
    </div>

    <!-- Alerts & Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Alerts -->
      <div class="lg:col-span-2 bg-white rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">Alertas Importantes</h3>
        <div class="space-y-3">
          <Alert
            v-for="alert in alerts"
            :key="alert.id"
            :alert="alert"
            @close="removeAlert(alert.id)"
            @action="handleAlertAction"
          />
          <div v-if="alerts.length === 0" class="text-center py-8 text-gray-500">
            No hay alertas por el momento
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="bg-white rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">Acciones Rápidas</h3>
        <div class="space-y-2">
          <button
            @click="goTo('appointments.create')"
            class="w-full px-4 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition font-semibold flex items-center gap-2"
          >
            Nueva Cita
          </button>
          <button
            @click="goTo('clients.create')"
            class="w-full px-4 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition font-semibold flex items-center gap-2"
          >
            Nuevo Cliente
          </button>
          <button
            @click="goTo('reports.index')"
            class="w-full px-4 py-3 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition font-semibold flex items-center gap-2"
          >
            Ver Reportes
          </button>
          <button
            @click="goTo('settings.edit')"
            class="w-full px-4 py-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition font-semibold flex items-center gap-2"
          >
            Configuración
          </button>
        </div>
      </div>
    </div>

    <!-- Upcoming Appointments -->
    <div class="bg-white rounded-lg shadow-lg p-6">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-xl font-bold text-gray-800">Próximas Citas</h3>
        <a href="#" class="text-blue-600 hover:text-blue-800 text-sm font-semibold">Ver todas →</a>
      </div>

      <div v-if="upcomingAppointments.length > 0" class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b-2 border-gray-200">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Hora</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Cliente</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Barbero</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Servicio</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Estado</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr
              v-for="apt in upcomingAppointments.slice(0, 5)"
              :key="apt.id"
              class="hover:bg-gray-50 transition"
            >
              <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ formatTime(apt.start_time) }}</td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ apt.client.name }}</td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ apt.barber.user.name }}</td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ apt.service.nombre }}</td>
              <td class="px-6 py-4">
                <span :class="getStatusClass(apt.status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                  {{ apt.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm">
                <button class="text-blue-600 hover:text-blue-800 font-semibold">Editar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="text-center py-12 text-gray-500">
        <p>No hay citas próximas</p>
      </div>
    </div>
  </div>
</template>

<script>
import { Chart, registerables } from 'chart.js';
import KPICard from '../Shared/KPICard.vue';
import Alert from '../Shared/Alert.vue';
import RevenueChart from './RevenueChart.vue';

Chart.register(...registerables);

export default {
  name: 'AdminDashboard',
  components: {
    KPICard,
    Alert,
    RevenueChart,
  },
  data() {
    return {
      userName: 'Administrador',
      stats: {
        revenueToday: 1250,
        appointmentsCompleted: 12,
        occupancyRate: 85,
        newClients: 5,
      },
      alerts: [
        {
          id: 1,
          type: 'warning',
          title: 'Citas Próximas',
          message: '2 citas en las próximas 2 horas - Confirma que todo está listo',
          action: [
            { label: 'Ver', action: 'view-appointments', style: 'bg-yellow-500 text-white' },
            { label: 'Descartar', action: 'dismiss', style: 'bg-yellow-700 text-white' },
          ],
        },
        {
          id: 2,
          type: 'error',
          title: 'Inventario Bajo',
          message: 'Cuchillas de afeitar: Solo quedan 15 unidades',
          action: [
            { label: 'Reabastecer', action: 'restock', style: 'bg-red-500 text-white' },
            { label: 'Después', action: 'dismiss', style: 'bg-red-700 text-white' },
          ],
        },
        {
          id: 3,
          type: 'info',
          title: 'Ocupación Baja',
          message: 'Juan tiene baja ocupación (30%) - Considera ofrecer promociones',
          action: [
            { label: 'Promociones', action: 'promotions', style: 'bg-blue-500 text-white' },
          ],
        },
      ],
      upcomingAppointments: [],
      appointmentsChart: null,
    };
  },
  mounted() {
    this.fetchData();
    this.initCharts();
    this.getUserName();
  },
  methods: {
    async fetchData() {
      try {
        // Datos de ejemplo (en producción vendrían de una API)
        this.upcomingAppointments = [
          {
            id: 1,
            start_time: new Date(Date.now() + 3600000).toISOString(),
            client: { name: 'Carlos López' },
            barber: { user: { name: 'Juan' } },
            service: { nombre: 'Corte Clásico' },
            status: 'confirmada',
          },
          {
            id: 2,
            start_time: new Date(Date.now() + 7200000).toISOString(),
            client: { name: 'Miguel García' },
            barber: { user: { name: 'Pedro' } },
            service: { nombre: 'Fade + Barba' },
            status: 'pendiente',
          },
          {
            id: 3,
            start_time: new Date(Date.now() + 10800000).toISOString(),
            client: { name: 'Roberto Díaz' },
            barber: { user: { name: 'Juan' } },
            service: { nombre: 'Corte Moderno' },
            status: 'confirmada',
          },
        ];
      } catch (error) {
        console.error('Error fetching dashboard data:', error);
      }
    },
    getUserName() {
      // En producción, esto vendría de la sesión del usuario
      this.userName = 'Administrador';
    },
    initCharts() {
      const appointmentsCtx = this.$refs.appointmentsChart;
      if (!appointmentsCtx) return;

      this.appointmentsChart = new Chart(appointmentsCtx, {
        type: 'doughnut',
        data: {
          labels: ['Completadas', 'Pendientes', 'Canceladas'],
          datasets: [
            {
              data: [65, 25, 10],
              backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
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
    formatTime(time) {
      return new Date(time).toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit',
      });
    },
    getStatusClass(status) {
      const classes = {
        pendiente: 'bg-yellow-100 text-yellow-800',
        confirmada: 'bg-blue-100 text-blue-800',
        completada: 'bg-green-100 text-green-800',
        cancelada: 'bg-red-100 text-red-800',
      };
      return classes[status] || 'bg-gray-100 text-gray-800';
    },
    removeAlert(id) {
      this.alerts = this.alerts.filter(a => a.id !== id);
    },
    handleAlertAction(action) {
      console.log('Alert action:', action);
      // Aquí iría la lógica para cada acción
    },
    goTo(route) {
      console.log('Going to:', route);
      // En una app real, usarías window.location o router
    },
  },
};
</script>

<style scoped>
/* Estilos adicionales si es necesario */
</style>
