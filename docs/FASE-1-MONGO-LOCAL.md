# Fase 1: MongoDB local de desarrollo

**Fecha:** 2026-09-16  
**Estado:** completada y validada.

## Resultado

- `mongo-dev` usa MongoDB 7, replica set `rsdev`, base `urbanblade_dev` y volumen
  persistente `mongo_dev_data`.
- `mongo-dev-init` inicializa el replica set de forma idempotente.
- El puerto del host es `27019`; `mongo-test` conserva `27018` y `barber_db_test`.
- `app`, `worker` y `scheduler` reciben explícitamente el contexto `development` cuando
  se usa el Compose de desarrollo.
- Las cachés Laravel de desarrollo viven en `/tmp` dentro de cada contenedor; no
  reutilizan `bootstrap/cache` generado con Atlas.
- `DataEnvironmentGuard` falla antes de conectar si mezcla contexto, host o nombre de
  base. Las trazas tempranas omiten argumentos para no revelar una URI con credenciales.

## Uso

Crear una sola vez el archivo local:

```powershell
Copy-Item .env.development.example .env.development
```

Levantar la API completa contra Mongo local:

```powershell
docker compose --env-file .env.development `
  -f docker-compose.yml `
  -f docker-compose.development.yml up -d
```

Consultar estado:

```powershell
docker compose --env-file .env.development `
  -f docker-compose.yml `
  -f docker-compose.development.yml ps
```

El archivo `.env.development` está ignorado. La plantilla no contiene secretos y sí se
versiona. Omitir `--env-file .env.development` hace que la guarda rechace cualquier URI
de Atlas heredada desde `.env`.

## Evidencia obtenida

- Compose efectivo: `app`, `worker` y `scheduler` resolvieron
  `DATA_ENVIRONMENT=development`, host `mongo-dev` y base `urbanblade_dev`.
- Replica set: `rsdev`, nodo primario escribible.
- Transacción multi-documento: completada correctamente.
- Persistencia: dos marcadores sobrevivieron al reinicio de `mongo-dev`.
- Aislamiento: los marcadores fueron `0` en `barber_db_test`.
- Limpieza: los dos marcadores temporales se eliminaron de `urbanblade_dev`.
- Laravel efímero: resolvió `urbanblade_dev`/`rsdev` sin migraciones ni escrituras.
- Pruebas focalizadas: 11 aprobadas, 11 aserciones.
- Pint completo: 428 archivos aprobados.
- Larastan con caché fría: sin errores.
- Suite completa después de recrear exclusivamente los contenedores desechables
  `barber-mongo-test` y `barber-mongo-test-init`: 593 aprobadas, 2,056 aserciones y
  cero fallos.

## Rollback

1. Iniciar el proyecto sólo con `docker-compose.yml`; así conserva el flujo compartido
   anterior y no carga servicios ni variables de desarrollo.
2. Detener `mongo-dev` sin borrar `mongo_dev_data` para conservar datos locales.
3. No usar `docker compose down --volumes`: también podría eliminar otros volúmenes.
4. Si se decide retirar definitivamente la Fase 1, borrar el volumen sólo con una
   autorización separada y después de confirmar su nombre exacto.

## Condiciones para Fase 2

- La Fase 2 requiere autorización expresa y no debe reutilizar credenciales de escritura
  entre `barber` y Spark.
- El exportador Spark debe dejar de hacer `delete_many({})` seguido de `insert_many()`
  antes del corte analítico; se necesita publicación atómica o versionada.
