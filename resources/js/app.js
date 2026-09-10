/**
 * Punto de entrada principal del bundle JS (Vite). Arranca Alpine.js (usado en
 * las vistas Blade para interactividad ligera: acordeones, dropdowns, etc.) y
 * la animación del hero de la landing pública.
 */
import './bootstrap';

import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import { initHeroAnimation } from './hero-animation';

// Plugin de Alpine que permite usar x-intersect (acciones al entrar en viewport,
// p. ej. animaciones de aparición o carga perezosa).
Alpine.plugin(intersect);

// Se expone globalmente porque las vistas Blade (fuera del bundle) lo referencian
// directamente como window.Alpine.
window.Alpine = Alpine;

// No-op si el hero de la landing pública no está en la página actual.
initHeroAnimation();

Alpine.start();
