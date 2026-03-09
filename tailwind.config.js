import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
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
                'main': '#0a0a0a',
                'card': '#141414',
                'accent': '#1e1e1e',
                'gold': '#d4af37',
                'gold-dim': '#aa8c2c',
                'line': '#333333',
                'muted': '#b0b0b0',
                'ink': '#ffffff',
                'panel': '#111111',
            },
        },
    },

    plugins: [forms],
};
