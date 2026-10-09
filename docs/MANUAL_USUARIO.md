# UrbanBlade — Manual de Usuario

Guía de uso del sistema de gestión de la barbería, organizada por rol. Cada rol
ve un menú distinto adaptado a lo que necesita hacer.

> **Nota de vigencia (2026-09-09):** la interfaz que describe este manual vive hoy en
> `frontend-urban` (Nuxt), no en el panel Blade original de este repo (retirado el
> 2026-09-06 — ver `.claude/skills/urbanblade-guardrails/SKILL.md`, guardrail #18). La
> sección 2.1 (reservar cita) ya se verificó contra la pantalla actual. El resto de
> este manual describe funcionalidad que sigue existiendo en Nuxt, pero el detalle
> paso a paso (nombres exactos de botones, orden de campos) no se ha vuelto a
> verificar pantalla por pantalla desde la migración — si algo no coincide con lo que
> ves en la app, confía en la app y avisa para corregir este documento.

---

## 1. Ingresar al sistema

1. Abre la dirección de la barbería en tu navegador.
2. Pulsa **Acceso** (esquina superior derecha) e introduce tu correo y
   contraseña.
3. Si eres cliente nuevo, pulsa **Reservar** en la página principal para crear
   tu cuenta.
4. Al entrar verás tu panel principal, distinto según tu rol: Administrador,
   Recepción, Barbero o Cliente.

---

## 2. Rol: Cliente

### 2.1 Reservar una cita

1. En **Mis Citas**, pulsa **Reservar nueva cita** (o entra desde la ficha de un
   barbero con **Reservar con [nombre]**, que ya trae ese barbero elegido).
2. Elige **barbero**, **servicio** y **fecha**. En cuanto los tres están
   completos, el campo de hora solo te ofrece los horarios realmente libres de
   ese barbero ese día — si no queda ninguno, te lo dice en vez de dejarte
   elegir cualquier hora.
3. Marca la casilla **«Acepto el cargo por inasistencia»** (ver 2.6) y confirma.
   Tu cita queda en estado **pendiente** hasta que el barbero la apruebe —
   recibirás una notificación cuando eso ocurra.
4. Si tienes un **adeudo por inasistencia** pendiente, no podrás reservar hasta
   pagarlo en la barbería (el sistema te dice cuánto es).

> La compra de productos (por ejemplo, algo que quieras que te tengan listo) es
> independiente de la reserva: se hace desde **Tienda**/**Carrito**, no dentro
> del formulario de la cita.

### 2.2 Mis citas

- En **Mis citas** puedes ver el historial completo y el estado de cada una:
  pendiente, confirmada, en proceso, completada, cancelada o no asistida.
- Puedes **cancelar** una cita mientras no haya iniciado el servicio.
- Cuando confirman tu cita, te llega un correo con la opción de agregarla a tu
  calendario (Google Calendar o archivo `.ics`).
- Cada estado te dice qué sigue: *pendiente* (espera la aprobación del
  barbero), *confirmada* (aprobada; debe pagarse antes de que empiece el
  servicio), *en proceso* y *completada*.
- **Cómo pagar una cita aprobada:** con **tarjeta** desde la app o la web;
  con **transferencia**, subiendo tu comprobante (recepción lo verifica); o en
  **efectivo**, en recepción al llegar. El barbero no puede iniciar el servicio
  hasta que el pago esté resuelto.
- Al terminar el servicio recibes por correo tu **comprobante y tu factura**, y
  puedes abrir el **ticket** desde *Ver ticket* en tu historial.

### 2.6 Cargo por inasistencia

- Si no llegas a tu cita y el barbero la marca como *no asistió*, se genera un
  cargo (por defecto el 50 % del servicio; lo configura la barbería).
- Si tienes una **tarjeta guardada**, se cobra sola y te avisamos. Si no se
  puede, queda como **adeudo**: debes pagarlo en la barbería (efectivo o
  transferencia) para volver a reservar. Si ya habías pagado por adelantado,
  eso se descuenta.
- Para evitarlo, **cancela a tiempo** (hasta el plazo de tu política de
  cancelación) o reagenda.

### 2.3 Tienda

- En **Tienda** puedes ver el catálogo de productos, agregarlos al carrito y
  hacer un pedido independiente de cualquier cita.
- En **Mis pedidos** ves el estado de tus compras (pendiente / entregado /
  cancelado). Recibes una notificación cuando tu pedido está listo para
  recoger en recepción.

### 2.4 Tarjeta de socio y puntos

- Tu panel principal muestra tu **tarjeta de membresía** con tu nivel actual
  (Nuevo, Regular, VIP, Leyenda) y tus puntos de fidelidad, que suman
  automáticamente cada vez que completas una cita.
- Toca la tarjeta para voltearla y ver tu **código QR** de identificación, o
  descárgala en PDF.

### 2.5 Muro de inspiración

- Explora los trabajos que publican los barberos, deja comentarios y
  reacciones — útil para elegir estilo y barbero antes de reservar.

---

## 3. Rol: Barbero

### 3.1 Mi agenda

- Tu panel muestra las **citas del día** con vista de línea de tiempo.
- Las citas nuevas llegan en estado **pendiente**: debes **aprobar o
  rechazar** cada una desde tu agenda antes de que se puedan cobrar o
  atender (recepción y administración pueden aprobar como respaldo).
- Un botón de acción rápida en cada cita te lleva directo al siguiente paso
  según su estado (aprobar, iniciar servicio, marcar como completada).
- **Iniciar servicio** solo funciona **el día de la cita** (desde 15 minutos
  antes de su hora) y **con el pago resuelto** (tarjeta cobrada, transferencia
  verificada o efectivo cobrado en recepción). Si no se puede, el botón queda
  deshabilitado y la cita te explica por qué.
- Si el cliente no llegó, marca **No asistió**: se le genera el cargo por
  inasistencia (ver 2.6). Esto no lo hace el sistema solo.

### 3.1.1 Servicio en curso: aviso y tiempo extra

- Mientras el servicio está en proceso ves cuánto tiempo queda.
- **5 minutos antes** de que termine recibes una notificación con tres botones:
  **Terminar ya**, **+10 min** y **+15 min**. Funcionan sin abrir la app.
- Al agregar tiempo se avisa al cliente. Si el nuevo fin choca con la siguiente
  cita, la app te pregunta si extiendes de todos modos (y avisa al siguiente
  cliente que podría empezar tarde). El máximo es 60 minutos extra por cita.
- Al terminar el servicio ves el **ticket** y el cliente recibe su comprobante
  y factura por correo.

### 3.2 Historial y propinas

- Puedes ver tu historial de citas completadas y el total de propinas
  recibidas en tu panel principal.

### 3.3 Publicar en el muro

- Desde tu perfil puedes subir fotos de tus trabajos al **muro de
  inspiración**, para que los clientes los vean, comenten y reaccionen —
  es tu portafolio dentro de la app.

---

## 4. Rol: Recepción

### 4.1 Gestión de citas

- Ves todas las citas del día de todos los barberos, con su estado.
- Puedes registrar una cita rápida de **walk-in** (cliente sin cita previa
  que llega directo al local).

### 4.2 Cobro

- El cobro solo está disponible para citas ya **aprobadas** (confirmada, en
  proceso o completada) — nunca para una cita pendiente sin revisar por el
  barbero.
- **El pago se resuelve antes de iniciar el servicio.** Cobra en efectivo (o con
  tarjeta) cuando el cliente llega, o verifica su transferencia en *Comprobantes
  por revisar*. Cobrar **ya no completa la cita**: la inicia y la termina el
  barbero, y los puntos de lealtad se dan al terminar.
- Mientras el pago no esté resuelto, «Iniciar» aparece bloqueado en la cita
  con el motivo.
- Se genera un recibo en PDF al cobrar; el **correo con comprobante y factura**
  le llega al cliente cuando el servicio termina.

### 4.2.1 Adeudos por inasistencia

- En *Comprobantes por revisar* aparece **Adeudos por inasistencia**: clientes
  que no llegaron a su cita y a quienes no se pudo cobrar en su tarjeta.
- Cobra el adeudo en **efectivo** o **transferencia**; el cliente puede volver
  a reservar de inmediato. Los cobros entran al **corte de caja** del día.
- Solo **administración** puede **condonar** un cargo, y debe escribir el
  motivo (queda registrado).

### 4.3 Bandeja de pedidos

- Todos los pedidos de tienda (y los add-ons de producto agregados dentro de
  una reserva) llegan a tu **bandeja de pedidos**.
- Al marcar un pedido como **entregado**, el cliente recibe una notificación
  automática y se genera su recibo.

### 4.4 Panel de resumen

- Tu panel principal muestra de un vistazo: pedidos pendientes por entregar,
  total cobrado en el día, y acceso directo al registro de walk-in.

---

## 5. Rol: Administrador

### 5.1 Panel principal

El panel del administrador está organizado en tres zonas:

1. **Resumen del negocio** — KPIs del día/mes: citas, ingresos, pedidos.
2. **Gestión** — accesos directos a usuarios, barberos, servicios, productos,
   configuración del negocio.
3. **Analítica avanzada** (sección plegable) — un widget con los insights
   generados por el módulo de Spark (ver más abajo), directamente en el
   dashboard de la app.

### 5.2 Gestión de usuarios y roles

- Desde **Usuarios** puedes crear/editar cuentas de recepción y barberos,
  y asignarles su rol.
- Desde **Barberos** administras el catálogo de barberos, su especialidad y su
  horario semanal.

### 5.3 Servicios, productos e inventario

- **Servicios**: catálogo de cortes/tratamientos con precio y duración.
- **Inventario**: catálogo de productos (de venta al cliente o de uso interno
  del barbero), con alertas cuando el stock baja del mínimo configurado —
  recibirás una notificación automática si algún producto necesita reorden.

### 5.4 Reportes e ingresos

- La sección de **Reportes** consolida los ingresos por citas y por tienda,
  con filtros por fecha, barbero y servicio.

### 5.5 Campañas de marketing

- Desde **Campañas** puedes redactar una promoción y enviarla ahora o
  programarla para una fecha futura.
- Cada campaña muestra métricas de **apertura y clics** una vez enviada, para
  medir su efectividad.

### 5.6 Configuración del negocio

- Horario de apertura/cierre, política de cancelación y demás ajustes
  generales se administran desde **Configuración**.

### 5.7 Analítica avanzada (módulo Spark)

Además del resumen del día a día, la barbería cuenta con un **dashboard de
analítica** aparte (construido con el módulo académico Spark), pensado para
decisiones de negocio de más largo plazo. Se accede por separado (lo ejecuta
quien administra el sistema) y ofrece, entre otras cosas:

- **Predicción de demanda**: qué horarios y días tienen más movimiento, para
  planear turnos de personal.
- **Fidelización**: cómo se distribuyen los puntos y niveles de los clientes.
- **Utilización de barberos**: quién tiene agenda saturada y quién tiene
  disponibilidad.
- **Inventario**: márgenes de los productos de venta y alertas de reorden.
- **Tienda y pedidos**: qué tan bien convierten los add-ons de producto dentro
  de una cita frente a la tienda suelta, y qué productos se venden más.
- **Muro social**: qué barberos generan más interacción con sus publicaciones,
  y si eso se relaciona con más citas.
- **Segmentación de clientes y recomendación**: agrupa clientes con hábitos
  similares y sugiere qué servicios/productos ofrecerles.

Este panel es de solo lectura sobre los mismos datos de la app — no se edita
nada desde ahí, solo se consulta para tomar decisiones.

---

## 6. Notificaciones

Todos los roles reciben notificaciones automáticas por correo (y dentro de la
app, en el ícono de campana) para eventos relevantes: confirmación o
cancelación de cita, recordatorio antes de la cita, recibo de pago, pedido
listo para entregar, y solicitud de reseña después de tu visita. Puedes ajustar
qué notificaciones recibir (incluyendo promociones) desde tu **perfil →
preferencias de notificación**.

---

## 7. ¿Problemas para entrar o usar el sistema?

- Si olvidaste tu contraseña, usa la opción **¿Olvidaste tu contraseña?** en la
  pantalla de acceso.
- Si algo no carga o ves un error, contacta al administrador del sistema con
  una captura de pantalla — ayuda mucho a diagnosticar el problema rápido.
