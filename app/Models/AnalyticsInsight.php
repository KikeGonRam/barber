<?php

namespace App\Models;

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
 *                      para Chart.js (tipo: "bar"|"doughnut"|"line") — no
 *                      todos los insights traen gráfica, solo los que tienen
 *                      suficiente detalle detrás como para justificar una
 *                      (ver la página de Analítica, resources/views/analytics/)
 *   generado_en        cuándo Spark calculó este resultado
 */
class AnalyticsInsight extends Model
{
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
}
