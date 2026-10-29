# Política de seguridad

Esta política cubre el repositorio backend de UrbanBlade y su API.
Revisión: 28 de octubre de 2026.

## Versiones y alcance de mantenimiento

Las correcciones se preparan mediante PR contra `main`. Al reportar, identifica el SHA,
tag o versión afectada y el entorno donde observaste el problema.
Una release histórica o un build aprobado no prueba que esa revisión siga desplegada
ni que esté libre de vulnerabilidades. No se ofrece un plazo de respuesta o soporte
por versión que el equipo no haya acordado.

## Reportar en privado

Usa [Report a vulnerability](https://github.com/KikeGonRam/barber/security/advisories/new).
El reporte privado está habilitado en este repositorio.

Para un hallazgo del frontend, también puedes usar su [canal privado](https://github.com/KikeGonRam/frontend_Urbanblade/security/advisories/new).
Si afecta a varios componentes, indícalo en un solo reporte y enlaza las revisiones implicadas.

Incluye:

- Componente y versión o commit afectado.
- Comportamiento observado, impacto y alcance estimado.
- Pasos mínimos para reproducir en un entorno aislado, con datos ficticios.
- Evidencia depurada y, si la tienes, una propuesta de mitigación.

No publiques secretos, cookies, headers Authorization, URLs firmadas, datos personales,
comprobantes o dumps en issues, PR, logs o capturas. Tampoco pruebes la validez de una
credencial de terceros para demostrar el hallazgo.
Los fallos funcionales sin información sensible pueden ir a
[Issues](https://github.com/KikeGonRam/barber/issues/new/choose).

## Evaluación y corrección

El equipo revisará el reporte, solicitará información cuando haga falta y coordinará
la reproducción, mitigación y corrección por el canal privado.
Antes de divulgar detalles técnicos, se coordinará con la persona que reportó para
evitar exponer a usuarios. Esta política no promete recompensas ni un SLA.

Reportar no autoriza pruebas sobre producción, acceso a cuentas ajenas, cobros reales,
ingeniería social, denegación de servicio ni cambios de datos.
Coordina cualquier reproducción que requiera salir del entorno aislado.

## Si se expone una credencial

Comunica en privado la ubicación y el tipo de credencial, sin reproducir el valor.
El responsable coordinará revocación o rotación, revisión de uso y actualización de
los servicios afectados. Eliminar un archivo o un commit actual no elimina su
contenido del historial. No reescribir Git ni modificar cuentas como reacción automática.

## Controles y dependencias

Las alertas de Dependabot, secret scanning, protección de push y análisis de CI son
controles complementarios. Un resultado verde no certifica ausencia de vulnerabilidades.
Los hallazgos se evalúan por impacto y alcance; no se descartan para ocultar avisos.

Los secretos se administran fuera del repositorio. Consulta
[accesos](docs/referencia/ACCESOS.md) y
[gestión de credenciales](docs/referencia/PLAN_ROTACION_CREDENCIALES.md).
Las pruebas locales del backend se ejecutan exclusivamente con `.\test.ps1`,
tras verificar el aislamiento descrito en [PRUEBAS](docs/referencia/PRUEBAS.md).

## Otras políticas

[Contribución](CONTRIBUTING.md) · [Código de conducta](CODE_OF_CONDUCT.md) ·
[Licencia y alcance](LICENSING.md).
