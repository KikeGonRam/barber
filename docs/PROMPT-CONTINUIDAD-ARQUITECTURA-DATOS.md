# Prompt de continuidad para otro proveedor

Copia y pega desde la siguiente línea en una nueva conversación:

---

Continúa la arquitectura de datos de UrbanBlade desde la Fase 5 operativa. Las fases
0 a 4 están terminadas y no deben repetirse.

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
   Para continuar, lee completa
   `barber/docs/FASE-5-CONTINUIDAD-OPERATIVA.md`.
4. No ejecutes pruebas Laravel directamente: usa sólo `barber/test.ps1`.
5. No muestres secretos ni contenido de `.env` y no escribas en Atlas sin autorización
   explícita para la fase concreta.
6. `barber` es la única puerta de escritura operativa; `frontend-urban` consume API;
   Spark debe leer core y escribir únicamente derivados analíticos.
7. Antes de editar, ejecuta `git status --short --branch` en cada repositorio implicado.
   Conserva todos los cambios locales existentes, no los descartes y no los mezcles
   con el trabajo de arquitectura.

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

1. Laravel usa `mongodb_analytics` con `laravel_analytics_reader`, que tiene únicamente
   `read@urbanblade_analytics`.
2. Spark separa `_connect_db()` de `_connect_analytics_db()` y rechaza reutilizar el
   mismo nombre de base.
3. El exportador publica por colección temporal y renombrado atómico; ya no borra la
   colección visible antes de insertar.
4. Pasaron 8 pruebas unitarias, 3 pruebas API, Pint focal y compilación Python.
5. El flujo se validó primero localmente y después en Atlas real: Spark leyó 100 citas
   con `spark_core_reader`, publicó 39 insights con `ANALYTIC`, creó cuatro índices y
   Laravel leyó los mismos 39. Atlas rechazó las escrituras intentadas con los usuarios
   de solo lectura.

Estado de Fases 3 y 4:

1. La Fase 3 quedó completada sin copia histórica porque `barber_db.analytics_insights`
   nunca existió; el corte fue directo a `urbanblade_analytics.analytics_insights`.
2. Laravel core usa `laravel_core_rw` con `readWrite@barber_db`; no usa la cuenta
   personal para la aplicación ni para staging.
3. Las pruebas bloquean tanto `mongodb` como `mongodb_analytics` si resuelven a Atlas o
   a una base distinta de `barber_db_test`.
4. Decisión explícita del propietario del 2026-09-23: no eliminar, rotar ni sustituir
   la credencial `luis`. Barber (`.env`, app, worker y scheduler) usa cuentas dedicadas,
   pero `spark/.env` y `spark-dashboard` deben conservar `luis` en `MONGO_USER` y
   `MONGODB_URI`. Es una excepción aceptada al mínimo privilegio; no afirmar que Atlas
   bloquea escrituras core para ese consumidor.
5. El 2026-09-23 se eliminó `bootstrap/cache/config.php` local porque materializaba URI
   con secretos. Laravel siguió iniciando correctamente sin ese caché.

Fase 5 pendiente: continuidad operativa

1. No crear otra base ni otro contenedor MongoDB: la separación vigente ya cubre core,
   analytics, desarrollo y pruebas.
2. Preparar primero un plan para una copia externa cifrada del respaldo operativo. El
   plan debe definir destino, cifrado, acceso, frecuencia, retención y eliminación
   segura, sin subir datos reales hasta contar con autorización explícita.
3. Diseñar una restauración periódica en un entorno aislado y criterios verificables:
   integridad del archivo, conteos de colecciones/documentos/índices y tiempo de
   recuperación. Una copia no se considera válida hasta probar su restauración.
4. Proponer monitoreo y alertas para fallos de respaldo, antigüedad de la última copia,
   conexiones cruzadas entre core/analytics y cualquier intento de usar Atlas durante
   pruebas.
5. Entregar la Fase 5 por etapas: documentación, ensayo local con datos no sensibles y,
   únicamente tras otra autorización, integración externa. Cada etapa debe incluir
   evidencia, reversión y riesgos pendientes.
6. Las fases 5A y 5B están completadas. El script local está en
   `barber/scripts/Invoke-SyntheticBackupDrill.ps1`. El ensayo sintético
   `20260923T182413Z-a8716c51` probó `mongodump`, cifrado AES-256-GCM, descifrado,
   `mongorestore`, documentos e índices y produjo un manifiesto `result: passed`. No
   quedaron bases temporales ni archivos planos.
7. La preparación de 5C para Amazon S3 está en `barber/docs/FASE-5C-S3.md` y el
   publicador en `barber/scripts/Publish-EncryptedBackupToS3.ps1`. Solo se autorizó
   preparación y dry-run. No crear infraestructura AWS, no configurar credenciales y no
   ejecutar `-Execute` sin una nueva autorización explícita que identifique bucket,
   región y perfil. AWS CLI v2 está instalada, pero al 2026-09-23 no había perfiles
   configurados y el dry-run no se había ejecutado. La Fase 5D continúa sin autorización.

No actúes sobre la credencial `luis`, no borres usuarios Atlas, no cambies secretos y no
toques producción sin una nueva instrucción explícita del propietario.

---
