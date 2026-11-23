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

Además, el despliegue no se ejecuta si el CI del commit no terminó en verde: lo impiden la condición del job y el paso *CI en verde del commit exacto* de la plantilla. Desde que existe el workflow de despliegue, `main` no ha tenido un CI en rojo, porque la protección de rama lo impide antes.

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

Controles que lo previenen: OIDC (no hay secretos que imprimir), enmascarado automático de GitHub Actions, Gitleaks y Trivy en el CI y GitGuardian en cada PR.

### T040 · Reversión de un despliegue de ECS

**Estado: pendiente de ejecutar con autorización del propietario.** Cambia la imagen que corre en staging durante unos minutos.

Procedimiento previsto, sobre el frontend (sin estado y con una sola tarea), siguiendo el apartado 5 del [runbook de incidentes](RUNBOOK_INCIDENTES_STAGING.md):

1. Anotar el digest que corre (`latest` = `sha-2ba5fb0`).
2. Revertir: poner `latest` en el manifiesto de `rollback-20261123-pre-2ba5fb0` (`sha-146898a`), forzar un nuevo despliegue de `uba-stg-frontend` y esperar a que se estabilice.
3. Comprobar que la tarea corre el digest anterior y que el sitio responde 200.
4. Volver a la versión actual por el mismo camino y comprobar de nuevo digest y 200.
