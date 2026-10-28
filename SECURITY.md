# Política de seguridad

## Alcance y mantenimiento

UrbanBlade está en uso en producción. Los hallazgos de autenticación,
autorización, pagos, datos personales o secretos requieren comunicación privada.
Las correcciones se preparan mediante PR hacia `main`; no se ofrece aquí una
garantía de soporte por versión ni un plazo de respuesta.

## Reportar una vulnerabilidad

Usa [Report a vulnerability](https://github.com/KikeGonRam/barber/security/advisories/new).
El reporte privado está habilitado en este repositorio.

Incluye el componente afectado, el impacto, los pasos mínimos de reproducción y
una propuesta de mitigación si la tienes. Utiliza datos ficticios y elimina
tokens, contraseñas, comprobantes y datos personales de las evidencias.
No publiques un exploit ni un secreto en un issue o PR público.

Un reporte no autoriza pruebas sobre producción, cuentas ajenas, pagos reales
ni modificaciones de datos. Coordina cualquier reproducción con el propietario.

## Credenciales y dependencias

Las credenciales se administran fuera del repositorio.
[ACCESOS.md](docs/referencia/ACCESOS.md) documenta el proceso de acceso sin contraseñas.
Si detectas un secreto expuesto, comunícalo en privado para que el propietario
coordine su revocación y rotación.

Las auditorías de dependencias y los escáneres del CI son controles
complementarios; un resultado verde no certifica ausencia de vulnerabilidades.
