<template>
  <div class="bg-white rounded-lg shadow-lg p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h3 class="text-2xl font-bold text-gray-900">👥 Gestión de Clientes</h3>
      <button
        @click="showCreateModal = true"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold"
      >
        ➕ Nuevo Cliente
      </button>
    </div>

    <!-- Controles de búsqueda y filtros -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 pb-6 border-b">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Buscar por nombre, email o teléfono..."
        class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
      />

      <select v-model="filterSegment" class="px-4 py-2 border rounded-lg">
        <option value="">Todos los segmentos</option>
        <option value="vip">⭐ VIP (Clientes frecuentes)</option>
        <option value="new">🆕 Nuevos (Últimas 2 semanas)</option>
        <option value="inactive">😴 Inactivos (>30 días)</option>
        <option value="debtors">💳 Deudores</option>
      </select>

      <select v-model="sortBy" class="px-4 py-2 border rounded-lg">
        <option value="name">Ordenar por Nombre</option>
        <option value="recent">Ordenar por Reciente</option>
        <option value="visits">Ordenar por Citas</option>
        <option value="revenue">Ordenar por Gasto</option>
      </select>

      <div class="flex gap-2">
        <button
          @click="exportToExcel"
          class="px-3 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-sm font-semibold"
        >
          📥 Excel
        </button>
        <button
          @click="exportToPDF"
          class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition text-sm font-semibold"
        >
          📄 PDF
        </button>
      </div>
    </div>

    <!-- Tabla de Clientes -->
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-100 border-b-2 border-gray-300">
          <tr>
            <th class="px-6 py-3 text-left text-sm font-bold text-gray-700">Nombre</th>
            <th class="px-6 py-3 text-left text-sm font-bold text-gray-700">Email</th>
            <th class="px-6 py-3 text-left text-sm font-bold text-gray-700">Teléfono</th>
            <th class="px-6 py-3 text-left text-sm font-bold text-gray-700">Citas</th>
            <th class="px-6 py-3 text-left text-sm font-bold text-gray-700">Gasto Total</th>
            <th class="px-6 py-3 text-left text-sm font-bold text-gray-700">Segmento</th>
            <th class="px-6 py-3 text-left text-sm font-bold text-gray-700">Estado</th>
            <th class="px-6 py-3 text-left text-sm font-bold text-gray-700">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr
            v-for="client in filteredClients"
            :key="client.id"
            class="hover:bg-blue-50 transition"
          >
            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ client.name }}</td>
            <td class="px-6 py-4 text-sm text-gray-600">{{ client.email }}</td>
            <td class="px-6 py-4 text-sm text-gray-600">{{ client.phone }}</td>
            <td class="px-6 py-4 text-sm text-blue-600 font-semibold">{{ client.appointmentCount }}</td>
            <td class="px-6 py-4 text-sm text-green-600 font-semibold">${{ client.totalSpent }}</td>
            <td class="px-6 py-4">
              <span :class="getSegmentBadge(client.segment)" class="px-3 py-1 rounded-full text-xs font-semibold">
                {{ getSegmentLabel(client.segment) }}
              </span>
            </td>
            <td class="px-6 py-4">
              <span :class="client.active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-3 py-1 rounded-full text-xs font-semibold">
                {{ client.active ? '✅ Activo' : '⛔ Inactivo' }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm space-x-2">
              <button
                @click="viewProfile(client.id)"
                class="text-blue-600 hover:text-blue-800 font-semibold"
              >
                👁️
              </button>
              <button
                @click="editClient(client.id)"
                class="text-yellow-600 hover:text-yellow-800 font-semibold"
              >
                ✏️
              </button>
              <button
                @click="deleteClient(client.id)"
                class="text-red-600 hover:text-red-800 font-semibold"
              >
                🗑️
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <div class="flex items-center justify-between mt-6 pt-6 border-t">
      <p class="text-sm text-gray-600">
        Mostrando {{ filteredClients.length }} de {{ clients.length }} clientes
      </p>
      <div class="flex gap-2">
        <button
          @click="previousPage"
          :disabled="currentPage === 1"
          class="px-3 py-2 border rounded hover:bg-gray-100 disabled:opacity-50"
        >
          ← Anterior
        </button>
        <span class="px-3 py-2">Página {{ currentPage }}</span>
        <button
          @click="nextPage"
          class="px-3 py-2 border rounded hover:bg-gray-100"
        >
          Siguiente →
        </button>
      </div>
    </div>

    <!-- Modal de crear/editar cliente -->
    <div
      v-if="showCreateModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
      <div class="bg-white rounded-lg p-6 max-w-md w-full">
        <h4 class="text-xl font-bold mb-4">{{ editingClientId ? 'Editar Cliente' : 'Nuevo Cliente' }}</h4>
        <form @submit.prevent="saveClient" class="space-y-4">
          <input
            v-model="formData.name"
            type="text"
            placeholder="Nombre"
            class="w-full px-3 py-2 border rounded"
            required
          />
          <input
            v-model="formData.email"
            type="email"
            placeholder="Email"
            class="w-full px-3 py-2 border rounded"
            required
          />
          <input
            v-model="formData.phone"
            type="tel"
            placeholder="Teléfono"
            class="w-full px-3 py-2 border rounded"
          />
          <textarea
            v-model="formData.notes"
            placeholder="Notas"
            class="w-full px-3 py-2 border rounded"
            rows="3"
          ></textarea>
          <div class="flex gap-2">
            <button
              type="submit"
              class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-semibold"
            >
              Guardar
            </button>
            <button
              type="button"
              @click="showCreateModal = false"
              class="flex-1 px-4 py-2 bg-gray-300 text-gray-900 rounded hover:bg-gray-400 font-semibold"
            >
              Cancelar
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Sin resultados -->
    <div v-if="filteredClients.length === 0" class="text-center py-12 text-gray-500">
      <p>👤 No se encontraron clientes con estos criterios</p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ClientsManagement',
  data() {
    return {
      searchQuery: '',
      filterSegment: '',
      sortBy: 'name',
      currentPage: 1,
      showCreateModal: false,
      editingClientId: null,
      formData: {
        name: '',
        email: '',
        phone: '',
        notes: '',
      },
      clients: [
        {
          id: 1,
          name: 'Carlos López',
          email: 'carlos@example.com',
          phone: '+521234567890',
          appointmentCount: 15,
          totalSpent: 450,
          segment: 'vip',
          active: true,
        },
        {
          id: 2,
          name: 'Miguel García',
          email: 'miguel@example.com',
          phone: '+521234567891',
          appointmentCount: 3,
          totalSpent: 90,
          segment: 'new',
          active: true,
        },
        {
          id: 3,
          name: 'Roberto Díaz',
          email: 'roberto@example.com',
          phone: '+521234567892',
          appointmentCount: 2,
          totalSpent: 60,
          segment: 'inactive',
          active: false,
        },
        {
          id: 4,
          name: 'Juan Pérez',
          email: 'juan@example.com',
          phone: '+521234567893',
          appointmentCount: 8,
          totalSpent: 240,
          segment: 'vip',
          active: true,
        },
        {
          id: 5,
          name: 'Antonio Sánchez',
          email: 'antonio@example.com',
          phone: '+521234567894',
          appointmentCount: 1,
          totalSpent: 30,
          segment: 'debtors',
          active: true,
        },
      ],
    };
  },
  computed: {
    filteredClients() {
      return this.clients.filter(client => {
        const matchSearch =
          client.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
          client.email.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
          client.phone.includes(this.searchQuery);

        const matchSegment = !this.filterSegment || client.segment === this.filterSegment;

        return matchSearch && matchSegment;
      });
    },
  },
  methods: {
    getSegmentLabel(segment) {
      const labels = {
        vip: '⭐ VIP',
        new: '🆕 Nuevo',
        inactive: '😴 Inactivo',
        debtors: '💳 Deudor',
      };
      return labels[segment] || 'Sin clasificar';
    },
    getSegmentBadge(segment) {
      const badges = {
        vip: 'bg-purple-100 text-purple-800',
        new: 'bg-green-100 text-green-800',
        inactive: 'bg-gray-100 text-gray-800',
        debtors: 'bg-red-100 text-red-800',
      };
      return badges[segment] || 'bg-gray-100 text-gray-800';
    },
    viewProfile(clientId) {
      console.log('View profile:', clientId);
      // Aquí iría la lógica para ver el perfil del cliente
    },
    editClient(clientId) {
      this.editingClientId = clientId;
      const client = this.clients.find(c => c.id === clientId);
      if (client) {
        this.formData = {
          name: client.name,
          email: client.email,
          phone: client.phone,
          notes: '',
        };
        this.showCreateModal = true;
      }
    },
    deleteClient(clientId) {
      if (confirm('¿Estás seguro de que deseas eliminar este cliente?')) {
        this.clients = this.clients.filter(c => c.id !== clientId);
      }
    },
    saveClient() {
      if (this.editingClientId) {
        const index = this.clients.findIndex(c => c.id === this.editingClientId);
        if (index !== -1) {
          this.clients[index] = { ...this.clients[index], ...this.formData };
        }
      } else {
        this.clients.push({
          id: Math.max(...this.clients.map(c => c.id), 0) + 1,
          ...this.formData,
          appointmentCount: 0,
          totalSpent: 0,
          segment: 'new',
          active: true,
        });
      }
      this.resetForm();
    },
    resetForm() {
      this.showCreateModal = false;
      this.editingClientId = null;
      this.formData = {
        name: '',
        email: '',
        phone: '',
        notes: '',
      };
    },
    previousPage() {
      if (this.currentPage > 1) this.currentPage--;
    },
    nextPage() {
      this.currentPage++;
    },
    exportToExcel() {
      console.log('Exporting to Excel...');
      // Aquí iría la lógica para exportar a Excel
    },
    exportToPDF() {
      console.log('Exporting to PDF...');
      // Aquí iría la lógica para exportar a PDF
    },
  },
};
</script>

<style scoped>
/* Estilos adicionales si es necesario */
</style>
