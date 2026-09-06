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
                 * Los valores cambian según el tema elegido en /profile (data-theme en <html>).
                 *
                 * NOTA: main/card/accent/panel/line/muted NO admiten opacidad directa
                 * (ej. bg-card/50) porque son hex planos. "ink" SÍ la admite (ver abajo) —
                 * úsala para cualquier wash/borde/texto translúcido en vez de bg-white/N,
                 * text-white/N, border-white/N: "white" es SIEMPRE blanco puro sin
                 * importar el tema (se ve mal en "libreta", el único tema claro), mientras
                 * que bg-ink/N, text-ink/N, border-ink/N se adaptan (casi blanco en los 3
                 * temas oscuros, tinta oscura en el claro).
                 */
                'main':   'var(--bg-main)',   // fondo general (body)
                'card':   'var(--bg-card)',   // fondo de tarjetas .ui-card
                'accent': 'var(--bg-accent)', // fondo de hover / elementos secundarios
                'panel':  'var(--panel)',     // fondo de paneles / tablas
                'line':   'var(--line)',      // color de bordes / separadores
                'muted':  'var(--muted)',     // texto secundario / etiquetas
                'ink':    'rgb(var(--ink-rgb) / <alpha-value>)', // texto principal — admite opacidad (bg-ink/10, text-ink/50...)

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
