# Prompt de continuidad para otro proveedor

Copia y pega desde la siguiente línea en una nueva conversación:

---

Continúa la arquitectura de datos de UrbanBlade desde la Fase 0 ya completada.

Repositorios:

- `C:\Users\luis1\Documents\UrbanBlade\barber`
- `C:\Users\luis1\Documents\UrbanBlade\frontend-urban`
- `C:\Users\luis1\Documents\UrbanBlade\spark`

Reglas obligatorias:

1. Trabaja únicamente en `main`; no crees ramas.
2. Ninguna IA puede ejecutar commit, push, merge, rebase ni publicar PR. El usuario
   humano realiza Git. Entrega comandos de commit en español y con la ruta correcta.
3. Lee completa primero
   `barber/.agents/skills/urbanblade-data-architecture/SKILL.md`, después
   `barber/docs/ADR-001-ARQUITECTURA-DE-DATOS.md` y
   `barber/docs/FASE-0-INVENTARIO-Y-RESPALDO.md`.
4. No ejecutes pruebas Laravel directamente: usa sólo `barber/test.ps1`.
5. No muestres secretos ni contenido de `.env` y no escribas en Atlas sin autorización
   explícita para la fase concreta.
6. `barber` es la única puerta de escritura operativa; `frontend-urban` consume API;
   Spark debe leer core y escribir únicamente derivados analíticos.
7. Conserva todos los cambios locales existentes. En particular, `spark` ya tenía
   cambios modificados en `.agents/skills/git-commit-conventions/SKILL.md`,
   `.claude/skills/git-commit-conventions/SKILL.md`, `CLAUDE.md`,
   `config/mongo_spark_conexion_sinnulos.py`, `requirements.txt`,
   `unidades/unidad_5_visualizacion/main_dashboard.py`, además de `docs_word/` y `nul`
   sin seguimiento. No los descartes ni los mezcles sin revisarlos.

Estado comprobado de Fase 0 (2026-09-16):

- Base operativa: `barber_db`.
- Inventario: 49 colecciones, 1,150 documentos, 136 índices.
- `analytics_insights` no existía en el corte inspeccionado.
- Respaldo BSON local verificado mediante restauración aislada:
  `barber/storage/app/backups/barber_db-2026-09-16_1815.archive.gz`.
- SHA-256:
  `3D4B9CCCE8AC284F0E8FEBE073B35D7CBD07028AE15E880BE2648F35BC8B07DE`.
- La restauración reprodujo 49 colecciones, 1,150 documentos y 136 índices.
- También existe un ZIP Extended JSON secundario, pero no conserva índices.
- Ambos respaldos contienen datos sensibles, están ignorados por Git y no deben
  publicarse.
- El respaldo es local; falta definir una copia externa cifrada antes de un corte real.

Siguiente trabajo, sólo después de que el usuario autorice expresamente la Fase 1:

1. Diseñar e implementar `mongo-dev` persistente y `mongo-dev-init` en Compose, con
   replica set propio, volumen propio y base `urbanblade_dev`.
2. Mantener `mongo-test` efímero, sin volumen compartido y con `barber_db_test`.
3. Agregar `.env.development.example` sin secretos.
4. Incorporar guardas que impidan usar Atlas o nombres de producción en desarrollo y
   pruebas.
5. Verificar transacciones, persistencia tras reinicio y aislamiento sin escribir en
   Atlas.
6. Actualizar la documentación y entregar al usuario las pruebas y el comando de
   commit; no ejecutar Git de escritura.

No comiences Fase 2 ni cambies todavía las conexiones de Laravel/Spark. Antes de Fase 2
debe resolverse el riesgo de `exportar_insights_dashboard.py`, que actualmente hace
`delete_many({})` antes de `insert_many(...)`; se requiere publicación atómica o
versionada para evitar dejar la analítica vacía si el proceso falla.

---
