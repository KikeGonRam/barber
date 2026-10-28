---
name: urbanblade-data-architecture
description: Guía para conexiones, aislamiento y persistencia de UrbanBlade.
---

# Arquitectura de datos

Fuentes canónicas: docs/referencia/ADR-001-ARQUITECTURA-DE-DATOS.md, docs/referencia/MONGODB_ATLAS.md,
docs/referencia/CONTINUIDAD_OPERATIVA.md y config/database.php.

- La conexión operativa conserva barber_db como nombre predeterminado; urbanblade_core
  es una propuesta, no un renombrado ejecutado.
- Los derivados usan mongodb_analytics. La configuración tiene fallback a la conexión
  operativa si faltan variables; declarar dos conexiones no garantiza aislamiento físico.
- Spark debe leer core y escribir únicamente derivados con mínimo privilegio.
  No se presupone que las cuentas remotas ya cumplan ese objetivo.
- Pulse usa SQLite; Redis es soporte de caché/colas, no fuente de verdad de negocio.
- mongo-dev es persistente; mongo-test es exclusivo de pruebas. Ambos requieren
  replica set para transacciones. No duplicar contenedores sin revisar Compose.
- Antes de cambiar datos: inventario, respaldo restaurable, ensayo aislado, comparación
  y reversión. Los runbooks no autorizan por sí mismos ninguna escritura.
- Las pruebas usan exclusivamente .\test.ps1; nunca probar contra Atlas.
- No rotar, eliminar o alterar cuentas remotas como parte de una revisión documental.
- Mantener este archivo sincronizado en .claude/skills y .agents/skills.
