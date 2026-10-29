# Pruebas y verificación del backend

Revisión: 28 de octubre de 2026. La suite existe y está activa.
Su configuración está en [phpunit.xml](../../phpunit.xml); las pruebas viven en cuatro carpetas por tipo:
[tests/UT](../../tests/UT), [tests/SIT](../../tests/SIT), [tests/UAT](../../tests/UAT) y
[tests/OAT](../../tests/OAT) (ver "Tipos de prueba").

## Ejecución segura

En Windows, desde la raíz de este checkout:

```powershell
.\test.ps1
```

[test.ps1](../../test.ps1) limpia las cachés de configuración/rutas, fuerza
`.env.testing` en el proceso `docker exec`, ejecuta PHPUnit y restaura las
cachés de desarrollo después. No usa un contenedor separado para la API:
trabaja sobre `barber-app`; comprueba su montaje antes de ejecutar.

El proceso debe resolver `APP_ENV=testing`, `DATA_ENVIRONMENT=testing`,
MongoDB local y `barber_db_test` para las conexiones operativa y analítica.
La configuración de entorno del contenedor tiene precedencia sobre Dotenv;
un archivo `.env.testing` por sí solo no garantiza aislamiento.

No ejecutes `php artisan test` directamente en el entorno de desarrollo.
[DataEnvironmentGuard](../../app/Support/DataEnvironmentGuard.php) y
[TestCase](../../tests/TestCase.php) son defensas adicionales, no un reemplazo del
runner. No se desactivan para hacer pasar una prueba.

## Tipos de prueba

Cada archivo vive en la carpeta de su tipo y declara el namespace `Tests\UT`,
`Tests\SIT`, `Tests\UAT` o `Tests\OAT`. Hay una suite de PHPUnit por tipo.

| Tipo | Carpeta y suite | Qué verifica | Cómo se reconoce |
| --- | --- | --- | --- |
| **UT / UIT** · Unitarias y de integración de unidades | `tests/UT` | Una clase o función aislada (reglas, normalización, formato) y unidades que colaboran con la base de datos de pruebas: servicios, comandos de negocio, correos, notificaciones y seeders. | No llaman a rutas HTTP. Las puramente unitarias no usan base de datos; las de integración de unidades sí, pero sin pasar por la API. |
| **SIT** · Integración de sistema | `tests/SIT` | Que los módulos y sistemas externos funcionen juntos a través de la API: contrato OpenAPI, autenticación y roles, entradas maliciosas, privacidad de datos, webhook de Stripe y el flujo de citas de punta a punta. | Llaman a las rutas y recorren varias capas o módulos; la regla es transversal, no de una sola funcionalidad. |
| **UAT** · Aceptación de usuario | `tests/UAT` | Criterios de aceptación de una funcionalidad vista por su rol (cliente, barbero, recepción, administrador): citas, pagos, inventario, pedidos, reseñas, perfil, notificaciones, reportes. | Llaman a las rutas con el rol correspondiente y comprueban respuesta y efecto visibles. |
| **OAT** · Aceptación operativa | `tests/OAT` | Que el sistema se pueda operar: aislamiento de entornos, colas y trabajos fallidos, correo, planificador (una sola ejecución), mantenimiento y respaldos, integridad de datos, límites de peticiones, bloqueo de IP abusivas, logs de lentitud y cabeceras de caché. | Verifican configuración, salud o mantenimiento, no una regla de negocio. |

Un archivo nuevo se clasifica así: si verifica configuración, salud o mantenimiento
del sistema, es OAT; si no llama rutas, es UT; si llama rutas y la regla es
transversal (seguridad, contrato, plataforma), es SIT; si llama rutas y verifica una
funcionalidad concreta de un rol, es UAT. El nombre de una carpeta no sustituye leer
la prueba: ante la duda, elige el tipo que realmente recorre.

```powershell
# Por tipo (suites de phpunit.xml)
.\test.ps1 --testsuite=UT
.\test.ps1 --testsuite=SIT
.\test.ps1 --testsuite=UAT
.\test.ps1 --testsuite=OAT
```

## Requisitos y comandos

- Docker disponible y `barber-app` montando este repositorio.
- `mongo-test` saludable, replica set inicializado por `mongo-test-init`.
- Dependencias PHP instaladas en el entorno probado.
- No compartir una ejecución con cambios concurrentes de cachés/configuración.

```powershell
# Suite completa
.\test.ps1 --compact

# Un caso o grupo concreto
.\test.ps1 --filter=ApiTokenSecurityTest
.\test.ps1 --filter=AppointmentStartRulesTest

# Formato y análisis: no modifican PHP
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan analyse --no-progress --memory-limit=1G --configuration=phpstan.neon.dist

# Recursos de Laravel
npx eslint resources/js --max-warnings=0
npm run build

# Documentación
python scripts/check_docs.py --all
git diff --check
```

No crear seeders masivos, ejecutar `migrate:fresh`, borrar volúmenes ni
limpiar colecciones operativas para preparar una suite.

## Matriz de regresiones

| Riesgo o regla | Pruebas de referencia |
| --- | --- |
| Mezcla de entornos de datos | [DataEnvironmentGuardTest](../../tests/OAT/DataEnvironmentGuardTest.php), [TestCase](../../tests/TestCase.php) |
| Operadores Mongo procedentes del cliente | [RejectMongoOperatorsTest](../../tests/UT/RejectMongoOperatorsTest.php) |
| Emisión, renovación y revocación de tokens | [ApiTokenSecurityTest](../../tests/SIT/ApiTokenSecurityTest.php), [AuthApiTest](../../tests/UAT/AuthApiTest.php) |
| Disponibilidad y límites de reserva | [AvailabilityApiTest](../../tests/UAT/AvailabilityApiTest.php), [AppointmentApiTest](../../tests/UAT/AppointmentApiTest.php) |
| Estados y condiciones para iniciar | [AppointmentStatusServiceTest](../../tests/UT/AppointmentStatusServiceTest.php), [AppointmentStartRulesTest](../../tests/UAT/AppointmentStartRulesTest.php) |
| Autorización por rol y pertenencia | [RoleAuthorizationTest](../../tests/SIT/RoleAuthorizationTest.php) |
| Contrato de respuestas | [ApiContractTest](../../tests/SIT/ApiContractTest.php) |
| Imágenes y cabeceras públicas | [UploadedImageResizeTest](../../tests/UT/UploadedImageResizeTest.php), [PublicUploadsCacheControlTest](../../tests/OAT/PublicUploadsCacheControlTest.php) |
| Configuración y fallos de cola | [QueueConfigurationTest](../../tests/OAT/QueueConfigurationTest.php), [QueueFailureMonitorTest](../../tests/OAT/QueueFailureMonitorTest.php) |

La tabla es un índice, no una lista exhaustiva de los casos.
Las pruebas UT también arrancan Laravel (extienden `Tests\TestCase`); no usan
la base de datos ni las rutas, pero el nombre de la carpeta no demuestra por sí
solo que una prueba esté libre de dependencias.

## Contrato HTTP

[verificar_contrato_api.mjs](../../scripts/verificar_contrato_api.mjs) compara el
resultado de `route:list --json` con el OpenAPI versionado.
La fuente de rutas es Laravel, no expresiones regulares sobre grupos de rutas.

No regenerar Scribe contra un entorno operativo para actualizar documentación.
Su generación puede realizar response calls según configuración.
Revisa el [procedimiento del contrato](PLAN_CONTRATO_API.md).

## CI y evidencia disponible

El commit de integración `4ca64c3` pasó CI, smoke HTTP, seguridad, documentación
y construcción de imágenes el 28 de octubre de 2026.
La ejecución local anterior reportó 951 pruebas y 3,679 aserciones.
Son resultados fechados de ese código, no un conteo permanente ni una ejecución
nueva de esta revisión documental.

CI usa MongoDB/Redis efímeros y variables propias; por eso su comando PHPUnit
directo no autoriza copiarlo al contenedor local con Atlas.
Consulta [CI/CD](CI_CD.md).

Los E2E con API simulada del frontend no prueban esta API.
Una suite verde tampoco verifica permisos Atlas efectivos, backups restaurables,
credenciales de proveedores, cobros reales o despliegue de producción.

## Cobertura mínima

El job `backend` de CI genera `coverage/coverage.xml` (clover) y
[verificar_cobertura.py](../../scripts/verificar_cobertura.py) falla si la cobertura de
sentencias baja del umbral `COVERAGE_MIN` definido en `.github/workflows/ci.yml`.

- Umbral inicial: **70 %**, fijado cuando la cobertura medida era 73,2 % (10 021 de 13 696
  sentencias, 29 de octubre de 2026). Es un piso, no una meta.
- Cuando se agreguen pruebas (autenticación y citas, TT24), sube el umbral; no lo bajes para
  que pase el CI.
- La cobertura mide líneas ejecutadas, no calidad de aserciones. Una cifra alta no sustituye
  las pruebas de regresión de la matriz anterior.

## Análisis estático

El workflow [semgrep.yml](../../.github/workflows/semgrep.yml) ejecuta Semgrep con las reglas
`p/owasp-top-ten` en cada PR y en `main`. Bloquea con cualquier hallazgo de severidad `ERROR`.

Para correrlo en local (PowerShell, desde la raíz del repositorio):

```powershell
docker run --rm -v "${PWD}:/src" -w /src semgrep/semgrep:1.182.1 semgrep scan --config p/owasp-top-ten --severity ERROR --error --metrics=off --exclude vendor --exclude node_modules --exclude public --exclude storage --exclude .claude --exclude .agents --exclude docs
```

**Supresiones aceptadas.** Un falso positivo o un riesgo aceptado se suprime en la propia línea
con `# nosemgrep: <regla>` y una justificación en el comentario anterior. No se suprime una
regla completa ni se excluyen carpetas de código para que pase el CI.

| Regla | Dónde | Justificación |
| --- | --- | --- |
| `dockerfile.security.missing-user` y `missing-user-entrypoint` | `Dockerfile` (desarrollo) | El proceso maestro arranca como root para preparar permisos; los workers de PHP-FPM corren como `www-data` (`.docker/php/www.conf`). |
| `dockerfile.security.missing-user-entrypoint` | `.docker/staging/Dockerfile` | El entrypoint corre como root para preparar `/run/app-secrets` y arrancar Caddy; PHP-FPM atiende como `www-data` y el programador con `su www-data`. |

Pendiente de evaluar: ejecutar los contenedores sin root (`USER` no root) exige revisar el
entrypoint, los puertos y el directorio de secretos; no se hizo en esta tarea.

## Sandbox de varios repositorios

El [sandbox](../../scripts/verificacion/README.md) agrega comprobaciones del workspace
y comienza por una puerta de seguridad. Usa únicamente los grupos del cambio;
no requiere activar Spark o Android para revisar Markdown del backend.

Si una prueba falla, conserva el error sin datos privados, verifica el entorno y
reproduce de forma acotada. No cambies fixtures o expectativas solo para ocultar
un fallo y no amplíes baselines sin revisar el motivo.
