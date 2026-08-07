import { ref, computed, watch } from 'vue';

/**
 * Composable para manejar Dark Mode
 * Persiste la preferencia en localStorage y respeta preferencias del SO
 */
export function useDarkMode() {
  const isDark = ref(false);

  // Detectar preferencia del SO al montar. Prioridad: valor guardado por el
  // usuario en localStorage > preferencia del sistema operativo (prefers-color-scheme).
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

  // Aplica/quita la clase "dark" en <html> (usada por Tailwind con estrategia
  // "class") y persiste la elección en localStorage para futuras visitas.
  const applyDarkMode = () => {
    if (isDark.value) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    localStorage.setItem('darkMode', isDark.value);
  };

  // Alterna entre modo claro y oscuro
  const toggleDarkMode = () => {
    isDark.value = !isDark.value;
  };

  // Cada vez que cambia isDark (por toggle o por código), se refleja en el DOM
  // y se guarda automáticamente.
  watch(isDark, () => {
    applyDarkMode();
  });

  return {
    // Se expone como computed (solo lectura) para forzar que los componentes
    // usen toggleDarkMode() en vez de mutar isDark directamente.
    isDark: computed(() => isDark.value),
    toggleDarkMode,
    initDarkMode,
  };
}
