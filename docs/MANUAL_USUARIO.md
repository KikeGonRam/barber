# UrbanBlade — Manual de Usuario

Guía de uso del sistema de gestión de la barbería, organizada por rol. Cada rol
ve un menú distinto adaptado a lo que necesita hacer.

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

1. En el menú, ve a **Reservar cita**.
2. Sigue el asistente paso a paso: elige el **servicio**, el **barbero** (o
   "sin preferencia"), la **fecha** (los domingos no están disponibles) y la
   **hora**. Un resumen de tu selección queda siempre visible mientras avanzas,
   y puedes volver a cualquier paso anterior con un clic.
3. Opcionalmente puedes añadir **productos** a tu cita en el mismo asistente
   (por ejemplo, un producto de venta que quieras que te tengan listo).
4. Confirma. Tu cita queda en estado **pendiente** hasta que el barbero la
   apruebe — recibirás una notificación cuando eso ocurra.

### 2.2 Mis citas

- En **Mis citas** puedes ver el historial completo y el estado de cada una:
  pendiente, confirmada, en proceso, completada, cancelada o no asistida.
- Puedes **cancelar** una cita mientras no haya iniciado el servicio.
- Cuando confirman tu cita, te llega un correo con la opción de agregarla a tu
  calendario (Google Calendar o archivo `.ics`).

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
  atender.
- Un botón de acción rápida en cada cita te lleva directo al siguiente paso
  según su estado (aprobar, iniciar servicio, marcar como completada).

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
- Al completar el servicio puedes **cobrar en un solo paso**: se registra el
  pago, la propina y automáticamente se generan los puntos de lealtad del
  cliente.
- Se genera un recibo/factura en PDF que puedes reenviar o imprimir.

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
