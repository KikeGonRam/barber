/**
 * Módulo de renderizado de gráficos para la página de Analítica (Spark).
 *
 * El backend (AnalyticsController + servicio de IA) entrega "insights" en un
 * formato genérico (tipo de gráfico, etiquetas, valores) que aquí se traduce a
 * configuraciones concretas de Chart.js. Se encarga de:
 *  - Mapear tipos de visualización "conceptuales" (heatmap, matrix, ranked, etc.)
 *    a los tipos reales que soporta Chart.js (bar, line, doughnut, polarArea, radar).
 *  - Formatear números (compacto, porcentaje) según el tipo de insight.
 *  - Dibujar plugins propios: etiquetas de valor sobre las barras/puntos y el
 *    texto central en gráficos de dona.
 *  - Detectar y limpiar datos "sucios" que vienen del backend (filas de leyenda
 *    mezcladas como si fueran categorías).
 *
 * Se exporta registerAnalyticsCharts(Chart) para inyectar la instancia global
 * de Chart.js (evita importar Chart.js dos veces) y se invoca desde app.js.
 */
const palette = [
  '#d4af37',
  '#38bdf8',
  '#34d399',
  '#a78bfa',
  '#f59e0b',
  '#fb7185',
  '#22d3ee',
  '#84cc16',
];
const textColor = 'rgba(255,255,255,.48)';
const gridColor = 'rgba(255,255,255,.07)';
const numberFormatter = new Intl.NumberFormat('es-MX');
const compactFormatter = new Intl.NumberFormat('es-MX', {
  maximumFractionDigits: 1,
  notation: 'compact',
});
const percentFormatter = new Intl.NumberFormat('es-MX', {
  maximumFractionDigits: 1,
  style: 'percent',
});

const chartJsTypeMap = {
  area: 'line',
  heatmap: 'bar',
  matrix: 'bar',
  'factor-list': 'bar',
  'horizontal-bar': 'bar',
  ranked: 'bar',
};
const supportedChartTypes = new Set(['bar', 'line', 'doughnut', 'polarArea', 'radar']);

// Insights cuyo eje de valores ya viene expresado en porcentaje (ej. "% de
// utilización de agenda", "% de cancelaciones por día", "% de varianza
// explicada por cada componente de PCA"). Sin esto, el eje y los tooltips
// muestran "98" en vez de "98%" y el usuario pierde la unidad del dato.
const percentInsightTypes = new Set([
  'pca_factores',
  'utilizacion_equipo',
  'utilizacion_propia',
  'clasificacion_cancelacion',
]);

// Algunos insights de Spark traen una fila de encabezado/leyenda mezclada
// como si fuera una categoría real de datos (ej. "LEYENDA" con un valor
// numérico). Mostrarla como una barra más confunde a un usuario no técnico,
// así que se descarta antes de graficar.
const placeholderLabelPattern = /^\s*leyenda\s*$/i;

function stripPlaceholderEntries(labels, values) {
  const cleanLabels = [];
  const cleanValues = [];

  labels.forEach((label, index) => {
    if (placeholderLabelPattern.test(String(label ?? ''))) return;
    cleanLabels.push(label);
    cleanValues.push(values[index]);
  });

  return cleanLabels.length ? [cleanLabels, cleanValues] : [labels, values];
}

const analyticsValueLabelsPlugin = {
  id: 'ubAnalyticsValueLabels',
  afterDatasetsDraw(chart, _args, options) {
    if (!options?.display) return;

    const { ctx } = chart;
    const dataset = chart.data.datasets[0];
    const meta = chart.getDatasetMeta(0);

    if (!dataset || !meta?.data?.length) return;

    ctx.save();
    ctx.font = '700 10px "Plus Jakarta Sans", sans-serif';
    ctx.fillStyle = 'rgba(255,255,255,.62)';
    ctx.textAlign = chart.options.indexAxis === 'y' ? 'left' : 'center';
    ctx.textBaseline = 'middle';

    meta.data.forEach((element, index) => {
      const raw = Number(dataset.data[index] ?? 0);
      if (!Number.isFinite(raw)) return;

      const value = formatTick(raw, options.insightType);
      const props = element.tooltipPosition?.() ?? { x: element.x, y: element.y };
      const x =
        chart.options.indexAxis === 'y'
          ? Math.min(props.x + 10, chart.chartArea.right - 22)
          : props.x;
      const y =
        chart.options.indexAxis === 'y'
          ? props.y
          : Math.max(props.y - 12, chart.chartArea.top + 10);

      ctx.fillText(value, x, y);
    });

    ctx.restore();
  },
};

const analyticsCenterTextPlugin = {
  id: 'ubAnalyticsCenterText',
  afterDraw(chart, _args, options) {
    if (!options?.display) return;
    if (!['doughnut', 'polarArea'].includes(chart.config.type)) return;

    const values = chart.data.datasets[0]?.data || [];
    const total = values.reduce((sum, value) => sum + Number(value || 0), 0);
    const { ctx, chartArea } = chart;
    const cx = (chartArea.left + chartArea.right) / 2;
    const cy = (chartArea.top + chartArea.bottom) / 2;

    ctx.save();
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#ffffff';
    ctx.font = '800 16px "Plus Jakarta Sans", sans-serif';
    ctx.fillText(formatTick(total), cx, cy - 4);
    ctx.fillStyle = 'rgba(255,255,255,.42)';
    ctx.font = '700 9px "Plus Jakarta Sans", sans-serif';
    ctx.fillText('total', cx, cy + 12);
    ctx.restore();
  },
};

// Lee la configuración del gráfico desde el <script type="application/json"> cuyo
// id está referenciado en data-chart-config (evita inyectar JSON gigante como atributo HTML).
function parseConfig(canvas) {
  const node = document.getElementById(canvas.dataset.chartConfig);

  if (!node) return null;

  try {
    return JSON.parse(node.textContent);
  } catch {
    return null;
  }
}

// Convierte valores del backend (a veces strings con comas de miles, ej. "1,234") a number.
function toValues(values) {
  return (values || []).map((value) => {
    const numeric = Number(String(value).replace(/,/g, ''));
    return Number.isFinite(numeric) ? numeric : 0;
  });
}

// Trunca etiquetas largas para que no rompan el layout de los ejes/leyenda;
// en modo "mini" (miniaturas de dashboard) se ocultan por completo.
function shortLabel(label, mini) {
  const text = String(label ?? '').replace(/\n/g, ' · ');

  if (mini) return '';

  return text.length > 30 ? `${text.slice(0, 28)}…` : text;
}

// Formatea números para ejes/tooltips: compacto (1.2k) si son grandes, y agrega
// "%" cuando el tipo de insight ya representa un porcentaje (ver percentInsightTypes).
function formatTick(value, insightType) {
  const numeric = Number(value);

  if (!Number.isFinite(numeric)) return value;

  const formatted =
    Math.abs(numeric) >= 1000 ? compactFormatter.format(numeric) : numberFormatter.format(numeric);

  return percentInsightTypes.has(insightType) ? `${formatted}%` : formatted;
}

// Determina el tipo "conceptual" del insight (puede venir del config JSON del
// canvas o del campo "tipo" que manda el backend en español).
function normalizeVisualType(config, graph) {
  return config.type || graph.tipo || 'bar';
}

// Traduce el tipo conceptual (heatmap, matrix, etc.) al tipo real de Chart.js,
// usando chartJsTypeMap; si no hay mapeo ni es un tipo soportado, cae a 'bar'.
function toChartJsType(visualType) {
  const mappedType = chartJsTypeMap[visualType] || visualType || 'bar';

  return supportedChartTypes.has(mappedType) ? mappedType : 'bar';
}

// Genera un color con opacidad proporcional al valor (usado en heatmap/factor-list
// para que las barras/celdas "más intensas" resalten visualmente).
function colorByIntensity(value, max, accent = '#d4af37') {
  const alpha = Math.max(0.24, Math.min(0.92, Number(value || 0) / Math.max(max, 1)));

  if (accent.startsWith('#') && accent.length === 7) {
    const red = parseInt(accent.slice(1, 3), 16);
    const green = parseInt(accent.slice(3, 5), 16);
    const blue = parseInt(accent.slice(5, 7), 16);

    return `rgba(${red},${green},${blue},${alpha})`;
  }

  return `rgba(212,175,55,${alpha})`;
}

// Colorea celdas de una matriz de confusión: verde si la etiqueta suena a
// "acierto/verdadero" o está en la diagonal (índices 0 y 3 de una 2x2), rojo si no.
function matrixColors(labels, values) {
  return values.map((_value, index) => {
    const label = String(labels[index] || '').toLowerCase();
    const isGood =
      label.includes('verdadero') ||
      label.includes('acierto') ||
      label.includes('correct') ||
      [0, 3].includes(index);

    return isGood ? 'rgba(52,211,153,.78)' : 'rgba(245,158,11,.78)';
  });
}

// Construye el dataset de Chart.js (colores, grosor de barra/línea, gradiente, etc.)
// aplicando reglas distintas según el tipo de gráfico (bar/line/radar/doughnut).
function buildDataset(ctx, canvas, type, visualType, labels, values, accent, mini) {
  const gradient = ctx.createLinearGradient(0, 0, 0, canvas.parentElement?.clientHeight || 240);
  gradient.addColorStop(0, `${accent}33`);
  gradient.addColorStop(1, `${accent}05`);
  const maxValue = Math.max(...values, 1);

  const dataset = {
    data: values,
    label: 'Resultado',
    backgroundColor: values.map((value, index) => {
      if (visualType === 'heatmap') return colorByIntensity(value, maxValue, '#f59e0b');
      if (visualType === 'matrix') return matrixColors(labels, values)[index];
      if (visualType === 'factor-list') return colorByIntensity(value, maxValue, accent);

      return `${palette[index % palette.length]}cc`;
    }),
    borderColor: accent,
    borderWidth: 0,
    borderRadius: type === 'bar' ? 8 : 0,
    borderSkipped: false,
    barPercentage: 0.74,
    categoryPercentage: 0.72,
    maxBarThickness: mini ? 14 : 34,
    hoverBorderColor: '#ffffff',
    hoverBorderWidth: type === 'bar' ? 0 : 1,
  };

  if (type === 'line') {
    dataset.backgroundColor = gradient;
    dataset.borderColor = accent;
    dataset.borderWidth = mini ? 1.8 : 2.6;
    dataset.fill = true;
    dataset.tension = 0.42;
    dataset.pointRadius = mini ? 0 : 3;
    dataset.pointHoverRadius = 5;
    dataset.pointBackgroundColor = '#101010';
    dataset.pointBorderColor = accent;
    dataset.pointBorderWidth = 2;
  }

  if (type === 'radar') {
    dataset.backgroundColor = `${accent}24`;
    dataset.borderColor = accent;
    dataset.borderWidth = 2;
    dataset.pointBackgroundColor = accent;
    dataset.pointRadius = 2;
  }

  if (['doughnut', 'polarArea'].includes(type)) {
    dataset.borderColor = '#101010';
    dataset.borderWidth = 2;
    dataset.hoverOffset = mini ? 2 : 6;
  }

  return dataset;
}

// Configura los ejes x/y (o el eje radial en radar). Gráficos "mini" ocultan los
// ejes por completo, y en modo horizontal se invierte cuál eje formatea valores vs. etiquetas.
function buildScales(type, visualType, horizontal, mini, insightType) {
  if (['doughnut', 'polarArea'].includes(type)) return {};

  if (type === 'radar') {
    return {
      r: {
        beginAtZero: true,
        angleLines: { color: gridColor },
        grid: { color: gridColor },
        pointLabels: { color: textColor, font: { size: 10, weight: '700' } },
        ticks: { display: false },
      },
    };
  }

  return {
    x: {
      display: !mini,
      beginAtZero: horizontal,
      ticks: {
        autoSkip: true,
        color: textColor,
        font: { size: 10, weight: '600' },
        maxRotation: 0,
        maxTicksLimit: horizontal ? 6 : visualType === 'matrix' ? 4 : 7,
        callback(value) {
          return horizontal
            ? formatTick(value, insightType)
            : shortLabel(this.getLabelForValue(value), false);
        },
      },
      grid: { color: gridColor, drawBorder: false },
    },
    y: {
      display: !mini,
      beginAtZero: !horizontal,
      ticks: {
        autoSkip: true,
        color: textColor,
        font: { size: 10, weight: '600' },
        maxRotation: 0,
        maxTicksLimit: horizontal ? (visualType === 'factor-list' ? 10 : 8) : 6,
        callback(value) {
          return horizontal
            ? shortLabel(this.getLabelForValue(value), false)
            : formatTick(value, insightType);
        },
      },
      grid: { color: gridColor, drawBorder: false },
    },
  };
}

// Arma el objeto "options" completo de Chart.js: animación (respeta prefers-reduced-motion),
// leyenda, tooltip con formato español, y activa los plugins propios (value labels / center text).
function chartOptions(type, visualType, labels, values, horizontal, mini, insightType) {
  const total = values.reduce((sum, value) => sum + Number(value || 0), 0);
  const isPercentInsight = percentInsightTypes.has(insightType);

  return {
    indexAxis: horizontal ? 'y' : 'x',
    responsive: true,
    maintainAspectRatio: false,
    animation: {
      duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ? 0
        : mini
          ? 550
          : 900,
      easing: 'easeOutQuart',
    },
    layout: { padding: mini ? 0 : 4 },
    interaction: {
      intersect: false,
      mode: 'nearest',
    },
    plugins: {
      legend: {
        display: !mini && ['doughnut', 'polarArea'].includes(type),
        position: 'bottom',
        labels: {
          color: 'rgba(255,255,255,.58)',
          usePointStyle: true,
          boxWidth: 7,
          padding: 12,
          font: { size: 10, weight: '700' },
        },
      },
      tooltip: {
        enabled: !mini,
        backgroundColor: '#0f0f0f',
        borderColor: 'rgba(212,175,55,.35)',
        borderWidth: 1,
        titleColor: '#d4af37',
        bodyColor: '#ffffff',
        padding: 10,
        callbacks: {
          title(items) {
            const index = items[0]?.dataIndex ?? 0;
            return String(labels[index] ?? '').replace(/\n/g, ' · ');
          },
          label(item) {
            const raw = Number(item.raw ?? 0);
            const parts = [
              `Resultado: ${isPercentInsight ? formatTick(raw, insightType) : numberFormatter.format(raw)}`,
            ];

            if (
              total > 0 &&
              !isPercentInsight &&
              ['doughnut', 'polarArea', 'matrix'].includes(visualType)
            ) {
              parts.push(`Participación: ${percentFormatter.format(raw / total)}`);
            }

            return parts;
          },
        },
      },
      ubAnalyticsValueLabels: {
        display: !mini && ['bar', 'line'].includes(type),
        insightType,
      },
      ubAnalyticsCenterText: {
        display: !mini && type === 'doughnut',
      },
    },
    cutout: type === 'doughnut' ? (mini ? '72%' : '64%') : undefined,
    scales: buildScales(type, visualType, horizontal, mini, insightType),
  };
}

export function registerAnalyticsCharts(Chart) {
  const instances = window.__ubAnalyticsCharts || {};
  window.__ubAnalyticsCharts = instances;

  if (!window.__ubAnalyticsChartPluginsRegistered) {
    Chart.register(analyticsValueLabelsPlugin, analyticsCenterTextPlugin);
    window.__ubAnalyticsChartPluginsRegistered = true;
  }

  const resizeCharts = () => {
    Object.values(instances).forEach((chart) => chart?.resize());
  };

  const bootCharts = () => {
    Chart.defaults.font.family = "'Plus Jakarta Sans', 'Figtree', sans-serif";
    Chart.defaults.color = textColor;

    document.querySelectorAll('[data-ub-analytics-chart]').forEach((canvas) => {
      const config = parseConfig(canvas);

      if (!config) return;

      if (instances[canvas.id]) {
        instances[canvas.id].resize();
        return;
      }

      const graph = config.graph || {};
      const [labels, values] = stripPlaceholderEntries(
        graph.labels || [],
        toValues(graph.valores || []),
      );

      if (!values.length) return;

      const mini = canvas.dataset.chartMini === 'true' || config.mini === true;
      const visualType = normalizeVisualType(config, graph);
      const type = toChartJsType(visualType);
      const longLabels = labels.length > 5 || labels.some((label) => String(label).length > 18);
      const horizontal =
        type === 'bar' &&
        !mini &&
        (longLabels || ['factor-list', 'matrix', 'horizontal-bar', 'ranked'].includes(visualType));
      const accent = config.accent || '#d4af37';
      const insightType = config.insightType;
      const ctx = canvas.getContext('2d');
      const dataset = buildDataset(ctx, canvas, type, visualType, labels, values, accent, mini);

      instances[canvas.id] = new Chart(ctx, {
        type,
        data: {
          labels: labels.map((label) => shortLabel(label, mini)),
          datasets: [dataset],
        },
        options: chartOptions(type, visualType, labels, values, horizontal, mini, insightType),
      });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootCharts);
  } else {
    bootCharts();
  }

  window.addEventListener('ub:analytics:resize', () => setTimeout(resizeCharts, 140));
  window.addEventListener('resize', () => setTimeout(resizeCharts, 80));
}
