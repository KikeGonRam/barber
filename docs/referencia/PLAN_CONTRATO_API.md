# Contrato HTTP y documentación de la API

Guía vigente del mecanismo existente, revisada el 28 de octubre de 2026.
El nombre del archivo se conserva para no romper referencias anteriores.

## Fuentes de verdad

| Elemento | Fuente |
| --- | --- |
| Rutas y middleware reales | [routes/api.php](../../routes/api.php), Laravel route:list. |
| Validación y respuestas | Requests, controladores y Resources. |
| Ejemplos comprobables | [docs/contrato](../contrato), [ApiContractTest](../../tests/SIT/ApiContractTest.php). |
| OpenAPI versionado | [public/docs/openapi.yaml](../../public/docs/openapi.yaml). |
| Detección de deriva de rutas | [verificar_contrato_api.mjs](../../scripts/verificar_contrato_api.mjs). |
| Required de respuestas | [ContractRequiredFieldsGenerator](../../app/Support/Docs/ContractRequiredFieldsGenerator.php). |

El frontend copia el spec a `contract/openapi.yaml` y genera tipos.
El Android consume el mismo contrato mediante sus modelos y capa HTTP.
No afirmar que ambos generan automáticamente todos sus DTO sin comprobarlo.

## Qué se verifica

- El comparador recibe la lista de rutas que Laravel resuelve y compara formas de
  path y métodos con OpenAPI.
- Los nombres de parámetros pueden variar entre Laravel y Scribe; se normaliza
  la forma, no se trata cada nombre como una ruta distinta.
- ApiContractTest compara respuestas seleccionadas con ejemplos versionados.
- El frontend comprueba que sus tipos corresponden a su copia local del contrato.
  El drift respecto al backend es una señal independiente.

El conjunto de casos es parcial. La coincidencia de rutas no demuestra permisos,
validación, nulabilidad ni semántica de todos los endpoints.
No mantener un conteo fijo de rutas como garantía de cobertura.

## Procedimiento para un cambio de API

1. Identificar consumidores web/Android y si el cambio es compatible.
2. Modificar rutas, validación, servicios y Resources con pruebas relevantes.
3. Actualizar ejemplos con datos ficticios.
4. Revisar la configuración de Scribe y su entorno antes de generar.
5. Obtener route:list y ejecutar el comparador en entorno aislado.
6. Revisar el diff del spec, métodos, respuestas, ejemplos y campos required.
7. Coordinar copia/tipos del frontend y compatibilidad móvil.
8. Escanear los artefactos antes de publicar.

Una actualización de Markdown no requiere regenerar el spec, cambiar tipos ni
hacer peticiones a una API real.

## Generación y seguridad

Scribe puede realizar response calls. No generar documentación con credenciales,
base o sesión de producción. Usar datos ficticios en ejemplos, incluidas
identidades, recibos y tokens. Los encabezados de autorización reales nunca se
guardan en fixtures, colecciones de Postman, HTML ni capturas.

La configuración actual contiene `auth.enabled=false` en
[config/scribe.php](../../config/scribe.php). Es una limitación conocida del generador:
la API real sí tiene autenticación Bearer. Esta revisión documental no modifica
la configuración ni regenera artefactos para corregirla; ese cambio necesita
validación técnica separada.

## Compatibilidad

Preferir ampliaciones compatibles. Retirar un campo o endpoint exige plan de
versionado, consumidores identificados y migración coordinada.
No cambiar enums, precisión monetaria, identificadores o tipos JSON en una guía
para hacerlos coincidir con un mock.

Consulta [arquitectura](DOCUMENTACION_TECNICA.md),
[decisiones](DECISIONES.md) y [pruebas](PRUEBAS.md).
