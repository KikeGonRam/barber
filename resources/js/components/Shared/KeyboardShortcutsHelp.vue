<template>
  <div>
    <!-- Botón para abrir el modal -->
    <button
      @click="showModal = true"
      title="Help & Keyboard Shortcuts (Ctrl+?)"
      aria-label="Show help and keyboard shortcuts"
      class="relative inline-flex items-center justify-center w-10 h-10 rounded-lg transition-all duration-300 bg-gray-100 hover:bg-gray-200 text-blue-600 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-yellow-400"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
      </svg>
    </button>

    <!-- Modal -->
    <transition name="fade">
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
        @click="showModal = false"
      >
        <div
          class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl max-w-2xl w-full mx-4 max-h-96 overflow-y-auto"
          @click.stop
        >
          <!-- Header -->
          <div class="sticky top-0 bg-blue-600 dark:bg-blue-700 text-white px-6 py-4 flex items-center justify-between">
            <h2 class="text-xl font-bold flex items-center gap-2">
              ⌨️ Atajos de Teclado
            </h2>
            <button
              @click="showModal = false"
              class="text-white hover:text-gray-200 transition"
              aria-label="Close"
            >
              ✕
            </button>
          </div>

          <!-- Content -->
          <div class="p-6">
            <!-- Categorías de atajos -->
            <div class="space-y-6">
              <!-- Navegación -->
              <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3">🧭 Navegación</h3>
                <div class="space-y-2">
                  <div v-for="shortcut in navigationShortcuts" :key="shortcut.keys" class="flex items-center justify-between">
                    <span class="text-gray-700 dark:text-gray-300">{{ shortcut.description }}</span>
                    <kbd class="px-3 py-1 bg-gray-100 dark:bg-gray-700 rounded font-mono text-sm font-semibold text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                      {{ shortcut.keys }}
                    </kbd>
                  </div>
                </div>
              </div>

              <!-- Edición -->
              <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3">✏️ Edición</h3>
                <div class="space-y-2">
                  <div class="flex items-center justify-between">
                    <span class="text-gray-700 dark:text-gray-300">Guardar</span>
                    <kbd class="px-3 py-1 bg-gray-100 dark:bg-gray-700 rounded font-mono text-sm font-semibold text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                      Ctrl+S
                    </kbd>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-gray-700 dark:text-gray-300">Cancelar</span>
                    <kbd class="px-3 py-1 bg-gray-100 dark:bg-gray-700 rounded font-mono text-sm font-semibold text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                      Escape
                    </kbd>
                  </div>
                </div>
              </div>

              <!-- Herramientas -->
              <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3">🛠️ Herramientas</h3>
                <div class="space-y-2">
                  <div class="flex items-center justify-between">
                    <span class="text-gray-700 dark:text-gray-300">Buscar</span>
                    <kbd class="px-3 py-1 bg-gray-100 dark:bg-gray-700 rounded font-mono text-sm font-semibold text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                      Ctrl+K
                    </kbd>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-gray-700 dark:text-gray-300">Toggle Dark Mode</span>
                    <kbd class="px-3 py-1 bg-gray-100 dark:bg-gray-700 rounded font-mono text-sm font-semibold text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                      Ctrl+Shift+D
                    </kbd>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-gray-700 dark:text-gray-300">Ayuda</span>
                    <kbd class="px-3 py-1 bg-gray-100 dark:bg-gray-700 rounded font-mono text-sm font-semibold text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                      Ctrl+?
                    </kbd>
                  </div>
                </div>
              </div>

              <!-- Tips -->
              <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4 border-l-4 border-blue-600">
                <p class="text-sm text-blue-900 dark:text-blue-100">
                  💡 <strong>Tip:</strong> Los atajos de teclado no funcionan mientras escribes en inputs o textareas.
                </p>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 border-t border-gray-200 dark:border-gray-600">
            <button
              @click="showModal = false"
              class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
import { ref } from 'vue';
import { useKeyboardShortcuts, COMMON_SHORTCUTS } from '../../composables/useKeyboardShortcuts';

export default {
  name: 'KeyboardShortcutsHelp',
  setup() {
    const showModal = ref(false);
    const { register } = useKeyboardShortcuts();

    // Registrar atajo para mostrar help
    register(COMMON_SHORTCUTS.HELP, () => {
      showModal.value = !showModal.value;
    }, 'Show help');

    const navigationShortcuts = [
      { keys: '←/→', description: 'Navegar entre pestañas' },
      { keys: 'Ctrl+1', description: 'Dashboard' },
      { keys: 'Ctrl+2', description: 'Barberos' },
      { keys: 'Ctrl+3', description: 'Clientes' },
    ];

    return {
      showModal,
      navigationShortcuts,
    };
  },
};
</script>

<style scoped>
/* Transición del modal */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
