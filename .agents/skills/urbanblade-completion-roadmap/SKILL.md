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

### Fase 4: pagos, pedidos e inventario

- Revisar Stripe, transferencias, recibos, reembolsos, precios server-side, stock y transacciones.
- Mantener MongoDB en replica set para pruebas.

Aceptación: importes y stock se calculan en servidor y webhooks son idempotentes.

### Fase 5: notificaciones y operación

- Revisar email, push, colas, reintentos, cumpleaños, citas, stock, pagos y logs.
- Añadir observabilidad accionable sin PII innecesaria.

Aceptación: fallos quedan registrados, reintentan según política y no rompen la petición principal.

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
