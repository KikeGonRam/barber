import './bootstrap';

import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import { Chart, registerables } from 'chart.js';

Alpine.plugin(intersect);
Chart.register(...registerables);

window.Alpine = Alpine;
window.Chart = Chart;

Alpine.start();
