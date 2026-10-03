# Plan de rotación y reubicación de credenciales — UrbanBlade

> Generado el 2026-10-02. Este documento **no contiene ningún valor secreto**, a
> propósito: se puede versionar o compartir sin filtrar nada.
>
> **Actualizado el 2026-10-02** tras reorganizar la carpeta raíz: las credenciales ya
> no están sueltas en la raíz, viven en `_seguridad/`. Ver "Reorganización" al final.

## Por qué

La carpeta contenedora `C:\Users\luis1\Documents\UrbanBlade` **no es un repositorio
git**, así que ningún `.gitignore` la protege, y guardaba credenciales en texto plano.
La buena noticia, verificada el 2026-10-02:

- **`Documents` NO está redirigido a OneDrive** (`OneDrive` vive en
  `C:\Users\luis1\OneDrive`), así que estos archivos no se están sincronizando a la
  nube por sí solos.
- **Los 4 repositorios están limpios**: una búsqueda de archivos sensibles
  rastreados (`git ls-files` contra `.env`, `.pem`, `.p12`, `.jks`, `*.keystore`,
  `adminsdk`, `accessKeys`, `credentials*.json`, `local.properties`,
  `google-services.json`) devolvió **0 coincidencias** en `barber`,
  `frontend-urban`, `spark` y `UrbanBladeMobile`.

El riesgo real es por copia: comprimir la carpeta, clonarla a otra máquina, subirla a
un respaldo o pasarla por chat se lleva todo junto. **Una de estas llaves ya quedó
expuesta en claro durante una sesión de inspección**, así que se trata como
comprometida.

## Inventario (sin valores)

Todos estos archivos están ahora en `_seguridad/`, que la raíz ignora por completo.

| Archivo en `_seguridad/` | Qué es | Quién lo consume | Acción |
|---|---|---|---|
| `barber-c6b3a-firebase-adminsdk-y9cgv-a1b026a9ba.json` | Cuenta de servicio del **Firebase Admin SDK** del proyecto `barber-c6b3a`. Control total del proyecto (FCM, Analytics, Crashlytics, Remote Config) | Local: **no** referenciado — `barber/.env` no define `FIREBASE_CREDENTIALS_PATH` y una búsqueda en los 4 repos no encontró ninguna referencia. Staging: su propia copia, vía el `FIREBASE_CREDENTIALS_PATH` de `barber/docs/DESPLIEGUE_AWS_STAGING.md` | **Rotar** (procedimiento A) |
| `staging-deploy_accessKeys.csv` | Par de claves IAM del usuario de despliegue a staging | **Ninguno**: 0 referencias en los 4 repos, `Makefile`, `docs/` ni workflows de CI. Es una llave de uso manual | **Rotar** (procedimiento B) |
| `AWS ACCESO CLI.txt` | Apuntes con la **misma** clave AWS anterior, en texto plano | Ninguno | **Borrar** después de rotar |
| `ACCESOS.md` | Contraseña del administrador en texto plano | Documento humano; lo referencian `barber/AGENTS.md` y `barber/CLAUDE.md` como `../_seguridad/ACCESOS.md` | **Restablecer** contraseña (procedimiento C) y no volver a dejarla en claro |

> No todo el proyecto es una credencial: `spark/.env`, `barber/.env` y
> `frontend-urban/.env` siguen viviendo dentro de cada repositorio (están en su
> `.gitignore`, y se verificó que ninguno está versionado). No se movieron: cada
> proyecto espera el suyo junto a su código.

> Hallazgo relacionado, verificado el 2026-10-02: en `spark/.env`, las contraseñas de
> `MONGO_PASSWORD` (lectura) y `ANALYTICS_MONGO_PASSWORD` (escritura de derivados) son
> **idénticas**, así que repartir la credencial de lectura entrega también la de
> escritura. Detalle y arreglo en
> [`PLAN_ENDURECIMIENTO_ATLAS.md`](PLAN_ENDURECIMIENTO_ATLAS.md), sección 1.

## Procedimiento A — Cuenta de servicio de Firebase

1. Firebase Console → proyecto `barber-c6b3a` → ⚙️ **Configuración del proyecto** →
   pestaña **Cuentas de servicio** → **Generar nueva clave privada** → descarga un
   JSON nuevo.
2. Sustituir el secreto en el servidor de staging (el `FIREBASE_CREDENTIALS_PATH`
   documentado en `barber/docs/DESPLIEGUE_AWS_STAGING.md`). Reiniciar el worker de
   colas: el push se envía desde la cola, no desde la petición web.
3. Volver a **Cuentas de servicio** → **Gestionar claves** y **revocar la clave
   antigua** (la que está en `_seguridad/`). Sin este paso la rotación no sirve de nada.
4. Verificar: reservar una cita desde la app Android en variante `staging` y
   confirmar que llega la notificación.
5. Borrar `barber-c6b3a-firebase-adminsdk-y9cgv-a1b026a9ba.json` de `_seguridad/`.

> Si el push de staging nunca se configuró, el paso 3 igual aplica: la llave existe y
> está en disco, así que se revoca.

## Procedimiento B — Claves IAM de AWS

1. Consola AWS → **IAM** → **Usuarios** → el usuario de despliegue a staging →
   pestaña **Credenciales de seguridad** → **Crear clave de acceso**.
2. Actualizar el lugar donde se usen manualmente (perfil local de AWS CLI,
   `aws configure`, o variables del script de despliegue). **No hay que tocar
   secretos de GitHub Actions**: la búsqueda confirmó que ningún workflow usa estas
   llaves.
3. **Desactivar** la clave antigua, probar un despliegue, y solo entonces
   **eliminarla**.
4. Borrar `staging-deploy_accessKeys.csv` y `AWS ACCESO CLI.txt` de `_seguridad/`.

> Considerar además crear el usuario de despliegue con permisos mínimos en lugar de
> claves de larga duración, y evaluar OIDC de GitHub Actions si algún día el
> despliegue se automatiza.

## Procedimiento C — Contraseña del administrador

1. Restablecer la contraseña desde el propio flujo de la app
   (`/forgot-password` en `frontend-urban` o el endpoint equivalente de la API).
2. Actualizar `_seguridad/ACCESOS.md`… o mejor, **dejar de usar texto plano**: si el
   equipo necesita compartir credenciales de demo, usar un gestor (Bitwarden,
   1Password) o el llavero de Windows (`cmdkey` / DPAPI).

## Procedimiento D — Usuario de MongoDB Atlas

Aplica si la carpeta llegó a salir de la máquina en un archivo comprimido, o por
precaución tras la exposición.

**Ya está documentado en el repositorio: seguir
[`barber/docs/MONGODB_ATLAS.md`](MONGODB_ATLAS.md)**, sección "Rotar la
contraseña de un usuario". No se duplica aquí a propósito. Dos detalles de ese
procedimiento que se pasan por alto con facilidad:

- El valor también vive en **AWS Secrets Manager** para staging
  (`aws secretsmanager put-secret-value`), no solo en los `.env`.
- Un contenedor **conserva** las variables con las que se creó: cambiar `.env` no
  basta, hay que recrear `app`, `worker` y `scheduler`.

> Para rotar la contraseña del usuario de escritura de `spark` hay un ayudante
> verificado: `spark/scripts/rotar_password_analytics.ps1`. No puede cambiarla en
> Atlas (ninguno de los dos usuarios de base de datos tiene privilegio para eso), pero
> sí la valida contra Atlas antes de escribirla en el `.env`.

## Orden recomendado

1. Firebase (A) — es la llave de mayor privilegio.
2. AWS (B).
3. Contraseña del administrador (C).
4. Contraseña del usuario de escritura de `spark` (ayudante del procedimiento D).

## Qué NO hacer

- **No borrar los archivos antes de rotar.** El JSON de Firebase y el CSV de AWS son
  la copia que hoy permite restaurar el acceso; se borran al final, no al principio.
- **No hacer `git init` en la carpeta contenedora** sin comprobar antes que
  `_seguridad/` queda ignorado: `git status --ignored` debe listarla.

## Reorganización de la raíz (2026-10-02)

Las credenciales se movieron de la raíz a `_seguridad/`, y las guías y planes a
`_docs/`. Se actualizaron las referencias que apuntaban a las rutas viejas:
`barber/AGENTS.md`, `barber/CLAUDE.md` y `spark/CLAUDE.md`.

El `.gitignore` de la raíz ahora **ignora `_seguridad/` entera**, en vez de enumerar
archivos uno por uno: así cualquier credencial que se agregue ahí en el futuro queda
cubierta sin tener que acordarse de añadir una línea.
