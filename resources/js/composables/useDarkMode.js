import { ref, computed, watch } from 'vue';

/**
 * Composable para manejar Dark Mode
 * Persiste la preferencia en localStorage y respeta preferencias del SO
 */
export function useDarkMode() {
  const isDark = ref(false);

  // Detectar preferencia del SO al montar
  const initDarkMode = () => {
    const saved = localStorage.getItem('darkMode');
    
    if (saved !== null) {
      isDark.value = saved === 'true';
    } else {
      // Usar preferencia del sistema
      isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    applyDarkMode();
  };

  // Aplicar dark mode al documento
  const applyDarkMode = () => {
    if (isDark.value) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    localStorage.setItem('darkMode', isDark.value);
  };

  // Toggle dark mode
  const toggleDarkMode = () => {
    isDark.value = !isDark.value;
  };

  // Watch para cambios
  watch(isDark, () => {
    applyDarkMode();
  });

  return {
    isDark: computed(() => isDark.value),
    toggleDarkMode,
    initDarkMode,
  };
}
