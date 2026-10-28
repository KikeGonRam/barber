# UrbanBlade · Backend API

API para gestionar la operación de una barbería: citas, clientes, cobros,
inventario, pedidos, lealtad, notificaciones y reportes.

[![CI](https://github.com/KikeGonRam/barber/actions/workflows/ci.yml/badge.svg)](https://github.com/KikeGonRam/barber/actions/workflows/ci.yml)
[![Docker Image CI](https://github.com/KikeGonRam/barber/actions/workflows/docker-image.yml/badge.svg)](https://github.com/KikeGonRam/barber/actions/workflows/docker-image.yml)

[Frontend](https://github.com/KikeGonRam/frontend_Urbanblade) ·
[Documentación](docs/README.md) · [Contribuir](CONTRIBUTING.md) · [Seguridad](SECURITY.md)

![Panel administrativo del frontend UrbanBlade](docs/assets/dashboard-admin.png)

## Responsabilidad del repositorio

Expone una API JSON bajo `/api/v1`, consumida por el frontend Nuxt y Android.
La autenticación usa tokens Bearer propios (`mobile_api_tokens`).
Las pantallas del producto viven en
[`frontend-urban`](https://github.com/KikeGonRam/frontend_Urbanblade).

Laravel conserva plantillas para correos, PDF, errores y documentación de la API.
Las rutas web heredadas redirigen al frontend cuando corresponde.
La [documentación técnica](docs/DOCUMENTACION_TECNICA.md) describe la arquitectura.

## Funcionalidades

- Agenda con disponibilidad y transiciones de estado de citas.
- Permisos para administración, recepción, barberos y clientes; ingeniero de solo lectura.
- Pagos con efectivo, transferencia y tarjeta mediante Stripe.
- Inventario, pedidos, puntos, descuentos y membresías.
- Reportes de ingresos, citas, clientes e inventario en JSON, PDF y Excel.
- Notificaciones, campañas y consultas de analítica.

Los importes y movimientos de inventario se validan en el backend.

## Stack

| Componente | Tecnología |
| --- | --- |
| API | Laravel 13; PHP `^8.3` en Composer y PHP 8.4 en Docker/CI. |
| Datos | MongoDB con `mongodb/laravel-mongodb`. |
| Caché y colas | Redis y workers de Laravel. |
| Integraciones | Stripe, Google OAuth, notificaciones y almacenamiento S3. |
| Documentos | DomPDF, Maatwebsite Excel y Scribe/OpenAPI. |
| Calidad | PHPUnit, Pint, Larastan, Gitleaks y Trivy. |

El build Vite sirve los recursos propios de Laravel; la aplicación web tiene
su repositorio y pipeline independientes.

## Desarrollo local

Requisitos: Docker con Compose, Node.js 22 y npm para los recursos de Laravel.
PHP y sus extensiones están definidos en [Dockerfile](Dockerfile).

```powershell
git clone https://github.com/KikeGonRam/barber.git
cd barber
Copy-Item .env.example .env
```

Configura conexiones locales aisladas antes de arrancar los servicios. Revisa
[.env.example](.env.example), [docker-compose.yml](docker-compose.yml) y
[la guía de MongoDB](docs/MONGODB_ATLAS.md).
La base de pruebas usa un replica set local; Atlas contiene datos operativos.

Con las conexiones locales verificadas:

```powershell
docker compose up -d --build
npm ci
npm run build
```

La API escucha en [localhost:8000](http://localhost:8000/api/v1);
Mailpit, en [localhost:8025](http://localhost:8025).
Levanta el frontend por separado en el puerto 3000.

Migraciones, claves, enlaces de almacenamiento y cuentas son pasos de
inicialización según el entorno; no deben ejecutarse sobre datos operativos como
parte de una instalación rutinaria. Consulta
[CLONAR_PROYECTOS.md](docs/CLONAR_PROYECTOS.md) y [ACCESOS.md](docs/ACCESOS.md).
No se utilizan seeders masivos de demostración.

## Pruebas y validación

Las pruebas locales se ejecutan **exclusivamente mediante `test.ps1`**:

```powershell
.\test.ps1
```

El script fuerza `.env.testing` en Docker, limpia las cachés antes de probar y
las restaura después. Ejecutar `php artisan test` directamente en el contenedor
de desarrollo puede usar la conexión real a Atlas. Verifica que `barber-app`
monta este checkout y que `mongo-test` está disponible.

| Comando | Qué comprueba |
| --- | --- |
| `docker compose exec app ./vendor/bin/pint --test` | Formato PHP sin modificar archivos. |
| `docker compose exec app ./vendor/bin/phpstan analyse --no-progress --memory-limit=1G --configuration=phpstan.neon.dist` | Análisis estático. |
| `npx eslint resources/js --max-warnings=0` | JavaScript de Laravel. |
| `npm run build` | Compilación de recursos. |
| `docker compose exec app composer audit` | Vulnerabilidades de dependencias PHP. |
| `python scripts/check_docs.py` | Enlaces locales de la documentación de entrada. |

Los resultados pertenecen al checkout y entorno probados; consulta las acciones
del repositorio para conocer el estado actual del CI.

## Contrato de API

El contrato está en [public/docs/openapi.yaml](public/docs/openapi.yaml).
Scribe sirve documentación navegable en `/docs` cuando se genera.
El frontend conserva una copia del contrato y genera sus tipos a partir de ella.

Los cambios de endpoints deben coordinarse con web y Android.
Consulta [PLAN_CONTRATO_API.md](docs/PLAN_CONTRATO_API.md).
Una actualización documental no requiere regenerar el contrato.

## CI y operación

[CI](.github/workflows/ci.yml) comprueba PHP, JavaScript, seguridad y rutas HTTP
con servicios efímeros.
[Docker Image CI](.github/workflows/docker-image.yml) construye las imágenes
de desarrollo y staging sin publicarlas.
[Documentación](.github/workflows/documentation.yml) valida los enlaces de entrada.

Estos workflows no despliegan. Un CI verde no acredita publicación.
Consulta [CI/CD](docs/CI_CD.md) y
[la guía operativa de staging](docs/DESPLIEGUE_AWS_STAGING.md).

## Documentación y colaboración

El [índice documental](docs/README.md) enlaza las guías de arquitectura, acceso,
pruebas, usuario y operación sin duplicar sus instrucciones.
Consulta [CONTRIBUTING.md](CONTRIBUTING.md) para preparar un PR y
[SECURITY.md](SECURITY.md) para reportar vulnerabilidades.
Las contraseñas son administradas fuera del repositorio.
