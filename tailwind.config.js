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
                analytics: ['"Plus Jakarta Sans"', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                /*
                 * TOKENS DE TEMA — apuntan a variables CSS en app.css.
                 * Los valores cambian automáticamente al agregar/quitar class="dark" en <html>.
                 *
                 * NOTA: estas clases NO admiten opacidad directa (ej. bg-card/50).
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
                 * ACENTO DE MARCA — apunta a --gold-rgb/--gold-dim-rgb (canales
                 * "R G B" separados por espacio, ver app.css) envueltos en
                 * rgb(... / <alpha-value>): así SÍ admite opacidad (bg-gold/10,
                 * border-gold/30, etc.) Y cambia con el tema elegido en /profile,
                 * a diferencia de un hex estático. Los 4 temas usan el nombre
                 * "gold" para su propio acento (oro, cobre, latón...) aunque no
                 * todos sean oro literal.
                 */
                'gold':     'rgb(var(--gold-rgb) / <alpha-value>)',
                'gold-dim': 'rgb(var(--gold-dim-rgb) / <alpha-value>)',
            },
        },
    },

    plugins: [forms],
};
