# Autenticación con Google

Revisión de [SocialAuthController](../app/Http/Controllers/Api/Auth/SocialAuthController.php):
28 de octubre de 2026. Describe los flujos implementados, no el estado de la consola
Google ni las credenciales del equipo.

## Flujo web

1. El navegador abre GET `/api/v1/auth/google/redirect`.
2. Laravel utiliza Socialite en modo stateless y dirige a Google.
3. Google devuelve el código al callback de la API.
4. Laravel busca/crea la cuenta y emite un token Bearer propio.
5. El callback redirige al frontend en `/auth/callback`.
   Existe también compatibilidad con el destino Spark.

Las cuentas nuevas reciben rol cliente. Una identidad Google no concede
privilegios administrativos.
El código actual transporta el token de retorno en la query de la redirección:
es información sensible y no debe aparecer en logs, capturas, tickets ni URLs
registradas como callback. Esta guía no afirma que el transporte por query elimine
los riesgos de historial o referer; una mejora requiere un cambio técnico aparte.

## Flujo Android

La app obtiene un ID token y lo envía a POST `/api/v1/auth/google/token`.
El backend valida firma con JWKS, expiración, emisor, audiencia, correo y su
verificación antes de emitir su token de API.

El ID de cliente web configurado por la API debe coincidir con la audiencia que
solicita Android. La configuración del paquete, certificados y variantes se mantiene
en el [repositorio Android](https://github.com/al140605/UrbanBladeMobile);
no se duplican huellas ni claves de firma aquí.

## Variables

| Variable | Función |
| --- | --- |
| GOOGLE_CLIENT_ID | Identificador de cliente utilizado para audiencia y Socialite. |
| GOOGLE_CLIENT_SECRET | Secreto del flujo web; solo backend. |
| GOOGLE_REDIRECT_URI | Callback exacto de la API para el entorno seleccionado. |
| FRONTEND_URL | Destino web al terminar el callback. |
| SPARK_URL | Destino de compatibilidad cuando se solicita Spark. |

La configuración se carga en [config/services.php](../config/services.php).
Sin client ID, los endpoints relevantes responden 503.
Un identificador de cliente público no es una clave secreta; el client secret sí
requiere custodia. Ninguno necesita publicarse en esta guía.

## Comprobación por entorno

- Revisar el esquema/host/ruta exactos del callback configurado, sin query ni token.
- Revisar audiencia y variante de Android.
- Identificar si la aplicación Google está restringida a usuarios de prueba.
  Su estado efectivo debe consultarse en la cuenta, no deducirse de este documento.
- Comprobar login, cancelación y error en una cuenta de prueba autorizada.
- Usar logs redactados; nunca imprimir códigos, tokens o credenciales.

Un cambio de URL o secreto puede exigir ajustes externos y recreación de procesos.
No se ejecuta como parte de una actualización de documentación.
