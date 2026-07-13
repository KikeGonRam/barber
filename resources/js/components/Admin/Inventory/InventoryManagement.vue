<template>
  <div class="bg-white rounded-lg shadow-lg p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h3 class="text-2xl font-bold text-gray-900">Gestión de Inventario</h3>
      <div class="flex gap-3">
        <input
          v-model="searchProduct"
          type="text"
          placeholder="Buscar producto..."
          class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <button
          @click="showAddModal = true"
          class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold"
        >
          Nuevo Producto
        </button>
      </div>
    </div>

    <!-- Filtros -->
    <div class="flex gap-4 mb-6 pb-6 border-b">
      <select v-model="filterStatus" class="px-4 py-2 border rounded-lg">
        <option value="">Todos los estados</option>
        <option value="ok">Stock OK</option>
        <option value="low">Stock Bajo</option>
        <option value="critical">Crítico</option>
        <option value="empty">Agotado</option>
      </select>
      <select v-model="filterCategory" class="px-4 py-2 border rounded-lg">
        <option value="">Todas las categorías</option>
        <option value="pomada">Pomadas</option>
        <option value="shampoo">Shampoos</option>
        <option value="tools">Herramientas</option>
        <option value="other">Otros</option>
      </select>
      <button
        @click="refreshInventory"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold"
      >
        Actualizar
      </button>
    </div>

    <!-- Resumen rápido -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-blue-50 rounded-lg p-4 border-l-4 border-blue-600">
        <p class="text-gray-600 text-sm">Total Productos</p>
        <p class="text-2xl font-bold text-blue-600">{{ totalProducts }}</p>
      </div>
      <div class="bg-green-50 rounded-lg p-4 border-l-4 border-green-600">
        <p class="text-gray-600 text-sm">Valor Total Stock</p>
        <p class="text-2xl font-bold text-green-600">${{ totalInventoryValue }}</p>
      </div>
      <div class="bg-yellow-50 rounded-lg p-4 border-l-4 border-yellow-600">
        <p class="text-gray-600 text-sm">Stock Bajo</p>
        <p class="text-2xl font-bold text-yellow-600">{{ lowStockCount }}</p>
      </div>
      <div class="bg-red-50 rounded-lg p-4 border-l-4 border-red-600">
        <p class="text-gray-600 text-sm">Crítico/Agotado</p>
        <p class="text-2xl font-bold text-red-600">{{ criticalCount }}</p>
      </div>
    </div>

    <!-- Tabla de productos -->
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-100 border-b-2 border-gray-300">
            <th class="px-4 py-3 text-left font-bold">Producto</th>
            <th class="px-4 py-3 text-center font-bold">Categoría</th>
            <th class="px-4 py-3 text-right font-bold">Stock</th>
            <th class="px-4 py-3 text-right font-bold">Mín.</th>
            <th class="px-4 py-3 text-right font-bold">Precio</th>
            <th class="px-4 py-3 text-right font-bold">Valor Total</th>
            <th class="px-4 py-3 text-center font-bold">Estado</th>
            <th class="px-4 py-3 text-center font-bold">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="product in filteredProducts" :key="product.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 font-semibold">{{ product.name }}</td>
            <td class="px-4 py-3 text-center">
              <span class="px-2 py-1 bg-gray-100 rounded text-xs">{{ product.category }}</span>
            </td>
            <td class="px-4 py-3 text-right font-bold">{{ product.quantity }}</td>
            <td class="px-4 py-3 text-right text-gray-600">{{ product.minStock }}</td>
            <td class="px-4 py-3 text-right">${{ product.price }}</td>
            <td class="px-4 py-3 text-right font-bold">${{ (product.quantity * product.price).toFixed(2) }}</td>
            <td class="px-4 py-3 text-center">
              <span
                :class="[
                  'px-3 py-1 rounded-full text-xs font-bold',
                  getStatusClass(product),
                ]"
              >
                {{ getStatusLabel(product) }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <button
                @click="editProduct(product.id)"
                class="px-2 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600 text-xs mr-2"
              >

              </button>
              <button
                @click="deleteProduct(product.id)"
                class="px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-xs"
              >

              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="filteredProducts.length === 0" class="text-center py-8 text-gray-500">
        No se encontraron productos
      </div>
    </div>

    <!-- Modal para agregar/editar producto -->
    <div v-if="showAddModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h4 class="text-lg font-bold mb-4">{{ editingId ? 'Editar Producto' : 'Nuevo Producto' }}</h4>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-semibold mb-2">Nombre:</label>
            <input
              v-model="formData.name"
              type="text"
              placeholder="Nombre del producto"
              class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label class="block text-sm font-semibold mb-2">Categoría:</label>
            <select v-model="formData.category" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option>Pomadas</option>
              <option>Shampoos</option>
              <option>Herramientas</option>
              <option>Otros</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold mb-2">Stock Actual:</label>
              <input
                v-model.number="formData.quantity"
                type="number"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label class="block text-sm font-semibold mb-2">Stock Mínimo:</label>
              <input
                v-model.number="formData.minStock"
                type="number"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold mb-2">Precio Unitario:</label>
              <input
                v-model.number="formData.price"
                type="number"
                step="0.01"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label class="block text-sm font-semibold mb-2">Proveedor:</label>
              <input
                v-model="formData.supplier"
                type="text"
                placeholder="Nombre proveedor"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>
        </div>

        <div class="flex gap-3 mt-6">
          <button
            @click="saveProduct"
            class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold"
          >
            Guardar
          </button>
          <button
            @click="closeModal"
            class="flex-1 px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500 font-semibold"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <!-- Movimientos recientes -->
    <div class="mt-8 pt-6 border-t">
      <h4 class="text-lg font-bold mb-4">Movimientos Recientes</h4>
      <div class="space-y-2 max-h-48 overflow-y-auto">
        <div v-for="movement in recentMovements" :key="movement.id" class="bg-gray-50 rounded-lg p-3 border-l-4" :class="getMovementClass(movement)">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-semibold">{{ movement.product }}</p>
              <p class="text-sm text-gray-600">{{ movement.description }}</p>
            </div>
            <div class="text-right">
              <p class="font-bold" :class="movement.type === 'entrada' ? 'text-green-600' : 'text-red-600'">
                {{ movement.type === 'entrada' ? '+' : '-' }}{{ movement.quantity }}
              </p>
              <p class="text-xs text-gray-500">{{ movement.date }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'InventoryManagement',
  data() {
    return {
      searchProduct: '',
      filterStatus: '',
      filterCategory: '',
      showAddModal: false,
      editingId: null,
      formData: {
        name: '',
        category: 'Pomadas',
        quantity: 0,
        minStock: 5,
        price: 0,
        supplier: '',
      },
      products: [
        { id: 1, name: 'Pomada Matte', category: 'Pomadas', quantity: 45, minStock: 10, price: 12.99, supplier: 'BarberSupply Co.' },
        { id: 2, name: 'Pomada Brillante', category: 'Pomadas', quantity: 3, minStock: 10, price: 14.99, supplier: 'BarberSupply Co.' },
        { id: 3, name: 'Shampoo Profesional', category: 'Shampoos', quantity: 8, minStock: 8, price: 8.99, supplier: 'ProBeauty Inc.' },
        { id: 4, name: 'Acondicionador', category: 'Shampoos', quantity: 0, minStock: 5, price: 9.99, supplier: 'ProBeauty Inc.' },
        { id: 5, name: 'Maquinilla Clipper', category: 'Herramientas', quantity: 2, minStock: 1, price: 89.99, supplier: 'Tools World' },
        { id: 6, name: 'Tijeras Profesionales', category: 'Herramientas', quantity: 12, minStock: 3, price: 45.50, supplier: 'Tools World' },
        { id: 7, name: 'Bálsamo Aftershave', category: 'Otros', quantity: 25, minStock: 8, price: 6.50, supplier: 'Beauty Plus' },
      ],
      recentMovements: [
        { id: 1, product: 'Pomada Matte', type: 'entrada', quantity: 20, description: 'Entrada de compra', date: '2026-05-09' },
        { id: 2, product: 'Shampoo Profesional', type: 'salida', quantity: 5, description: 'Venta', date: '2026-05-09' },
        { id: 3, product: 'Tijeras Profesionales', type: 'entrada', quantity: 3, description: 'Devolución proveedor', date: '2026-05-08' },
        { id: 4, product: 'Acondicionador', type: 'salida', quantity: 8, description: 'Venta', date: '2026-05-08' },
      ],
    };
  },
  computed: {
    filteredProducts() {
      return this.products.filter(product => {
        const matchSearch = product.name.toLowerCase().includes(this.searchProduct.toLowerCase());
        const matchCategory = !this.filterCategory || product.category === this.filterCategory;
        const matchStatus = !this.filterStatus || this.getStatus(product) === this.filterStatus;

        return matchSearch && matchCategory && matchStatus;
      });
    },
    totalProducts() {
      return this.products.length;
    },
    totalInventoryValue() {
      return this.products.reduce((sum, p) => sum + p.quantity * p.price, 0).toFixed(2);
    },
    lowStockCount() {
      return this.products.filter(p => p.quantity > 0 && p.quantity <= p.minStock).length;
    },
    criticalCount() {
      return this.products.filter(p => p.quantity === 0 || p.quantity < p.minStock / 2).length;
    },
  },
  methods: {
    getStatus(product) {
      if (product.quantity === 0) return 'empty';
      if (product.quantity < product.minStock / 2) return 'critical';
      if (product.quantity <= product.minStock) return 'low';
      return 'ok';
    },
    getStatusLabel(product) {
      const status = this.getStatus(product);
      return {
        ok: 'OK',
        low: 'Bajo',
        critical: 'Crítico',
        empty: 'Agotado',
      }[status];
    },
    getStatusClass(product) {
      const status = this.getStatus(product);
      return {
        ok: 'bg-green-100 text-green-800',
        low: 'bg-yellow-100 text-yellow-800',
        critical: 'bg-orange-100 text-orange-800',
        empty: 'bg-red-100 text-red-800',
      }[status];
    },
    getMovementClass(movement) {
      return movement.type === 'entrada' ? 'border-green-500' : 'border-red-500';
    },
    editProduct(productId) {
      const product = this.products.find(p => p.id === productId);
      if (product) {
        this.formData = { ...product };
        this.editingId = productId;
        this.showAddModal = true;
      }
    },
    saveProduct() {
      if (!this.formData.name) {
        alert('Ingresa el nombre del producto');
        return;
      }

      if (this.editingId) {
        const index = this.products.findIndex(p => p.id === this.editingId);
        if (index > -1) {
          this.products.splice(index, 1, { ...this.formData, id: this.editingId });
        }
      } else {
        this.products.push({
          ...this.formData,
          id: Math.max(...this.products.map(p => p.id), 0) + 1,
        });
      }

      this.closeModal();
      alert('Producto guardado correctamente');
    },
    deleteProduct(productId) {
      if (confirm('¿Eliminar este producto?')) {
        const index = this.products.findIndex(p => p.id === productId);
        if (index > -1) {
          this.products.splice(index, 1);
          alert('Producto eliminado');
        }
      }
    },
    closeModal() {
      this.showAddModal = false;
      this.editingId = null;
      this.formData = {
        name: '',
        category: 'Pomadas',
        quantity: 0,
        minStock: 5,
        price: 0,
        supplier: '',
      };
    },
    refreshInventory() {
      console.log('Refreshing inventory...');
      alert('Inventario actualizado');
    },
  },
};
</script>

<style scoped>
/* Estilos adicionales si es necesario */
</style>
