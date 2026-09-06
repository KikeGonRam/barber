---
name: inertia-vue-migration
description: >
  RETIRED 2026-09-06 — historical record only. Documents the Inertia.js+Vue 3
  migration of the appointments-calendar and 4 role dashboards, which was fully
  removed from this repo once Nuxt (frontend-urban) reached confirmed functional
  parity with both. Nothing under `resources/js/Pages/`, `resources/js/inertia.js`,
  `app/Http/Middleware/HandleInertiaRequests.php`, or the Inertia/Vue packages
  exists anymore — do not follow this skill's "before touching X" instructions.
  The rest of the site is, and remains, Blade+Alpine.
---

# Migración Blade+Alpine → Inertia.js+Vue 3

> **RETIRADO — 2026-09-06.** Nuxt (`frontend-urban`) alcanzó paridad funcional
> confirmada con las 2 páginas que este documento describe (los 4 dashboards
> por rol y el calendario de citas), y el dueño del proyecto confirmó
> explícitamente retirar las páginas Inertia. Se eliminaron: todos los
> `.vue` bajo `resources/js/Pages|Layouts|Components`, `resources/js/inertia.js`,
> `resources/js/chart-theme.js`, `resources/views/app.blade.php`,
> `config/inertia.php`, `app/Http/Middleware/HandleInertiaRequests.php`,
> `App\Http\Controllers\Dashboard\DashboardController` (100% Inertia, sin
> lógica reutilizada en otro lado), `calendar()`/`calendarData()` de
> `App\Http\Controllers\Appointment\AppointmentController` (el resto del
> controlador sigue vivo, es Blade), los paquetes `inertiajs/inertia-laravel`
> + `tightenco/ziggy` (composer) y `@inertiajs/vue3` + `vue` +
> `@vitejs/plugin-vue` + `ziggy-js` + `@fullcalendar/*` + `vue-chartjs` +
> `eslint-plugin-vue` + `vue-eslint-parser` (npm), y los 4 tests
> `tests/Feature/Dashboard*InertiaTest.php`. Las rutas `dashboard` y
> `appointments.calendar` en `routes/web.php` ahora son redirects simples a
> `config('app.frontend_url')` (env `FRONTEND_URL`, default
> `http://localhost:3000`) — los links de navegación Blade que ya apuntaban
> a `route('dashboard')`/`route('appointments.calendar')` en todo el resto
> del sitio (que sigue siendo Blade+Alpine, sin cambios) siguen funcionando
> sin editarlos, porque solo cambió a dónde redirige la ruta, no su nombre.
> Este documento queda como registro histórico de cómo se construyó esa
> migración — ya no aplica ninguna de sus instrucciones de "antes de tocar
> X" porque X ya no existe en este repo.

## Por qué existe esto

El proyecto tenía una landing pública, dashboards (Admin/Recepción/Barbero/Cliente),
calendario de citas (FullCalendar) y analítica (Chart.js) construidos con Blade + Alpine.js
+ jQuery-style DOM manipulation embebida en `<script>` tags dentro de las vistas. El dueño
del proyecto pidió migrar a un framework reactivo moderno usando Inertia.js como puente,
sin perder Laravel como backend ni tocar MongoDB.

**Migración completa y mergeada a `main` el 2026-09-05.** Las 7 fases (infraestructura,
`AppLayout.vue`, calendario de citas, y los 4 dashboards por rol) se hicieron en la rama
`feature/inertia-vue-migration`, cada una verificada en verde (Pint, phpstan,
`.\test.ps1`, `npm run build`, eslint, `npm audit --audit-level=high`, y una verificación
visual en navegador con las credenciales reales de cada rol) antes de pasar a la
siguiente. La rama ya se borró (local y remota) — todo el trabajo vive en `main`. Este
documento se conserva como referencia del patrón a seguir si se migra otra página Blade
en el futuro (el resto del sitio sigue siendo Blade+Alpine).

## Decisión de stack: Vue 3, no React

Ver [[laravel-13-reference]] para contexto general del framework backend. La decisión
de Vue sobre React (tomada 2026-09-05, no revertir sin volver a analizar):

- El equipo ya usa **Alpine.js** en todo el sitio (`x-data`, `x-if`, `x-model`,
  plantillas HTML con directivas). Vue 3 (SFC, `v-if`, `v-model`) es el mismo modelo
  mental, solo más estructurado — transferencia de conocimiento directa. React exige un
  salto conceptual mayor (JSX, sin directivas de plantilla) sin ninguna ventaja concreta
  aquí.
- **FullCalendar** (`@fullcalendar/vue3`) y **Chart.js** (`vue-chartjs`) tienen soporte
  oficial de primera clase en Vue, exactamente al mismo nivel que sus equivalentes React
  (`@fullcalendar/react`, `react-chartjs-2`) — este factor NO inclinó la decisión, quedó
  en empate.
- El futuro app móvil nativo (Android Studio/Kotlin, ver guardrails skill) hace
  irrelevante el argumento de "React Native reutilizaría código" — no aplica.
- MongoDB/Eloquent no se ven afectados en absoluto: Inertia es una capa de presentación.
  Los controladores siguen usando los mismos Services (`AppointmentService`,
  `LoyaltyService`, etc.) — cero lógica de negocio en componentes Vue.

## Estrategia: migración incremental, coexistencia total

**Nunca hay un "big bang".** Blade+Alpine (`resources/js/app.js`) y Vue+Inertia
(`resources/js/inertia.js`) son dos bundles de Vite completamente separados que coexisten
indefinidamente durante la migración:

- Las páginas NO migradas siguen usando `x-app-layout`, `view(...)` normal, y cargan
  `app.js` — no se tocan hasta que les llegue su turno explícito.
- Solo las rutas cuyo controlador se cambió a `Inertia::render(...)` usan el nuevo
  `resources/views/app.blade.php` (root template de Inertia) y cargan `inertia.js`.
- Esto permite mergear cada fase intermedia sin romper nada, y hace trivial revertir una
  página migrada si algo sale mal (solo revertir ese controlador + borrar el `.vue`).

## Checklist de verificación (correr antes de CADA commit en esta rama)

Igual que en `main` (ver [[urbanblade-guardrails]]), más los pasos de frontend nuevo:

```bash
docker exec barber-app ./vendor/bin/pint --test
docker exec barber-app ./vendor/bin/phpstan analyse --memory-limit=1G   # SIN scope, completo
```
```powershell
.\test.ps1   # NUNCA `php artisan test` directo — ver urbanblade-guardrails
```
```bash
npm run build                                    # confirma que AMBOS bundles compilan
npx eslint <archivos .js/.vue tocados> --fix      # el proyecto usa 2 espacios, no 4
npm audit --audit-level=high                      # el job "frontend" de CI lo exige
```

Y una verificación visual en el navegador (Browser pane) de que las páginas NO migradas
siguen funcionando igual — el riesgo real de esta migración es romper algo que Vite
recompila globalmente (Tailwind `content`, `vite.config.js`) aunque el cambio "lógico"
esté en un bundle separado.

## Estado de las fases

### ✅ Fase 1 — Infraestructura base (completa, 2026-09-05)

Sin páginas migradas todavía — solo se instaló y verificó que la infraestructura conviva
con lo existente sin romper nada.

**Backend (`docker exec barber-app composer require ...`):**
- `inertiajs/inertia-laravel` (^3.3)
- `tightenco/ziggy` (^2.6) — expone `route()` en JS/Vue con los mismos nombres que ya usa
  Blade (`appointments.calendar.data`, etc.), para no reescribir esa lógica.
- `app/Http/Middleware/HandleInertiaRequests.php` generado vía
  `php artisan inertia:middleware`, registrado en `bootstrap/app.php` dentro del grupo
  `web` (`$middleware->web(append: [...])`). Transparente para requests no-Inertia — no
  afecta ninguna ruta existente.

**Frontend (`npm install`, en el HOST, no en el contenedor — mismo patrón que `gsap`):**
- `@inertiajs/vue3`, `vue`, `@vitejs/plugin-vue`, `ziggy-js`
- `@fullcalendar/core`, `@fullcalendar/vue3`, `@fullcalendar/daygrid`,
  `@fullcalendar/timegrid`, `@fullcalendar/list`, `@fullcalendar/interaction`
- `vue-chartjs`

**Archivos nuevos:**
- `resources/js/inertia.js` — entry point de Vite SEPARADO de `app.js`. Monta la app Vue
  con `createInertiaApp`, resuelve páginas desde `resources/js/Pages/**/*.vue` (carpeta
  aún no existe — se crea en la Fase 3 con la primera página real), usa `ZiggyVue`.
- `resources/views/app.blade.php` — root template de Inertia (`@inertia`,
  `@inertiaHead`, `@routes`, `@safeVite(['resources/css/app.css',
  'resources/js/inertia.js'])`). Paralelo a `resources/views/layouts/app.blade.php`
  (el layout Blade/Alpine, que sigue siendo el root de todo lo no migrado).

**Archivos modificados (compatibles hacia atrás, verificado):**
- `vite.config.js` — añade el plugin `vue()` y `resources/js/inertia.js` como segundo
  entry point junto al `app.js` existente (que no cambió).
- `tailwind.config.js` — añade `./resources/js/**/*.vue` a `content` para que Tailwind
  escanee clases usadas en componentes Vue. No quita nada de lo existente.
- `bootstrap/app.php` — una línea (`HandleInertiaRequests::class`) en el grupo `web`.
- `composer.json`/`composer.lock`, `package.json`/`package-lock.json` — nuevas
  dependencias, nada removido.

**Verificado:**
- `npm run build` compila ambos bundles sin conflicto (`app-*.js` ~327KB sin cambios de
  comportamiento, `inertia-*.js` ~186KB nuevo, `bootstrap-*.js` compartido).
- Homepage pública revisada en navegador tras el cambio — sin errores de consola,
  render idéntico al de antes de esta fase.
- Pint (334 archivos), phpstan (0 errores), `.\test.ps1` (201 tests) y
  `npm audit --audit-level=high` (0 vulnerabilidades) — todos en verde.

### ✅ Fase 2 — `AppLayout.vue` (completa, 2026-09-05)

**Decisión de diseño deliberada (leer antes de tocar el shell):** el sidebar/topbar
móvil/bottom-nav/widgets globales (`<x-toast/>`, `<x-command-palette/>`, `<x-chatbot/>`,
`<x-notification-toaster/>`) **NO se reescribieron en Vue**. Se quedan como el mismo
Blade+Alpine de siempre (`@include('layouts.navigation')`, mismo helper
`NavigationMenu`), ahora también incluidos en `resources/views/app.blade.php` (el root
template de Inertia) — literalmente el mismo HTML/Alpine que
`resources/views/layouts/app.blade.php` usa para las páginas no migradas. Razón: ese
sidebar tiene bastante estado no trivial (rail colapsable persistido en localStorage,
acordeón de secciones con localStorage, 3 breakpoints responsive, store de notificaciones
en tiempo real) — reimplementarlo en Vue ahora es puro riesgo sin beneficio funcional. Se
puede revisar esta decisión más adelante si el dueño del proyecto lo pide explícitamente,
pero no asumir que "migrar a Vue" implica reescribir esto sin que lo pida.

Lo que SÍ es Vue: solo la porción de header-card + main que antes proveía
`<x-app-layout>` (la parte que varía por página). Eso vive en
`resources/js/Layouts/AppLayout.vue`, y cada página Inertia se envuelve en él —
exactamente como las páginas Blade se envuelven en `<x-app-layout>`.

**Cambios de esta fase:**
- `resources/views/app.blade.php` — reescrito para incluir el shell completo (topbar
  móvil, `@include('layouts.navigation')`, drawer backdrop, bottom-nav, los 4 widgets
  globales, script de confetti). El `@isset($header)<header>...<main>{{ $slot }}</main>`
  de la versión Blade se reemplaza por un solo `@inertia` (Vue monta ahí).
- `resources/js/Layouts/AppLayout.vue` — NUEVO. Slot `#header` (opcional, como
  `@isset($header)`) + slot default para el contenido. Además hace de puente de
  notificaciones: escucha `page.props.flash.status`/`.error` (compartidos por
  `HandleInertiaRequests::share()`) y re-emite el mismo evento
  `window.dispatchEvent(new CustomEvent('notify', ...))` que ya escucha `<x-toast/>` —
  así el toast Alpine sigue funcionando sin reimplementarlo, incluso en navegaciones
  internas de Inertia donde `app.blade.php` no se vuelve a renderizar.
- `app/Http/Middleware/HandleInertiaRequests.php` — añade la prop compartida `flash`
  (`status`/`error` desde la sesión) que consume el bridge de arriba.
- `resources/js/inertia.js` — ahora también inicializa Alpine.js (`Alpine.start()`),
  porque el shell y los widgets que quedaron en Blade lo necesitan para funcionar en las
  páginas Inertia.
- `vite.config.js` — alias `@` → `resources/js` (convención estándar de
  Inertia/Breeze), para que las páginas importen `AppLayout.vue` como
  `import AppLayout from '@/Layouts/AppLayout.vue'`.
- `eslint.config.js` — se añadió soporte real para `.vue` (`eslint-plugin-vue` +
  `vue-eslint-parser`). Antes de esta fase, `npx eslint resources/js` (el comando exacto
  que corre CI) **ignoraba silenciosamente cualquier archivo `.vue`** — un hueco real que
  se cerró antes de que hubiera más código Vue que lintear. `.lintstagedrc` también se
  actualizó (`*.{js,jsx,ts,tsx,vue}`) para que el hook de pre-commit cubra `.vue` también.

**Verificado:** `npm run build` compila los 3 bundles sin conflicto; se creó una página
Inertia temporal (`Pages/Dev/LayoutSmoke.vue` + una ruta ad-hoc) SOLO para probar en el
navegador — confirmé visualmente sidebar, topbar móvil, bottom-nav y header-card/main
todos con el estilo correcto, en desktop y en viewport mobile (375px), sin errores de
consola — y se borraron ambas antes de commitear (no quedan en el repo). El dashboard
Blade clásico (`/dashboard`) se verificó después de borrar el smoke test, para confirmar
que tocar infraestructura compartida (`vite.config.js`, `tailwind.config.js`,
`eslint.config.js`) no rompió nada de lo existente. Pint (334 archivos), phpstan
(0 errores), `.\test.ps1` (201 tests), `npx eslint resources/js --max-warnings=0` (el
comando exacto de CI, 0 problemas) y `npm audit --audit-level=high` (0 vulnerabilidades)
— todos en verde.

### ✅ Fase 3 — Primera página real: Calendario de Citas (completa, 2026-09-05)

`AppointmentController::calendar()` ahora devuelve
`Inertia::render('Appointments/Calendar', ['barbers' => ...])` en vez de `view(...)`.
`calendarData()` se dejó **exactamente igual** — sigue siendo un endpoint JSON plano, no
se convirtió en prop de Inertia. Razones: mínimo riesgo (si el render Vue falla, la fuente
de datos no se ve afectada), y consistencia con `routes/api.php` como contrato estable
(ver guardrails) — es el mismo tipo de endpoint que eventualmente podría reutilizar la
futura app Android.

`resources/js/Pages/Appointments/Calendar.vue` reemplaza el `<script>` vanilla de la
vista Blade (manipulación directa del DOM para el modal de detalle) por estado reactivo
de Vue (`reactive(modal)`), con la MISMA lógica de negocio: mismo fetch a
`calendarData()`, mismos colores por estado, mismo comportamiento de filtro por barbero.
Usa `route()` importado directamente de `ziggy-js` (no como global property — más
robusto en `<script setup>`) y se envuelve en `AppLayout.vue` vía
`import AppLayout from '@/Layouts/AppLayout.vue'`.

La vista Blade vieja (`resources/views/appointments/calendar.blade.php`) se **borró** —
quedó huérfana en cuanto el controlador dejó de referenciarla (confirmado con
`grep -rn "view('appointments.calendar')" app/` antes de borrar). Norma para las
próximas fases: cuando una página se migra, su Blade vieja se borra en el mismo commit,
no se deja "por si acaso" — evita que dos fuentes de verdad para la misma página
diverjan silenciosamente.

**Paquetes de FullCalendar instalados:**
`@fullcalendar/core @fullcalendar/vue3 @fullcalendar/daygrid @fullcalendar/timegrid
@fullcalendar/list @fullcalendar/interaction`, todos fijados a la versión exacta
`6.1.21` (sin `^`) — ver gotcha abajo sobre por qué es exacta y no un rango.

**Regla que ya mordió en la Fase 3 — leer antes de escribir cualquier `<Link>`:** un
`<Link>` de Inertia que apunta a una ruta que TODAVÍA es Blade (no migrada) no navega —
Inertia detecta que la respuesta no trae el header `X-Inertia` y muestra su modal de
depuración "unexpected response" (un iframe con el HTML crudo) en vez de la página. Toda
página migrada en esta fase temprana de la migración enlaza inevitablemente a páginas
hermanas TODAVÍA no migradas (el calendario enlaza a índice/crear/editar de citas, por
ejemplo) — esos enlaces deben ser `<a href="...">` normales, NUNCA `<Link>`, hasta que el
destino también esté migrado a Inertia. Revisar esto en cada página nueva: ¿el destino de
este enlace ya es `Inertia::render()`? Si no, `<a>`, no `<Link>`.

### ✅ Fase 4 — Dashboard de Recepcionista (completa, 2026-09-05)

`DashboardController::index()` es un solo método que arma un dashboard distinto por rol
(4 ramas: administrador/barbero/recepcionista/cliente, todas antes renderizadas por
`resources/views/dashboard.blade.php`, 1472 líneas). Dado el tamaño, el dueño del proyecto
confirmó dividir la migración por rol en vez de todo junto — esta fase migró SOLO la rama
`recepcionista`; las otras 3 siguen devolviendo `view('dashboard', [...])` sin cambios,
hasta su propia fase futura. `index()` ahora tiene tipo de retorno `View|InertiaResponse`.

**Hallazgo que amplió el alcance de esta fase:** las 4 ramas del dashboard usan
`<x-analytics-insights>`, un componente Blade compartido de 335 líneas (matrices, mapas
de calor, listas de factores, canvases de Chart.js con config JSON dinámica). El dueño
del proyecto confirmó portarlo a Vue una sola vez ahora (no diferirlo), ya que las
próximas 3 fases lo van a reusar tal cual. Pero un análisis del propio Blade reveló que
**las 4 llamadas dentro de `dashboard.blade.php` invocan el componente SIN `showCharts`**
(el prop que activa matriz/heatmap/canvas) — solo `resources/views/analytics/index.blade.php`
(página NO migrada, fuera de alcance) lo activa. Eso significa que
`resources/js/Components/AnalyticsInsights.vue` **solo necesitaba portar la rama simple**
(título + dato + badge + barra de progreso/puntos + `<details>` "Ver hallazgo") — la
rama con gráficas NO se portó (se documenta como extensión futura si `/analytics` se
migra algún día, no se reimplementó especulativamente).

Para no duplicar reglas de negocio (truncado a 34 palabras, extracción de porcentaje,
mapeo tipo→etiqueta visual) en JS, se centralizaron en PHP:
`App\Models\AnalyticsInsight::toDashboardCardArray()` (nuevo método) devuelve un array
plano ya listo para pintar — Vue solo pinta, no decide. Cubierto por
`tests/Unit/AnalyticsInsightTest.php` (7 casos: color por defecto, mapeo de
`visual_label`, truncado corto/largo, extracción de porcentaje con/sin match).

**Otros archivos nuevos:**
- `resources/js/Components/AnalyticsCta.vue` — puerto trivial de `analytics-cta.blade.php`.
- `resources/js/chart-theme.js` — registra Chart.js UNA vez y exporta los defaults
  compartidos (fuente, tooltip, `chartScale`, paleta `UB_CATEGORICAL`, `fmtMoney`/`fmtInt`)
  que hoy vive duplicado inline en el `<script>` de `dashboard.blade.php` — las próximas
  fases (admin/barbero/cliente) importan esto en vez de reescribirlo.
- `resources/js/Pages/Dashboard/Recepcion.vue` — usa `vue-chartjs` (`<Line>`) para el
  "Flujo Operativo" (antes `Chart.js` vía CDN + `makeChart()` inline en Blade).

**Prop compartida nueva:** `HandleInertiaRequests::share()` ahora incluye
`'auth' => ['user' => $request->user()?->only(['id','name','email'])]` (patrón estándar
de Breeze/Inertia) — necesaria para el saludo "Hola, {nombre}"; cualquier página futura
puede leerla vía `usePage().props.auth.user` en vez de pasarla de nuevo como prop propia.

**Datos de `Appointment`/`Order` mapeados a arrays planos en el controlador** (mismo
patrón que `barbers` en la Fase 3) en vez de pasar los Models/Collections de Eloquent
directo a Inertia — mantiene consistencia con cómo `calendarData()` ya serializa MongoDB
a JSON en este repo, y evita sorpresas de serialización de ObjectId.

**Verificado:** con las credenciales reales de recepcionista (`docs/ACCESOS.md`) contra
la Atlas real (no hay forma de probar esto contra datos falsos sin sembrar la BD
compartida, que las guías de este repo prohíben) — sidebar, saludo con nombre real
("Hola, Valeria"), 6 KPIs, CTA de analítica, y los 3 estados vacíos (sin llegadas/sin
flujo/sin pedidos, reales ya que la BD fue limpiada 2026-09-04) se ven correctos, consola
limpia en pestaña nueva. La sección "Prioridades del turno" no se pudo ver CON datos
reales (0 documentos en `analytics_insights`, pipeline de Spark pausado) — cubierto en su
lugar por el test unitario dedicado. Pint (336 archivos), phpstan (0 errores, baseline
regenerado wholesale — ver gotcha abajo), `.\test.ps1` (209 tests, +8 nuevos), eslint
(0 warnings) y `npm audit` — todos en verde.

### ✅ Fase 5 — Dashboard de Barbero (completa, 2026-09-05)

Mismo patrón de la Fase 4: `DashboardController::index()` devuelve
`Inertia::render('Dashboard/Barbero', [...])` solo para la rama `barbero`;
administrador/cliente siguen en Blade.

**Bug retroactivo encontrado y corregido:** la Fase 4 se lanzó SIN el header compartido
que las 4 ramas de `dashboard.blade.php` muestran arriba de todo (`<x-slot name="header">`
con "Panel {Administrativo/Profesional/Operativo/Personal}" + fecha + badge "Sistema
Activo") — `Recepcion.vue` nunca usó el slot `#header` de `AppLayout.vue`. Se descubrió al
construir Barbero.vue y notar que el Blade original SÍ lo muestra para los 4 roles. Se
creó `resources/js/Components/DashboardHeader.vue` (props `label`, `color`, `todayLabel`
— la fecha en español viene precalculada del controlador vía
`now()->translatedFormat('l d \\d\\e F, Y')`, no reimplementada en JS) y se retrofiteó en
`Recepcion.vue` en el MISMO commit de esta fase, no como fix separado después. **Lección:
al construir la segunda instancia de un patrón compartido (la primera página de un tipo
casi nunca expone todas las piezas realmente compartidas), revisar la vista Blade
ORIGINAL completa de arriba a abajo, no solo la sección que ya se leyó en la fase
anterior.**

`barberToday`/`barberPending` se mapean a arrays planos en el controlador (mismo patrón
de Fases 3-4); el campo `isNext` (cuál cita es "la siguiente") se calcula en PHP, no en
Vue — mismo principio que `AnalyticsInsight::toDashboardCardArray()`. Aprobar/Rechazar
usan `<form method="POST">` NATIVOS (no `router.patch()`/`useForm()` de Inertia): el
endpoint (`barber.appointments.status`) sigue siendo una redirección Blade clásica, y un
`<form>` nativo simplemente hace un POST + recarga completa del navegador — evita el
mismo riesgo de "respuesta inesperada" que ya mordió con `<Link>` en la Fase 3, sin tener
que razonar si el manejo de redirects de Inertia sería seguro aquí (no se investigó a
fondo esa alternativa porque el `<form>` nativo es una salida garantizada y ya usada en
otras partes de este shell, como el logout).

**Bug preexistente encontrado, NO corregido aquí (fuera de alcance):**
`DashboardService::buildBarberMetrics()` tiene `'rating' => 4.9` hardcodeado — el KPI
"Rating" del dashboard del barbero siempre muestra 4.9 sin importar sus reseñas reales,
mientras que `calificacion_promedio` (el campo real, mantenido por
`BarberReviewService` y sí usado en `Api/Admin/Barber/BarberAdminController` y
`Api/Profile/ProfileController`) existe y está disponible. Se portó tal cual (mismo bug,
mismo valor) porque arreglarlo es un cambio de lógica de negocio ajeno a esta migración
— reportado aparte, no en este commit.

Nuevos paquetes de gráficas usados: `Bar` y `Doughnut` de `vue-chartjs` (ya instalado
desde la Fase 1) — sin instalar nada nuevo.

**Verificado:** con las credenciales reales de barbero (`docs/ACCESOS.md`) contra Atlas
— header "Panel Profesional", saludo "Maestro Barbero", 5 KPIs (incluido el 4.9
hardcodeado, confirmando el puerto fiel del bug), CTA de analítica, "Sin citas hoy" y
ambas gráficas en su estado vacío ("Sin datos suficientes"/"Sin especialidades aún") se
ven correctos, consola limpia en pestaña nueva. Se re-verificó también
`Recepcion.vue` con el header ya corregido. Pint (337 archivos), phpstan (0 errores,
regenerado wholesale otra vez — mismo criterio de la Fase 4), `.\test.ps1` (210 tests,
+1 nuevo), eslint (0 warnings) y `npm audit` — todos en verde.

### ✅ Fase 6 — Dashboard de Cliente (completa, 2026-09-05)

El más grande de los 4 hasta ahora. `DashboardController::index()` devuelve
`Inertia::render('Dashboard/Cliente', [...])` solo para la rama `cliente`; solo falta
administrador.

**Componente nuevo grande:** `resources/js/Components/MembershipCard.vue`, puerto de
`components/membership-card.blade.php` — tarjeta con tilt 3D al mover el mouse, flip a
QR, contador de puntos animado y celebración (confetti) al subir de nivel. La versión
Blade hacía todo esto con manipulación directa del DOM + `data-*` attributes; se
reimplementó con estado reactivo de Vue (`ref`/`computed` para tilt/flip/contador) en vez
de tocar el DOM a mano. El evento `celebrate` se sigue disparando igual
(`window.dispatchEvent(new CustomEvent('celebrate'))`) — lo sigue escuchando el script de
confetti que YA vive en `resources/views/app.blade.php` desde la Fase 2, no se tocó. Se
reusó la MISMA clave de `localStorage` (`ub_lvl_rank`) que la versión Blade para no perder
el estado de "ya vio este nivel" de clientes que ya visitaron el dashboard viejo.

**Gradiente de Chart.js resuelto de forma declarativa:** a diferencia de `flowChart`
(Fase 4, color plano) y `performanceChart`/`servicesChart` (Fase 5, sin gradiente),
`visitChart` SÍ necesita un gradiente (igual que `incomeChart` de administrador, todavía
sin migrar). La versión Blade obtenía el gradiente de forma imperativa
(`canvas.getContext('2d').createLinearGradient(...)` antes de construir el `Chart`). En
Vue con `vue-chartjs` eso se resuelve con una **opción scriptable de Chart.js**:
`backgroundColor` como función `(context) => { const {ctx, chartArea} = context.chart; ...crear gradiente...; return gradiente; }`
— Chart.js la invoca en cada render con acceso al canvas real, sin necesitar una ref
manual al elemento `<canvas>`. Se usó `chartArea.top/bottom` en vez del `260` hardcodeado
del original (más robusto ante cambios de altura). **Cuando llegue la Fase de
administrador, `incomeChart` puede reusar este mismo patrón** — si para entonces ya son 2
gráficas con gradiente dorado idéntico, vale la pena extraer un `goldGradient()`
exportado desde `chart-theme.js` en vez de mantener 2 copias.

Todos los campos que dependían de formato de fecha en español (`nextAppointment.day`,
`.monthShort`, `.dateLong`) se precalculan en el controlador vía Carbon — mismo principio
que `todayLabel` (Fase 5) y `AnalyticsInsight::toDashboardCardArray()` (Fase 4). En
cambio, el "mejor mes" de la gráfica de visitas SÍ se calculó en Vue (`visitPeak`
computed): es aritmética pura sobre `visitChart.labels`, que YA vienen en español desde
`DashboardService` — no hay i18n que reimplementar ahí, solo buscar el máximo.

**Verificado:** con las credenciales reales de cliente (`docs/ACCESOS.md`) contra Atlas —
header "Panel Personal", saludo, 4 KPIs, "Sin citas próximas" + CTA, "Aún no hay patrón
suficiente", gráfica de visitas en estado vacío, tarjeta de membresía completa (nivel
Caballero, número de socio real, QR real generado por `MemberCardService`), el flip a QR
probado en vivo (funciona, cambia el texto del botón a "Ver tarjeta"), progreso de
lealtad en 0% con "faltan 5 visitas" al siguiente nivel, los 4 beneficios correctamente
inactivos para nivel nuevo — consola limpia en pestaña nueva. Pint (338 archivos),
phpstan (0 errores, **sin necesidad de tocar el baseline esta vez** — ver nota abajo),
`.\test.ps1` (211 tests, +1 nuevo), eslint (0 warnings) y `npm audit` — todos en verde.

**Nota sobre phpstan en esta fase:** a diferencia de las Fases 4-5, aquí NO hizo falta
tocar `phpstan-baseline.neon`. La razón: `DashboardService::clientMetrics(): array` ya
devolvía `next_appointment`/`visit_chart` como arrays planos (no Collections de Eloquent)
y el único `.map()` nuevo en el controlador (`loyalty['recent_transactions']`) opera
sobre un valor extraído de un array (`$data['loyalty']['recent_transactions']`), no sobre
una variable con tipo de Collection precisamente conocido — igual que pasó con
`next_appointments` en la Fase 4, Larastan no puede analizar el tipo del callback lo
bastante a fondo como para marcarlo. Confirma el patrón: **cuanto más "vago" (`array`) es
el tipo de retorno de un método de servicio, menos puede quejarse Larastan — no es una
ventaja real, solo significa que ese análisis no está pasando ahí.**

### ✅ Fase 7 — Dashboard de Administrador (completa, 2026-09-05) — última rama

La fase más grande de las 4: KPIs con sparkline SVG en miniatura, insights de negocio
precomputados, panel con 3 tabs (Actividad/Estaciones/Top Mes), sección "Analítica
avanzada" plegable con 4 gráficas + predicciones IA (fetch en vivo a `/api/v1/admin/
predictions/*` con un token de Sanctum recién pedido) + telemetría del chatbot.

**Simplificación real, no solo de estilo:** la versión Blade colapsaba la sección de
analítica avanzada con `max-height` (nunca `display:none`) específicamente porque los
`<canvas>` de Chart.js se inicializaban a 0×0 si el contenedor estaba oculto en el
momento del `new Chart(...)`. Con `vue-chartjs` y `v-if` no hace falta ese truco: el
`<canvas>` recién se monta cuando el contenedor YA es visible (el `v-if` se vuelve
verdadero después del click), así que Chart.js mide el tamaño real desde el principio.
Se usó `v-if` simple + el mismo `localStorage` (`adminAnalytics`) para recordar si estaba
abierto.

**Predicciones IA portadas tal cual:** el IIFE async de Blade (pedir un token de Sanctum
fresco vía `/api/v1/auth/get-api-token`, luego 3 fetches en paralelo a
`/api/v1/admin/predictions/{income,appointments,insights}`) se portó literalmente a un
`onMounted` — incluido el `'72%'` de confianza fijo del original (no es una regresión
introducida aquí, ya estaba hardcodeado). Verificado en vivo con la cuenta real de
administrador: las 3 métricas (`Ingresos Est. $550`, `Citas Est. 7`, `Confianza 72%`) y
la telemetría del chatbot (`Eventos 2`, `Latencia 5564ms`, `Top Fuentes: MANUAL 2`)
cargaron con datos reales — confirma que el flujo completo de token+fetch funciona
igual desde Vue.

**Limpieza final de Blade, hecha en este mismo commit:**
- `resources/views/dashboard.blade.php` (1472 líneas) — **borrado**. Era el último
  archivo que las 4 ramas del controlador compartían; con `administrador` migrado, nada
  lo referencia más (confirmado con `grep -rn "view('dashboard'"` antes de borrar).
- `resources/views/components/membership-card.blade.php` — **borrado**. Solo lo usaba
  la rama `cliente` de `dashboard.blade.php` (Fase 6); quedó huérfano al borrar ese
  archivo.
- `resources/views/components/analytics-insights.blade.php` y `analytics-cta.blade.php`
  — **NO se borraron**. `resources/views/analytics/index.blade.php` (página `/analytics`,
  todavía sin migrar) sigue usando `<x-analytics-insights :showCharts="true">` — la
  variante CON gráficas que `AnalyticsInsights.vue` nunca implementó (ver Fase 4). Si
  `/analytics` se migra en el futuro, ver esa nota antes de tocar esta variante.
- Dos ramas muertas de `DashboardController::index()` que también devolvían
  `view('dashboard', ...)` se migraron a una página nueva y mínima,
  `resources/js/Pages/Dashboard/SinRol.vue`: el fallback real para un usuario autenticado
  sin ninguno de los 4 roles reconocidos, y un fallback defensivo para `$user === null`
  que en la práctica es inalcanzable (la ruta ya exige el middleware `auth`) pero seguía
  necesitando un valor de retorno válido.
- `phpstan-baseline.neon` — solo bumps de contador (5 patrones ya conocidos en
  `DashboardController.php`, mismo criterio de las Fases 4-5), sin entradas nuevas.

**Verificado:** con las credenciales reales de administrador contra Atlas — header
"Panel Administrativo" con AMBOS botones extra (mantenimiento, backup — el de
mantenimiento se verificó solo visualmente, **no se activó** para no apagar el sitio
real), 4 acciones rápidas, 4 KPIs, los 3 tabs del panel (Actividad/Estaciones/Top Mes,
cada uno con sus estados vacíos correctos), el panel de analítica avanzada expandido con
las 4 gráficas en su estado vacío y las predicciones IA/telemetría chatbot con datos
reales en vivo — consola limpia en pestaña nueva. Pint (339 archivos), phpstan (0
errores), `.\test.ps1` (213 tests, +2 nuevos), eslint (0 warnings) y `npm audit` — todos
en verde.

## Migración completa — las 4 fases de dashboard y las páginas de citas están migradas

A partir de aquí, `resources/views/dashboard.blade.php` ya no existe: los 4 roles
(administrador/barbero/recepcionista/cliente) tienen su propia página Inertia+Vue, además
del calendario de citas (Fase 3). Sigue pendiente para un futuro trabajo (fuera de esta
migración, no iniciar sin que el dueño del proyecto lo pida): el resto de las páginas
Blade+Alpine del sitio (`/analytics`, listados de citas/clientes/pagos/inventario/etc.) —
usar exactamente el mismo patrón documentado aquí.

### Merge a `main` (hecho — 2026-09-05)

1. Verificación completa (checklist de arriba) sobre el HEAD de la rama — ya en verde.
2. `git fetch origin` + revisar que `main` no avanzó de forma incompatible.
3. Merge de `feature/inertia-vue-migration` a `main` (preservando el historial de fases
   para trazabilidad, no squash silencioso).
4. Confirmar CI verde en `main` post-merge.
5. Rama `feature/inertia-vue-migration` borrada en local y en remoto — todo el trabajo
   vive ya en `main`.

**Pendiente para una sesión futura, NO hecho en este merge (fuera de alcance, evaluar
solo si se pide explícitamente):** retirar `alpinejs`/plugins asociados de
`package.json` — NUNCA quitar esa dependencia sin confirmar primero que ninguna vista
Blade restante la sigue usando (`grep -rn "x-data\|x-show\|x-if" resources/views/`); el
resto del sitio (fuera de dashboard/calendario) sigue siendo Blade+Alpine y todavía la
necesita.

## Gotchas encontrados

### `assertInertia()->component(...)` pasaba en local y fallaba en CI por mayúsculas/minúsculas (post-merge, 2026-09-05)

El más peligroso de todos: pasó desapercibido durante las 7 fases enteras (todas
verificadas en verde localmente, ver [[urbanblade-guardrails]] para el hábito de correr
`.\test.ps1` siempre) y solo reventó en CI **después del merge a `main`**. Los 4 tests
`Dashboard*InertiaTest` fallaban en el runner de GitHub Actions con
`Inertia page component file [Dashboard/Administrador] does not exist.` (y lo mismo para
Barbero/Cliente/Recepcion/SinRol) — pero pasaban siempre en local.

Causa raíz: `inertiajs/inertia-laravel` trae un default de paquete
`config('inertia.pages.paths')` = `resource_path('js/pages')` (minúscula) — pero este
repo usa `resources/js/Pages` (mayúscula, la convención real desde la Fase 1, y la misma
que usa el resolver de `resources/js/inertia.js`:
`import.meta.glob('./Pages/**/*.vue')`). `AssertableInertia::component()` valida en disco
que el archivo exista usando ese path — con la ruta en minúscula, nunca debería haber
encontrado nada.

Por qué pasaba en local: `barber-app` corre Docker Desktop sobre Windows, y el bind-mount
del proyecto hereda la insensibilidad a mayúsculas de NTFS —
`ls /var/www/html/resources/js/pages` y `ls .../js/Pages` **devuelven el mismo
directorio** dentro del contenedor local (confirmado explícitamente). Un runner real de
GitHub Actions (Ubuntu, ext4, sí distingue mayúsculas) nunca tuvo ese accidente y detectó
el problema real de inmediato.

Fix: `php artisan vendor:publish --provider="Inertia\ServiceProvider"` (nunca se había
publicado `config/inertia.php` en este repo) y corregir `pages.paths` a
`resource_path('js/Pages')` explícitamente.

**Lección que aplica a todo el resto de este proyecto, no solo a Inertia:** un
`docker exec barber-app` local en Windows/Docker Desktop NO es una réplica fiel de un
runner Linux real para temas de sensibilidad a mayúsculas en rutas de archivos — un
`.\test.ps1` en verde local **no garantiza** que un `require`/`config path`/`glob` con
mayúsculas incorrectas vaya a fallar donde debería. Si alguna vez se sospecha de un typo
de mayúsculas en una ruta, verificar con `ls` las dos variantes exactas dentro del
contenedor (como se hizo aquí) en vez de confiar en que "los tests locales pasaron".
Además: **este bug se coló hasta el merge a `main` porque nunca se verificó CI en la
rama `feature/inertia-vue-migration` misma** (el workflow de este repo solo corre en
push/PR a `main`, no en cualquier rama) — para una migración larga como esta, valdría la
pena abrir un PR de borrador temprano (aunque no se vaya a mergear todavía) solo para que
CI corra contra la rama real en cada fase, en vez de descubrir esto hasta el final.

### `<Link>` de Inertia hacia una ruta todavía-Blade rompe la navegación (Fase 3)

Ver el detalle en la Fase 3 arriba. Regla corta: **`<Link>` solo entre dos páginas
Inertia; `<a>` normal hacia cualquier ruta que siga siendo Blade.** Esto va a repetirse en
cada fase mientras queden páginas sin migrar — no es un bug puntual, es la naturaleza de
una migración incremental.

### `@fullcalendar/vue3` publicó un major nuevo (7.x) horas antes de instalarlo, sus paquetes hermanos no (Fase 3)

Al instalar `@fullcalendar/core @fullcalendar/vue3 @fullcalendar/daygrid
@fullcalendar/timegrid @fullcalendar/list @fullcalendar/interaction` sin versión
explícita, npm resolvió `core` y `vue3` a `7.1.0` (publicado el mismo 2026-09-05, horas
antes) pero `daygrid`/`timegrid`/`list`/`interaction` se quedaron en `6.1.21` porque su
dist-tag `latest` todavía no se había movido a 7.x (sus versiones 7.x solo existen como
`-rc.0`, ni siquiera estables) — un rollout de release incompleto/inconsistente del lado
de FullCalendar, no un error de instalación. Síntoma: el calendario renderizaba sin
NINGÚN estilo (toolbar como texto plano, sin colores) y la consola mostraba
`TypeError: Class constructor EF cannot be invoked without 'new'`. Diagnóstico:
`cat node_modules/@fullcalendar/*/package.json | grep version` mostró el desfase 7.1.0 vs
6.1.21 entre paquetes que deben ser la misma versión siempre. Fix: reinstalar los 6
paquetes fijados exactamente a `6.1.21` (`npm install ... --save-exact`, sin `^`) — la
última versión donde TODOS, incluido `vue3`, coinciden. **Si en el futuro se actualiza
FullCalendar, instalar SIEMPRE los 6 paquetes juntos a la MISMA versión explícita, nunca
dejar que npm resuelva cada uno por su cuenta** — verificar con el mismo comando `grep
version` antes de dar por buena la instalación.

### phpstan-baseline.neon necesita una entrada nueva al escribir código similar a uno ya baselineado en el mismo archivo (Fase 3)

`AppointmentController` ya tenía baseline para varios patrones "Larastan no puede resolver
el tipo de una relación de Eloquent/MongoDB" (`Model::$name` undefined,
nullsafe-innecesario, `Collection<X>::map() contains unresolvable type`). El nuevo código
de `calendar()` (`$barbers->map(fn (Barber $b) => ['name' => $b->user?->name ...])`)
disparó el MISMO tipo de error, no uno nuevo — pero como es un `Collection<Barber>` y las
entradas existentes eran para `Collection<Appointment>`/`Collection<Client>`, hizo falta
una entrada NUEVA (no solo subir un contador). Regla práctica: al tocar un archivo que ya
tiene entradas de baseline por relaciones Eloquent/MongoDB no tipables, esperar necesitar
tocar el baseline también — no es señal de un bug real, es la firma de trabajo ya conocida
en este repo (ver [[laravel-13-reference]]). Diferencia con la "regeneración wholesale" de
esa skill: aquí el conteo subió por UNA línea de código nueva, así que se editó a mano
(sumar 1 a los contadores existentes + agregar la entrada nueva para `Barber`), no se
regeneró el archivo completo — regenerar wholesale es para cuando decenas de entradas
cambian de golpe por un bump de versión, no para esto.

### A veces SÍ conviene regenerar el baseline wholesale sin que sea un bump de versión (Fase 4)

Contradice a medias el gotcha anterior — matiz importante. En la Fase 4, mover el mapeo de
`Appointment`/`Order`/`AnalyticsInsight` del Blade (nunca analizado por phpstan) al
controlador/modelo (sí analizado) disparó ~12 errores nuevos de golpe (3 bumps de contador
+ 9 entradas nuevas, repartidas en 2 archivos). A mano habría sido tedioso y propenso a
error (calcular cada `count:` exacto). Se usó
`docker exec barber-app ./vendor/bin/phpstan analyse --generate-baseline --memory-limit=1G`
y LUEGO se verificó con `git diff --stat phpstan-baseline.neon` +
`git diff phpstan-baseline.neon | grep "^+.*path:" | sort -u` que el diff tocara
ÚNICAMENTE los 2 archivos recién editados — si hubiera aparecido un tercer archivo en ese
diff, habría sido señal de regresión real en otro lado, no de este cambio. Regla real
(no la de "wholesale solo para bumps de versión"): **usar `--generate-baseline` cuando el
volumen de entradas nuevas/cambiadas hace el conteo manual poco confiable, sin importar la
causa — pero SIEMPRE verificar el diff resultante antes de commitear**, nunca confiar el
regenerado a ciegas.
