# Documentación del backend

Revisada el 28 de octubre de 2026. Cada tema tiene una guía responsable.
La implementación versionada prevalece sobre planes y resultados históricos;
ninguna guía certifica por sí sola un entorno remoto.

## Organización de `docs/`

| Carpeta | Contenido |
| --- | --- |
| [manuales](manuales/README.md) | Los siete manuales completos del proyecto, cada uno en Markdown y Word. |
| [referencia](referencia/) | Guías técnicas y operativas (runbook de AWS, MongoDB, continuidad, credenciales, pruebas, CI/CD, decisiones). Los manuales las resumen y remiten a ellas. |
| `assets`, `aws`, `contrato` | Capturas, políticas de ejemplo y ejemplos del contrato de la API. |

## Manuales

| Manual | Markdown | Word |
| --- | --- | --- |
| Manual de Usuario | [md](manuales/01-manual-de-usuario/manual-de-usuario.md) | [docx](manuales/01-manual-de-usuario/manual-de-usuario.docx) |
| Manual Técnico | [md](manuales/02-manual-tecnico/manual-tecnico.md) | [docx](manuales/02-manual-tecnico/manual-tecnico.docx) |
| Análisis y Requerimientos | [md](manuales/03-analisis-y-requerimientos/analisis-y-requerimientos.md) | [docx](manuales/03-analisis-y-requerimientos/analisis-y-requerimientos.docx) |
| Diseño y Arquitectura | [md](manuales/04-diseno-y-arquitectura/diseno-y-arquitectura.md) | [docx](manuales/04-diseno-y-arquitectura/diseno-y-arquitectura.docx) |
| Instalación y Despliegue | [md](manuales/05-instalacion-y-despliegue/instalacion-y-despliegue.md) | [docx](manuales/05-instalacion-y-despliegue/instalacion-y-despliegue.docx) |
| Administrador de Sistemas | [md](manuales/06-administrador-de-sistemas/administrador-de-sistemas.md) | [docx](manuales/06-administrador-de-sistemas/administrador-de-sistemas.docx) |
| Mantenimiento | [md](manuales/07-mantenimiento/mantenimiento.md) | [docx](manuales/07-mantenimiento/mantenimiento.docx) |

## Desarrollo y arquitectura

| Necesidad | Fuente |
| --- | --- |
| Presentación | [README](../README.md) |
| Instalación aislada | [Clonar proyectos](referencia/CLONAR_PROYECTOS.md) |
| Capas, datos, dominios e integraciones | [Arquitectura](referencia/DOCUMENTACION_TECNICA.md) |
| Decisiones implementadas y propuestas | [Decisiones](referencia/DECISIONES.md) |
| Separación de cargas y entornos | [ADR-001](referencia/ADR-001-ARQUITECTURA-DE-DATOS.md) |
| Ejecución y matriz de regresiones | [Pruebas](referencia/PRUEBAS.md) |
| OpenAPI y consumidores | [Contrato API](referencia/PLAN_CONTRATO_API.md) |
| Controles y publicación | [CI/CD](referencia/CI_CD.md) |

## Seguridad y operación

| Necesidad | Fuente |
| --- | --- |
| Reportar una vulnerabilidad | [Política de seguridad](../SECURITY.md) |
| Revisión de secretos y documentos retirados | [Seguridad documental](referencia/SEGURIDAD_DOCUMENTACION.md) |
| Custodia y rotación | [Gestión de credenciales](referencia/PLAN_ROTACION_CREDENCIALES.md) |
| Acceso de aplicación | [Accesos y roles](referencia/ACCESOS.md) |
| Base de datos y privilegios | [MongoDB](referencia/MONGODB_ATLAS.md) |
| Respaldos y recuperación | [Continuidad](referencia/CONTINUIDAD_OPERATIVA.md) |
| Operación y publicación | [Runbook](referencia/DESPLIEGUE_AWS_STAGING.md) |
| Pagos en sandbox | [Stripe local](referencia/STRIPE_PRUEBAS_LOCALES.md) |
| Autenticación externa | [Google OAuth](referencia/GOOGLE_CLOUD_OAUTH.md) |

## Uso y colaboración

- [Manual funcional](referencia/MANUAL_USUARIO.md).
- [Demostración en entorno aislado](referencia/DEMO_DEMOSTRACION.md).
- [Contribuir](../CONTRIBUTING.md), [código de conducta](../CODE_OF_CONDUCT.md).
- [Instrucciones para agentes](../AGENTS.md).
- [Sandbox del workspace](../scripts/verificacion/README.md).

El [Canvas académico](referencia/Canvas_UrbanBlade_Bloques_3-5.docx) se conserva como
entregable histórico; no es especificación técnica vigente.
Los antiguos manuales Word/PDF duplicados y planes retirados se identifican en
[decisiones](referencia/DECISIONES.md). Sus versiones anteriores permanecen en Git.

El contrato se conserva en [OpenAPI](../public/docs/openapi.yaml).
No se regenera ni modifica como efecto lateral de editar Markdown.
