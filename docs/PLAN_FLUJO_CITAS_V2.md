# Plan: flujo de citas V2 (aprobación, pago, inicio, fin, inasistencia)

Estado (2026-10-08): **etapas 1 a 5 implementadas y probadas en local** (backend, web y app); falta la prueba de punta
a punta con el celular y subir. Se implementó por etapas y **no se sube nada a `main` hasta que la integración
completa (backend + web + app) pase sus pruebas**. Ver «Notas de despliegue» al final.

## Decisiones del propietario
1. **Comisión por inasistencia:** 50 % del precio del servicio, configurable (`BarbershopSetting`). Se mantiene la
   política actual de depósito (umbral de 2 inasistencias en 90 días) como está; la comisión es un cargo **nuevo y
   adicional** que aplica a toda inasistencia marcada por el barbero.
2. **El barbero no puede iniciar una cita sin pago resuelto.**
3. **Recepción y administración siguen pudiendo aprobar citas** (respaldo si el barbero no está).

## Flujo objetivo
```
Cliente reserva ──► pendiente ──(barbero aprueba)──► confirmada ──(día de la cita, pago resuelto)──► en_proceso
                       │                                 │                                              │
                 (nadie aprueba → se cancela)            ├─► no_asistio (+ comisión)                    ├─ aviso 5 min antes del fin
                                                         └─► cancelada                                  ├─ terminar ya / agregar tiempo
                                                                                                        └─► completada (+ ticket)
```
- **Tarjeta:** se paga al reservar; queda `pendiente` hasta que el barbero apruebe.
- **Transferencia:** el cliente sube la captura; recepción la verifica (`pendiente_verificacion` → `verificado`). Sin
  verificación no se puede iniciar.
- **Efectivo:** se cobra en recepción cuando el cliente llega y antes de iniciar. Sin cobro no se puede iniciar.
- La **cita** y el **pago** son máquinas de estado separadas. Para iniciar deben cumplirse las dos: cita
  `confirmada` **y** pago resuelto (tarjeta cobrada, transferencia verificada o efectivo cobrado).

## Lo que ya existe (no se rehace)
`AppointmentStatusService` (transiciones y roles), `MarkNoShowAppointmentsCommand` (red de seguridad horaria),
`NotifyServiceOverrunCommand` (aviso **después** de pasarse), `DepositService` (depósito tras 2 inasistencias),
`Payment::ESTADO_*` (verificado / pendiente_verificacion / rechazado / reembolsado), `servicio_iniciado_en`,
`PaymentReceiptNotification` y comprobante PDF.

## Etapas

### Etapa 1 — Reglas de estado y pago (backend)
- Iniciar (`confirmada → en_proceso`): solo el **día de la cita** y desde `hora_inicio` menos un margen configurable;
  exige pago resuelto. Error claro (422) con el motivo.
- Aprobar (`pendiente → confirmada`): barbero de esa cita, recepción y administración; push al barbero cuando llega una
  cita nueva por aprobar.
- Helper único `Appointment::paymentResolved()` usado por la API, la web y la app (la UI solo lo refleja).
- Pruebas: matriz de método de pago × estado de pago × día/hora × rol.

### Etapa 2 — Comisión por inasistencia
- Campo configurable `comision_no_show_porcentaje` (50 por defecto; 0 la desactiva) y migración.
- Al marcar `no_asistio` (barbero o proceso automático): se crea un **cargo** ligado a la cita.
  - Con tarjeta guardada: cobro automático por Stripe, con aviso al cliente; si falla, queda como adeudo.
  - Con transferencia o efectivo: queda como **adeudo**; mientras exista, el cliente no puede reservar hasta pagarlo en
    sucursal (recepción lo marca pagado).
- El cliente acepta el cargo por inasistencia al reservar (texto en el paso final y en términos).
- Correo y push al cliente con el monto y cómo pagarlo.
- **Requiere migración: se pide el "sí" antes de desplegar.**

### Etapa 3 — Aviso previo al fin y extender tiempo
- El revisor pasa de cada 5 min a **cada minuto** (`appointments:notify-service-end`).
- Push al barbero 5 min antes del fin: **Terminar ya** / **+10 min** / **+15 min**.
- Endpoint de extensión: valida que no choque con la siguiente cita del barbero; si choca, avisa y no extiende;
  actualiza `hora_fin` y el cálculo de fin esperado; avisa al cliente y registra el cambio en el log de actividad.
- El aviso de "te pasaste" actual se conserva.

### Etapa 4 — Ticket al terminar
- Al pasar a `completada`: pantalla de ticket en web y app, y correo con comprobante y factura para **cualquier**
  método (tarjeta, transferencia verificada, efectivo cobrado). Reutiliza el comprobante actual.

### Etapa 5 — Pantallas (web y app)
- Agenda del barbero: Aprobar / Rechazar, Iniciar (deshabilitado con motivo si falta pago), No asistió, y tarjeta de
  tiempo restante con Terminar / +10 / +15.
- Recepción: lista de transferencias por verificar, cobro en efectivo antes de iniciar y adeudos por inasistencia.
- Cliente: estado de su cita en lenguaje claro ("esperando aprobación", "falta verificar tu transferencia"), aviso de
  adeudo y ticket.
- Rutas de la app y enlaces de correo nuevos dentro de la lista cerrada `PUSH_ROUTES` y de `/abrir`.

### Etapa 6 — Integración y verificación (antes de subir)
- Backend: `.\test.ps1` completo, Pint y Larastan.
- Web: ESLint, build y e2e de los flujos nuevos (Playwright).
- App: `testDebugUnitTest`, lint y `assembleDebug`.
- **Prueba de extremo a extremo con el celular y staging local** de los tres métodos de pago: reservar → aprobar →
  (verificar/cobrar) → iniciar → aviso previo → extender → terminar → ticket; y la ruta de inasistencia con comisión.
- Documentar en `MANUAL_USUARIO.md` y `DOCUMENTACION_TECNICA.md` (sin crear documentos duplicados).
- Solo con todo en verde: una rama por repo, PR, CI en verde, fusión y despliegue; la migración de la etapa 2 se
  confirma antes con el propietario.

## Riesgos y cuidados
- Efectivo + inasistencia no deja nada que cobrar: por eso el adeudo bloquea la siguiente reserva.
- Cobro automático de la comisión: exige aceptación previa del cliente y distinguir fallo de tarjeta de cargo disputado.
- Citas ya existentes: las reglas nuevas aplican solo a citas creadas después del despliegue o se migran con un valor
  por defecto seguro (pago resuelto = verdadero si ya estaba cobrada).
- Cambios en la máquina de estados afectan a web, app y dashboards: se cubren con la matriz de pruebas de la etapa 1.

## Notas de despliegue

Orden y requisitos para subir este cambio (no desplegar solo una parte):

1. **barber, frontend-urban y la app van juntos.** El backend ya bloquea «Iniciar» sin pago y cobrar deja de completar
   la cita; las pantallas viejas mostrarían el error del servidor. Primero barber, luego frontend, luego el APK.
2. **Migración** `2026_10_08_000000_create_no_show_fee_indexes` (solo crea dos índices en `no_show_fees`; no toca
   datos). El contenedor de staging migra al arrancar contra Atlas: requiere el «sí» del propietario.
3. **Scheduler cada minuto:** `appointments:notify-service-ending` se programa en `routes/console.php`. En staging no
   hay servicio `scheduler` aparte: el contenedor de `barber` corre `schedule:work` solo con `RUN_SCHEDULER=true`
   (ver «Tareas programadas» en `DESPLIEGUE_AWS_STAGING.md`). **Sin esa variable no sale el aviso de 5 minutos**;
   verificar su valor antes de la demo y no tener más de una tarea de `barber` corriendo el scheduler.
4. **Variables (opcionales):** `APPOINTMENT_START_MARGIN_MINUTES` (15), `APPOINTMENT_MAX_EXTRA_MINUTES` (60) y
   `APPOINTMENT_REQUIRE_NO_SHOW_ACCEPTANCE` (**false** al desplegar; pasar a **true** cuando web y app nuevas estén
   publicadas, porque las versiones viejas no mandan la casilla y no podrían reservar).
5. **Contrato OpenAPI:** `public/docs/openapi.yaml` regenerado con `php artisan scribe:generate` y copiado a
   `frontend-urban/contract/` con `npm run contract:sync` (el CI de ambos lo verifica).
6. **Stripe:** el cobro automático del cargo usa la tarjeta guardada fuera de sesión (`off_session`); puede pedir
   autenticación y entonces queda como adeudo. La clave restringida debe permitir *PaymentIntents* (Write); los
   reembolsos siguen necesitando *Charges and Refunds* (Write).
7. **Citas confirmadas sin pago al desplegar:** no podrán iniciarse hasta cobrarse. Revisar la agenda del día antes de
   desplegar.
8. **Push con botones:** el mensaje `service_ending` viaja solo como datos; hace falta el APK nuevo para ver los
   botones (las versiones viejas lo ignoran sin romperse).
