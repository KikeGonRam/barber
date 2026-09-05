---
name: inertia-vue-migration
description: >
  Context, decisions, and phase-by-phase progress for the in-flight migration of this
  repo's frontend from Blade+Alpine.js to Inertia.js+Vue 3, happening entirely on the
  `feature/inertia-vue-migration` branch (NOT merged to `main` until every phase is done
  and the project owner explicitly approves). Consult this BEFORE touching anything under
  `resources/js/Pages/`, `resources/js/inertia.js`, `resources/views/app.blade.php`,
  `app/Http/Middleware/HandleInertiaRequests.php`, or before adding/removing any
  Inertia/Vue/FullCalendar-Vue/vue-chartjs package — so work continues from the real
  state instead of re-deciding things already settled. If you are a fresh session/agent
  picking this up, read this whole file before writing any code.
---

# Migración Blade+Alpine → Inertia.js+Vue 3

## Por qué existe esto

El proyecto tiene una landing pública, dashboards (Admin/Recepción/Barbero/Cliente),
calendario de citas (FullCalendar) y analítica (Chart.js) construidos con Blade + Alpine.js
+ jQuery-style DOM manipulation embebida en `<script>` tags dentro de las vistas. El dueño
del proyecto pidió migrar a un framework reactivo moderno usando Inertia.js como puente,
sin perder Laravel como backend ni tocar MongoDB.

**Todo este trabajo vive en la rama `feature/inertia-vue-migration`.** No se mergea a
`main` hasta que TODAS las fases de este documento estén completas, verificadas, y el
dueño del proyecto lo confirme explícitamente. Cada fase debe quedar en verde (Pint,
phpstan, `.\test.ps1`, `npm run build`, `npm audit --audit-level=high`) antes de pasar a
la siguiente.

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

### ⬜ Fase 3 — Primera página real: Calendario de Citas

Candidato ya analizado y con ejemplo de código discutido con el dueño del proyecto:
`AppointmentController::calendar()` (en
[app/Http/Controllers/Appointment/AppointmentController.php](../../../app/Http/Controllers/Appointment/AppointmentController.php))
→ `Inertia::render('Appointments/Calendar', [...])`.

**Decisión de diseño importante:** `calendarData()` (el endpoint que devuelve los eventos
JSON para FullCalendar) se queda **exactamente igual** — sigue siendo un endpoint JSON
plano, no se convierte en prop de Inertia. Razones: mínimo riesgo (si el render Vue falla,
la fuente de datos no se ve afectada), y consistencia con el patrón ya establecido de
`routes/api.php` como contrato estable (ver guardrails) — es el mismo tipo de endpoint que
eventualmente podría reutilizar la futura app Android.

El componente `resources/js/Pages/Appointments/Calendar.vue` reemplaza el `<script>`
vanilla de `resources/views/appointments/calendar.blade.php` (manipulación directa del DOM
para el modal de detalle) por estado reactivo de Vue (`reactive(modal)`), pero mantiene la
MISMA lógica de negocio: mismo fetch a `calendarDataUrl`, mismos colores por estado, mismo
comportamiento de filtro por barbero.

Como toda página real (a diferencia del smoke test descartable de la Fase 2), debe
envolverse en el layout ya construido: `import AppLayout from '@/Layouts/AppLayout.vue'`
y usar el slot `#header` para el título de la página — igual que hoy
`calendar.blade.php` usa `<x-app-layout>` con `<x-slot name="header">`.

### ⬜ Fases siguientes (a definir según prioridad del dueño del proyecto)

Candidatos evidentes por su uso de Chart.js: `DashboardController` (admin/recepción),
`BarberDashboardController`. Cada uno sigue el mismo patrón: controlador →
`Inertia::render()`, vista Blade → componente `.vue`, endpoints JSON de datos (si los hay)
sin tocar. Migrar de a una página por vez, verificar, commitear, repetir.

### ⬜ Fase final — Merge a `main`

Solo cuando el dueño del proyecto confirme que todas las páginas objetivo están migradas
y aprobadas:
1. Verificación completa (checklist de arriba) sobre el HEAD de la rama.
2. `git fetch origin` + revisar que `main` no avanzó de forma incompatible.
3. Merge (no squash silencioso — preservar el historial de fases para trazabilidad).
4. Confirmar CI verde en `main` post-merge.
5. Solo entonces evaluar si `alpinejs`/plugins asociados pueden retirarse de
   `package.json` — NUNCA quitar una dependencia sin confirmar primero que ninguna vista
   Blade restante la sigue usando (`grep -rn "x-data\|x-show\|x-if" resources/views/`).

## Gotchas encontrados

Ninguno todavía (Fase 1 fue limpia). Actualizar esta sección en cuanto aparezca alguno —
es la parte más valiosa de este documento para quien continúe el trabajo.
