# Seguridad de la documentación

Revisión local: 28 de octubre de 2026. Esta revisión no modifica cuentas, secretos,
bases de datos ni despliegues y no certifica la seguridad completa de la aplicación.

## Alcance y método

- Archivos versionados presentes del backend, Markdown, ejemplos y documentación generada.
- Extracción de texto de los DOCX y de los tres manuales PDF históricos.
- Historial disponible en las referencias locales de Git.
- Gitleaks 8.30.1, descargado del proyecto oficial y verificado mediante su checksum.
  Reglas predeterminadas, sin ampliar allowlists y con salida redactada.
- Revisión de enlaces y de comandos que podían exponer contraseñas o actuar sobre datos reales.
- No se imprimieron ni enviaron archivos .env reales a servicios externos.

## Resultados y tratamiento

El análisis inicial de 787 archivos versionados presentes produjo cuatro alertas:
un hash de Scribe, dos ejemplos id_token de Scribe y un placeholder de clave de
Stripe en una guía. La guía de Stripe se reescribió para evitar ejemplos con forma
de clave. El texto extraído de los tres PDF no produjo alertas.
La repetición sobre 773 archivos presentes, incluidos los nuevos Markdown, produjo
tres alertas: el hash y los dos ejemplos generados de Scribe; el placeholder de Stripe
ya no aparece. No se ampliaron excepciones para ocultar resultados.

El análisis del historial produjo 18 alertas. Incluye headers de ejemplo, hashes,
tokens en ejemplos de contrato/Scribe y fixtures de pruebas. Un detector por sí solo
no acredita que un token histórico haya sido emitido o siga siendo válido.
Los valores de esos hallazgos no se reproducen en esta guía ni se validaron contra proveedores.

Se retiraron inventarios de cuentas desactualizados y ejemplos que pegaban contraseñas
en comandos. Los manuales Word/PDF duplicados se sustituyen por Markdown mantenido.
No se ha confirmado una credencial activa durante esta revisión; la procedencia y
revocación de cualquier valor histórico dudoso requieren revisión privada del propietario.
Si se confirma una exposición, seguir [rotación de credenciales](PLAN_ROTACION_CREDENCIALES.md)
y [SECURITY](../../SECURITY.md); borrar un archivo actual no lo elimina del historial.

## Límites y pendientes técnicos

- Los escáneres no detectan todos los secretos ni validan permisos remotos.
- No se hizo OCR de imágenes ni validación de cuentas o claves en servicios externos.
- La configuración de Scribe puede generar una introducción incorrecta sobre autenticación:
  consultar [contrato](PLAN_CONTRATO_API.md), no asumir rutas públicas.
- El callback OAuth web transporta un token en la URL; tratar logs, historial y capturas
  de esa URL como sensibles. Su rediseño es un cambio técnico independiente.
- El fallback local de comprobantes usa almacenamiento público; no ofrece la misma
  privacidad que S3 privado con URLs temporales.
- Conexiones separadas en configuración no prueban separación efectiva de cuentas o permisos.

## Mantenimiento

Usar ejemplos como TOKEN_DE_EJEMPLO y datos ficticios. No publicar cookies, headers
Authorization, URLs firmadas, dumps, llaves privadas ni pantallas de configuración.
Antes de publicar cambios, repetir el análisis del diff y de los archivos candidatos,
manteniendo redactados los informes. Git conserva el historial; no reescribirlo para
silenciar un hallazgo sin un plan explícito.
