# Integración continua y publicación del backend

Revisión documental: 28 de octubre de 2026.
El comportamiento de los workflows está en .github/workflows; las integraciones
de proveedores se configuran también fuera del repositorio.

## Controles versionados

| Workflow | Comprobaciones |
| --- | --- |
| [CI](../.github/workflows/ci.yml) | Pint, Larastan, PHPUnit con cobertura, auditorías Composer/npm, ESLint/Vite, Gitleaks, Trivy y smoke HTTP/contrato. |
| [Docker Image CI](../.github/workflows/docker-image.yml) | Construcción de imágenes de desarrollo/staging y mínimo de PHP. |
| [Documentación](../.github/workflows/documentation.yml) | Validación de documentos y destinos locales. |

Corren con PR hacia main y push a main. Sus jobs usan permisos acotados y
timeouts. La concurrencia cancela ejecuciones anteriores del mismo PR; las de
main utilizan un grupo único por ejecución.

CI prepara MongoDB replica set y Redis efímeros. Su comando PHPUnit no debe
copiarse al contenedor local con configuración operativa.
En local se utiliza exclusivamente [test.ps1](../test.ps1).

Construir una imagen en el runner no la publica.
Estos workflows no contienen docker push ni actualización de ECS.
Los checks externos y sus requisitos se consultan en GitHub; no se infieren de
una lista antigua de nombres.

## Documentación

```powershell
python scripts/check_docs.py --all
git diff --check
```

Con --all el validador comprueba Markdown de raíz, docs y .github; también
admite rutas explícitas sin --all.
No utiliza red ni credenciales. Comprueba enlaces Markdown en línea y HTML,
archivos vacíos y conflictos; omite URLs externas, anclas y enlaces de referencia.
No certifica semántica, permisos remotos ni ausencia de secretos.

La revisión integral añade un escaneo de archivos rastreados, documentos
ofimáticos extraídos e historial; alcance en
[SEGURIDAD_DOCUMENTACION.md](SEGURIDAD_DOCUMENTACION.md).

## Push, fusión y despliegue

1. La validación local comprueba el checkout y entorno probados.
2. Los checks del PR comprueban ese commit.
3. Fusionar cambia main y dispara sus workflows.
4. Publicar cambia un servicio remoto y requiere alcance propio.

Un paso no acredita el siguiente. No usar saltos de CI para evitar un despliegue:
los controles de calidad y el mecanismo de publicación son distintos.

En el mantenimiento anterior, Vercel generó una Preview con un push documental.
Para fusionar el frontend sin publicar otra versión se autorizó una pausa temporal
del Ignored Build Step; el merge recibió Canceled by Ignored Build Step y luego se
restauró Automatic. Esta evidencia no configura una exclusión permanente.
Antes de otro push/merge debe revisarse la integración vigente.

El [runbook](DESPLIEGUE_AWS_STAGING.md) describe los límites operativos.
No se publican claves ni se copian instrucciones de cambios de secretos al CI.

## Evidencia histórica comprobada

La integración anterior, backend main en `4ca64c3`, terminó con CI,
documentación, construcción de imágenes y análisis aprobados el 28 de octubre de
2026. La ejecución local previa reportó 951 pruebas y 3,679 aserciones.
[PR #49](https://github.com/KikeGonRam/barber/pull/49).

La presente revisión documental tiene su propio diff y validaciones; no se
presenta aquel resultado como una suite recién ejecutada.

## Presentación del repositorio

About describe la API de barberías con Laravel, MongoDB, Redis y Stripe.
El sitio del producto es [urbanblade.com.mx](https://urbanblade.com.mx/).
Los metadatos de GitHub no modifican la aplicación ni la licencia.
