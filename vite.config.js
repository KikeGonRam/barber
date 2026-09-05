import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            // 'inertia.js' es un entry point NUEVO y separado para las páginas
            // que se vayan migrando a Inertia+Vue; 'app.js' (Alpine/Chart.js/GSAP)
            // sigue intacto para el resto del sitio. Ver .claude/skills/inertia-vue-migration.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/inertia.js'],
            refresh: true,
        }),
        vue(),
    ],
});
