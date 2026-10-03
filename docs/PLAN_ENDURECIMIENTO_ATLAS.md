# Plan de endurecimiento de MongoDB Atlas — UrbanBlade

> Generado el 2026-10-02, **corregido el mismo día** tras verificar los privilegios
> reales contra Atlas. Requiere la consola de Atlas para los pasos pendientes: no se
> pueden aplicar por código. Complementa a `PLAN_ROTACION_CREDENCIALES.md`.

## Corrección importante

**La primera versión de este plan proponía crear dos usuarios de base de datos y
separar la escritura analítica de la base real. Eso ya estaba hecho, y mejor de lo que
yo proponía.** La verificación del 2026-10-02 (comando `connectionStatus` con
`showPrivileges`, sin escribir nada) devolvió:

| Usuario | Rol real | Recursos | Acciones de escritura |
|---|---|---|---|
| `MONGO_USER` (lo usan los 40+ scripts de `spark`) | `read` | `barber_db.*` | **ninguna** |
| `ANALYTICS_MONGO_USER` (lo usa el exportador) | `readWrite` | `urbanblade_analytics.*` | insert, update, remove, create/dropCollection, create/dropIndex |

Y las bases son distintas: `MONGO_DB` = `barber_db`, `ANALYTICS_MONGO_DB` =
`urbanblade_analytics`. `barber` lee los derivados por una conexión propia,
`mongodb_analytics` (`config/database.php`), apuntada a `ANALYTICS_MONGO_DATABASE`.

Consecuencia: **un error en cualquiera de los 40+ scripts de análisis no puede
modificar datos reales, porque el usuario de base de datos no puede escribir.** La
barrera existe y está aplicada en el servidor, no solo en el código. El trabajo
"pendiente" que describí en el Paso 1 y el Paso 2 de la versión anterior ya no aplica.

## Lo que sí faltaba, y quedó corregido por código

`data_ingestion/generar_datos_urbanblade.py` escribía con las credenciales del
**core** contra `MONGO_DB`. Dos problemas en uno:

1. Era una vía de escritura sobre la base real usando el usuario de lectura.
2. **El script estaba roto**: como ese usuario tiene rol `read`, el `insert_many`
   habría sido rechazado por el servidor con *not authorized*. No podía funcionar.

Ahora usa la conexión de ANALYTICS (`ANALYTICS_MONGO_*`) y, como segunda barrera
independiente de las credenciales, **se niega a arrancar si `ANALYTICS_MONGO_DB`
coincide con `MONGO_DB`**. Ambas barreras verificadas: se activan antes de intentar
conectar.

`appointments_synthetic` no lo lee nadie (verificado en los 4 repos), así que mover su
destino no rompe ningún consumidor.

## Lo que queda (requiere consola de Atlas)

### 1. Los dos usuarios comparten la misma contraseña — el hueco real

Comparación de `spark/.env` sin revelar valores:

```
MONGO_PASSWORD vs ANALYTICS_MONGO_PASSWORD  ->  IDÉNTICAS
```

Eso anula buena parte de la separación: `MONGO_PASSWORD` vive en el `.env` de cada
integrante del equipo (se reparte por necesidad, para el trabajo escolar), así que
quien tenga la credencial de lectura tiene también la de escritura analítica.

**Impacto acotado**: el usuario de analytics solo puede escribir en
`urbanblade_analytics`, nunca en `barber_db`. No es el escenario catastrófico de
septiembre, pero sí la mitad del beneficio perdido.

**Arreglo** (Atlas → Database Access → editar `ANALYTICS_MONGO_USER` → Edit Password):
contraseña nueva y distinta, y actualizarla solo en los `.env` que ejecutan el
exportador. No reutilizar la del core.

### 2. Verificar qué está habilitado en Atlas → Backup

No está confirmado. Ver el Paso 5 corregido más abajo.

### 3. Base de desarrollo separada

Sigue siendo válido y no se ha hecho: crear `barber_dev` en el mismo clúster y apuntar
ahí los `.env` de desarrollo local, dejando `barber_db` solo para staging. Así un
`migrate:fresh` o un seeder equivocado en local destruye datos de juguete.

**No cambiar sin actualizar `barber/.env` y `spark/.env` a la vez**, o la app local
aparecerá vacía.

### 4. Lista de acceso IP

Atlas → **Network Access**. `MONGODB_ATLAS.md` ya documenta que hoy el acceso desde
AWS depende de IPs que cambian en cada despliegue y que cerrarlo de verdad requiere
NAT Gateway o PrivateLink. Es la mejora menos urgente de las cuatro.

## Paso 5 — Respaldos: ya hay toolchain, faltan los últimos tramos

`barber` ya tiene un plan de continuidad documentado y **ensayado**; no hay que
construirlo de cero:

- [`barber/docs/FASE-5-CONTINUIDAD-OPERATIVA.md`](FASE-5-CONTINUIDAD-OPERATIVA.md)
  — fases 5A–5D. El ensayo del **2026-09-23** hizo `mongodump` → cifrado AES-256-GCM →
  descifrado → `mongorestore` sobre `barber-mongo-test`, verificando documentos e
  índices. Resultado `passed`.
- `barber/scripts/Invoke-SyntheticBackupDrill.ps1` — el ensayo **rechaza por diseño
  cualquier contenedor que no sea `barber-mongo-test`**: es imposible que apunte a
  Atlas por accidente. Buen diseño; no relajarlo.
- `barber/scripts/Publish-EncryptedBackupToS3.ps1` — publicador a S3, dry-run por
  defecto, exige `-Execute` y confirmación explícita.
- [`barber/docs/MONGODB_ATLAS.md`](MONGODB_ATLAS.md) — la rotación de
  contraseña de Atlas **ya está documentada** ahí, incluido el paso por Secrets
  Manager. No duplicarla: usarla.

Tramos que sí faltan, por orden de valor:

1. **Verificar Atlas → Backup.** La documentación dice "revisar", es decir, no está
   confirmado. Si el clúster es M10+, activar *Cloud Backup* con snapshots
   programados y point-in-time recovery: es lo único que cubre un error humano dentro
   de la ventana de retención.
2. **Completar 5C**: la publicación cifrada a S3 está en dry-run, pendiente de un
   perfil AWS de mínimo privilegio. Sin esto, el respaldo vive en la misma máquina que
   la base.
3. **Completar 5D** (automatización y monitoreo), hoy "sujeta a nueva autorización".
   Un respaldo que nadie ejecuta no es un respaldo.
4. **Cuidado con los `.zip` de la aplicación**: `MONGODB_ATLAS.md` advierte que varios
   de la segunda mitad de septiembre tenían colecciones enteras vacías. Antes de
   confiar en uno, contar documentos por colección contra Atlas.

## Ya aplicado por código (no requiere Atlas)

- `spark/tests/test_guarda_solo_lectura.py` — 9 pruebas. Falla si algún script escribe
  en una colección fuera de las autorizadas, y ahora también si el generador vuelve a
  leer las credenciales del core. Verificado: detecta una violación inyectada en un
  script de unidad, y no da falsos positivos con `rename(columns=…)` de pandas ni
  `.drop()` de Spark.
- `spark/.github/workflows/ci.yml` — corre esa guarda en cada push a `main`, valida la
  sintaxis de los 45 scripts y rechaza cualquier `.env` o llave versionada.
- Auditoría del 2026-10-02: **0 archivos sensibles rastreados** en los 4 repositorios.

## Nota sobre el "trabajo futuro" de la versión anterior

La versión anterior proponía cambiar `publish_insights_atomically` para usar una
colección de staging de nombre fijo, para poder limitar al publicador a unas pocas
colecciones. **Ya no aplica**: los derivados viven en una base propia
(`urbanblade_analytics`) cuyo usuario solo puede escribir ahí, así que el privilegio
amplio sobre colecciones temporales está correctamente acotado a la base de
derivados. El nombre dinámico no supone ningún riesgo para `barber_db`.
