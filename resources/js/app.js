import './bootstrap';

import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import { Chart, registerables } from 'chart.js';
import { registerAnalyticsCharts } from './analytics-charts';

Alpine.plugin(intersect);
Chart.register(...registerables);

window.Alpine = Alpine;
window.Chart = Chart;

registerAnalyticsCharts(Chart);

Alpine.start();
