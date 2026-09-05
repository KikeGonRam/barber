<?php

namespace App\Models;

use Illuminate\Support\Str;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Un "insight" en lenguaje natural, calculado por el proyecto Spark (unidades
 * II-V) y escrito en la colección `analytics_insights`.
 *
 * IMPORTANTE: este modelo es de SOLO LECTURA desde Laravel. Quien escribe en
 * esta colección es exclusivamente el script de Python
 * spark/unidades/unidad_5_visualizacion/exportar_insights_dashboard.py — es
 * la única colección de toda la base de datos que Spark llena (todo lo demás
 * en ese proyecto es solo lectura). Laravel nunca debe hacer
 * AnalyticsInsight::create()/update() salvo en pruebas.
 *
 * Cada vez que Spark corre, BORRA todos los documentos anteriores y escribe
 * los nuevos — por eso no hay que preocuparse por "limpiar" datos viejos
 * desde aquí: es un estado calculado, no un historial que se acumula.
 *
 * Campos:
 *   tipo               slug único del insight (ej. "demanda_horas_pico")
 *   unidad             de qué unidad de la materia viene ("I".."V"), solo
 *                      para trazabilidad/documentación, no se usa para nada
 *                      funcional en la app
 *   roles              array de roles que pueden ver este insight
 *                      (administrador|recepcionista|barbero|cliente)
 *   barbero_user_id    si el insight es privado de un barbero (ej. su propio
 *                      engagement en el muro social) — referencia a users._id
 *   barbero_perfil_id  si el insight es privado de un barbero pero calculado
 *                      sobre su AGENDA (utilización, demanda) — referencia a
 *                      barbers._id. Ver el comentario en el script de Python
 *                      para por qué existen dos ids distintos.
 *   titulo             título corto en español simple
 *   mensaje            1-2 frases explicando el hallazgo sin jerga técnica
 *   valor_destacado     el número/frase grande que se muestra en la tarjeta
 *   color              "gold"|"success"|"warning"|"danger"|"info" — define
 *                      el acento visual de la tarjeta (ver
 *                      components/analytics-insights.blade.php)
 *   grafica            null, o un array {tipo, labels, valores} YA LISTO
 *                      para visualización. Spark entrega la data y Laravel
 *                      decide la forma ejecutiva más legible para cada insight
 *                      (barras, dona, línea, matriz, mapa de demanda, etc.).
 *   generado_en        cuándo Spark calculó este resultado
 */
class AnalyticsInsight extends Model
{
    public const VISUAL_TYPES_BY_INSIGHT = [
        'resumen_ejecutivo' => 'line',
        'calidad_datos_etl' => 'doughnut',
        'control_limpieza_datos' => 'doughnut',
        'demanda_horas_pico' => 'heatmap',
        'demanda_horas_pico_propia' => 'heatmap',
        'clientes_en_riesgo' => 'doughnut',
        'segmentacion_clientes' => 'doughnut',
        'perfil_citas_premium' => 'doughnut',
        'fidelizacion_ratio' => 'bar',
        'utilizacion_equipo' => 'bar',
        'utilizacion_propia' => 'bar',
        'engagement_muro_top' => 'bar',
        'engagement_propio' => 'bar',
        'tienda_pedidos' => 'bar',
        // 'radar' se ve como una línea vacía con solo 2 ejes (precio/duración);
        // el dato ya viene como {tipo: "bar"} desde Spark, así que se respeta
        // esa forma en vez de forzar un radar que no tiene sentido con 2 puntos.
        'pca_factores' => 'bar',
        // Comparativo por día de la semana (Lunes..Sábado) — son categorías,
        // no una serie temporal continua, así que una línea sugiere una
        // tendencia que conecta días entre sí que en realidad no existe.
        // Barras comparan cada día como categoría independiente.
        'clasificacion_cancelacion' => 'bar',
        'alertas_cancelacion' => 'factor-list',
        'confirmacion_cancelacion_reforzada' => 'bar',
        'matriz_resultados_cancelacion' => 'matrix',
        'regresion_facturacion' => 'line',
    ];

    public const VISUAL_FAMILIES = [
        'line' => ['titulo' => 'Tendencias', 'descripcion' => 'Evolución histórica y pronóstico', 'color' => 'gold'],
        'bar' => ['titulo' => 'Comparativos', 'descripcion' => 'Ranking por equipo, servicio o categoría', 'color' => 'info'],
        'doughnut' => ['titulo' => 'Distribuciones', 'descripcion' => 'Participación, segmentos y composición', 'color' => 'success'],
        'heatmap' => ['titulo' => 'Mapas de demanda', 'descripcion' => 'Horarios y concentración de actividad', 'color' => 'warning'],
        'radar' => ['titulo' => 'Factores clave', 'descripcion' => 'Variables que explican el resultado', 'color' => 'info'],
        'matrix' => ['titulo' => 'Matriz de resultados', 'descripcion' => 'Aciertos, falsas alarmas y omisiones', 'color' => 'danger'],
        'factor-list' => ['titulo' => 'Importancia de factores', 'descripcion' => 'Señales que conviene vigilar', 'color' => 'warning'],
    ];

    public const VISUAL_LABELS = [
        'bar' => 'Comparativo',
        'line' => 'Tendencia',
        'doughnut' => 'Distribución',
        'polarArea' => 'Distribución',
        'radar' => 'Factores clave',
        'matrix' => 'Matriz',
        'heatmap' => 'Mapa de demanda',
        'factor-list' => 'Importancia',
    ];

    protected $connection = 'mongodb';

    protected $collection = 'analytics_insights';

    protected $fillable = [
        'tipo',
        'unidad',
        'roles',
        'barbero_user_id',
        'barbero_perfil_id',
        'titulo',
        'mensaje',
        'valor_destacado',
        'color',
        'grafica',
        'generado_en',
    ];

    protected $casts = [
        'roles' => 'array',
        'grafica' => 'array',
        'generado_en' => 'datetime',
    ];

    // Resuelve el tipo de grafica a renderizar: primero el mapa fijo por tipo de insight,
    // si no hay match cae al campo 'tipo' que Spark manda dentro de 'grafica'.
    public static function visualTypeFor(?string $insightType, ?array $graph = null): string
    {
        $visualType = $insightType !== null
            ? (self::VISUAL_TYPES_BY_INSIGHT[$insightType] ?? data_get($graph, 'tipo', 'bar'))
            : data_get($graph, 'tipo', 'bar');

        return is_string($visualType) && $visualType !== '' ? $visualType : 'bar';
    }

    // Determina si hay algo que dibujar: valores numericos en 'grafica.valores',
    // o (caso especial) un heatmap que solo trae 'valor_destacado' sin serie de valores.
    public function hasRenderableVisual(): bool
    {
        $values = collect(data_get($this->grafica, 'valores', []))->filter(
            fn ($value) => is_numeric(str_replace(',', '', (string) $value))
        );

        if ($values->isNotEmpty()) {
            return true;
        }

        return self::visualTypeFor($this->tipo, $this->grafica) === 'heatmap'
            && filled($this->valor_destacado);
    }

    /**
     * Payload plano para la tarjeta SIN gráfica (los 4 dashboards por rol
     * llaman a <x-analytics-insights>/AnalyticsInsights.vue sin showCharts,
     * que por defecto es false — la variante con matriz/heatmap/canvas de
     * Chart.js solo la usa resources/views/analytics/index.blade.php, que
     * sigue sin migrar). Centraliza aquí el truncado/porcentaje que antes
     * vivía inline en el Blade, para que el frontend (Vue) reciba datos ya
     * calculados en vez de reimplementar estas reglas de negocio en JS.
     */
    public function toDashboardCardArray(): array
    {
        $visualType = self::visualTypeFor($this->tipo, $this->grafica);
        $mensaje = (string) ($this->mensaje ?? '');
        $briefLimit = 34;
        $isTruncated = str_word_count(strip_tags($mensaje)) > $briefLimit;

        $progressValue = null;
        if (preg_match('/([\d]+(?:[.,]\d+)?)\s*%/', (string) $this->valor_destacado, $matches)) {
            $progressValue = max(3, min(100, (float) str_replace(',', '.', $matches[1])));
        }

        return [
            'titulo' => $this->titulo,
            'dato' => $this->valor_destacado,
            'color' => $this->color ?: 'gold',
            'visual_label' => self::VISUAL_LABELS[$visualType] ?? 'Indicador',
            'mensaje' => $mensaje,
            'brief' => $isTruncated ? Str::words($mensaje, $briefLimit) : $mensaje,
            'is_truncated' => $isTruncated,
            'progress_value' => $progressValue,
        ];
    }
}
