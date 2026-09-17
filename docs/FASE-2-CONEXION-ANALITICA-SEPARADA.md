# Fase 2: conexión analítica separada

**Fecha:** 2026-09-16  
**Estado:** implementada y validada end-to-end localmente; aprovisionamiento Atlas pendiente.

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
- `CORE_MONGODB_URI` permite validar el lector contra Mongo local; en Atlas se conservan
  las variables separadas `MONGO_USER`, `MONGO_PASSWORD`, `MONGO_CLUSTER` y `MONGO_DB`.
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
- Publicador Python: 2 pruebas unitarias aprobadas.
- Integración real en `urbanblade_analytics_e2e`: reemplazo atómico, cuatro índices,
  cero colecciones temporales residuales y base temporal eliminada al terminar.
- Exportador Spark completo: procesó 96 citas, 24 clientes y 3 barberos sintéticos;
  generó 18 insights en `urbanblade_analytics_e2e`.
- Laravel confirmó `connection=mongodb_analytics` y leyó los 18 insights publicados.
- Se corrigió el manejo de inventario vacío; ahora produce cero alertas sin intentar
  acceder a una columna inexistente.
- `urbanblade_core_e2e` y `urbanblade_analytics_e2e` fueron eliminadas después de la
  comprobación; no quedaron bases ni colecciones temporales.

No se escribió en Atlas. El flujo Spark → Mongo analytics → Laravel quedó validado
end-to-end con datos sintéticos locales. Falta crear y comprobar las credenciales Atlas
separadas antes de activar el destino remoto.

## Activación y rollback

1. Crear las cuentas con mínimo privilegio fuera del repositorio.
2. Configurar las variables `ANALYTICS_*` reales en cada servicio.
3. Ejecutar primero contra un destino local/aislado y comprobar el renombrado atómico.
4. Activar Laravel después de confirmar que `analytics_insights` existe en el destino.
5. Para rollback, retirar temporalmente `ANALYTICS_MONGODB_URI` y
   `ANALYTICS_MONGO_DATABASE`; Laravel vuelve a leer el origen histórico.

No eliminar la colección histórica hasta terminar la verificación funcional y conservar
un respaldo.
