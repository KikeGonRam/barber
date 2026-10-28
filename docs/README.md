# Documentación del backend

Revisada el 28 de octubre de 2026. Cada tema tiene una guía responsable.
La implementación versionada prevalece sobre planes y resultados históricos;
ninguna guía certifica por sí sola un entorno remoto.

## Desarrollo y arquitectura

| Necesidad | Fuente |
| --- | --- |
| Presentación | [README](../README.md) |
| Instalación aislada | [Clonar proyectos](CLONAR_PROYECTOS.md) |
| Capas, datos, dominios e integraciones | [Arquitectura](DOCUMENTACION_TECNICA.md) |
| Decisiones implementadas y propuestas | [Decisiones](DECISIONES.md) |
| Separación de cargas y entornos | [ADR-001](ADR-001-ARQUITECTURA-DE-DATOS.md) |
| Ejecución y matriz de regresiones | [Pruebas](PRUEBAS.md) |
| OpenAPI y consumidores | [Contrato API](PLAN_CONTRATO_API.md) |
| Controles y publicación | [CI/CD](CI_CD.md) |

## Seguridad y operación

| Necesidad | Fuente |
| --- | --- |
| Reportar una vulnerabilidad | [Política de seguridad](../SECURITY.md) |
| Revisión de secretos y documentos retirados | [Seguridad documental](SEGURIDAD_DOCUMENTACION.md) |
| Custodia y rotación | [Gestión de credenciales](PLAN_ROTACION_CREDENCIALES.md) |
| Acceso de aplicación | [Accesos y roles](ACCESOS.md) |
| Base de datos y privilegios | [MongoDB](MONGODB_ATLAS.md) |
| Respaldos y recuperación | [Continuidad](CONTINUIDAD_OPERATIVA.md) |
| Operación y publicación | [Runbook](DESPLIEGUE_AWS_STAGING.md) |
| Pagos en sandbox | [Stripe local](STRIPE_PRUEBAS_LOCALES.md) |
| Autenticación externa | [Google OAuth](GOOGLE_CLOUD_OAUTH.md) |

## Uso y colaboración

- [Manual funcional](MANUAL_USUARIO.md).
- [Demostración en entorno aislado](DEMO_DEMOSTRACION.md).
- [Contribuir](../CONTRIBUTING.md), [código de conducta](../CODE_OF_CONDUCT.md).
- [Instrucciones para agentes](../AGENTS.md).
- [Sandbox del workspace](../scripts/verificacion/README.md).

El [Canvas académico](Canvas_UrbanBlade_Bloques_3-5.docx) se conserva como
entregable histórico; no es especificación técnica vigente.
Los antiguos manuales Word/PDF duplicados y planes retirados se identifican en
[decisiones](DECISIONES.md). Sus versiones anteriores permanecen en Git.

El contrato se conserva en [OpenAPI](../public/docs/openapi.yaml).
No se regenera ni modifica como efecto lateral de editar Markdown.
