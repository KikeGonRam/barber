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

### Fase 1: contrato API y autenticación

- Inventariar endpoints realmente usados por Nuxt y compararlos con rutas Laravel.
- Estandarizar recursos de usuario, paginación, validación y errores sin cambiar respuestas existentes de forma silenciosa.
- Auditar login/password, Google OAuth, avatar, completar perfil, tokens, logout, refresh y CORS de producción.
- Añadir pruebas de contrato y de regresión para cada cambio.

Aceptación: todos los endpoints consumidos tienen respuesta documentada, errores previsibles y prueba; `./test.ps1`, Pint, ESLint y build pasan.

### Fase 2: perfiles, permisos y seguridad de cuenta

- Completar perfil por rol, foto propia, preferencias y notificaciones.
- Revisar autorización objeto por objeto y exposición de PII.
- Verificar que los cambios de rol mantengan `users.role_id` como fuente MongoDB.

Aceptación: matrices de rol cubiertas por pruebas y sin acceso cruzado entre usuarios.

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
6. **Calendario/disponibilidad (frontend)** — `AvailabilityController::
   slots()` existe en el backend pero **frontend-urban no lo consume
   todavía**: los formularios de citas (staff y cliente) son un
   `<input type="date">`/`<input type="time">` plano, sin selector de
   horarios disponibles. No es un bug de esta fase (nada se rompió), pero
   es la razón por la que el hallazgo #1 (índice único) es la protección
   real hoy — si se construye un selector de slots más adelante, debe
   asumir que el slot puede dejar de estar disponible entre que se listó y
   que se envió el submit (el backend ya lo maneja con un 422 claro).

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

### Fase 6: pruebas E2E y producción

- Añadir recorridos E2E críticos para login, Google, completar perfil, reserva y pago.
- Verificar variables de Vercel/backend, CORS, storage, health checks y CI.

Aceptación: flujo crítico probado en build de producción y ambos repositorios sincronizados.

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
