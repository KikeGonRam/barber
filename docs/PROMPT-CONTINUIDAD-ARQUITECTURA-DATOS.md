# Prompt de continuidad para otro proveedor

Copia y pega desde la siguiente línea en una nueva conversación:

---

Continúa la arquitectura de datos de UrbanBlade desde la Fase 2 implementada en código.

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
   Lee también `barber/docs/FASE-2-CONEXION-ANALITICA-SEPARADA.md`.
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

Estado de Fase 2:

1. Laravel ya tiene `mongodb_analytics`; `AnalyticsInsight` usa esa conexión con fallback
   temporal al origen histórico si todavía no se configuran variables analytics.
2. Spark separa `_connect_db()` de `_connect_analytics_db()` y rechaza reutilizar el
   mismo nombre de base.
3. El exportador publica por colección temporal y renombrado atómico; ya no borra la
   colección visible antes de insertar.
4. Pasaron 8 pruebas unitarias, 3 pruebas API, Pint focal y compilación Python.
5. No se escribió en Atlas. Falta aprovisionar credenciales de mínimo privilegio y hacer
   la prueba end-to-end primero contra un destino aislado.

No retires el fallback ni borres la colección histórica hasta validar el flujo completo.
No crees usuarios Atlas ni cambies secretos sin autorización concreta del usuario.

---
