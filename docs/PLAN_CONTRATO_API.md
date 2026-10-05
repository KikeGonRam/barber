# Plan de contrato de API — UrbanBlade

> Generado el 2026-10-02. Corresponde a la prioridad 4 de la revisión del stack:
> *"el contrato de API no se verifica"*.
>
> **Cerrado el 2026-10-02**: el spec se regeneró, coincide 160/160 con las rutas
> reales, y el job de CI ya es bloqueante.
>
> **Actualizado el 2026-10-05** (T094 / HU-27, ver la skill `api-contract-plan`): las
> *formas de respuesta* de auth y citas ya son verdaderas y están atadas a la API real
> por `ApiContractTest`; el frontend genera sus tipos del spec y el CI lo verifica.
> Quedan pendientes la ampliación a más endpoints y los DTO de Android.

## El problema

`barber` expone `routes/api.php` como contrato externo para `frontend-urban` y
`UrbanBladeMobile`. Pero:

1. El OpenAPI versionado en `barber/public/docs/openapi.yaml` **estaba desactualizado**.
2. Nada en CI lo detectaba. Peor: el job E2E de `frontend-urban` **intercepta la API en
   el navegador** (`e2e/support/api-mock.ts`), así que web y móvil podían estar en
   verde mientras el backend cambiaba una ruta o un método.
3. Los modelos de cada cliente están escritos a mano (Kotlin en
   `UrbanBladeMobile/.../data/model/`, tipos de TS en `frontend-urban/app/types/`), y
   nada los ata a la API real.

### Evidencia de la deriva (medida el 2026-10-02)

| Métrica | Valor |
|---|---|
| Rutas reales (`route:list --json`, paths únicos) | **160** |
| Paths en `openapi.yaml` antes de regenerar | **111** |
| Rutas sin documentar | ~49, incluidas recientes como `profile/push-test` y `orders/{order}/receipt-link` |
| Paths en `openapi.yaml` después de regenerar | **160** |

> **Corrección de una cifra propia.** La primera medición de este plan hablaba de
> "194 rutas frente a 111 paths (43% de deriva)". Estaba mal por dos motivos, y ambos
> importan:
>
> 1. **No eran comparables.** Los 194 contaban *declaraciones* en `routes/api.php`
>    (una por método), los 111 contaban *paths* (una ruta con GET y POST cuenta una
>    vez). La comparación honesta es 160 paths reales contra 111 documentados.
> 2. **El validador tenía un falso positivo.** Scribe escribe las rutas con parámetro
>    **entre comillas** en el YAML (porque llevan llaves) y además **renombra los
>    parámetros** (`/appointments/{appointment}` → `/appointments/{appointment_code}`).
>    La primera versión del script no contemplaba ninguna de las dos cosas, así que
>    reportaba como "sin documentar" rutas que sí lo estaban. Se detectó al ver que el
>    script seguía reportando 47 rutas justo después de que `scribe` las documentara.

## Lo aplicado

### 1. `barber/scripts/verificar_contrato_api.mjs`

Compara la API real contra el spec. **No** deduce las rutas leyendo `routes/api.php`
con expresiones regulares: los grupos anidados `Route::prefix('admin')->middleware(…)->group(…)`
hacen ese enfoque frágil. Usa la fuente autoritativa de Laravel:

```bash
php artisan route:list --json > rutas.json
node scripts/verificar_contrato_api.mjs rutas.json public/docs/openapi.yaml
```

Normaliza dos cosas antes de comparar, y sin eso daría falsos positivos en todas las
rutas con parámetro:

- las rutas citadas del YAML (`'  ''/api/v1/…/{param}'':''`);
- el **nombre** del parámetro, comparando la forma (`{cualquiera}` → `{}`).

Reporta, por separado, rutas sin documentar (con sus métodos), paths a los que les falta
un método, y paths del spec que ya no existen como ruta. Sale con código 1 si hay
deriva. Tiene una lista `EXCEPCIONES` comentada para rutas deliberadamente fuera del
contrato; hoy está vacía a propósito: una excepción sin justificar es deriva escondida.

### 2. Job bloqueante en `barber/.github/workflows/ci.yml`

El paso vive en el job `smoke` (que ya levanta la app con Mongo y Redis). Se agregó
primero con `continue-on-error: true` **a propósito**: el spec estaba desactualizado y
un gate que nace en rojo solo enseña al equipo a ignorarlo. Una vez regenerado y
verificado, se quitó: **ahora un cambio de ruta sin documentar rompe el CI.**

## Cómo se cerró

Se regeneró dentro del contenedor, con el **entorno de pruebas** (contra `mongo-test`,
nunca Atlas) — el mismo patrón que usa `barber/test.ps1`:

```bash
docker exec barber-app php artisan config:clear
docker exec barber-app php artisan route:clear
docker exec --env-file .env.testing barber-app php artisan scribe:generate
```

`config:clear` primero es obligatorio: el entrypoint del contenedor cachea la config con
los valores de Atlas al arrancar, y con la caché puesta los `--env-file` no tienen efecto.

Resultado: **160 rutas reales = 160 paths en el spec**, comparador en código 0. El spec
generado se escaneó en busca de credenciales antes de darlo por bueno: limpio.

## Hecho el 2026-10-05: respuestas veraces y tipos generados (web)

El plan original pedía generar tipos del spec, pero al mirar el spec resultó que las
**respuestas estaban inventadas** (ids `integer` donde Mongo da strings, `user.role` donde
el API devuelve `user.roles`, citas planas en vez de `client{}`/`barber{}`/`service{}`):
generar tipos de eso habría dado tipos falsos con aspecto oficial. Se resolvió así:

- **barber** (PR #12): `docs/contrato/*.json` son los ejemplos que Scribe publica
  (`@responseFile`) y los mismos contra los que `tests/Feature/ApiContractTest.php` compara
  la respuesta real (mismo conjunto de claves y tipo JSON). `ContractRequiredFieldsGenerator`
  marca `required` en esas respuestas, porque Scribe nunca lo emite y sin eso todo sale
  opcional. Cubre `auth/login`, `auth/me`, `GET`/`POST /appointments`.
- **frontend-urban** (PR #9): `contract/openapi.yaml` es una copia versionada del spec,
  `app/types/api.d.ts` se genera con `openapi-typescript`, y el job `contract` del CI falla
  si no corresponden (`npm run contract:check`) y avisa si la copia quedó atrás de
  `barber@main` (`npm run contract:drift`). `app/types/contract.ts` corrige los `null`.
- Al tipar salieron divergencias reales: `productos_agregados.total` es string
  (`decimal:2`), el mock del E2E omitía `client`, y `descuento_activo_pct` solo viene en
  `/me`.

**Lección:** un ejemplo con aspecto de secreto (`ub_3f9c...`) disparó GitGuardian y Sonar
S6418; el token de ejemplo es `TOKEN_DE_EJEMPLO`.

## Pendiente

**Móvil (`UrbanBladeMobile`):** `openapi-generator` (generador `kotlin`) o
`openapi-typescript` + conversión. La app ya tiene su contrato en
`data/model/Models.kt`, `AccountModels.kt` y `ChatbotModels.kt`; conviene generar solo
los DTO y no las capas de Retrofit, que ya funcionan. Solo sirve para los endpoints con
ejemplo veraz.

Resto del backlog (más endpoints, `operationId` ilegibles, `vue-tsc`, exclusiones de
Sonar): ver la sección «Fase 4» de `.claude/skills/api-contract-plan/SKILL.md`.

## Orden recomendado

1. ~~Regenerar el spec (`scribe:generate`) y commitearlo.~~ Hecho.
2. ~~Volver bloqueante el job de contrato.~~ Hecho.
3. ~~Agregar un paso de generación de tipos en CI que falle si `app/types/api.d.ts`
   queda desactualizado.~~ Hecho (frontend-urban, `contract:check`).
4. Evaluar el mismo paso para los modelos Kotlin (pendiente).
5. Extender los ejemplos veraces a pagos, pedidos, dashboards, inventario y perfil.
