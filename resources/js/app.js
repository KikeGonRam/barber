/**
 * Punto de entrada principal del bundle JS (Vite). Arranca Alpine.js, usado
 * en las vistas Blade que sobreviven (verify-email, confirm-password, etc.)
 * para interactividad ligera: acordeones, dropdowns, el input de código de
 * verificación, etc.
 */
import './bootstrap';

import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';

// Plugin de Alpine que permite usar x-intersect (acciones al entrar en viewport,
// p. ej. animaciones de aparición o carga perezosa).
Alpine.plugin(intersect);

// Se expone globalmente porque las vistas Blade (fuera del bundle) lo referencian
// directamente como window.Alpine.
window.Alpine = Alpine;

Alpine.start();
