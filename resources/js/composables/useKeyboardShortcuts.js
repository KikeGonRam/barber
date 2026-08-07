import { onMounted, onUnmounted } from 'vue';

/**
 * Composable para manejar Keyboard Shortcuts
 * Permite registrar y usar atajos de teclado en la aplicación
 */
export function useKeyboardShortcuts() {
  // Mapa keyCombo -> { callback, description }; keyCombo es un string normalizado
  // como "Ctrl+K" generado por handleKeyDown.
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
   * Manejar eventos de teclado: construye la misma representación de string
   * usada al registrar ("Ctrl+K", etc.) y ejecuta el callback si coincide.
   */
  const handleKeyDown = (event) => {
    // Ignorar si está escribiendo en un input, textarea o select, para no
    // interceptar atajos mientras el usuario teclea texto normal.
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName)) {
      return;
    }

    let keyCombo = '';

    // Construir la combinación en el mismo orden que se espera en register()
    // (Ctrl/Cmd -> Shift -> Alt -> tecla). metaKey cubre Cmd en Mac.
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

    // Buscar atajo y, si existe, prevenir el comportamiento por defecto del
    // navegador (p. ej. Ctrl+S abriendo el diálogo de guardar) antes de ejecutarlo.
    if (shortcuts.has(keyCombo)) {
      event.preventDefault();
      shortcuts.get(keyCombo).callback();
    }
  };

  // Registra el listener global al montar el componente y lo retira al
  // desmontar, para no acumular listeners duplicados entre navegaciones.
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
