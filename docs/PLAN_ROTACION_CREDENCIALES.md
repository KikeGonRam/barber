# Gestión y rotación de credenciales

Guía revisada el 28 de octubre de 2026. No contiene claves, contraseñas, nombres
de archivos privados ni un inventario de cuentas operativas.
Una revisión documental no modifica credenciales ni servicios de producción.

## Qué se considera sensible

- Claves privadas, claves API secretas o restringidas, webhooks y tokens Bearer.
- Contraseñas, URIs con autenticación y archivos de cuenta de servicio.
- Cookies, sesiones, enlaces de recuperación o acceso firmado todavía vigentes.
- Dumps, exportaciones de cuentas, comprobantes y logs con datos personales.

Un identificador público de proyecto o una clave publicable no equivale a una
clave secreta, pero no justifica copiar datos operativos innecesarios.

## Si aparece un secreto

1. No copiar su valor al chat, informe, issue, PR o captura.
2. Registrar solo proveedor, archivo y ubicación de la coincidencia.
3. Informar por el [canal privado](../SECURITY.md).
4. El propietario verifica si es real y coordina revocación o rotación.
5. Eliminarlo de la versión actual y sustituirlo por configuración externa.
6. Evaluar el historial, artefactos, logs y otras copias de la exposición.
7. Verificar los consumidores antes de revocar el valor anterior cuando la
   continuidad del servicio lo requiera.

Borrar un archivo no revoca una credencial ni la elimina de Git.
Reescribir historial publicado es una operación separada, coordinada y autorizada;
no se ejecuta como limpieza automática.

## Procedimiento por proveedor

| Tipo | Secuencia que debe coordinar el responsable |
| --- | --- |
| MongoDB | Identificar consumidores y privilegios; generar credencial con alcance mínimo; actualizar destinos; verificar conexión; revocar la anterior. |
| AWS | Preferir roles de servicio; para claves de acceso, crear sustituta con alcance mínimo, actualizar consumidores y desactivar/revocar la anterior. |
| Firebase | Sustituir la cuenta/clave utilizada por el servicio, verificar entrega controlada y revocar la clave antigua. |
| Stripe | Separar test/live, asignar permisos por funciones utilizadas, actualizar backend/webhook y probar en sandbox. Nunca pasar claves secretas al frontend. |
| Cuenta de aplicación | Usar recuperación o administración autorizada; no introducir contraseñas en comandos públicos, historial ni fixtures. |
| Tokens de sesión/API | Revocar los afectados mediante el mecanismo de la aplicación; no basta editar el ejemplo donde aparecieron. |

No ejecutar ninguna fila sin comprobar entorno, dependencias, forma de recuperación
y autorización. La rotación puede requerir reinicios o despliegues y queda fuera
del mantenimiento documental sin publicación.

## Prevención

- Usar el gestor de secretos del entorno y plantillas sin valores reales.
- Compartir acceso por identidad y permisos, no copiando una contraseña común.
- Mantener separados test, desarrollo y producción.
- Revisar el diff y el listado de archivos antes de publicar.
- Escanear texto, documentos generados e historial con resultados redactados.
- No confiar únicamente en `.gitignore`: no elimina archivos ya rastreados ni
  protege una carpeta contenedora ajena al repositorio.

La revisión de este cambio está en
[SEGURIDAD_DOCUMENTACION.md](SEGURIDAD_DOCUMENTACION.md).
