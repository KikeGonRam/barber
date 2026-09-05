/**
 * Configuración compartida de Chart.js para las páginas del dashboard
 * migradas a Inertia+Vue. Espejo del <script> al final de
 * resources/views/dashboard.blade.php (que siguen usando los roles aún no
 * migrados) — mismos defaults de fuente/tooltip, misma paleta categórica y
 * mismos formateadores, para que un dashboard migrado se vea idéntico al
 * que sigue en Blade. Importar este módulo registra Chart.js una sola vez.
 */
import { Chart as ChartJS, registerables } from 'chart.js';

ChartJS.register(...registerables);

ChartJS.defaults.font.family = "'Figtree', sans-serif";
ChartJS.defaults.color = 'rgba(255,255,255,0.3)';
ChartJS.defaults.font.weight = 'bold';

Object.assign(ChartJS.defaults.plugins.tooltip, {
  backgroundColor: 'rgba(10,10,10,0.96)',
  titleColor: '#d4af37',
  titleFont: { weight: '900', size: 11 },
  bodyColor: '#ffffff',
  bodyFont: { weight: 'bold', size: 11 },
  borderColor: 'rgba(212,175,55,0.3)',
  borderWidth: 1,
  padding: 10,
  cornerRadius: 8,
  boxPadding: 4,
  usePointStyle: true,
});

export const chartScale = {
  ticks: { color: 'rgba(255,255,255,0.3)', font: { size: 10 } },
  grid: { color: 'rgba(255,255,255,0.05)' },
  border: { display: false },
};

// Paleta categórica (validada: banda de luminosidad oscura, ΔE CVD >= 8 frente
// al fondo de tarjeta #111) para gráficas con varias series del mismo tipo.
export const UB_CATEGORICAL = ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300'];

export function fmtMoney(v) {
  return '$' + Number(v ?? 0).toLocaleString('es-MX', { maximumFractionDigits: 0 });
}

export function fmtInt(v) {
  return Number(v ?? 0).toLocaleString('es-MX', { maximumFractionDigits: 0 });
}
