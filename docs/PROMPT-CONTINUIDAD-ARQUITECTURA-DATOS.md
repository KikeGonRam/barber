# Prompt de continuidad para otro proveedor

Copia y pega desde la siguiente línea en una nueva conversación:

---

Continúa la arquitectura de datos de UrbanBlade desde la Fase 1 implementada.

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
   `barber/docs/FASE-0-INVENTARIO-Y-RESPALDO.md` y
   `barber/docs/FASE-1-MONGO-LOCAL.md`.
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

Estado de Fase 1:

1. `mongo-dev`/`mongo-dev-init`, `rsdev`, `urbanblade_dev` y el volumen persistente ya
   están implementados en `docker-compose.development.yml`.
2. La guarda de entorno, cachés Laravel aisladas y 11 pruebas focalizadas ya existen.
3. Transacciones, reinicio persistente, aislamiento de `mongo-test`, Laravel local,
   Pint y Larastan quedaron verificados.
4. Con autorización del usuario se recrearon exclusivamente `barber-mongo-test` y
   `barber-mongo-test-init`. La suite oficial `test.ps1` terminó con 593 pruebas
   aprobadas, 2,056 aserciones y cero fallos.

Siguiente trabajo, sólo después de que el usuario autorice expresamente la Fase 2:

1. Añadir `mongodb_analytics` en Laravel con URI/base separadas.
2. Asignar `AnalyticsInsight` exclusivamente a esa conexión.
3. Dividir las credenciales de Spark: core sólo lectura y analytics escritura limitada.
4. Probar todo contra bases locales antes de cualquier cambio en Atlas.
5. Conservar fallback temporal de lectura desde `barber_db.analytics_insights`.

No comiences Fase 2 ni cambies todavía las conexiones de Laravel/Spark. Antes de Fase 2
debe resolverse el riesgo de `exportar_insights_dashboard.py`, que actualmente hace
`delete_many({})` antes de `insert_many(...)`; se requiere publicación atómica o
versionada para evitar dejar la analítica vacía si el proceso falla.

---
