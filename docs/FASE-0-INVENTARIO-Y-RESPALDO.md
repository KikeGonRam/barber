# Fase 0: inventario y respaldo

**Fecha de corte:** 2026-09-16  
**Estado:** completada  
**Alcance:** lectura e inventario de la base configurada en `barber`, respaldo local
y ensayo de restauración aislado. No hubo migraciones, escrituras de negocio ni
cambios en Atlas.

## Resultado ejecutivo

- Base operativa actual: `barber_db`.
- 49 colecciones, 1,150 documentos y 136 índices.
- Tamaño lógico de documentos: 283,318 bytes.
- Almacenamiento de colecciones: 1,327,104 bytes.
- Almacenamiento de índices: 2,838,528 bytes.
- `analytics_insights` no existe en el corte inspeccionado.
- Spark consulta datos operativos directamente y su exportador todavía contiene
  `delete_many({})` seguido de `insert_many(...)` sobre `analytics_insights`.
- El respaldo BSON completo fue restaurado en un Mongo temporal: coincidieron las
  49 colecciones, los 1,150 documentos y los 136 índices.

Los conteos son una fotografía de este corte y cambiarán con la operación. No deben
convertirse en constantes del código ni sustituir una verificación previa al corte.

## Inventario por colección

| Colección | Documentos | Colección | Documentos |
|---|---:|---|---:|
| activities | 273 | activity_log | 0 |
| appointments | 100 | barber_reviews | 16 |
| barber_schedules | 42 | barbers | 6 |
| barbershop_settings | 1 | cache | 0 |
| cache_locks | 0 | client_memberships | 8 |
| client_packages | 10 | clients | 20 |
| combo_service | 0 | comments | 38 |
| database_notifications | 2 | failed_jobs | 0 |
| gift_cards | 6 | inventories | 0 |
| inventory_movements | 0 | job_batches | 0 |
| jobs | 0 | loyalty_transactions | 65 |
| membership_invoices | 16 | membership_plans | 3 |
| migrations | 40 | mobile_api_tokens | 3 |
| model_has_permissions | 0 | model_has_roles | 0 |
| notifications | 0 | orders | 40 |
| password_reset_tokens | 0 | payments | 64 |
| permissions | 12 | products | 30 |
| push_subscriptions | 1 | raffle_results | 5 |
| reactions | 200 | referrals | 10 |
| role_has_permissions | 0 | roles | 5 |
| saved_works | 0 | service_combos | 3 |
| service_packages | 3 | services | 20 |
| sessions | 0 | users | 27 |
| waitlists | 8 | work_images | 50 |
| works | 23 |  |  |

## Contrato de lectura observado en Spark

La capa principal inspeccionada es
`spark/config/mongo_spark_conexion_sinnulos.py`. Los scripts históricos bajo
`spark/unidades/` repiten parte del mismo acceso. Este inventario registra campos
consultados, no autoriza a copiarlos todos a otra base.

| Colección operativa | Campos consumidos observados |
|---|---|
| appointments | `_id`, `client_id`, `barber_id`, `service_id`, `precio_cobrado`, `estado`, `fecha`, `hora_inicio`, `metodo_pago`, `deleted_at` |
| services | `_id`, `nombre`, `precio`, `duracion_min`, `categoria` |
| barbers | `_id`, `user_id`, `nombre`, `especialidad`, `activo`, `comision_pct` |
| clients | `_id`, `user_id`, `nivel`, `puntos`, `total_citas`, `fecha_nacimiento` |
| users | `_id`, `name`, `email` |
| roles | `_id`, `name` |
| payments | `appointment_id`, `monto`, `propina`, `metodo_pago`, `created_by`, `created_at`, `estado` |
| loyalty_transactions | `client_id`, `tipo`, `puntos`, `created_at` |
| barber_schedules | `barber_id`, `day_of_week`, `is_working`, `start_time`, `end_time` |
| products | `nombre`, `categoria`, `tipo`, `precio_compra`, `precio_venta`, `stock_actual`, `stock_minimo` |
| orders | `folio`, `client_id`, `tipo`, `estado`, `total`, `items`, `metodo_pago`, `appointment_id`, `entregado_en`, `created_at` |
| works | `_id`, `barbero_id` |
| work_images | `work_id` |
| comments | `work_id`, `rating` |
| reactions | `work_id` |
| gift_cards | `code`, `comprador_client_id`, `comprador_nombre`, `monto_inicial`, `saldo`, `metodo_pago`, `estado`, `comprado_en` |
| membership_plans | `_id`, `nombre`, `precio_mensual`, `descuento_pct` |
| client_memberships | `_id`, `client_id`, `membership_plan_id`, `estado`, `cancelar_al_finalizar`, `periodo_actual_fin` |
| membership_invoices | `client_membership_id`, `monto`, `pagado_en` |
| service_packages | `_id`, `nombre` |
| client_packages | `client_id`, `service_package_id`, `service_id`, `usos_totales`, `usos_restantes`, `precio_pagado`, `metodo_pago`, `estado`, `comprado_en` |
| combo_service | `combo_id`, `service_id` |
| service_combos | `_id`, `nombre`, `precio_combo`, `descuento` |
| referrals | `referrer_client_id`, `referee_client_id`, `estado`, `recompensa_otorgada_en` |
| waitlists | `client_id`, `barber_id`, `service_id`, `fecha`, `estado`, `activa` |
| raffle_results | `client_id`, `mes`, `premio`, `nivel_ganador`, `reclamado_en`, `vence_en` |
| barber_reviews | `barber_id`, `client_id`, `rating`, `comment` |
| barbershop_settings | documento de configuración usado por controles de calidad |

### Datos sensibles detectados

Spark lee `users.email`, nombres, fecha de nacimiento y comentarios libres. La Fase 2
debe aplicar mínimo privilegio y reducir proyecciones. Una futura exportación histórica
debe seudonimizar personas y excluir correo, nombres y texto libre salvo justificación
explícita. `frontend-urban` continúa sin credenciales de base.

## Respaldos generados y verificados

Los archivos viven en `storage/app/backups/`, están ignorados por Git y contienen datos
sensibles. No deben adjuntarse a commits, mensajes, servicios públicos ni repositorios.

### Respaldo primario completo

- Archivo: `barber_db-2026-09-16_1815.archive.gz`
- Formato: archivo BSON comprimido de `mongodump`.
- Tamaño: 40,992 bytes.
- SHA-256: `3D4B9CCCE8AC284F0E8FEBE073B35D7CBD07028AE15E880BE2648F35BC8B07DE`.
- Restauración temporal con `mongorestore`: 49 colecciones, 1,150 documentos y
  136 índices, iguales al origen.

### Respaldo secundario de la aplicación

- Archivo: `backup-2026-09-16_180906.zip`.
- Formato: Extended JSON, un archivo por colección.
- Tamaño: 42,662 bytes.
- SHA-256: `2D4D791964B2DF46029F5B93C0B9B18694291C5A51D4B5D1B2ECCC9B6E01385D`.
- Validación: 49 JSON válidos y 1,150 documentos restaurados en un Mongo aislado.
- Limitación: no conserva índices. Además, los archivos `[]` deben restaurarse creando
  primero la colección vacía; `mongoimport --jsonArray` por sí solo falla en ese caso.

El BSON es el respaldo primario para rollback. El ZIP es una segunda representación
legible y no debe considerarse sustituto del BSON.

## Plan de rollback para las fases siguientes

1. Antes de cada corte, crear un nuevo `mongodump` y registrar hash, conteos e índices.
2. No borrar ni renombrar `barber_db` durante la misma entrega.
3. Ensayar la restauración en una base o contenedor aislado, nunca encima del origen.
4. En Fase 2, mantener una bandera temporal para leer `analytics_insights` desde el
   origen si falla la conexión analítica.
5. En Fase 3, cambiar primero el lector Laravel y después el escritor Spark.
6. Ante una discrepancia, revertir variables/conexiones; no intentar reparar borrando
   colecciones.
7. Conservar el origen durante una ventana acordada y retirar el fallback únicamente
   después de comparar conteos, esquema lógico y muestras sin PII.

## Pendientes y siguiente decisión

- El respaldo está sólo en este equipo: protege frente a una migración fallida, pero
  no frente a pérdida del disco. Definir almacenamiento cifrado fuera del equipo antes
  de un corte real.
- La Fase 1 fue autorizada e implementada después de este corte. Ver
  [`FASE-1-MONGO-LOCAL.md`](FASE-1-MONGO-LOCAL.md).
- `spark` tenía cambios locales previos durante el inventario; no se modificaron.
- Antes de Fase 2, el exportador de Spark que borra toda `analytics_insights` debe reemplazarse por una
  publicación atómica o versionada antes de apuntarlo a la base analítica definitiva.
