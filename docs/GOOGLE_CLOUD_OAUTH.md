# Google Cloud Console: login con Google

Cómo está configurado el botón "Continuar con Google" y qué hay que tocar en Google
Cloud Console cuando cambia una URL. Aplica a local, staging en AWS
([`DESPLIEGUE_AWS_STAGING.md`](DESPLIEGUE_AWS_STAGING.md)) y a producción futura.

Estado al 23 de septiembre de 2026. Los valores reales (`client_id`, `client_secret`)
no viven aquí: están en `barber/.env` (local) y en Secrets Manager (AWS).

## Cómo funciona el flujo

1. El frontend manda al usuario a `GET <API>/api/v1/auth/google/redirect`
   (con `?target=spark` si viene del dashboard de analítica).
2. Laravel (`SocialAuthController::redirect`, Socialite en modo `stateless`) redirige a
   Google. El destino viaja como `state`.
3. Google devuelve al usuario a `GET <API>/api/v1/auth/google/callback`.
4. Laravel busca o crea el usuario por correo (siempre con rol `cliente`), emite un
   token propio y redirige a `<FRONTEND_URL>/auth/callback?token=...`
   (o a `<SPARK_URL>/?google_token=...`).

Es un flujo **de servidor**: Google solo habla con la API. Por eso en Google Cloud
solo hace falta la **URI de redirección** de la API; no se registran "Authorized
JavaScript origins" para el frontend.

Sin `GOOGLE_CLIENT_ID` la API responde 503 a propósito ("El login con Google no está
configurado todavía"); no rompe nada más.

## Login nativo en la app Android (UrbanBladeMobile)

La app Android no usa el flujo de redirección: pide un **ID token** a Google con
Credential Manager y lo manda a `POST <API>/api/v1/auth/google/token`
(`SocialAuthController::token`). Laravel verifica firma, emisor y **audiencia**; la
audiencia debe ser el mismo `GOOGLE_CLIENT_ID` (cliente **web**) de esta API.

Requisitos en Google Cloud, en el **mismo proyecto** del cliente web:

1. La app usa como `GOOGLE_CLIENT_ID` (en su `local.properties`) el ID del cliente
   **web**, idéntico al de `barber/.env` o Secrets Manager. Nunca el del cliente Android.
2. Existe un **ID de cliente de OAuth de tipo Android** con el paquete
   `com.urbanblade.mobile` y la huella **SHA-1** del certificado con que se firma el
   build instalado. Cada integrante tiene su propia llave de debug, así que cada uno
   agrega su SHA-1 (se pueden registrar varias). Para verla en Windows:

   ```powershell
   & "C:\Program Files\Android\Android Studio\jbr\bin\keytool.exe" '-J-Duser.language=en' -list -v -keystore "$env:USERPROFILE\.android\debug.keystore" -alias androiddebugkey -storepass android -keypass android | Select-String "SHA1"
   ```

   El `'-J-Duser.language=en'` entre comillas evita un fallo de `keytool` con el idioma
   español (`MissingFormatArgumentException`). También sale en Android Studio:
   Gradle → app → Tasks → android → `signingReport`.

Cómo apuntar la app al backend (emulador, celular físico por USB o staging) está en el
README de UrbanBladeMobile, sección "Dispositivo físico".

## Crear las credenciales (una sola vez)

1. Entrar a <https://console.cloud.google.com/> y crear o elegir un proyecto.
2. **APIs y servicios → Pantalla de consentimiento de OAuth**: tipo *Externo*, nombre de
   la app "UrbanBlade", correo de soporte y de contacto. Alcances por defecto
   (`email`, `profile`, `openid`); no se piden alcances sensibles.
3. Mientras la app esté en estado **Pruebas**, solo pueden entrar las cuentas agregadas
   en *Usuarios de prueba*. Para abrirla a cualquiera hay que pasarla a
   **En producción** (con alcances básicos no exige verificación de Google).
4. **APIs y servicios → Credenciales → Crear credenciales → ID de cliente de OAuth**,
   tipo **Aplicación web**.
5. En **URI de redirección autorizadas** agregar cada callback de la tabla de abajo.
6. Guardar. Copiar el ID de cliente y el secreto a las variables de cada entorno.

## URIs de redirección autorizadas

Deben coincidir **exactamente** (esquema, host, ruta, sin barra final) con
`GOOGLE_REDIRECT_URI` del entorno.

| Entorno | URI |
|---|---|
| Local (Docker) | `http://localhost:8000/api/v1/auth/google/callback` |
| Staging AWS | `https://d1s2thm3f8g40t.cloudfront.net/api/v1/auth/google/callback` |
| Producción (cuando exista dominio) | `https://<dominio-api>/api/v1/auth/google/callback` |

Google acepta `http://` solo para `localhost`; cualquier otro host exige `https`. Los
dominios `*.cloudfront.net` son válidos como URI de redirección.

## Variables

| Variable | Dónde | Notas |
|---|---|---|
| `GOOGLE_CLIENT_ID` | `.env` / Secrets Manager `urbanblade/staging/barber/GOOGLE_CLIENT_ID` | secreto en AWS |
| `GOOGLE_CLIENT_SECRET` | `.env` / Secrets Manager `.../GOOGLE_CLIENT_SECRET` | secreto en AWS |
| `GOOGLE_REDIRECT_URI` | `.env` / variable de la task definition de barber | no secreta; debe igualar la tabla |

Tras cambiar un secreto en AWS hay que forzar un nuevo despliegue de `uba-stg-barber`
(ver runbook del documento de AWS). En local, recrear `app`, `worker` y `scheduler`
(`docker compose up -d --force-recreate app worker scheduler`), porque el contenedor
conserva las variables de entorno con las que se creó.

## Cuando cambia una URL

Cambiar el dominio de la API (por ejemplo al comprar el dominio propio) obliga a:
1. Agregar la URI nueva en Google Cloud Console (puede convivir con la anterior).
2. Actualizar `GOOGLE_REDIRECT_URI` y desplegar.
3. Quitar la URI vieja cuando ya no se use.

Cambiar solo el dominio del **frontend** no requiere tocar Google: el frontend se
configura en `FRONTEND_URL`/CORS de la API.

## Errores frecuentes

| Síntoma | Causa | Solución |
|---|---|---|
| `Error 400: redirect_uri_mismatch` | La URI que envía la API no está registrada tal cual | Copiar la URI que muestra el error a Google Cloud (o corregir `GOOGLE_REDIRECT_URI`); revisar `https`/`http` y barra final |
| "Acceso bloqueado: esta app no ha completado la verificación" | App en *Pruebas* y el correo no es usuario de prueba | Agregar el correo en *Usuarios de prueba* o pasar la app a producción |
| 503 al pulsar el botón | Falta `GOOGLE_CLIENT_ID` en el entorno | Definir las tres variables y reiniciar |
| El login funciona pero vuelve a `/login?error=google_retry` | Fallo al canjear el código (secreto incorrecto o reloj) | Revisar `GOOGLE_CLIENT_SECRET` y los logs de la API |
| Android: "No pudimos abrir Google…" | Falta el cliente OAuth **Android** o su SHA-1 no coincide con la del build instalado | Registrar el cliente Android con `com.urbanblade.mobile` y la SHA-1 correcta |
| Android: "El token de Google no es válido." (401) | El `GOOGLE_CLIENT_ID` de la app no es el mismo cliente web que usa la API | Usar en `local.properties` exactamente el ID web de la API |
| Android: el login se queda cargando y el log muestra `failed to connect to /10.0.2.2` | Build `debug` instalado en un **celular físico**: `10.0.2.2` solo existe en el emulador | `adb reverse tcp:8000 tcp:8000` + `DEBUG_API_BASE_URL=http://127.0.0.1:8000/api/v1/`, o variante `staging` |
| La URI generada usa `http://` detrás de CloudFront | `APP_URL` sin `https` (Laravel no fuerza el esquema) | `APP_URL=https://...`; la app fuerza `https` cuando empieza así |

## Seguridad

- El secreto de cliente no se commitea ni se comparte por chat. Si se expone, generar
  uno nuevo en la misma credencial (Google permite tener dos a la vez durante la
  rotación), actualizar el entorno y borrar el viejo.
- Los usuarios creados con Google siempre reciben el rol `cliente`; los roles de
  personal se asignan solo desde el panel de administración.
