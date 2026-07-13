<template>
  <div class="bg-white rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-6">
      <h3 class="text-2xl font-bold text-gray-900">Calendario de Citas</h3>
      <button
        @click="createNewAppointment"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold"
      >
        Nueva Cita
      </button>
    </div>

    <!-- Controles del calendario -->
    <div class="flex items-center justify-between mb-6 pb-6 border-b">
      <div class="flex gap-2">
        <button
          @click="previousMonth"
          class="px-3 py-2 border rounded hover:bg-gray-100 transition"
        >
          ← Anterior
        </button>
        <button
          @click="today"
          class="px-3 py-2 border rounded hover:bg-gray-100 transition"
        >
          Hoy
        </button>
        <button
          @click="nextMonth"
          class="px-3 py-2 border rounded hover:bg-gray-100 transition"
        >
          Siguiente →
        </button>
      </div>

      <h4 class="text-lg font-semibold text-gray-800">{{ currentMonthYear }}</h4>

      <select v-model="viewMode" class="px-3 py-2 border rounded">
        <option value="month">Mes</option>
        <option value="week">Semana</option>
        <option value="day">Día</option>
      </select>
    </div>

    <!-- Filtros -->
    <div class="flex gap-3 mb-6 pb-6 border-b flex-wrap">
      <input
        v-model="searchClient"
        type="text"
        placeholder="Buscar cliente..."
        class="px-3 py-2 border rounded text-sm flex-1 min-w-[200px]"
      />
      <select v-model="filterBarber" class="px-3 py-2 border rounded text-sm">
        <option value="">Todos los barberos</option>
        <option value="juan">Juan</option>
        <option value="pedro">Pedro</option>
        <option value="carlos">Carlos</option>
      </select>
      <select v-model="filterStatus" class="px-3 py-2 border rounded text-sm">
        <option value="">Todos los estados</option>
        <option value="confirmada">Confirmada</option>
        <option value="pendiente">Pendiente</option>
        <option value="completada">Completada</option>
        <option value="cancelada">Cancelada</option>
      </select>
    </div>

    <!-- Vista de Mes (Tabla simple para demostración) -->
    <div v-if="viewMode === 'month'" class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-100 border-b-2 border-gray-300">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-bold">Hora</th>
            <th class="px-4 py-3 text-left text-sm font-bold">Cliente</th>
            <th class="px-4 py-3 text-left text-sm font-bold">Barbero</th>
            <th class="px-4 py-3 text-left text-sm font-bold">Servicio</th>
            <th class="px-4 py-3 text-left text-sm font-bold">Duración</th>
            <th class="px-4 py-3 text-left text-sm font-bold">Estado</th>
            <th class="px-4 py-3 text-left text-sm font-bold">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr
            v-for="apt in filteredAppointments"
            :key="apt.id"
            class="hover:bg-blue-50 transition"
          >
            <td class="px-4 py-3 text-sm font-medium">{{ formatTime(apt.start_time) }}</td>
            <td class="px-4 py-3 text-sm">{{ apt.client.name }}</td>
            <td class="px-4 py-3 text-sm">{{ apt.barber.user.name }}</td>
            <td class="px-4 py-3 text-sm">{{ apt.service.nombre }}</td>
            <td class="px-4 py-3 text-sm">{{ apt.duration }} min</td>
            <td class="px-4 py-3">
              <span :class="getStatusClass(apt.status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                {{ apt.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm space-x-2">
              <button
                @click="editAppointment(apt.id)"
                class="text-blue-600 hover:text-blue-800 font-semibold"
              >

              </button>
              <button
                @click="deleteAppointment(apt.id)"
                class="text-red-600 hover:text-red-800 font-semibold"
              >

              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Vista de Semana -->
    <div v-if="viewMode === 'week'" class="text-gray-600 text-center py-12">
      Vista de semana disponible pronto
    </div>

    <!-- Vista de Día -->
    <div v-if="viewMode === 'day'" class="text-gray-600 text-center py-12">
      Vista de día disponible pronto
    </div>

    <!-- Sin resultados -->
    <div v-if="filteredAppointments.length === 0" class="text-center py-12 text-gray-500">
      <p>No se encontraron citas con estos filtros</p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AppointmentCalendar',
  data() {
    return {
      viewMode: 'month',
      currentDate: new Date(),
      searchClient: '',
      filterBarber: '',
      filterStatus: '',
      appointments: [
        {
          id: 1,
          start_time: '2026-05-09T10:00:00',
          client: { name: 'Carlos López' },
          barber: { user: { name: 'Juan' } },
          service: { nombre: 'Corte Clásico' },
          duration: 30,
          status: 'confirmada',
        },
        {
          id: 2,
          start_time: '2026-05-09T11:00:00',
          client: { name: 'Miguel García' },
          barber: { user: { name: 'Pedro' } },
          service: { nombre: 'Fade + Barba' },
          duration: 45,
          status: 'pendiente',
        },
        {
          id: 3,
          start_time: '2026-05-09T14:00:00',
          client: { name: 'Roberto Díaz' },
          barber: { user: { name: 'Juan' } },
          service: { nombre: 'Corte Moderno' },
          duration: 30,
          status: 'confirmada',
        },
      ],
    };
  },
  computed: {
    currentMonthYear() {
      return this.currentDate.toLocaleDateString('es-ES', {
        month: 'long',
        year: 'numeric',
      });
    },
    filteredAppointments() {
      return this.appointments.filter(apt => {
        const matchClient =
          apt.client.name.toLowerCase().includes(this.searchClient.toLowerCase());
        const matchBarber = !this.filterBarber || apt.barber.user.name === this.filterBarber;
        const matchStatus = !this.filterStatus || apt.status === this.filterStatus;

        return matchClient && matchBarber && matchStatus;
      });
    },
  },
  methods: {
    previousMonth() {
      this.currentDate.setMonth(this.currentDate.getMonth() - 1);
      this.currentDate = new Date(this.currentDate);
    },
    nextMonth() {
      this.currentDate.setMonth(this.currentDate.getMonth() + 1);
      this.currentDate = new Date(this.currentDate);
    },
    today() {
      this.currentDate = new Date();
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
    createNewAppointment() {
      console.log('Creating new appointment');
      // Aquí iría la lógica para crear una nueva cita
    },
    editAppointment(id) {
      console.log('Editing appointment:', id);
      // Aquí iría la lógica para editar una cita
    },
    deleteAppointment(id) {
      if (confirm('¿Estás seguro de que deseas eliminar esta cita?')) {
        this.appointments = this.appointments.filter(a => a.id !== id);
      }
    },
  },
};
</script>

<style scoped>
/* Estilos adicionales si es necesario */
</style>
