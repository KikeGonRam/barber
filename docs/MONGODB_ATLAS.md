# MongoDB Atlas: configuración, usuarios y respaldos

Guía operativa de la base de datos compartida por `barber`, `spark` y el staging de AWS.
Complementa [`ADR-001-ARQUITECTURA-DE-DATOS.md`](ADR-001-ARQUITECTURA-DE-DATOS.md)
(decisiones y diagrama), [`FASE-0-INVENTARIO-Y-RESPALDO.md`](FASE-0-INVENTARIO-Y-RESPALDO.md)
y [`FASE-2-CONEXION-ANALITICA-SEPARADA.md`](FASE-2-CONEXION-ANALITICA-SEPARADA.md).

Estado al 18 de septiembre de 2026. Aquí no hay contraseñas ni URIs reales: viven en
`barber/.env`, `spark/.env` y Secrets Manager (`urbanblade/staging/...`).

## Bases de datos

| Base | Contenido | Quién escribe |
|---|---|---|
| `barber_db` | Datos operativos (usuarios, citas, pagos, inventario, etc.) | Solo Laravel |
| `urbanblade_analytics` | Colección `analytics_insights` (derivados de Spark) | Solo Spark |

Local y pruebas nunca usan Atlas: desarrollo usa `urbanblade_dev`, y la suite solo corre
contra `barber_db_test` en el Mongo local (`mongo-test`), siempre con `.\test.ps1`.

## Usuarios de base de datos (mínimo privilegio)

Se crean en Atlas → **Database Access → Add New Database User**, con autenticación por
contraseña y, en **Specific Privileges**, la base exacta. **No usar "Built-in Role"**:
en Atlas aplica a todo el proyecto (`readAnyDatabase`, `atlasAdmin`), no a una base.

| Usuario | Privilegio | Lo usa | Variable |
|---|---|---|---|
| `laravel_core_rw` | `readWrite@barber_db` | API Laravel (local y AWS) | `MONGODB_URI` |
| `laravel_analytics_reader` | `read@urbanblade_analytics` | API Laravel, lectura de insights | `ANALYTICS_MONGODB_URI` |
| `spark_core_reader` | `read@barber_db` | Spark, lectura del core | `MONGO_USER` / `MONGO_PASSWORD` |
| `ANALYTIC` | `readWrite@urbanblade_analytics` | Spark, publicación de insights | `ANALYTICS_MONGO_USER` / `ANALYTICS_MONGO_PASSWORD` |

Pendiente: el usuario personal `luis` conserva el rol `atlasAdmin`. Ya nada de la
aplicación lo usa; debe eliminarse o rotarse su contraseña (ver ADR, Fase 4).

### Comprobar con qué usuario y rol se conecta un servicio

```bash
docker exec barber-app php artisan tinker --execute='$s=DB::connection("mongodb")->getClient()->selectDatabase("admin")->command(["connectionStatus"=>1])->toArray()[0]; echo json_encode($s["authInfo"]["authenticatedUsers"]), json_encode($s["authInfo"]["authenticatedUserRoles"]);'
```

Debe mostrar `laravel_core_rw` con `readWrite` sobre `barber_db`, nunca `atlasAdmin`.

## Variables por servicio

| Servicio | Variables |
|---|---|
| barber | `MONGODB_URI`, `MONGO_DATABASE=barber_db`, `ANALYTICS_MONGODB_URI`, `ANALYTICS_MONGO_DATABASE=urbanblade_analytics` |
| spark | `MONGO_USER`, `MONGO_PASSWORD`, `MONGO_CLUSTER`, `MONGO_DB`, `ANALYTICS_MONGO_USER`, `ANALYTICS_MONGO_PASSWORD`, `ANALYTICS_MONGO_DB` (debe ser distinta de `MONGO_DB`) |
| AWS | los secretos `MONGODB_URI`, `ANALYTICS_MONGODB_URI` (barber) y `MONGO_PASSWORD`, `ANALYTICS_MONGO_PASSWORD` (spark); ver [`DESPLIEGUE_AWS_STAGING.md`](DESPLIEGUE_AWS_STAGING.md) |

Contraseñas con caracteres especiales deben ir codificadas en la URI (URL-encoding).

## Acceso de red

Atlas → **Network Access** decide qué IP pueden conectar. Las tareas de Fargate salen
con IP públicas que cambian en cada despliegue, así que hoy el acceso desde AWS
funciona porque la lista permite esas IP (verificar el detalle en Atlas). Para cerrarlo
de verdad hay que dar salida por una IP fija (NAT Gateway, con costo mensual) o usar
un peering/PrivateLink; queda como mejora antes de producción.

## Rotar la contraseña de un usuario

1. Atlas → Database Access → Edit del usuario → nueva contraseña.
2. Actualizar el valor en el `.env` del servicio y en Secrets Manager
   (`aws secretsmanager put-secret-value`).
3. Recrear los contenedores locales (`docker compose up -d --force-recreate app worker scheduler`)
   o forzar un nuevo despliegue en ECS. Un contenedor conserva las variables con las
   que se creó: cambiar `.env` no basta.
4. Verificar con el comando de `connectionStatus` de arriba.

## Respaldos

- **Local:** `storage/app/backups/` (ignorado por Git, contiene datos sensibles).
  El backup de la aplicación genera un `.zip` en Extended JSON con un archivo por
  colección. El respaldo completo de referencia de la Fase 0 es
  `barber_db-2026-09-16_1815.archive.gz` (`mongodump`), verificado por restauración.
- **No dar por buenos todos los zips.** Los de la segunda mitad de septiembre tenían
  colecciones enteras vacías que sí existían en Atlas (probablemente respaldaron una
  base distinta). Antes de confiar en uno, contar documentos por colección y compararlo
  con Atlas.
- Antes de cualquier operación destructiva o de un corte, generar un respaldo nuevo y
  anotar conteos.
- Atlas también ofrece respaldos en la nube y restauración a un punto en el tiempo según
  el nivel del clúster; revisar en *Backup* qué está habilitado. Es la opción más
  reciente ante una pérdida.

### Restaurar colecciones desde un `.zip` de la aplicación

El zip no conserva índices y las colecciones vacías (`[]`) hay que crearlas antes.
El procedimiento que se usó el 2026-09-18:

1. Extraer el JSON de cada colección con PHP (`ZipArchive`).
2. Convertirlo con `MongoDB\BSON\Document::fromJSON('{"d":'.$json.'}')->get('d')`
   (respeta `$oid` y `$date`).
3. Insertar con `insertMany(..., ['ordered'=>false])` **solo los `_id` que no existan**,
   sin borrar nada.
4. Comparar conteos con el zip al terminar.

Requiere autorización explícita del dueño: es una escritura en la base compartida.

## Incidentes que originaron estas reglas

- **2026-08-28 y 2026-09-04:** una suite y seeders masivos corrieron contra Atlas y
  borraron o inflaron datos reales (~214k citas sintéticas). Ver guardrails 1, 2 y 12.
- **2026-09-18:** un `artisan test` ejecutado directamente con la configuración de Atlas
  en caché borró `services`, `barbers`, `clients`, `payments`, `appointments` y
  `barbershop_settings`; se restauraron desde el zip del 16 de septiembre. El guard de
  `tests/TestCase.php` ahora termina el proceso (`exit(1)`) antes de cualquier
  `tearDown()` si `mongodb` o `mongodb_analytics` no apuntan a `barber_db_test` en local.

Reglas que se derivan:
- Las pruebas solo se ejecutan con `.\test.ps1`, nunca con `php artisan test` directo.
- No ejecutar `DatabaseSeeder` completo ni comandos de `make` que toquen la base sin
  confirmar antes a qué apunta `.env`.
- Los borrados de usuarios son lógicos (`SoftDeletes`): un correo "borrado" sigue
  ocupando el índice único hasta un `forceDelete()`.
