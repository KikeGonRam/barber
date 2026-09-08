---
name: urbanblade-completion-roadmap
description: 'Plan por fases para completar y endurecer el contrato API de UrbanBlade y su integración con frontend-urban. Usar antes de tocar auth, perfiles, contratos JSON, permisos, pagos, citas, notificaciones o pruebas E2E; cada fase exige pruebas, revisión de regresiones, commit y push separado en barber y frontend-urban.'
---

# UrbanBlade completion roadmap

## Objetivo

Completar el backend Laravel y el frontend Nuxt como un producto coordinado, sin romper el contrato API que también consumirá la futura app Android.

## Estado base verificado

- Backend: Laravel 13 + MongoDB Atlas en desarrollo, 145 rutas bajo `/api/v1`.
- Frontend: Nuxt con pantallas para autenticación, dashboards, citas, clientes, pagos, pedidos, inventario, reportes, servicios, usuarios, social y ajustes.
- Última línea base conocida: suite backend completa verde (`340 tests`, `1320 assertions`), Pint, ESLint y build de Nuxt verdes.
- Los repositorios `barber` y `frontend-urban` tienen historiales y pushes independientes.

## Reglas de ejecución

1. No tocar `.env`, secretos ni credenciales.
2. No ejecutar tests con `php artisan test`; usar siempre `./test.ps1`.
3. No ejecutar migraciones, seeders completos, resets ni comandos destructivos contra Atlas.
4. Los cambios en `routes/api.php` y `app/Http/Controllers/Api/**` son contratos públicos: preferir cambios aditivos y actualizar Scribe cuando cambie la API.
5. Cada fase debe tener una hipótesis verificable, una prueba focalizada, una prueba de regresión y validación de frontend cuando corresponda.
6. Cada fase aprobada se publica con commits separados: uno en `barber` y otro en `frontend-urban`.
7. No mezclar cambios ajenos o artefactos generados en los commits de una fase.

## Fases

### Fase 1: contrato API y autenticación — ✅ DONE (2026-09-08)

- Inventariar endpoints realmente usados por Nuxt y compararlos con rutas Laravel.
- Estandarizar recursos de usuario (`UserResource`), paginación, validación y errores sin cambiar respuestas existentes de forma silenciosa.
- Auditar login/password, Google OAuth, avatar, completar perfil, tokens, logout, refresh y CORS de producción.
- Blindar cookies de sesión en Nuxt (`useCookie('ub_token', { sameSite: 'lax', secure: process.env.NODE_ENV === 'production' })`).
- Cobertura completa de autenticación en `tests/Feature/AuthApiTest.php` con 14 pruebas que validan registro, inicio de sesión, bloqueo de credenciales inválidas sin revelar existencia de correos, bloqueo de correos no verificados, `GET /auth/me`, `POST /auth/logout` con revocación en base de datos, `POST /auth/refresh-token` con rotación atómica de tokens, rechazo de 401 en endpoints protegidos sin autenticación, y recuperación/reseteo de contraseña.
- Actualización de documentación OpenAPI/Scribe sin advertencias de esquema.

Aceptación: todos los endpoints consumidos tienen respuesta documentada, errores previsibles y prueba; `.\test.ps1` (377/377 tests verdes), Pint limpio (365 files), Larastan limpio (0 errores en 286 files), ESLint limpio y Playwright E2E verde (16/16 tests).

### Fase 2: perfiles, permisos y seguridad de cuenta — ✅ DONE (2026-09-08)

- Completar perfil por rol, foto propia, preferencias y notificaciones.
- Revisar autorización objeto por objeto y exposición de PII.
- Verificar que los cambios de rol mantengan `users.role_id` como fuente MongoDB.
- Blindar eliminación de cuenta en `ProfileController::destroy()`: prohibir auto-eliminación de administradores desde el perfil y bloquear eliminación de clientes con citas activas pendientes.
- Ocultar atributos sensibles en modelos (`verification_code`, `verification_code_expires_at` en `User`, `token_hash` en `MobileApiToken`).
- Documentar parámetros del perfil de barbero en `UpdateBarberProfileRequest::bodyParameters()` para Scribe.
- Cobertura completa con 13 pruebas automatizadas en `tests/Feature/ProfileApiTest.php` cubriendo ver perfil, actualizar perfil, unicidad de email, cambio de password con confirmación de password actual, push token, eliminación de cuenta y bio/perfil de barbero.

Aceptación: matrices de rol cubiertas por pruebas y sin acceso cruzado entre usuarios. `.\test.ps1` (370/370 tests verdes), Pint limpio, Larastan limpio y Scribe actualizado.

### Fase 3: citas y disponibilidad — ✅ DONE (2026-09-07, commit `f1bcb46`)

- Auditar conflictos, zona horaria, estados, cancelaciones, reprogramaciones, recordatorios y calendario.
- Cubrir carreras y límites de negocio con pruebas de integración.

Aceptación: ningún flujo permite doble reserva o transición inválida.

**Resultado de la auditoría (6 áreas revisadas, ver el prompt original del
subagente de exploración para el detalle completo con líneas exactas):**

1. **Doble reserva (race real, corregido)** — `ensureNoOverlap()` era un
   check-then-create de aplicación sin ninguna garantía de base de datos.
   Índice único parcial nuevo (`barber_id, fecha, hora_inicio` sobre citas
   activas) lo cierra a nivel de Mongo. No cubre el caso más raro de dos
   servicios de duración distinta que se solapan sin compartir el mismo
   `hora_inicio` exacto (eso requeriría serializar escrituras por barbero) —
   aceptado como residual, documentado en la migración.
2. **Zona horaria (gap latente, NO corregido a propósito)** — todo el
   backend asume `America/Mexico_City` (config/app.php), sin concepto de
   timezone por cliente. Hoy no es explotable porque nada envía un timezone
   propio; sería relevante si la futura app Android (o cualquier cliente en
   otra zona) empezara a mandar su hora local sin ajustar. No se tocó en
   esta fase — el fix correcto depende de decidir el contrato con esa app,
   fuera de alcance de un cambio aditivo silencioso.
3. **Transiciones de estado (gap real, corregido)** — el PUT de edición
   completa dejaba a admin/recepción saltarse la máquina de estados
   (`completada -> pendiente` sin bloqueo). Ahora valida contra
   `AppointmentStatusService::canTransition()`.
4. **Cancelación/reprogramación** — cancelar libera el slot correctamente
   (ya filtraba `cancelada`/`no_asistio`). Reprogramar sí revalida
   conflictos (comparte el fix #1). Nuevo: reprogramar ahora resetea los
   recordatorios ya enviados si la fecha/hora realmente cambia (antes
   quedaban huérfanos, ver #5 abajo).
5. **Recordatorios (gap real, corregido)** — reprogramar una cita que ya
   tenía su recordatorio de 24h/2h enviado nunca reseteaba esos timestamps,
   así que el comando programado la saltaba para siempre en el nuevo
   horario. Corregido comparando contra el valor persistido, no solo "vino
   en el payload".
6. **Calendario/disponibilidad (frontend)** — ✅ **cerrado el 2026-09-07**
   (frontend-urban `8dd5afa` cliente, `app/pages/appointments/index.vue` staff):
   el modal de reserva del cliente consume `/availability/slots` y solo ofrece
   huecos reales con fallback a input libre si la llamada falla. El formulario
   de staff (`pages/appointments/index.vue`) ofrece los horarios libres como
   sugerencia (`<datalist>`) y avisa si la hora escrita no está libre,
   manteniendo el campo editable para respetar la necesidad de recepción de
   agendar huecos excepcionales acordados por teléfono. En ambos flujos, el
   índice único parcial de Fase 3 y el 422 de `AppointmentController::store()`
   garantizan que ninguna doble reserva ocurra en base de datos.

Verificación: `.\test.ps1` x2 en verde (348/348 ambas veces), Larastan en
frío limpio, Pint limpio.

### Fase 4: pagos, pedidos e inventario — ✅ DONE (2026-09-07, commit `163100d`)

- Revisar Stripe, transferencias, recibos, reembolsos, precios server-side, stock y transacciones.
- Mantener MongoDB en replica set para pruebas.

Aceptación: importes y stock se calculan en servidor y webhooks son idempotentes.

**Resultado de la auditoría (4 áreas revisadas):**

1. **Pagos duplicados por cita (race real, corregido)** —
   `PaymentService::create()`/`uploadTransferReceipt()` solo tenían
   `PaymentRepository::existsForAppointment()` como guardia, un check-then-create
   de aplicación sin ninguna garantía de base de datos (mismo patrón que el
   hallazgo #1 de Fase 3, aquí con impacto directo en dinero real y puntos de
   lealtad duplicados si un reintento de webhook de Stripe cruza con un
   doble-click de "cobrar" en recepción). Índice único parcial nuevo sobre
   `payments(appointment_id)`, filtrado por el campo derivado `bloquea_cita`
   (Mongo no soporta `$ne`/`$nin` en `partialFilterExpression`, solo
   igualdad — mismo patrón que `bloquea_horario` de Fase 3). `BulkWriteException`
   se traduce a `PaymentException` en ambos call sites; los controllers ya
   la capturaban, sin cambios ahí.
2. **Pedido sin rollback ante fallo a mitad de camino (gap real, corregido)** —
   `OrderService::place()` descontaba stock línea por línea sin transacción:
   si una línea posterior fallaba (p.ej. el chequeo de stock del paso 1
   quedó obsoleto por un pedido concurrente sobre el mismo producto), las
   líneas anteriores quedaban con stock ya descontado de verdad y ningún
   `Order` que lo respaldara. Ahora todo el paso 2+3 vive dentro de un solo
   `DB::transaction()`.
3. **`lockForUpdate()` es un no-op silencioso en este driver (gap real,
   corregido)** — confirmado leyendo el vendor: ni `Query\Builder` ni
   `Eloquent\Builder` de `mongodb/laravel-mongodb` lo sobreescriben, así que
   hereda el `lockForUpdate()` base de Illuminate, que solo marca una
   bandera consumida por grammars SQL (`FOR UPDATE`) — el grammar de Mongo
   no la traduce a nada. `InventoryService::registerMovement()` usaba esto
   como "protección" contra sobreventa concurrente sin que hiciera nada
   real. Reemplazado por un decrement condicional atómico
   (`where('stock_actual', '>=', $qty)->decrement(...)`, un único op
   `$inc`-con-filtro de Mongo que no puede colar una lectura obsoleta entre
   el chequeo y la escritura sin importar cuántas escrituras concurrentes
   lleguen).
4. **Transacciones anidadas no soportadas (bug introducido y corregido en la
   misma fase)** — al envolver `OrderService::place()` en su propia
   `DB::transaction()` (hallazgo #2), `InventoryService::registerMovement()`
   seguía abriendo su propia transacción por dentro; `mongodb/laravel-mongodb`
   no soporta savepoints, así que `Session::startTransaction()` truena con
   `RuntimeException: "Transaction already in progress"` en cuanto hay
   anidamiento real. Corregido con `DB::transactionLevel() > 0` como guardia:
   `registerMovement()` participa en la transacción ya activa del llamador en
   vez de abrir una nueva, pero sigue abriendo la suya propia cuando se
   invoca standalone (p.ej. desde `InventoryController`).

**Decisión de negocio pendiente, NO implementada a propósito**: el webhook
`charge.refunded` de Stripe (`StripeWebhookController`) registra el reembolso
pero no revierte automáticamente puntos de lealtad otorgados ni restaura
stock — es una decisión de política de negocio (¿se revierten siempre? ¿solo
si el producto/servicio no se usó?) que cambiaría economía real de puntos y
stock sin un spec claro, no un bug puro. Señalado para que el dueño del
proyecto decida antes de implementarlo.

Verificación: `.\test.ps1` x2 en verde (351/351 ambas veces), Larastan en
frío limpio, Pint limpio (360 archivos).

### Fase 5: notificaciones y operación — ✅ DONE (2026-09-07, commit `f16314e`)

- Revisar email, push, colas, reintentos, cumpleaños, citas, stock, pagos y logs.
- Añadir observabilidad accionable sin PII innecesaria.

Aceptación: fallos quedan registrados, reintentan según política y no rompen la petición principal.

**Resultado de la auditoría (7 áreas revisadas):**

1. **SMS/WhatsApp via Twilio (gap real, corregido)** — `MessagingService::
   sendSms()`/`sendWhatsapp()` nunca revisaban la respuesta HTTP de Twilio.
   Un 4xx/5xx (numero invalido, credenciales rechazadas, rate limit) era
   invisible por completo: sin log, sin excepcion, sin evento a Sentry.
   Hallazgo mas claro de "se pierde en silencio" de toda la auditoria — este
   canal alimenta directamente los recordatorios de citas. Corregido:
   `Log::warning` con status/body cuando `$response->failed()`.
2. **Push web (gap real, corregido)** — `WebPushService::sendToUser()` solo
   manejaba 2 de 3 desenlaces posibles de un envio fallido: excepcion
   (logueada+podada) y suscripcion vencida (podada). Un fallo de envio
   genuino que no es ninguna de las dos (5xx del servicio push, payload
   rechazado) caia sin ningun rastro. Corregido con `Log::warning`.
3. **Seis `catch (\Throwable) {}` vacios alrededor de notify() (gap real,
   corregido)** — `SendBirthdayGreetingsCommand`, `CancelExpiredOrdersCommand`,
   `NotifyServiceOverrunCommand`, `LoyaltyService` (x3: puntos vencidos, baja
   de nivel, subida de nivel) tragaban fallos de notificacion sin dejar
   rastro, inconsistente con el patron ya correcto en
   `SendAppointmentRemindersCommand` (`Log::warning` con contexto). Aplicado
   el mismo patron a los seis sitios.
4. **Campañas sin aislamiento por item (gap real, corregido)** —
   `DispatchDueCampaignsCommand` no tenia try/catch alrededor del loop de
   despacho: una campaña con datos raros abortaba el comando completo,
   dejando sin enviar cualquier otra campaña vencida en ese ciclo (visible
   via Sentry/monitor, pero no aislado como los demas comandos batch).
   Corregido con try/catch por campaña + `Log::warning`.
5. **Webhook de Stripe — ya sólido** — si algo truena a mitad del
   procesamiento (falla de escritura en BD), la excepcion no capturada
   produce un 500 real (no el 200 esperado), asi que el reintento propio de
   Stripe se activa correctamente — no hay riesgo de "aceptado en silencio".
6. **ScheduledTaskMonitor/SystemController — ya sólido** — mecanismo real,
   probado end-to-end, conectado de verdad a los 12 comandos programados
   reales (no una lista paralela decorativa). Expuesto en `/status`.
7. **Cola Redis + failed_jobs sobre Mongo — arquitectura sólida, sin cobertura
   de pruebas propia** — el driver `database-uuids` de Laravel usa solo la
   API generica del query builder, compatible con `mongodb/laravel-mongodb`;
   no se encontró ninguna prueba que lo ejercite directamente, pero
   `SystemController` ya expone el conteo de jobs fallidos. Prioridad baja,
   no corregido en esta fase.

Se agregó `tests/Feature/MessagingServiceTest.php` (no existía ninguna
prueba de `MessagingService` antes) cubriendo el nuevo logging de fallos de
Twilio.

Verificación: `.\test.ps1` x2 en verde (355/355 ambas veces), Larastan en
frío limpio, Pint limpio (361 archivos).

### Fase 6: pruebas E2E y producción — ✅ DONE con un hallazgo abierto (2026-09-07, commit `2472a3b` en frontend-urban)

- Añadir recorridos E2E críticos para login, Google, completar perfil, reserva y pago.
- Verificar variables de Vercel/backend, CORS, storage, health checks y CI.

Aceptación: flujo crítico probado en build de producción y ambos repositorios sincronizados.

**Resultado de la auditoría:**

1. **E2E (entregado)** — `frontend-urban` no tenía NINGUNA herramienta de
   pruebas. Se agregó Playwright con 9 pruebas corriendo contra el build de
   producción (`nuxt build` + `nuxt preview`), no contra `nuxt dev`: guard de
   ruta protegida con `?redirect`, credenciales inválidas, login con perfil
   completo, gate de perfil incompleto (login y registro), enlace de acceso
   con Google, guard de invitado, y humo del bundle (landing sin errores de
   consola + páginas legales). La API de barber se intercepta en el navegador
   (`e2e/support/api-mock.ts`) — estas pruebas verifican el frontend, el
   backend ya tiene sus 355 propias, y así el job de CI no necesita levantar
   Laravel/Mongo/Redis.
2. **Gate de perfil incompleto (gap real, corregido)** — `login()` y
   `register()` mandaban siempre a `/dashboard` sin mirar `profile_complete`.
   Como el registro por correo nunca pide teléfono ni fecha de nacimiento
   (los dos datos que `User::profileCompletion()` exige a un cliente), esos
   usuarios entraban con el perfil incompleto y nada volvía a pedírselo —
   solo el callback de Google respetaba el gate. Los tres caminos ahora son
   consistentes, con pruebas de regresión.
3. **CORS — ya sólido** — `config/cors.php` (configurado el 2026-09-06)
   verificado en vivo: `Access-Control-Allow-Origin` responde con el origen
   permitido. Incluye patrón regex para los subdominios aleatorios de las
   preview de Vercel, y `supports_credentials: false` (auth por Bearer, no
   por cookie).
4. **Storage — ya sólido** — symlink `public/storage` presente y el disco
   `public` deriva su URL de `APP_URL`, así que avatares/comprobantes
   resuelven a URLs absolutas que el frontend puede cargar cross-origin.
5. **Health checks — ya sólidos** — `/up` (ruta de salud del framework,
   registrada en `bootstrap/app.php`) responde 200; el diagnóstico detallado
   `api/v1/admin/system/status` responde 401 sin autenticar (correcto: expone
   latencias de Mongo/Redis y jobs fallidos, no debe ser público);
   `docker-compose.yml` tiene healthcheck en app, worker, scheduler y ollama.
6. **CI — ampliado** — `frontend-urban/.github/workflows/ci.yml` ahora tiene
   un job `e2e` aparte (instala chromium, corre Playwright, sube el reporte
   HTML como artefacto si falla). El CI de barber no cambió.

**Hallazgo cerrado el mismo día (frontend-urban `cecb460`)** — el cliente ya
puede reservar: el modal de `/my/appointments` ahora también crea citas
(POST sin `client_id`, el backend lo deriva del token) y `/barbers/[slug]`
tiene un CTA "Reservar con X" que lo abre con el barbero preseleccionado.
Se agregaron 3 pruebas E2E (reserva feliz verificando el cuerpo del POST,
preselección desde la ficha, y choque de horario mostrando el 422 real del
backend), así que **"reserva" ya tiene cobertura E2E**; "pago" sigue sin
ella por depender de Stripe Elements dentro de un iframe. De paso se corrigió
un bug latente en `/barbers/[slug]`: el middleware `auth` solo mira la
cookie, así que en una entrada directa `user` seguía en null y
`hasRole('cliente')` escondía el formulario de reseña que ya existía.

Descripción original del hallazgo, que sigue explicando el porqué:

**El cliente NO podía reservar desde el frontend Nuxt**:
`POST /api/v1/appointments` sí permite el rol `cliente` (el controlador
tiene una rama explícita: "El cliente reserva para sí mismo, creando su
perfil Client si aún no existe"), pero en `frontend-urban` la creación de
citas existe únicamente en `app/pages/appointments/index.vue`, protegida por
`middleware: ['auth', 'staff']`. `app/pages/my/appointments/index.vue` (la
página del cliente) solo permite **reagendar y cancelar** citas que ya
existen. El CTA "Reservar" de la landing lleva a `/register`, y desde ahí el
cliente no tiene ninguna ruta para agendar. Por eso **"reserva" y "pago" no
tienen cobertura E2E**: la primera no existe todavía en el frontend, y la
segunda depende de Stripe Elements dentro de un iframe (no probable de forma
significativa con mocks). Construir esa pantalla es trabajo de producto, no
de esta fase de verificación — queda señalado para que el dueño del proyecto
decida, igual que la reversión de reembolsos de Fase 4.

Verificación: 9/9 pruebas E2E en verde, también con `CI=1` (1 worker +
reintentos); `eslint . --max-warnings=0` limpio; `npm audit
--audit-level=high` limpio; `/up` 200 y CORS verificados contra el backend
corriendo.

## Ciclo por fase

1. Leer la skill de la fase y el código propietario.
2. Documentar hipótesis, alcance y prueba discriminante.
3. Implementar el mínimo cambio aditivo.
4. Ejecutar prueba focalizada.
5. Reparar y repetir en la misma fase si falla.
6. Ejecutar regresión backend/frontend.
7. Revisar diff y archivos incluidos.
8. Crear commit y hacer push de ambos repositorios.
9. Registrar el resultado y continuar con la siguiente fase.
