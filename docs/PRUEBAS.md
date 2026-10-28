# Pruebas y verificación del backend

Revisión: 28 de octubre de 2026. La suite existe y está activa.
Su configuración está en [phpunit.xml](../phpunit.xml); las pruebas viven en
[tests/Unit](../tests/Unit) y [tests/Feature](../tests/Feature).

## Ejecución segura

En Windows, desde la raíz de este checkout:

```powershell
.\test.ps1
```

[test.ps1](../test.ps1) limpia las cachés de configuración/rutas, fuerza
`.env.testing` en el proceso `docker exec`, ejecuta PHPUnit y restaura las
cachés de desarrollo después. No usa un contenedor separado para la API:
trabaja sobre `barber-app`; comprueba su montaje antes de ejecutar.

El proceso debe resolver `APP_ENV=testing`, `DATA_ENVIRONMENT=testing`,
MongoDB local y `barber_db_test` para las conexiones operativa y analítica.
La configuración de entorno del contenedor tiene precedencia sobre Dotenv;
un archivo `.env.testing` por sí solo no garantiza aislamiento.

No ejecutes `php artisan test` directamente en el entorno de desarrollo.
[DataEnvironmentGuard](../app/Support/DataEnvironmentGuard.php) y
[TestCase](../tests/TestCase.php) son defensas adicionales, no un reemplazo del
runner. No se desactivan para hacer pasar una prueba.

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
| Mezcla de entornos de datos | [DataEnvironmentGuardTest](../tests/Unit/DataEnvironmentGuardTest.php), [TestCase](../tests/TestCase.php) |
| Operadores Mongo procedentes del cliente | [RejectMongoOperatorsTest](../tests/Unit/RejectMongoOperatorsTest.php) |
| Emisión, renovación y revocación de tokens | [ApiTokenSecurityTest](../tests/Feature/ApiTokenSecurityTest.php), [AuthApiTest](../tests/Feature/AuthApiTest.php) |
| Disponibilidad y límites de reserva | [AvailabilityApiTest](../tests/Feature/AvailabilityApiTest.php), [AppointmentApiTest](../tests/Feature/AppointmentApiTest.php) |
| Estados y condiciones para iniciar | [AppointmentStatusServiceTest](../tests/Unit/AppointmentStatusServiceTest.php), [AppointmentStartRulesTest](../tests/Feature/AppointmentStartRulesTest.php) |
| Autorización por rol y pertenencia | [RoleAuthorizationTest](../tests/Feature/RoleAuthorizationTest.php) |
| Contrato de respuestas | [ApiContractTest](../tests/Feature/ApiContractTest.php) |
| Imágenes y cabeceras públicas | [UploadedImageResizeTest](../tests/Unit/UploadedImageResizeTest.php), [PublicUploadsCacheControlTest](../tests/Unit/PublicUploadsCacheControlTest.php) |
| Configuración y fallos de cola | [QueueConfigurationTest](../tests/Unit/QueueConfigurationTest.php), [QueueFailureMonitorTest](../tests/Unit/QueueFailureMonitorTest.php) |

La tabla es un índice, no una lista exhaustiva de los casos.
Algunos archivos bajo Unit también arrancan Laravel; el nombre de la carpeta
no demuestra que una prueba esté libre de dependencias o base de datos.

## Contrato HTTP

[verificar_contrato_api.mjs](../scripts/verificar_contrato_api.mjs) compara el
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

## Sandbox de varios repositorios

El [sandbox](../scripts/verificacion/README.md) agrega comprobaciones del workspace
y comienza por una puerta de seguridad. Usa únicamente los grupos del cambio;
no requiere activar Spark o Android para revisar Markdown del backend.

Si una prueba falla, conserva el error sin datos privados, verifica el entorno y
reproduce de forma acotada. No cambies fixtures o expectativas solo para ocultar
un fallo y no amplíes baselines sin revisar el motivo.
