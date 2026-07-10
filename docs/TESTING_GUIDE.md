# Guía de Testing

## Stack

- **PHPUnit** (vía `php artisan test`), sin Pest.
- Base de datos: **MongoDB** real (Atlas o una instancia local), nunca SQLite — el driver `mongodb/laravel-mongodb` no funciona sobre un motor relacional. `phpunit.xml` fija `DB_CONNECTION=mongodb` y `MONGO_DATABASE=barber_db_test`, así que las pruebas corren contra una base separada de la de desarrollo (misma URI de conexión, distinto nombre de base).
- Aislamiento entre tests: no se usa `RefreshDatabase` (las transacciones multi-documento de Mongo no son confiables con Atlas + múltiples factories). En su lugar, `Tests\Support\RefreshMongoDatabase` trunca todas las colecciones antes de cada test, preservando `roles`/`permissions`/`model_has_permissions` (se siembran una sola vez para no saturar el free tier de Atlas).

## Cómo correr los tests

Todo dentro del contenedor Docker (`barber-app`), nunca en el host:

```bash
docker exec barber-app php artisan test --compact
docker exec barber-app php artisan test --compact tests/Feature/Auth/AuthenticationTest.php   # un archivo
docker exec barber-app php artisan test --compact --filter=test_admin_puede_crear_servicio     # por nombre
```

Cada test que usa `RefreshMongoDatabase` hace varias escrituras/lecturas contra Atlas, así que la suite completa tarda **~20-25 minutos** (no es un problema local, es latencia de red real). Al iterar sobre un bug, filtra por archivo o por nombre de test en vez de correr todo.

## Estructura

La suite de pruebas está en **base limpia** — la infraestructura está lista y los
tests se escriben desde cero sobre ella:

```
tests/
├── TestCase.php                          # base: desactiva CSRF/verified/throttle, siembra roles/permisos,
│                                         #   y FUERZA barber_db_test (con red de seguridad — ver abajo)
├── Support/RefreshMongoDatabase.php       # trait de truncado de colecciones entre tests
├── Unit/                                  # (vacío — lógica de dominio pura)
└── Feature/                               # (vacío — tests de feature/HTTP, agrupados por módulo)
```

Sugerencia de organización al ir escribiendo: una subcarpeta por módulo dentro de
`Feature/` (`Auth/`, `Appointments/`, `Payments/`, `Services/`, `Clients/`,
`Barbers/`, `Social/`, `Api/`, `Dashboard/`, `Security/`).

> **Aislamiento reforzado:** `TestCase::setUp()` fija en PHP la base a `barber_db_test`
> y aborta la corrida si el nombre resuelto no termina en `_test`. Esto es obligatorio:
> el contenedor Docker exporta `MONGO_DATABASE`/`MONGODB_URI` como variables de entorno
> reales del proceso, que le ganan a `.env.testing` y a `phpunit.xml` — sin este guard,
> la suite truncaría la base de datos real.

## Convenciones al escribir un test nuevo

- Extiende `Tests\TestCase` y usa el trait `Tests\Support\RefreshMongoDatabase`.
- Crea usuarios con `User::factory()->create()` y asigna rol con `$user->assignRole('administrador')` (método de instancia de Spatie). **Nunca uses el scope de consulta `User::role('x')`** — el driver de Mongo no soporta ese `MorphToMany` y lanza `LogicException`.
- **Siempre construye URLs con el helper `route($nombre, $modelo)`**, nunca concatenando manualmente `"/recurso/{$modelo->id}"`. Varios modelos no usan el `_id` de Mongo como route key:
  - `Barber`, `Client`, `Service` → usan `slug` (trait `HasSlug`).
  - `Appointment` → usa `code` (trait `HasPublicCode`).
  
  Pasar el `id` crudo en la URL para estos modelos produce un 404 silencioso — este fue exactamente el bug de producción (`UrlGenerationException` en `/descubrir`) que motivó la re-auditoría completa de la suite.
- Para flujos de barbero/cliente, crea el perfil explícitamente si la ruta lo requiere: `Barber::factory()->create(['user_id' => $user->id])` / `Client::factory()->create(['user_id' => $user->id])`. Varias rutas hacen `abort_if(!$barber, 403)`.
- `User` tiene `SoftDeletes`. Para verificar borrado usa `$user->fresh()->trashed()`, no `assertNull($user->fresh())`.
- Evita `UploadedFile::fake()->image()` (requiere la extensión GD, no instalada en el contenedor). Si necesitas probar una subida de imagen, usa `UploadedFile::fake()->create('foto.jpg', 10, 'image/jpeg')` o, mejor, evita el campo de archivo si no es el foco del test.
- Verifica siempre reglas de validación y nombres de campo leyendo el controlador/FormRequest real antes de escribir la aserción — los nombres de campo son en español (`nombre`, `precio`, `fecha`, `hora_inicio`, `estado`, etc.).

## CI

`.github/workflows/testing.yml` levanta un contenedor de servicio `mongo:7` (no usa Atlas ni MySQL) y corre `php artisan test --compact` contra `barber_db_test`. Los workflows viejos `php.yml` y `laravel.yml` (SQLite, incompatibles con los modelos Mongo) fueron eliminados por duplicar y romper la pipeline.
