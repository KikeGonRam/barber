<script setup>
/**
 * Equivalente Vue de <x-app-layout> (resources/views/layouts/app.blade.php)
 * PERO solo para la porción de header-card + main: el sidebar/topbar/
 * bottom-nav/widgets globales se quedan en Blade+Alpine dentro de
 * resources/views/app.blade.php a propósito (ver el comentario ahí y
 * .claude/skills/inertia-vue-migration/SKILL.md, Fase 2). Cada página Vue
 * migrada se envuelve en este layout igual que las páginas Blade usan
 * <x-app-layout>.
 *
 * Puente de notificaciones: los flashes de sesión (status/error) llegan como
 * prop compartida de Inertia (ver HandleInertiaRequests::share()) en vez de
 * el <script> inline que usaba layouts/app.blade.php, porque Inertia no
 * vuelve a renderizar app.blade.php en navegaciones internas (SPA). Se
 * re-emiten como el mismo evento 'notify' que ya escucha <x-toast/>, así el
 * widget de toast (Alpine) no se reimplementa en Vue.
 */
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

watch(
  () => page.props.flash,
  (flash) => {
    if (!flash) return;

    if (flash.status) {
      window.dispatchEvent(
        new CustomEvent('notify', { detail: { message: flash.status, type: 'success' } }),
      );
    }
    if (flash.error) {
      window.dispatchEvent(
        new CustomEvent('notify', { detail: { message: flash.error, type: 'error' } }),
      );
    }
  },
  { immediate: true, deep: true },
);
</script>

<template>
  <header class="p-4 pb-3 sm:p-6 sm:pb-4" v-if="$slots.header">
    <div class="ui-card-premium px-5 py-4 sm:px-6 sm:py-5">
      <slot name="header" />
    </div>
  </header>

  <main class="px-4 pb-28 sm:px-6 lg:pb-8 lg:px-8">
    <div class="mx-auto w-full max-w-[1340px] page-content">
      <slot />
    </div>
  </main>
</template>
