<template>
  <div class="relative group">
    <!-- Toggle Button -->
    <button
      @click="toggle"
      title="Toggle Dark Mode (Ctrl+Shift+D)"
      aria-label="Toggle dark mode"
      class="relative inline-flex items-center justify-center w-10 h-10 rounded-lg transition-all duration-300"
      :class="[
        isDark
          ? 'bg-gray-700 hover:bg-gray-600 text-yellow-400'
          : 'bg-gray-100 hover:bg-gray-200 text-blue-600',
      ]"
    >
      <!-- Sun Icon (Light Mode) -->
      <svg
        v-if="!isDark"
        xmlns="http://www.w3.org/2000/svg"
        class="w-6 h-6 transition-transform duration-300 rotate-0"
        fill="currentColor"
        viewBox="0 0 24 24"
      >
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z" />
      </svg>

      <!-- Moon Icon (Dark Mode) -->
      <svg
        v-else
        xmlns="http://www.w3.org/2000/svg"
        class="w-6 h-6 transition-transform duration-300 rotate-0"
        fill="currentColor"
        viewBox="0 0 24 24"
      >
        <path d="M21.64 13a1 1 0 0 0-1.05-.14 8 8 0 1 1 .12-11.6 1 1 0 0 0 1.25-1.25 10 10 0 1 0 .27 14.26 1 1 0 0 0-.19-1.31z" />
      </svg>
    </button>

    <!-- Tooltip -->
    <div
      class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1 text-sm text-white bg-gray-900 rounded-md whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none"
    >
      {{ isDark ? 'Modo Claro' : 'Modo Oscuro' }}
      <span class="text-xs text-gray-400 block">Ctrl+Shift+D</span>
    </div>
  </div>
</template>

<script>
import { useDarkMode } from '../../composables/useDarkMode';
import { useKeyboardShortcuts, COMMON_SHORTCUTS } from '../../composables/useKeyboardShortcuts';

export default {
  name: 'DarkModeToggle',
  setup() {
    const { isDark, toggleDarkMode, initDarkMode } = useDarkMode();
    const { register } = useKeyboardShortcuts();

    // Inicializar dark mode
    initDarkMode();

    // Registrar atajo de teclado
    register(COMMON_SHORTCUTS.TOGGLE_DARK, toggleDarkMode, 'Toggle Dark Mode');

    const toggle = () => {
      toggleDarkMode();
    };

    return {
      isDark,
      toggle,
    };
  },
};
</script>

<style scoped>
/* Transiciones suaves */
button {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

button:active {
  transform: scale(0.95);
}
</style>
