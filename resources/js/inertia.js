/**
 * Punto de entrada SEPARADO de app.js, solo para páginas migradas a
 * Inertia+Vue (ver .claude/skills/inertia-vue-migration/SKILL.md). app.js
 * (Alpine.js/Chart.js/GSAP) sigue siendo el bundle de las vistas Blade
 * clásicas y no se toca — ambos coexisten mientras dure la migración.
 *
 * Alpine también se inicializa aquí (no solo en app.js): el shell de
 * navegación (sidebar/topbar/bottom-nav) y los widgets globales (toast,
 * notificaciones, chatbot, command palette) de resources/views/app.blade.php
 * siguen siendo Blade+Alpine a propósito — ver el comentario de diseño en
 * AppLayout.vue y el SKILL.md de la migración.
 */
import './bootstrap';

import Alpine from 'alpinejs';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';

window.Alpine = Alpine;
Alpine.start();

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });

    return pages[`./Pages/${name}.vue`];
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(ZiggyVue)
      .mount(el);
  },
});
