# UrbanBlade · Backend API

API para gestionar citas, clientes, pagos, inventario, pedidos, lealtad,
notificaciones y reportes de una barbería.

[![CI](https://github.com/KikeGonRam/barber/actions/workflows/ci.yml/badge.svg)](https://github.com/KikeGonRam/barber/actions/workflows/ci.yml)
[![Docker Image CI](https://github.com/KikeGonRam/barber/actions/workflows/docker-image.yml/badge.svg)](https://github.com/KikeGonRam/barber/actions/workflows/docker-image.yml)

[Frontend](https://github.com/KikeGonRam/frontend_Urbanblade) ·
[Documentación](docs/README.md) · [Contribuir](CONTRIBUTING.md) · [Seguridad](SECURITY.md)

![Panel ilustrativo del frontend UrbanBlade](docs/assets/dashboard-admin.png)

## Responsabilidad y arquitectura

Monolito modular Laravel que expone JSON bajo /api/v1.
Nuxt y Android consumen la API con tokens Bearer propios; no acceden a MongoDB.
Las pantallas del producto viven en el frontend. Laravel conserva plantillas para
correos/PDF, errores, redirecciones y documentación de API.

| Componente | Tecnología |
| --- | --- |
| API | Laravel 13; PHP ^8.3 en Composer y PHP 8.4 en Docker/CI. |
| Datos | MongoDB operativo, conexión analítica y entornos locales separados. |
| Trabajo asíncrono | Redis, workers y scheduler de Laravel. |
| Pagos | Stripe: intentos, depósitos, tarjetas guardadas y membresías. |
| Documentos y calidad | DomPDF, Excel, Scribe/OpenAPI, PHPUnit, Pint y Larastan. |

La [arquitectura](docs/referencia/DOCUMENTACION_TECNICA.md) explica capas, procesos,
integraciones y límites. Las [decisiones](docs/referencia/DECISIONES.md) distinguen lo
implementado de las propuestas.

## Desarrollo local

Requisitos: Git, Docker con Compose y Node.js/npm para los recursos Laravel.
La [guía de instalación aislada](docs/referencia/CLONAR_PROYECTOS.md) utiliza el overlay de
desarrollo con mongo-dev y evita copiar credenciales de Atlas.

No ejecutar setup, migraciones o seeders sobre un entorno operativo para revisar
documentación. Las [cuentas y roles](docs/referencia/ACCESOS.md) se gestionan sin publicar
credenciales ni inventarios de usuarios.

## Pruebas

En local, exclusivamente:

```powershell
.\test.ps1
```

El runner aplica el entorno de pruebas y gestiona cachés.
Comprobar antes el montaje de barber-app y el Mongo local.
La [guía de pruebas](docs/referencia/PRUEBAS.md) incluye comandos, matriz de regresiones,
dependencias y límites de evidencia.

Para documentación:

```powershell
python scripts/check_docs.py --all
git diff --check
```

## Contrato y publicación

[OpenAPI](public/docs/openapi.yaml) y los ejemplos versionados describen la API
para clientes web/Android. Consulta el
[procedimiento de contrato](docs/referencia/PLAN_CONTRATO_API.md).

CI verifica código, seguridad, contrato y builds; no acredita publicación.
Incluso un push de Markdown puede activar un proveedor externo.
Consulta [CI/CD](docs/referencia/CI_CD.md) antes de subir o fusionar cambios.

## Documentación y seguridad

El [índice](docs/README.md) enlaza las fuentes vigentes de arquitectura,
decisiones, pruebas, operación y uso.
Los documentos obsoletos se retiran con sus sustitutos identificados;
no se mantienen copias Word/PDF independientes del Markdown.

Nunca publicar secretos, sesiones, dumps o datos personales.
Consulta [SECURITY.md](SECURITY.md) y
[la revisión documental de seguridad](docs/referencia/SEGURIDAD_DOCUMENTACION.md).

## Políticas y licencia

- [Política de seguridad y reporte privado](SECURITY.md).
- [Código de conducta](CODE_OF_CONDUCT.md) y [contribución](CONTRIBUTING.md).
- [GNU GPLv3, versión 3 únicamente](LICENSE) — identificador GPL-3.0-only.
- [Alcance, autoría y componentes de terceros](LICENSING.md).
