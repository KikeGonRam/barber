---
name: api-contract-plan
description: "Plan por fases para que el contrato de API de UrbanBlade (barber -> frontend-urban/Android) sea verdadero y verificable: ejemplos de respuesta reales con prueba, tipos TypeScript generados del OpenAPI, mocks E2E tipados y un paso de CI que falla si el contrato diverge. Usar antes de tocar @response/@responseFile de Scribe, docs/contrato/, public/docs/openapi.yaml, app/types/api.d.ts, e2e/support/api-mock.ts o cualquier tipo de respuesta de la API en las paginas Nuxt. Ligado a T094 (HU-27) y T103/T104 (HU-30)."
---

# Contrato de API verificable — plan por fases

Skill gemela en `barber` y `frontend-urban` (mismo nombre, mismo cuerpo). Si cambias una,
cambia la otra. Contexto previo: `barber/docs/PLAN_CONTRATO_API.md` (cerro la deriva de
rutas: 160 = 160) y esta skill cierra la deriva de **formas de respuesta**.

Tarea de origen: **T094** (HU-27, sincronizar y validar la informacion compartida entre
web, movil y base de datos) y la base de **T103/T104** (HU-30). Si un criterio de esas
historias contradice esta skill, ganan los Excel (ver `urbanblade-historias-sprint`).

## El problema (medido el 2026-10-05)

El OpenAPI tenia las **rutas** al dia pero las **respuestas inventadas**: `@response`
escritos a mano que ya no coinciden con `App\Http\Resources\*`.

- `GET /appointments` documentaba `id: 1`, `client_id: 10`, `client_name`... y el Resource
  real devuelve `id` string (ObjectId de Mongo), `code`, y `client{}`, `barber{}`,
  `service{}` anidados.
- `POST /auth/login` documentaba `user.role: "cliente"`; el real es `user.roles: []` mas
  `profile_complete`, `client_id`, `barber_id`...
- Con eso, generar tipos con `openapi-typescript` habria producido tipos **falsos pero con
  aspecto oficial** (ids `number` donde son `string`): peor que no tenerlos.

Ademas: `frontend-urban/app/types/` solo tenia `choice.ts`; cada pagina declaraba su propia
interfaz y `useApi.apiFetch<T>` hace `as T` sin verificar nada; el E2E intercepta la API con
`e2e/support/api-mock.ts`, asi que los mocks podian divergir sin que nadie se enterara.

## Principio

**El ejemplo de respuesta es la fuente; una prueba lo ata a la API real.** Scribe infiere el
schema OpenAPI a partir del ejemplo (string -> `string`, entero -> `integer`), asi que un
ejemplo veraz da un schema veraz, sin inventar un segundo formato.

- Ejemplos en `barber/docs/contrato/<recurso>.<status>.json`, referenciados con
  `@responseFile docs/contrato/<archivo>` (la ruta se resuelve desde la raiz del proyecto).
- `tests/Feature/ApiContractTest.php` llama al endpoint real y compara **estructura** con el
  archivo: mismo conjunto de claves y mismo tipo JSON en cada nivel (`null` en cualquiera
  de los dos lados es comodin, porque `null` no dice nada del tipo). Una clave nueva sin
  documentar, o una documentada que desaparecio, rompe la prueba.
- Los ejemplos usan valores realistas (ids de 24 hex, fechas ISO), nunca datos reales.
- **Nada que parezca un secreto.** Un token inventado de aspecto aleatorio
  (`ub_3f9c1d7e...`) disparo GitGuardian ("5 secrets", se propago a 5 archivos generados)
  y Sonar S6418 (Security Rating E) en el PR #12. El valor del token es literalmente
  `TOKEN_DE_EJEMPLO`. Antes de commitear, mirar los checks de GitGuardian y Sonar, no solo
  los obligatorios.
- **`required`:** Scribe nunca emite `required`, asi que `openapi-typescript` tipa todo como
  opcional. `App\Support\Docs\ContractRequiredFieldsGenerator` (registrado en
  `config/scribe.php`, `openapi.generators`) lo agrega a las respuestas cuyos ejemplos son
  archivos de `docs/contrato/`: es seguro porque la prueba exige el mismo conjunto de
  claves que la respuesta real. Las respuestas escritas a mano quedan sin `required`.
- **`null`:** un ejemplo no dice que campos pueden ser nulos; el schema los da como
  `string`. La capa de tipos del frontend (`app/types/contract.ts`, `Nullable<T, K>`)
  lo corrige con claves comprobadas por el compilador.

## Fases

Estados: ⬜ pendiente · 🔄 en curso · ✅ hecha (con commit y CI en verde).

### Fase 1 — Contrato verdadero en barber (auth + citas) — ✅ (2026-10-05, barber PR #12, merge `975b62a`, CI de `main` verde)

- `docs/contrato/` con ejemplos de `auth/login`, `auth/me`, `appointments` (staff y
  cliente) y `appointments` POST 201.
- `@responseFile` en `AuthController` y `Appointment\AppointmentController`; corregir los
  `@bodyParam` que dicen `int` donde el id es string.
- `ApiContractTest` (estructura real vs ejemplo). Regenerar spec con el procedimiento de
  `PLAN_CONTRATO_API.md` (entorno de pruebas, nunca Atlas). `verificar_contrato_api.mjs` en 0.
- Evidencia local: `.\test.ps1` 721/721, Pint 456 archivos, Larastan en frio limpio,
  contrato de rutas 160 = 160. Hallazgo de la propia prueba: `productos_agregados.total`
  es **string** (`decimal:2`), no number como se habria escrito a mano.

### Fase 2 — Tipos generados y CI en frontend-urban — ✅ (2026-10-05, frontend PR #9, merge `171399d`, CI de `main` verde)

- `openapi-typescript` como devDependency; copia versionada del spec en
  `frontend-urban/contract/openapi.yaml` (el repo es independiente, no puede leer
  `../barber` en CI) y `app/types/api.d.ts` generado de esa copia.
- Scripts `contract:sync` (copia desde `../barber` o desde GitHub raw), `contract:types` y
  `contract:check` (regenera y falla si `git diff` no esta limpio).
- Job `contract` en `.github/workflows/ci.yml`; compara tambien contra el spec de `main` de
  `barber` y avisa (no bloquea) si la copia quedo atras.

### Fase 3 — Frontend usa los tipos; mocks tipados — ✅ (2026-10-05, mismo PR #9 de frontend-urban)

- Alias en `app/types/` (`ApiUser`, `ApiAppointment`...) derivados de `api.d.ts`.
- Migrar primero lo de mayor riesgo: `useAuth.ts`, citas del cliente y del staff.
- `e2e/support/api-mock.ts` y `dashboard-fixtures.ts` tipados con esos mismos alias: un
  mock que no cabe en el contrato no compila.

### Fase 4 — Cierre y ampliacion — ✅ cierre; la ampliacion queda como backlog (abajo)

- Actualizar `PLAN_CONTRATO_API.md` (pasos 3 y 4 del orden recomendado) y esta skill.
- Lista de endpoints pendientes de ejemplo veraz, por prioridad: pagos
  (`payments/*`, `stripe-intent`), pedidos/tienda, dashboards por rol, inventario, perfil.
  Cada uno: archivo en `docs/contrato/` + caso en `ApiContractTest`.
- Android: evaluar el mismo spec para los DTO de `UrbanBladeMobile` (solo modelos, no
  Retrofit).

**Backlog que deja la fase (no hecho):**

1. Mas endpoints con ejemplo veraz (pagos, pedidos, dashboards, inventario, perfil).
   Cada respuesta sin ejemplo atado sigue siendo documentacion inventada: no confiar en
   su tipo generado.
2. `operationId` ilegibles: los docblocks sin titulo generan ids como
   `feedPaginadoDeTrabajosPublicados...` (cientos de caracteres). Dar titulo (primera
   linea del docblock) a cada metodo.
3. `vue-tsc` no esta instalado en `frontend-urban`: 29 errores previos en dashboards,
   analytics y agenda. Con `tsc` solo no se analiza el `<script setup>` de los `.vue`.
4. Sonar en barber: excluir `public/docs` (HTML generado) y `docs/contrato` (ejemplos
   duplicados a proposito) de la duplicacion y la fiabilidad.
5. DTO de Android desde el mismo spec.

## Reglas

1. Contrato publico: cambios **aditivos**. Corregir la documentacion no cambia la API; si
   la prueba revela que la API es la que esta mal, se dice, no se arregla en silencio.
2. Regenerar Scribe solo con el entorno de pruebas (`docker exec --env-file .env.testing`)
   tras `config:clear` y `route:clear`; luego `.\test.ps1` restaura las cachas.
3. Tests siempre con `.\test.ps1`. Pint y Larastan (con cache limpia) antes del push.
4. Flujo de Git: rama por fase, PR, CI en verde, merge commit sin borrar la rama
   (`git-commit-conventions`). Commits en espanol, sin coautoria de IA.
5. Una fase solo pasa a ✅ con evidencia: commit en `main`, prueba que pasa y CI verde.
6. No se despliega: solo cambian documentacion, ejemplos, pruebas y comentarios de
   Scribe (sin comportamiento). Si una fase toca comportamiento de la API, aplicar la
   seccion de despliegue de `git-commit-conventions`.
