# Pruebas del pipeline de CI/CD

Evidencia de las historias **HT-12 Despliegue continuo** (Jira SCRUM-184) y **HT-14 Pruebas del pipeline de CI/CD** (SCRUM-186), Sprint 5. Revisión del 23 de noviembre de 2026 sobre `KikeGonRam/barber` y `KikeGonRam/frontend_Urbanblade`, que despliegan con la plantilla compartida [`urbanblade-ci/desplegar-ecs.yml`](https://github.com/KikeGonRam/urbanblade-ci/blob/main/.github/workflows/desplegar-ecs.yml). El funcionamiento del pipeline está en [CI/CD](CI_CD.md); la reversión manual, en el [runbook de incidentes](RUNBOOK_INCIDENTES_STAGING.md).

Todo lo de este documento se obtuvo con consultas de solo lectura (GitHub y AWS), salvo la prueba de reversión, que se indica aparte.

## HT-12 · Despliegue continuo

| Tarea | Criterio | Evidencia |
| --- | --- | --- |
| T030 | Las imágenes se suben a ECR y los servicios de ECS se actualizan solos. | Despliegues correctos de barber [44696408889](https://github.com/KikeGonRam/barber/actions/runs/44696408889) y [44689837044](https://github.com/KikeGonRam/barber/actions/runs/44689837044), y del frontend [45109297766](https://github.com/KikeGonRam/frontend_Urbanblade/actions/runs/45109297766) y [44696363625](https://github.com/KikeGonRam/frontend_Urbanblade/actions/runs/44696363625). En ECR cada despliegue deja `sha-<commit>` y `latest`, más la imagen anterior como `rollback-<fecha>-pre-<sha>`. |
| T031 | Credenciales y secretos del pipeline. | Ninguno de los dos repositorios guarda secretos en GitHub (0 a nivel de repositorio y 0 en el entorno `staging`). La única configuración es la variable `AWS_DEPLOY_ROLE_ARN`: AWS se asume por OIDC con un rol de privilegio mínimo y credenciales temporales por ejecución. No hay llaves de AWS de larga duración. |
| T032 | Despliegue automático a staging tras un build correcto en `main`. | El workflow arranca con `workflow_run` cuando el CI de `main` termina, y la plantilla comprueba que el CI de ese commit exacto terminó en verde y que el origen es un push a `main`. Las 15 ejecuciones más recientes de barber son `workflow_run`, salvo una manual. El entorno `staging` exige la aprobación del propietario (`required_reviewers`) y restringe las ramas que pueden desplegar. |
| T033 | Validación posterior al despliegue. | Tras redesplegar, la plantilla verifica que todas las tareas corren el digest recién subido y que las rutas de salud responden 200; después ejecuta `.github/scripts/humo.sh` (solo lectura). Si algo falla, revierte sola a la imagen `rollback-*`. En los despliegues citados los pasos *Verificar el despliegue* y *Pruebas de humo* terminaron en verde y *Revertir* quedó omitido. |

## HT-14 · Pruebas del pipeline

### T038 · El pipeline bloquea la integración cuando fallan las pruebas o el análisis

La rama `main` de ambos repositorios exige estos checks y no admite excepciones, ni siquiera para el administrador:

| Regla | barber | frontend |
| --- | --- | --- |
| Checks requeridos | 10 (Pint, Larastan, PHPUnit y cobertura; frontend; seguridad; smoke HTTP; builds; documentación; GitGuardian; Semgrep; SonarCloud) | 11 (ESLint y build; Playwright; contrato de API; Cypress x2; documentación; GitGuardian; imagen Docker; Vitest; Semgrep; SonarCloud) |
| Rama al día con `main` | Sí | Sí |
| Conversaciones de revisión resueltas | Sí | Sí |
| Aplica a administradores | Sí | Sí |
| Push forzado o borrado de `main` | Prohibido | Prohibido |

Bloqueos reales registrados en noviembre de 2026:

- **barber#104**, commit `5d00b65`: Larastan falló en el job [Backend](https://github.com/KikeGonRam/barber/actions/runs/44686561311/job/136122163406) y GitHub rechazó la fusión con «the base branch policy prohibits the merge» hasta corregirlo.
- **barber#106**, commit `7328ac0`, y **frontend#163**: SonarCloud marcó duplicación de código nuevo por encima del 3 % y los PR quedaron en estado `BLOCKED` hasta corregirla.
- Los hilos sin resolver de la revisión automática también dejaron PR en `BLOCKED` (regla de conversaciones).

Además, el despliegue no se ejecuta si el CI del commit no terminó en verde: lo impiden la condición del job y el paso *CI en verde del commit exacto* de la plantilla. La protección de rama exige los checks del PR, pero no garantiza que el CI que corre en `main` después de fusionar salga en verde (por ejemplo, si `main` cambió entre la última ejecución del PR y la fusión). Es una observación histórica, no una garantía: desde que existe el workflow de despliegue, `main` no ha tenido un CI en rojo, y si lo tuviera ese commit no se desplegaría.

### T039 · Los secretos no se exponen en los registros

Se descargaron los registros completos de 4 despliegues y del último CI de `main` de cada repositorio, y se buscaron patrones de secretos: llaves de AWS permanentes y temporales, secretos y tokens de sesión de AWS, URIs de MongoDB con credenciales, llaves de Stripe, tokens de GitHub, JWT, llaves privadas, contraseñas en claro y `APP_KEY` de Laravel.

| Registro | Valores enmascarados (`***`) | Secretos encontrados |
| --- | --- | --- |
| barber, despliegue 44696408889 | 35 | Ninguno |
| barber, despliegue 44689837044 | 35 | Ninguno |
| frontend, despliegue 45109297766 | 32 | Ninguno |
| frontend, despliegue 44696363625 | 32 | Ninguno |
| barber, CI 45108946479 | 15 | Solo el `APP_KEY` de pruebas (ver nota) |
| frontend, CI 45108951882 | 14 | Ninguno |

Nota: el `APP_KEY` que aparece en el CI de barber es una llave de pruebas declarada en claro en `.github/workflows/ci.yml` con `APP_ENV: testing`. Ya es pública en el repositorio, así que verla en el registro no es una fuga. Queda por confirmar que staging usa una llave distinta.

Lo que protege los registros: OIDC (no hay secretos guardados que se puedan imprimir) y el enmascarado automático de GitHub Actions para los valores registrados como secretos; este barrido es la comprobación sobre los registros. Gitleaks y Trivy en el CI y GitGuardian en cada PR revisan el **código** (que no se versionen secretos), no los registros de Actions: un valor sensible que un paso imprimiera sin estar registrado como secreto no lo detectarían ellos, sino un barrido como este.

### T040 · Reversión de un despliegue de ECS

**Ejecutada el 23 de noviembre de 2026, de 04:29 a 04:41 UTC, con autorización del propietario**, sobre el frontend (`uba-stg-frontend`, sin estado, una tarea, despliegue con mínimo sano 100 % y máximo 200 %), siguiendo el apartado 5 del [runbook de incidentes](RUNBOOK_INCIDENTES_STAGING.md). Durante cada cambio se consultó `https://urbanblade.com.mx/login` cada 10 segundos.

| Paso | Imagen en la tarea | Tiempo hasta estable | Sitio tras el cambio | Sondeos durante el cambio |
| --- | --- | --- | --- | --- |
| Estado inicial | `sha-2ba5fb0` (`3ef9b1cd…`) | | 200 | |
| 1. Revertir a `rollback-20261123-pre-2ba5fb0` (`sha-146898a`) | `0ff925a2…`: mismo contenido que el respaldo `ccfa56b0…` (mismos manifiestos de plataforma `35e4dad8…` y `d6d13b51…`) | 166 s | 200 | 16 de 16 con 200 |
| 2. Volver a la versión actual | `9428d914…`: mismo contenido que `3ef9b1cd…` (manifiestos `a63dcfc7…` y `562a3466…`) | 158 s | 200 | 16 de 16 con 200 |
| 3. Dejar `latest` en el digest original exacto | `3ef9b1cd…` | 167 s | 200 | 15 de 16 con 200 |

Resultado: **la reversión funciona y no deja el sitio sin servicio**. ECS arranca la tarea nueva antes de retirar la anterior. El único sondeo sin respuesta (04:38:15 UTC) ocurrió antes de registrar la tarea nueva, con la anterior todavía sana y atendiendo, así que se atribuye a la red del equipo de prueba y no al cambio. Staging quedó como estaba al empezar.

Hallazgos de la prueba:

- **El comando de reversión manual del runbook fallaba en PowerShell.** `--output text` devuelve el manifiesto como arreglo de líneas y `aws` recibía `{` como manifiesto. Se corrigió el runbook para pasar el manifiesto como archivo con sus bytes exactos; se comprobó que el SHA-256 del archivo coincide con el digest de la imagen.
- **Si el texto del manifiesto cambia (saltos de línea, formato), ECR crea otro digest para la misma imagen.** Les pasó a los pasos 1 y 2, ejecutados desde Windows. La imagen es la misma, pero `latest` y la tarea dejan de coincidir con la etiqueta `sha-…` o `rollback-…`. El pipeline no tiene este problema: su respaldo `rollback-20261123-pre-fcae844` de barber conserva el mismo digest que `sha-7cdc87e`.
