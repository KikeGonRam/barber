# Fase 2: conexión analítica separada

**Fecha:** 2026-09-16  
**Estado:** código implementado y validado; aprovisionamiento Atlas pendiente.

## Resultado

- Laravel define `mongodb_analytics` y `AnalyticsInsight` lee exclusivamente mediante
  esa conexión.
- `ANALYTICS_MONGODB_URI` y `ANALYTICS_MONGO_DATABASE` permiten usar un usuario de
  solo lectura y una base separada.
- La ausencia temporal de esas variables conserva la colección anterior como fallback
  durante la migración; no cambia datos ni mueve documentos automáticamente.
- El Compose de desarrollo usa `urbanblade_analytics_dev`, separada de
  `urbanblade_dev`, dentro del replica set local `rsdev`.
- Spark mantiene `_connect_db()` para leer el core y usa `_connect_analytics_db()` sólo
  para publicar derivados.
- Spark exige credenciales analytics completas y rechaza que `ANALYTICS_MONGO_DB` sea
  igual a `MONGO_DB`.
- La publicación ya no ejecuta `delete_many({})`: escribe una colección temporal,
  crea índices y hace un `rename(..., dropTarget=True)` atómico.

## Permisos previstos

- Usuario core de Spark: lectura sobre `barber_db`; ninguna escritura operativa.
- Usuario analytics de Spark: lectura/escritura y `renameCollection` únicamente sobre
  `urbanblade_analytics`.
- Usuario analytics de Laravel: sólo lectura sobre `urbanblade_analytics`.

No guardar usuarios, contraseñas ni URI reales en Git.

## Evidencia local de código

- `AnalyticsInsightTest`: 8 pruebas, 14 aserciones.
- `AnalyticsApiTest`: 3 pruebas, 17 aserciones.
- Pint focal: 3 archivos aprobados.
- Compilación Python de conexión y exportador: aprobada.

No se ejecutó el exportador ni se escribió en Atlas. La validación end-to-end queda
pendiente hasta que el usuario cree credenciales separadas o autorice un destino local
equivalente.

## Activación y rollback

1. Crear las cuentas con mínimo privilegio fuera del repositorio.
2. Configurar las variables `ANALYTICS_*` reales en cada servicio.
3. Ejecutar primero contra un destino local/aislado y comprobar el renombrado atómico.
4. Activar Laravel después de confirmar que `analytics_insights` existe en el destino.
5. Para rollback, retirar temporalmente `ANALYTICS_MONGODB_URI` y
   `ANALYTICS_MONGO_DATABASE`; Laravel vuelve a leer el origen histórico.

No eliminar la colección histórica hasta terminar la verificación funcional y conservar
un respaldo.
