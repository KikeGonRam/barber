<template>
  <div class="bg-white rounded-lg shadow-lg p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h3 class="text-2xl font-bold text-gray-900">Gestor de Horarios</h3>
      <div class="flex gap-3">
        <button
          @click="previousDay"
          class="px-4 py-2 bg-gray-300 text-gray-900 rounded-lg hover:bg-gray-400 transition font-semibold"
        >
          ← Anterior
        </button>
        <input
          v-model="selectedDate"
          type="date"
          class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <button
          @click="nextDay"
          class="px-4 py-2 bg-gray-300 text-gray-900 rounded-lg hover:bg-gray-400 transition font-semibold"
        >
          Siguiente →
        </button>
      </div>
    </div>

    <!-- Selector de barbero -->
    <div class="mb-6 pb-6 border-b">
      <label class="block text-sm font-semibold mb-3">Seleccionar Barbero:</label>
      <select v-model="selectedBarber" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option v-for="barber in barbers" :key="barber.id" :value="barber.id">
          {{ barber.name }} ({{ barber.appointments || 0 }} citas hoy)
        </option>
      </select>
    </div>

    <!-- Vista de horario -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Columna izquierda: Horarios del día -->
      <div class="lg:col-span-2">
        <h4 class="text-lg font-bold mb-4">Horarios - {{ formatDate(selectedDate) }}</h4>
        <div class="bg-gray-50 rounded-lg p-4 space-y-2 max-h-96 overflow-y-auto">
          <div
            v-for="slot in timeSlots"
            :key="slot"
            @click="selectSlot(slot)"
            :class="[
              'px-4 py-3 rounded-lg cursor-pointer transition',
              isSlotBooked(slot) ? 'bg-red-200 border-2 border-red-500 text-red-900' : 'bg-green-100 border-2 border-green-500 hover:bg-green-200',
              selectedSlot === slot ? 'ring-4 ring-blue-500' : '',
            ]"
          >
            <div class="flex items-center justify-between">
              <span class="font-bold">{{ slot }}</span>
              <span v-if="isSlotBooked(slot)" class="text-xs bg-red-500 text-white px-2 py-1 rounded">Ocupado</span>
              <span v-else class="text-xs bg-green-600 text-white px-2 py-1 rounded">Disponible</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Columna derecha: Citas del día -->
      <div>
        <h4 class="text-lg font-bold mb-4">Citas Programadas</h4>
        <div class="space-y-3 max-h-96 overflow-y-auto">
          <div
            v-for="appointment in dayAppointments"
            :key="appointment.id"
            class="bg-blue-50 border-l-4 border-blue-600 rounded-lg p-3"
          >
            <p class="font-bold text-blue-900">{{ appointment.clientName }}</p>
            <p class="text-sm text-blue-700">{{ appointment.time }}</p>
            <p class="text-sm text-blue-700">{{ appointment.service }}</p>
            <p class="text-sm text-blue-700">${{ appointment.price }}</p>
            <div class="flex gap-2 mt-2">
              <button
                @click="editAppointment(appointment.id)"
                class="flex-1 px-2 py-1 bg-yellow-500 text-white rounded text-xs hover:bg-yellow-600"
              >
                Editar
              </button>
              <button
                @click="deleteAppointment(appointment.id)"
                class="flex-1 px-2 py-1 bg-red-500 text-white rounded text-xs hover:bg-red-600"
              >
                Eliminar
              </button>
            </div>
          </div>

          <div v-if="dayAppointments.length === 0" class="text-center py-4 text-gray-500">
            Sin citas programadas
          </div>
        </div>
      </div>
    </div>

    <!-- Formulario para agregar cita -->
    <div v-if="selectedSlot" class="mt-8 pt-6 border-t">
      <h4 class="text-lg font-bold mb-4">Crear Nueva Cita</h4>
      <div class="bg-blue-50 rounded-lg p-6 border-2 border-blue-200">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold mb-2">Hora:</label>
            <input
              v-model="newAppointment.time"
              type="text"
              :value="selectedSlot"
              disabled
              class="w-full px-4 py-2 border rounded-lg bg-gray-100"
            />
          </div>
          <div>
            <label class="block text-sm font-semibold mb-2">Cliente:</label>
            <input
              v-model="newAppointment.clientName"
              type="text"
              placeholder="Nombre del cliente"
              class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-semibold mb-2">Servicio:</label>
            <select v-model="newAppointment.service" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option>Corte</option>
              <option>Barba</option>
              <option>Corte + Barba</option>
              <option>Diseño</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold mb-2">Precio:</label>
            <input
              v-model.number="newAppointment.price"
              type="number"
              placeholder="$0"
              class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>

        <div class="flex gap-3 mt-4">
          <button
            @click="createAppointment"
            class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-bold"
          >
            Agendar Cita
          </button>
          <button
            @click="cancelNewAppointment"
            class="flex-1 px-4 py-3 bg-gray-400 text-white rounded-lg hover:bg-gray-500 transition font-bold"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <!-- Resumen del día -->
    <div class="mt-8 pt-6 border-t">
      <h4 class="text-lg font-bold mb-4">Resumen del Día</h4>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-blue-50 rounded-lg p-4 border-l-4 border-blue-600">
          <p class="text-gray-600 text-sm">Total Citas</p>
          <p class="text-2xl font-bold text-blue-600">{{ dayAppointments.length }}</p>
        </div>
        <div class="bg-green-50 rounded-lg p-4 border-l-4 border-green-600">
          <p class="text-gray-600 text-sm">Ingresos Hoy</p>
          <p class="text-2xl font-bold text-green-600">${{ totalRevenue }}</p>
        </div>
        <div class="bg-purple-50 rounded-lg p-4 border-l-4 border-purple-600">
          <p class="text-gray-600 text-sm">Ocupación</p>
          <p class="text-2xl font-bold text-purple-600">{{ occupancy }}%</p>
        </div>
        <div class="bg-orange-50 rounded-lg p-4 border-l-4 border-orange-600">
          <p class="text-gray-600 text-sm">Próxima Cita</p>
          <p class="text-2xl font-bold text-orange-600">{{ nextAppointmentTime }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { format, addDays, subDays } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
  name: 'BarberScheduleManager',
  data() {
    return {
      selectedDate: new Date().toISOString().split('T')[0],
      selectedBarber: 1,
      selectedSlot: null,
      barbers: [
        { id: 1, name: 'Juan Pérez', appointments: 7 },
        { id: 2, name: 'Pedro García', appointments: 5 },
        { id: 3, name: 'Carlos López', appointments: 8 },
        { id: 4, name: 'Miguel Sánchez', appointments: 3 },
      ],
      appointments: [
        { id: 1, barber_id: 1, date: new Date().toISOString().split('T')[0], time: '09:00', clientName: 'Juan Díaz', service: 'Corte', price: 35 },
        { id: 2, barber_id: 1, date: new Date().toISOString().split('T')[0], time: '09:30', clientName: 'Pedro Ruiz', service: 'Corte + Barba', price: 50 },
        { id: 3, barber_id: 1, date: new Date().toISOString().split('T')[0], time: '10:15', clientName: 'Carlos Gómez', service: 'Barba', price: 25 },
        { id: 4, barber_id: 1, date: new Date().toISOString().split('T')[0], time: '11:00', clientName: 'Miguel López', service: 'Corte', price: 35 },
        { id: 5, barber_id: 1, date: new Date().toISOString().split('T')[0], time: '14:00', clientName: 'Ana García', service: 'Diseño', price: 60 },
        { id: 6, barber_id: 1, date: new Date().toISOString().split('T')[0], time: '15:00', clientName: 'Lucia Martínez', service: 'Corte', price: 35 },
        { id: 7, barber_id: 1, date: new Date().toISOString().split('T')[0], time: '16:00', clientName: 'Sofia García', service: 'Corte + Barba', price: 50 },
      ],
      newAppointment: {
        time: '',
        clientName: '',
        service: 'Corte',
        price: 35,
      },
      timeSlots: [
        '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
        '12:00', '12:30', '13:00', '13:30',
        '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30',
      ],
    };
  },
  computed: {
    dayAppointments() {
      return this.appointments.filter(
        apt => apt.barber_id === this.selectedBarber && apt.date === this.selectedDate
      );
    },
    totalRevenue() {
      return this.dayAppointments.reduce((sum, apt) => sum + apt.price, 0);
    },
    occupancy() {
      return Math.round((this.dayAppointments.length / 16) * 100);
    },
    nextAppointmentTime() {
      const sorted = this.dayAppointments.sort((a, b) => a.time.localeCompare(b.time));
      return sorted.length > 0 ? sorted[0].time : 'N/A';
    },
  },
  methods: {
    formatDate(date) {
      const d = new Date(date);
      const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
      return d.toLocaleDateString('es-ES', options);
    },
    previousDay() {
      const d = new Date(this.selectedDate);
      d.setDate(d.getDate() - 1);
      this.selectedDate = d.toISOString().split('T')[0];
      this.selectedSlot = null;
    },
    nextDay() {
      const d = new Date(this.selectedDate);
      d.setDate(d.getDate() + 1);
      this.selectedDate = d.toISOString().split('T')[0];
      this.selectedSlot = null;
    },
    isSlotBooked(slot) {
      return this.dayAppointments.some(apt => apt.time === slot);
    },
    selectSlot(slot) {
      if (!this.isSlotBooked(slot)) {
        this.selectedSlot = slot;
        this.newAppointment.time = slot;
      }
    },
    createAppointment() {
      if (!this.newAppointment.clientName) {
        alert('Por favor ingresa el nombre del cliente');
        return;
      }

      this.appointments.push({
        id: Math.max(...this.appointments.map(a => a.id), 0) + 1,
        barber_id: this.selectedBarber,
        date: this.selectedDate,
        time: this.selectedSlot,
        clientName: this.newAppointment.clientName,
        service: this.newAppointment.service,
        price: this.newAppointment.price,
      });

      this.cancelNewAppointment();
      alert('Cita agendada correctamente');
    },
    cancelNewAppointment() {
      this.selectedSlot = null;
      this.newAppointment = {
        time: '',
        clientName: '',
        service: 'Corte',
        price: 35,
      };
    },
    editAppointment(appointmentId) {
      console.log('Edit appointment:', appointmentId);
    },
    deleteAppointment(appointmentId) {
      const index = this.appointments.findIndex(a => a.id === appointmentId);
      if (index > -1) {
        this.appointments.splice(index, 1);
        alert('Cita eliminada');
      }
    },
  },
};
</script>

<style scoped>
/* Estilos adicionales si es necesario */
</style>
