# Manuales de UrbanBlade

Siete documentos completos del proyecto, cada uno en Markdown (fuente) y en Word (entregable con el diseño del equipo: Arial, carta, negro y oro).
Revisados el 28 de octubre de 2026.

| # | Documento | Para quién | Markdown | Word |
| --- | --- | --- | --- | --- |
| 1 | Manual de Usuario | Clientes, barberos, recepción, administración e ingeniería | [md](01-manual-de-usuario/manual-de-usuario.md) | [docx](01-manual-de-usuario/manual-de-usuario.docx) |
| 2 | Manual Técnico | Desarrolladores | [md](02-manual-tecnico/manual-tecnico.md) | [docx](02-manual-tecnico/manual-tecnico.docx) |
| 3 | Análisis y Requerimientos | Docentes, Product Owner y QA | [md](03-analisis-y-requerimientos/analisis-y-requerimientos.md) | [docx](03-analisis-y-requerimientos/analisis-y-requerimientos.docx) |
| 4 | Diseño y Arquitectura | Arquitectos y revisores | [md](04-diseno-y-arquitectura/diseno-y-arquitectura.md) | [docx](04-diseno-y-arquitectura/diseno-y-arquitectura.docx) |
| 5 | Instalación y Despliegue | Desarrolladores y quien publica | [md](05-instalacion-y-despliegue/instalacion-y-despliegue.md) | [docx](05-instalacion-y-despliegue/instalacion-y-despliegue.docx) |
| 6 | Administrador de Sistemas | Quien opera la infraestructura | [md](06-administrador-de-sistemas/administrador-de-sistemas.md) | [docx](06-administrador-de-sistemas/administrador-de-sistemas.docx) |
| 7 | Mantenimiento | Desarrollo y operación | [md](07-mantenimiento/mantenimiento.md) | [docx](07-mantenimiento/mantenimiento.docx) |

## Cómo se mantienen

- El **Markdown es la fuente**. El Word se genera a partir de él; no se edita a mano.
- Para regenerar los Word: `cd scripts/docs`, `npm install` y `node generar-word.js` (o `node generar-word.js 02-manual-tecnico` para uno solo).
- Los documentos de referencia técnica y operación (runbook de AWS, MongoDB, continuidad, credenciales, pruebas, CI/CD, decisiones) viven en [`../referencia/`](../referencia/) y **no se borran**: los manuales los resumen y remiten a ellos.
- Valida los enlaces con `python scripts/check_docs.py --all`.
