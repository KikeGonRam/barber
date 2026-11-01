# Integración continua y publicación del backend

Revisión documental: 28 de octubre de 2026.
El comportamiento de los workflows está en .github/workflows; las integraciones
de proveedores se configuran también fuera del repositorio.

## Controles versionados

| Workflow | Comprobaciones |
| --- | --- |
| [CI](../../.github/workflows/ci.yml) | Pint, Larastan, PHPUnit con cobertura, auditorías Composer/npm, ESLint/Vite, Gitleaks, Trivy y smoke HTTP/contrato. |
| [Docker Image CI](../../.github/workflows/docker-image.yml) | Construcción de imágenes de desarrollo/staging y mínimo de PHP. |
| [Documentación](../../.github/workflows/documentation.yml) | Validación de documentos y destinos locales. |

Corren con PR hacia main y push a main. Sus jobs usan permisos acotados y
timeouts. La concurrencia cancela ejecuciones anteriores del mismo PR; las de
main utilizan un grupo único por ejecución.

CI prepara MongoDB replica set y Redis efímeros. Su comando PHPUnit no debe
copiarse al contenedor local con configuración operativa.
En local se utiliza exclusivamente [test.ps1](../../test.ps1).

Construir una imagen en el runner no la publica: CI y Docker Image CI no contienen docker push ni actualización de ECS.
La publicación a staging vive en un workflow aparte, descrito en la sección siguiente.
Los checks externos y sus requisitos se consultan en GitHub; no se infieren de
una lista antigua de nombres.

## Despliegue a staging

[Desplegar a staging](../../.github/workflows/desplegar-staging.yml) publica el backend en ECS Fargate. Arranca solo cuando
el CI de `main` termina en verde tras fusionar un PR, o a mano desde Actions con Run workflow. Nunca arranca desde un PR, un
fork ni otra rama. **Siempre espera la aprobación del propietario**: arrancar solo significa que la solicitud ya está
lista, no que se despliegue sin ti.

Un job previo (`decidir`) compara el commit con el anterior de `main`:

| El commit cambia… | Qué hace |
| --- | --- |
| Solo Markdown, `.github/`, `tests/` o archivos de agentes | No pide aprobación: nada de eso entra a la imagen. |
| Migraciones o seeders (`database/migrations`, `database/seeders`) | No despliega solo y avisa. Se revisan y se lanza a mano con `confirmar_migraciones`. |
| Cualquier otro archivo (código, dependencias, configuración) | Pide la aprobación del entorno `staging` y despliega. |

A mano siempre se despliega, y es ahí donde se confirman las migraciones. El commit que se construye es el que terminó el CI
(`head_sha`), no la punta de `main`: si entran dos fusiones seguidas, cada ejecución usa el suyo.

| Control | Cómo se cumple |
| --- | --- |
| Credenciales | OIDC: el job pide un token a GitHub y asume un rol de AWS con credenciales temporales. No hay llaves de AWS en GitHub. |
| Privilegio mínimo | El rol solo sube imágenes a dos repositorios ECR y redespliega dos servicios; lo define `oidc_despliegue.tf` en urbanbladeTerraform. |
| Aprobación | El entorno `staging` de GitHub exige revisión del propietario antes de que el job use el rol. |
| Commit probado | Se comprueba que el CI de ese SHA terminó en verde. |
| Migraciones | Se detiene si cambian `database/migrations` o `database/seeders` desde el commit desplegado antes, salvo `confirmar_migraciones`. |
| Reversibilidad | Etiqueta `rollback-<fecha>-pre-<sha>` con la imagen anterior; si la verificación falla, vuelve sola a ella. |
| Trazabilidad | Cada imagen lleva `sha-<corto>` además de `latest`; el resumen del job guarda commit, digest y respaldo. |
| Verificación | El digest de las tareas en ejecución debe ser el recién subido, y `/up` y `/api/v1/barbershop` deben responder 200. |
| Pruebas de humo | Solo lectura y sin sesión: `/api/v1/services`, `barbers` y `products` devuelven una lista (la de servicios, no vacía), `/api/v1/barbershop` trae nombre y `/api/v1/profile` sin token responde 401. Si falla, revierte como la verificación. |
| Vulnerabilidades | Informativo: el resumen de la ejecución muestra el escaneo básico de ECR por severidad. Nunca bloquea ni revierte; si el escaneo al subir no está activo, solo avisa. |

Dependencias fuera del repositorio, ya creadas el 31-oct-2026: el rol OIDC de AWS (`oidc_despliegue.tf` en urbanbladeTerraform) y,
en GitHub, el entorno `staging` con revisor obligatorio, limitado a la rama `main`, y la variable `AWS_DEPLOY_ROLE_ARN`
(el ARN del rol, que no es un secreto). Si falta alguna, el workflow falla en el paso de credenciales sin publicar nada.

El frontend tiene su propio workflow con la misma estructura. El agente `urbanblade-deploy` sigue siendo el
procedimiento manual de respaldo si el workflow no estuviera disponible.

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
