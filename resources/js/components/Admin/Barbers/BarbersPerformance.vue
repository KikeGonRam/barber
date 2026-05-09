<template>
  <div class="bg-white rounded-lg shadow-lg p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h3 class="text-2xl font-bold text-gray-900">✂️ Performance de Barberos</h3>
      <button
        @click="refreshData"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold"
      >
        🔄 Actualizar
      </button>
    </div>

    <!-- Filtros -->
    <div class="flex gap-4 mb-6 pb-6 border-b">
      <input
        v-model="searchBarber"
        type="text"
        placeholder="Buscar barbero..."
        class="px-4 py-2 border rounded-lg flex-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
      />
      <select v-model="filterStatus" class="px-4 py-2 border rounded-lg">
        <option value="">Todos los estados</option>
        <option value="active">✅ Activos</option>
        <option value="inactive">⛔ Inactivos</option>
      </select>
    </div>

    <!-- Tarjetas de Barberos -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="barber in filteredBarbers"
        :key="barber.id"
        class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-lg border-2 border-slate-200 overflow-hidden hover:shadow-lg transition"
      >
        <!-- Foto y nombre -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-4">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center text-2xl">
              {{ barber.initial }}
            </div>
            <div>
              <h4 class="font-bold text-lg">{{ barber.name }}</h4>
              <p class="text-sm opacity-90">{{ barber.specialties }}</p>
            </div>
          </div>
        </div>

        <!-- Métricas -->
        <div class="p-4 space-y-3">
          <!-- Calificación -->
          <div class="flex items-center justify-between">
            <span class="text-gray-700 font-semibold">⭐ Calificación</span>
            <div class="flex items-center gap-1">
              <span class="text-lg font-bold text-yellow-500">{{ barber.rating }}</span>
              <span class="text-sm text-gray-600">({{ barber.ratingCount }})</span>
            </div>
          </div>

          <!-- Citas hoy -->
          <div class="flex items-center justify-between">
            <span class="text-gray-700 font-semibold">📅 Citas Hoy</span>
            <span class="text-lg font-bold text-blue-600">{{ barber.appointmentsToday }}/{{ barber.maxAppointments }}</span>
          </div>

          <!-- Ocupación -->
          <div class="flex items-center justify-between">
            <span class="text-gray-700 font-semibold">📊 Ocupación</span>
            <div class="flex items-center gap-2">
              <div class="w-24 h-2 bg-gray-300 rounded-full overflow-hidden">
                <div
                  class="h-full transition-all"
                  :class="barber.occupancyRate > 80 ? 'bg-red-500' : barber.occupancyRate > 60 ? 'bg-yellow-500' : 'bg-green-500'"
                  :style="{ width: barber.occupancyRate + '%' }"
                ></div>
              </div>
              <span class="text-sm font-bold">{{ barber.occupancyRate }}%</span>
            </div>
          </div>

          <!-- Ingresos -->
          <div class="flex items-center justify-between">
            <span class="text-gray-700 font-semibold">💰 Ingresos Hoy</span>
            <span class="text-lg font-bold text-green-600">${{ barber.revenueToday }}</span>
          </div>

          <!-- Cliente favorito -->
          <div class="flex items-center justify-between">
            <span class="text-gray-700 font-semibold">👥 Clientes</span>
            <span class="text-sm text-gray-600">{{ barber.totalClients }} regulares</span>
          </div>

          <!-- Tiempo promedio -->
          <div class="flex items-center justify-between">
            <span class="text-gray-700 font-semibold">⏱️ Promedio</span>
            <span class="text-sm text-gray-600">{{ barber.avgServiceTime }} min</span>
          </div>

          <!-- Estado -->
          <div class="border-t pt-3">
            <span :class="barber.active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="inline-block px-3 py-1 rounded-full text-xs font-semibold">
              {{ barber.active ? '✅ Activo' : '⛔ Inactivo' }}
            </span>
          </div>
        </div>

        <!-- Acciones -->
        <div class="bg-gray-100 px-4 py-3 flex gap-2">
          <button
            @click="viewProfile(barber.id)"
            class="flex-1 px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition text-sm font-semibold"
          >
            👁️ Ver
          </button>
          <button
            @click="editBarber(barber.id)"
            class="flex-1 px-3 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600 transition text-sm font-semibold"
          >
            ✏️ Editar
          </button>
          <button
            @click="viewSchedule(barber.id)"
            class="flex-1 px-3 py-2 bg-purple-500 text-white rounded hover:bg-purple-600 transition text-sm font-semibold"
          >
            📅 Agenda
          </button>
        </div>
      </div>
    </div>

    <!-- Sin resultados -->
    <div v-if="filteredBarbers.length === 0" class="text-center py-12 text-gray-500">
      <p>✂️ No se encontraron barberos con estos criterios</p>
    </div>

    <!-- Resumen de performance -->
    <div v-if="filteredBarbers.length > 0" class="mt-8 pt-6 border-t">
      <h4 class="text-lg font-bold mb-4">📊 Resumen de Performance</h4>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-blue-50 rounded-lg p-4">
          <p class="text-gray-600 text-sm">Promedio de Calificación</p>
          <p class="text-2xl font-bold text-blue-600">{{ averageRating }}</p>
        </div>
        <div class="bg-green-50 rounded-lg p-4">
          <p class="text-gray-600 text-sm">Ingresos Totales Hoy</p>
          <p class="text-2xl font-bold text-green-600">${{ totalRevenueToday }}</p>
        </div>
        <div class="bg-purple-50 rounded-lg p-4">
          <p class="text-gray-600 text-sm">Ocupación Promedio</p>
          <p class="text-2xl font-bold text-purple-600">{{ averageOccupancy }}%</p>
        </div>
        <div class="bg-orange-50 rounded-lg p-4">
          <p class="text-gray-600 text-sm">Citas Totales Hoy</p>
          <p class="text-2xl font-bold text-orange-600">{{ totalAppointmentsToday }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'BarbersPerformance',
  data() {
    return {
      searchBarber: '',
      filterStatus: '',
      barbers: [
        {
          id: 1,
          name: 'Juan Pérez',
          initial: 'J',
          specialties: 'Fade, Barba',
          rating: 4.8,
          ratingCount: 45,
          appointmentsToday: 7,
          maxAppointments: 8,
          occupancyRate: 87,
          revenueToday: 210,
          totalClients: 28,
          avgServiceTime: 30,
          active: true,
        },
        {
          id: 2,
          name: 'Pedro García',
          initial: 'P',
          specialties: 'Corte Clásico',
          rating: 4.6,
          ratingCount: 32,
          appointmentsToday: 5,
          maxAppointments: 8,
          occupancyRate: 62,
          revenueToday: 150,
          totalClients: 21,
          avgServiceTime: 30,
          active: true,
        },
        {
          id: 3,
          name: 'Carlos López',
          initial: 'C',
          specialties: 'Moderno',
          rating: 4.9,
          ratingCount: 58,
          appointmentsToday: 8,
          maxAppointments: 8,
          occupancyRate: 100,
          revenueToday: 240,
          totalClients: 35,
          avgServiceTime: 30,
          active: true,
        },
        {
          id: 4,
          name: 'Miguel Sánchez',
          initial: 'M',
          specialties: 'Especialista en diseños',
          rating: 4.7,
          ratingCount: 38,
          appointmentsToday: 3,
          maxAppointments: 8,
          occupancyRate: 37,
          revenueToday: 90,
          totalClients: 18,
          avgServiceTime: 45,
          active: true,
        },
      ],
    };
  },
  computed: {
    filteredBarbers() {
      return this.barbers.filter(barber => {
        const matchSearch = barber.name.toLowerCase().includes(this.searchBarber.toLowerCase());
        const matchStatus =
          !this.filterStatus ||
          (this.filterStatus === 'active' && barber.active) ||
          (this.filterStatus === 'inactive' && !barber.active);

        return matchSearch && matchStatus;
      });
    },
    averageRating() {
      if (this.filteredBarbers.length === 0) return '0.0';
      const sum = this.filteredBarbers.reduce((acc, b) => acc + b.rating, 0);
      return (sum / this.filteredBarbers.length).toFixed(1);
    },
    totalRevenueToday() {
      return this.filteredBarbers.reduce((acc, b) => acc + b.revenueToday, 0);
    },
    averageOccupancy() {
      if (this.filteredBarbers.length === 0) return '0';
      const sum = this.filteredBarbers.reduce((acc, b) => acc + b.occupancyRate, 0);
      return Math.round(sum / this.filteredBarbers.length);
    },
    totalAppointmentsToday() {
      return this.filteredBarbers.reduce((acc, b) => acc + b.appointmentsToday, 0);
    },
  },
  methods: {
    viewProfile(barberId) {
      console.log('View barber profile:', barberId);
    },
    editBarber(barberId) {
      console.log('Edit barber:', barberId);
    },
    viewSchedule(barberId) {
      console.log('View barber schedule:', barberId);
    },
    refreshData() {
      console.log('Refreshing data...');
    },
  },
};
</script>

<style scoped>
/* Estilos adicionales si es necesario */
</style>
