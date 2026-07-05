import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    /*
     * darkMode: 'class'  →  el modo oscuro se activa cuando <html> tiene la clase "dark".
     * Para cambiar el modo: document.documentElement.classList.add/remove('dark')
     */
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                /*
                 * TOKENS DE TEMA — apuntan a variables CSS en app.css.
                 * Los valores cambian automáticamente al agregar/quitar class="dark" en <html>.
                 *
                 * ⚠️  NOTA: estas clases NO admiten opacidad directa (ej. bg-card/50).
                 *    Para opacidad usa bg-white/10, bg-black/20, etc.
                 */
                'main':   'var(--bg-main)',   // fondo general (body)
                'card':   'var(--bg-card)',   // fondo de tarjetas .ui-card
                'accent': 'var(--bg-accent)', // fondo de hover / elementos secundarios
                'panel':  'var(--panel)',     // fondo de paneles / tablas
                'line':   'var(--line)',      // color de bordes / separadores
                'muted':  'var(--muted)',     // texto secundario / etiquetas
                'ink':    'var(--ink)',       // texto principal

                /*
                 * ORO — valor estático porque nunca cambia entre temas
                 * y necesitamos soporte de opacidad: border-gold/30, bg-gold/10, etc.
                 * Para cambiar el color de los botones dorados, cambia AQUÍ y en --gold de app.css.
                 */
                'gold':     '#d4af37',
                'gold-dim': '#aa8c2c',
            },
        },
    },

    plugins: [forms],
};
