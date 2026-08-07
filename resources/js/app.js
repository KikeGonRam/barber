/**
 * Punto de entrada principal del bundle JS (Vite). Arranca Alpine.js (usado en
 * las vistas Blade para interactividad ligera: acordeones, dropdowns, etc.) y
 * registra Chart.js junto con los gráficos de analítica (analytics-charts.js).
 */
import './bootstrap';

import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import { Chart, registerables } from 'chart.js';
import { registerAnalyticsCharts } from './analytics-charts';

// Plugin de Alpine que permite usar x-intersect (acciones al entrar en viewport,
// p. ej. animaciones de aparición o carga perezosa).
Alpine.plugin(intersect);
// Registra todos los controladores/escalas/plugins built-in de Chart.js
// (barras, líneas, dona, radar, etc.) de una sola vez.
Chart.register(...registerables);

// Se exponen globalmente porque las vistas Blade (fuera del bundle) los referencian
// directamente como window.Alpine / window.Chart.
window.Alpine = Alpine;
window.Chart = Chart;

// Inicializa los gráficos de la página de Analítica/Spark buscando canvases
// marcados con [data-ub-analytics-chart] en el DOM.
registerAnalyticsCharts(Chart);

Alpine.start();
