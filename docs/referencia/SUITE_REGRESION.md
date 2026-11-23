# Suite de regresión

Historia **HT-18 Suite de pruebas de regresión** (Jira SCRUM-191), Sprint 5. La suite no es un programa aparte: son los CI de los tres repositorios, que ya corren en cada PR y en cada fusión a `main`. Desde HT-18 también corren **cada lunes a las 06:00 (hora del centro de México) sobre `main`** y a mano antes de cada entrega, para detectar regresiones que no vienen de un cambio propio (dependencias, imágenes base, servicios externos).

## Qué incluye (T049)

| Repositorio | Flujo | Pruebas |
| --- | --- | --- |
| barber | `ci.yml` | Pint, Larastan, PHPUnit (suites UT, SIT, UAT y OAT, con MongoDB y Redis efímeros) con cobertura mínima de 70 %; smoke HTTP de la API recién migrada; contrato OpenAPI; Gitleaks y auditoría de dependencias |
| barber | `semgrep.yml`, `documentation.yml` | Reglas OWASP Top 10 y enlaces de la documentación |
| frontend-urban | `ci.yml` | ESLint, build de Nuxt, auditoría de dependencias, Vitest, contrato con la API, Playwright con API simulada e imagen Docker |
| frontend-urban | `cypress.yml` | Flujos de Cypress |
| UrbanBladeMobile | `android-ci.yml` | Lint de Android, pruebas unitarias (`testDebugUnitTest`) y compilación debug, release y staging |

Ninguna corrida programada ni manual despliega: `desplegar-staging` solo actúa tras un `push` a `main` (condición en el flujo reutilizable de `urbanblade-ci`).

Lo que la suite **no** cubre: los E2E del frontend usan una API simulada, no esta API; no hay pruebas instrumentadas de Android en un dispositivo; y una suite verde no demuestra permisos de Atlas, respaldos restaurables, cobros reales ni el despliegue de producción (ver [Pruebas](PRUEBAS.md)).

## Cómo correrla a mano

```powershell
gh workflow run ci.yml -R KikeGonRam/barber --ref main
gh workflow run ci.yml -R KikeGonRam/frontend_Urbanblade --ref main
gh workflow run cypress.yml -R KikeGonRam/frontend_Urbanblade --ref main
gh workflow run android-ci.yml -R al140605/UrbanBladeMobile --ref main
```

Los resultados se ven en la pestaña Actions de cada repositorio. Un fallo en la corrida programada se corrige con un PR normal (T051); no se reintenta hasta que pase.

## Resultados (T050)

Última corrida completa sobre `main` antes de cerrar el Sprint 5:

| Repositorio | Commit | Fecha (UTC) | Resultado |
| --- | --- | --- | --- |
| barber | `2c0f4dd` | 23-nov-2026 06:20 | Verde. PHPUnit: 1,042 pruebas y 4,102 aserciones; cobertura de sentencias 76.0 % (10,625 de 13,977) |
| frontend-urban (CI) | `2ba5fb0` | 23-nov-2026 04:01 | Verde. Vitest: 30 archivos; Playwright: 113 pruebas pasadas y 2 omitidas |
| frontend-urban (Cypress) | `2ba5fb0` | 23-nov-2026 03:59 | Verde. 7 pruebas |
| UrbanBladeMobile | `a52e53c` | 19-nov-2026 17:59 | Verde. Lint, pruebas unitarias y las tres compilaciones |

Defectos encontrados por la suite: ninguno en esta corrida. La corrida previa a la entrega final (4 de diciembre) se agrega a esta tabla.
