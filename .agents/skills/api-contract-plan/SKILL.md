---
name: api-contract-plan
description: Mantener ejemplos, OpenAPI, tipos y mocks sincronizados con la API.
---

# Contrato verificable

La guía vigente es docs/PLAN_CONTRATO_API.md en barber. En frontend consulta
docs/ARQUITECTURA.md y docs/PRUEBAS.md.

- Backend: ejemplos ficticios docs/contrato, ApiContractTest y generador de required.
- Frontend: contract/openapi.yaml, app/types/api.d.ts y adaptaciones en contract.ts.
- Los mocks E2E verifican frontend; no sustituyen pruebas reales del backend.
- No usar tokens aleatorios como ejemplos; usar TOKEN_DE_EJEMPLO.
- No regenerar Scribe contra un entorno real: sus llamadas de respuesta pueden ejecutar
  endpoints. La configuración auth.enabled=false tiene una limitación conocida de documentación.
- Sincronizar únicamente cuando cambie el contrato y revisar diff. Una tarea documental
  no necesita modificar YAML, JSON o tipos generados.
- Conteos de rutas y resultados de CI son evidencia de una revisión concreta.
- Para probar backend usar exclusivamente .\test.ps1.
- Mantener las copias .claude/.agents coherentes, sin heredar autorizaciones históricas.
