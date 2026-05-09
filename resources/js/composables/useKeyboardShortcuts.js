import { onMounted, onUnmounted } from 'vue';

/**
 * Composable para manejar Keyboard Shortcuts
 * Permite registrar y usar atajos de teclado en la aplicación
 */
export function useKeyboardShortcuts() {
  const shortcuts = new Map();

  /**
   * Registrar un atajo de teclado
   * @param {String} keys - Combinación (ej: 'Ctrl+K', 'Cmd+/', 'Escape')
   * @param {Function} callback - Función a ejecutar
   * @param {String} description - Descripción del atajo
   */
  const register = (keys, callback, description = '') => {
    shortcuts.set(keys, { callback, description });
  };

  /**
   * Manejar eventos de teclado
   */
  const handleKeyDown = (event) => {
    // Ignorar si está escribiendo en un input
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName)) {
      return;
    }

    let keyCombo = '';

    // Construir la combinación
    if (event.ctrlKey || event.metaKey) {
      keyCombo += 'Ctrl+';
    }
    if (event.shiftKey) {
      keyCombo += 'Shift+';
    }
    if (event.altKey) {
      keyCombo += 'Alt+';
    }

    keyCombo += event.key.toUpperCase();

    // Buscar atajo
    if (shortcuts.has(keyCombo)) {
      event.preventDefault();
      shortcuts.get(keyCombo).callback();
    }
  };

  // Montar y desmontar listeners
  onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
  });

  onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
  });

  /**
   * Obtener lista de atajos registrados
   */
  const getShortcuts = () => {
    return Array.from(shortcuts.entries()).map(([keys, { description }]) => ({
      keys,
      description,
    }));
  };

  return {
    register,
    getShortcuts,
  };
}

/**
 * Atajos predefinidos útiles
 */
export const COMMON_SHORTCUTS = {
  SEARCH: 'Ctrl+K',
  SAVE: 'Ctrl+S',
  HELP: 'Ctrl+?',
  TOGGLE_DARK: 'Ctrl+Shift+D',
  ESCAPE: 'ESCAPE',
  ENTER: 'ENTER',
};
