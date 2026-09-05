import prettierConfig from 'eslint-config-prettier';
import prettierPlugin from 'eslint-plugin-prettier';
import vuePlugin from 'eslint-plugin-vue';
import vueParser from 'vue-eslint-parser';
import globals from 'globals';

export default [
    {
        ignores: ['node_modules/**', 'public/build/**', 'vendor/**', 'storage/**', 'bootstrap/cache/**'],
    },
    {
        files: ['resources/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
            },
        },
        plugins: {
            prettier: prettierPlugin,
        },
        rules: {
            ...prettierConfig.rules,
            'prettier/prettier': 'warn',
        },
    },
    // Páginas/componentes de la migración a Inertia+Vue (ver
    // .claude/skills/inertia-vue-migration/SKILL.md). Separado del bloque de
    // arriba porque .vue necesita el parser dedicado de eslint-plugin-vue.
    {
        files: ['resources/js/**/*.vue'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                ecmaVersion: 'latest',
                sourceType: 'module',
            },
            globals: {
                ...globals.browser,
                ...globals.node,
            },
        },
        plugins: {
            vue: vuePlugin,
            prettier: prettierPlugin,
        },
        rules: {
            ...vuePlugin.configs['flat/recommended'].rules,
            ...prettierConfig.rules,
            'prettier/prettier': 'warn',
        },
    },
];
