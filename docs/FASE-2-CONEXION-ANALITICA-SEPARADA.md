# Fase 2: conexión analítica separada

**Fecha:** 2026-09-16 (código); 2026-09-17 (aprovisionamiento Atlas y validación end-to-end real)
**Estado:** completada. Aprovisionamiento Atlas hecho y flujo real Spark → Atlas → Laravel validado.

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

El flujo Spark → Mongo analytics → Laravel quedó validado end-to-end primero con datos
sintéticos locales y después contra Atlas real (ver "Validación end-to-end en Atlas
real" abajo).

## Activación y rollback

1. Crear las cuentas con mínimo privilegio fuera del repositorio. ✅ Hecho el
   2026-09-17: `spark_core_reader` (`read` sobre `barber_db`) y `ANALYTIC`
   (`readWrite` sobre `urbanblade_analytics`), ambos como Specific Privileges en
   Atlas — no Built-in Roles, que aplican a todo el proyecto.
2. Configurar las variables `ANALYTICS_*` reales en cada servicio. ✅ Hecho en
   `barber/.env` y `spark/.env` (no en `.env.example`, que solo documenta la forma).
3. Ejecutar primero contra un destino local/aislado y comprobar el renombrado atómico.
   ✅ Hecho localmente (`urbanblade_analytics_e2e`) antes del aprovisionamiento Atlas.
4. Activar Laravel después de confirmar que `analytics_insights` existe en el destino.
   ✅ Confirmado: Laravel lee 39 insights reales vía `mongodb_analytics`.
5. Para rollback, retirar temporalmente `ANALYTICS_MONGODB_URI` y
   `ANALYTICS_MONGO_DATABASE`; Laravel vuelve a leer el origen histórico.

## Validación end-to-end en Atlas real (2026-09-17)

Durante el aprovisionamiento se encontraron y corrigieron dos errores de
configuración real, documentados aquí porque son un riesgo repetible:

- El usuario configurado en `MONGO_USER`/`MONGO_PASSWORD` (variables de lectura del
  core que usa Spark) seguía siendo el usuario operativo original con rol
  `atlasAdmin` (control total del proyecto Atlas), no un usuario de solo lectura.
  Se confirmó el problema insertando y borrando un documento de prueba en
  `barber_db.appointments` con esas credenciales (sí se pudo escribir, lo cual
  viola el invariante "Spark core solo lectura"). Se creó `spark_core_reader` con
  privilegio específico `read` sobre `barber_db` y se apuntaron las variables ahí.
- El usuario de `ANALYTICS_MONGO_USER` tenía asignado el Built-in Role "Only read
  any database" (`readAnyDatabase`), que impidió `createCollection` al publicar.
  Se corrigió asignando el privilegio específico `readWrite` sobre
  `urbanblade_analytics` (usuario real en Atlas: `ANALYTIC`).
- Lección para futuras cuentas: en Atlas, un "Built-in Role" aplica a **todo el
  proyecto**, no a una base — siempre usar "Specific Privileges" con la base
  exacta para cumplir mínimo privilegio real, no solo nominal.

Con los roles corregidos (`read@barber_db` y `readWrite@urbanblade_analytics`,
confirmados vía `connectionStatus`), se validó:

- Spark lee `barber_db` real (100 citas) y su escritura queda rechazada por Atlas.
- `publish_insights_atomically` publicó primero un insight de prueba y después el
  exportador completo (`exportar_insights_dashboard.py`) publicó **39 insights
  reales** (24 tipos distintos) calculados de 100 citas / 20 clientes / 6 barberos
  reales, con 4 índices creados y cero colecciones temporales residuales
  (`urbanblade_analytics` solo contiene `analytics_insights`).
- Laravel, vía la conexión `mongodb_analytics`, leyó los 39 insights reales
  (`AnalyticsInsight::count() === 39`).

No se eliminó ninguna colección histórica: `barber_db` nunca tuvo
`analytics_insights` (confirmado en Fase 0), así que no había nada que conservar
como fallback — el corte fue directo a la base analítica separada.
