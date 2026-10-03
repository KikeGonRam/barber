# Sandbox de verificación — UrbanBlade

Un solo comando para verificar los cuatro proyectos **antes de subir a producción**:

```powershell
cd C:\Users\luis1\Documents\UrbanBlade\barber\scripts\verificacion
.\verificar.ps1
```

Vive dentro de `barber/scripts/verificacion/` para quedar **versionado con git** — la
carpeta contenedora de UrbanBlade no es un repositorio, así que ahí no tendría respaldo.

Los scripts localizan la raíz del workspace **subiendo desde su propia ubicación**
(ver `lib/comun.ps1`), de modo que siguen funcionando si se mueven de sitio. Se puede
forzar con la variable de entorno `UB_RAIZ`, útil para probar la puerta contra una
configuración simulada.

## Por qué existe

Los cuatro repos tienen CI en GitHub Actions, pero eso verifica **lo que ya se subió**.
Este sandbox verifica **antes de subirlo**, incluye comprobaciones que el CI no hace
(como que la configuración de pruebas no apunte a datos reales), y funciona sin
depender de que GitHub esté disponible.

Y hay una razón concreta: el **2026-08-28** la suite de `barber` corrió contra la base
compartida de Atlas y su `tearDown()` borró clientes y citas reales. No había respaldo
recuperable. La primera cosa que hace este sandbox es comprobar que eso no pueda
volver a pasar.

## Cómo se usa

```powershell
.\verificar.ps1                     # todo
.\verificar.ps1 -Rapido             # sin build de producción ni e2e (segundos, no minutos)
.\verificar.ps1 -Proyecto spark     # solo un grupo
.\verificar.ps1 -Proyecto frontend,spark
.\verificar.ps1 -Python "python"    # intérprete para spark (ver más abajo)

.\puerta-seguridad.ps1              # solo la puerta de seguridad, por separado
```

Sale con **código 1** si algo falla, y con **0** si todo lo ejecutado pasó (aunque
queden partes omitidas).

## La puerta de seguridad

Se ejecuta siempre, antes que cualquier prueba, y **puede abortar todo**. No modifica
nada: solo lee archivos de configuración y el estado de git.

| Comprueba | Por qué |
|---|---|
| Ningún `.env`, llave o `google-services.json` **versionado** | Una credencial en git es una credencial filtrada para siempre |
| `barber/.env.testing` apunta a destino **local** y a una base `*_test` | Es literalmente el escenario del incidente del 2026-08-28 |
| `spark`: `MONGO_DB` ≠ `ANALYTICS_MONGO_DB` | Si se igualan, se rompe la separación lectura/escritura |
| `spark`: `ANALYTICS_E2E_URI` / `CORE_E2E_URI` no apuntan a Atlas | Las pruebas de integración **escriben** |
| `frontend-urban`: la API configurada no es producción | Aviso: una comprobación manual golpearía el entorno real |
| Cambios sin commitear y rama distinta de `main` | Lo que verificas no es lo que vas a subir |

Los bloqueos se muestran en rojo y **detienen la ejecución**. Las advertencias
(amarillo) dejan seguir, pero hay que leerlas.

## Qué verifica cada grupo

| Grupo | Chequeos | ¿Corre en cualquier máquina? |
|---|---|---|
| `seguridad` | Credenciales versionadas + llaves en el contenido, en los 4 repos | Sí |
| `documentacion` | Todos los enlaces relativos de READMEs y documentos resuelven | Sí |
| `barber` | PHPUnit completo (104 archivos) **vía `.\test.ps1`**, y contrato OpenAPI contra las rutas reales | No — requiere Docker con `barber-app` arriba |
| `frontend` | ESLint `--max-warnings=0`, tipos de TypeScript (bloqueante), build de producción, Playwright (58 pruebas, incluidas las de seguridad) | Sí, si están las dependencias npm |
| `spark` | pytest (incluye la guarda de solo lectura) + sintaxis de 45+ scripts | Sí, con Python y `pymongo`/`pytest` |
| `mobile` | Android lint, 40 pruebas JVM, build de debug | No — requiere Android SDK + Gradle 8.13 |

### Los omitidos no son aprobados

Si falta Docker, Gradle o el SDK, el chequeo sale como **OMITIDO** con el motivo y el
comando exacto que hay que correr. El resumen lo repite:

> Un omitido NO es un aprobado: significa que esa parte sigue sin verificar.

Esto es deliberado. Un sandbox que dice "todo bien" cuando en realidad no ejecutó la
mitad de las pruebas es peor que no tener sandbox.

## Cómo habilitar lo que falta

**barber (104 archivos de PHPUnit).** Con Docker Desktop abierto y el `.env` real:

> ⚠️ **Cuidado al levantar el contenedor.** El contenedor recibe `barber/.env`, que
> apunta a **Atlas**. Desde el 2026-10-02 `.docker/entrypoint.sh` ya **no** migra solo al
> arrancar (solo con `RUN_MIGRATIONS=true`; requiere reconstruir la imagen con
> `docker compose up -d --build` para tomar el cambio), pero `php artisan optimize` sí
> corre y cachea la configuración real. Este sandbox **no lo levanta por ti**: si
> `barber-app` no está corriendo, lo reporta como omitido y te dice el comando, para que
> la decisión sea tuya.

```powershell
# Levanta el backend (desde la raíz de barber, o sea ..\.. desde aquí)
cd ..\..
docker compose up -d

# Y luego verifica (desde esta carpeta)
cd scripts\verificacion
.\verificar.ps1 -Proyecto barber
```

El sandbox invoca `barber\test.ps1`, que fuerza `.env.testing` (base `barber_db_test`
en el Mongo local). **Nunca** corras `php artisan test` a mano: el contenedor tiene las
credenciales de Atlas horneadas como variables de entorno y la suite terminaría
borrando datos reales. La historia completa está en `barber/test.ps1`.

**UrbanBladeMobile.** Instala el Android SDK (Android Studio) y Gradle 8.13. El repo
**no versiona `gradlew`** a propósito: el CI usa la misma versión declarada en
`gradle-wrapper.properties`. Luego:

```powershell
.\verificar.ps1 -Proyecto mobile
```

**spark.** Si usas el entorno del curso (conda dentro de WSL), activa el entorno antes
o pásale el intérprete:

```powershell
.\verificar.ps1 -Proyecto spark -Python "python"
```

## Inestabilidad conocida

El **2026-10-02** la suite e2e falló 1 de 55 con el paralelismo por defecto
(`fullyParallel`, ~CPU/2 workers): `auth.spec.ts:38` se pasó del timeout de 5 s de
`toHaveURL` por contención de recursos. Caracterizado después:

| Medición | Resultado |
|---|---|
| Suite completa, workers por defecto | 55/55 una vez; 54/55 otra |
| `auth.spec.ts` ×3 (`--repeat-each=3`), en solitario | **24/24 pasan**; el test sospechoso tarda ~2.5 s |
| Mock de SSR (`e2e/support/mock-api.mjs`) | sin estado: descarta interferencia entre pruebas |

Por eso el sandbox ejecuta e2e con **`--workers=1`**, igual que el CI
(`playwright.config.ts` usa `workers: 1` y `retries: 2` en CI). Local no se añaden
reintentos a propósito: reintentar esconde la inestabilidad, y esta puerta existe justo
para detectarla.

Quedaba pendiente endurecer ese test. **Ya está hecho**: `playwright.config.ts` fija
`expect: { timeout: 10_000 }` en vez del default de 5 s, con la medición como
justificación. Es holgura para la aserción, no un parche: si la app de verdad tarda 10 s
en redirigir, sigue fallando.

## Tipos de TypeScript (bloqueante)

El **2026-10-02**, añadiendo este sandbox, `tsc` encontró **3 errores de tipo en 2
archivos** de `frontend-urban`:

```
app/composables/useApi.ts
app/composables/usePush.ts
```

Sobre un árbol que pasaba `eslint`, el build de producción **y** las 58 pruebas e2e. La
razón: **el build de Nuxt no verifica tipos**, así que nada los había comprobado nunca.

Primero se reportó como AVISO (eran preexistentes, y una puerta siempre en rojo se
ignora). **Los 3 errores ya están corregidos**, así que ahora el chequeo es
**bloqueante**: un error nuevo es una regresión de verdad.

Las correcciones fueron solo de tipos, sin cambio de comportamiento (una aserción de
tipo se borra al compilar):

| Archivo | Causa | Arreglo |
|---|---|---|
| `useApi.ts` | `$fetch<T>` devuelve un condicional que no se reduce a `T` con `T` genérico | `as T` en el retorno |
| `useApi.ts` | Nuxt no admite `useFetch<T>` con `T` sin restringir (espera `T extends void ? unknown : T`) | se deja inferir y se asegura el retorno |
| `usePush.ts` | desde TS 5.7 `Uint8Array` es genérico y `BufferSource` exige `ArrayBuffer` | tipo de retorno `Uint8Array<ArrayBuffer>` |

Sigue siendo un chequeo **parcial**: sin `vue-tsc` (no instalado, y no se puede instalar
sin red) los archivos `.vue` no se revisan, solo el TypeScript suelto. Para completarlo:
instalar `vue-tsc` y añadir `"typecheck": "nuxt typecheck"` al `package.json`.

## Qué NO cubre este sandbox

Conviene saberlo, para no confiar de más:

- **El contrato entre el backend y sus clientes no se prueba contra el backend real.**
  Desde el 2026-10-02 el sandbox **sí** verifica que el OpenAPI describa las rutas reales
  (160/160) — eso detecta una ruta nueva sin documentar. Pero lo que **no** se prueba es
  que Laravel responda lo que el frontend espera: las 58 pruebas e2e del frontend
  **interceptan la API** en el navegador (`e2e/support/api-mock.ts`), así que verifican el
  frontend, no el contrato end-to-end. Está documentado en
  `barber/docs/PLAN_CONTRATO_API.md`.
- **No hay pruebas instrumentadas de Android** (0 archivos en `app/src/androidTest/`):
  lo que se verifica es lint + pruebas JVM + que compile.
- **No reemplaza al CI.** El CI corre además `pint`, Larastan, `composer audit`,
  CodeQL y Trivy, y publica la imagen Docker. Este sandbox es la puerta **local**,
  complementaria.
- **No hay pruebas de carga ni de rendimiento.**
- ⚠️ **El limitador de intentos del login NO es verificable con este entorno, y la
  medición sugiere que ahí no funciona.** Medido el 2026-10-02 contra el API levantado
  con `.env.testing`: la respuesta trae `X-RateLimit-Limit: 5` y
  `X-RateLimit-Remaining: 4`, pero ese `Remaining` **se queda clavado en 4** en 7
  intentos consecutivos de login con contraseña incorrecta, y nunca aparece un 429.

  La causa más probable es que `.env.testing` usa `CACHE_STORE=array`: con una caché
  por proceso, el contador del limitador no sobrevive entre peticiones. El `.env` real
  usa `redis`, que sí lo haría, pero **no pude confirmarlo**: forzar `CACHE_STORE=redis`
  en el servidor de pruebas no dio un resultado concluyente.

  **Por qué importa aunque sea un artefacto de pruebas:** si por cualquier motivo
  (config mal puesta, Redis caído) la aplicación cae al store `array`, la protección
  contra fuerza bruta desaparece **en silencio** — y peor, el API sigue anunciando
  `X-RateLimit-Limit: 5`, que da apariencia de estar protegido. Vale la pena comprobarlo
  una vez en un entorno con Redis de verdad, con el mismo sondeo de 8 intentos.

## Añadir un chequeo

Los chequeos viven en `verificar.ps1`, una función por grupo (`Verificar-Spark`,
`Verificar-Frontend`, …). Para añadir uno:

```powershell
Ejecutar-Chequeo -Grupo 'spark' -Chequeo 'mi chequeo nuevo' `
    -Directorio $dir -Comando $Python -Argumentos @('-m', 'loquesea')
```

`Ejecutar-Chequeo` mide el tiempo, captura la salida, y decide OK/FALLO por el código
de salida (mostrando las últimas 12 líneas solo si falla). Si el comando no existe en
la máquina, marca **OMITIDO** en vez de fallar.

## Herramientas de las que depende

| Comprobación | Necesita |
|---|---|
| `seguridad`, `documentacion` | Solo PowerShell y git |
| `frontend` | Node + `node_modules` instalados (`npm install --legacy-peer-deps`) |
| `spark` | Python con `pytest` y `pymongo` |
| `barber` | Docker Desktop + contenedor `barber-app` |
| `mobile` | Android SDK + Gradle 8.13 |

Todo lo que no esté, el sandbox lo detecta solo y lo reporta como omitido.
