# Manual funcional de UrbanBlade

Guía de funciones del producto. La interfaz web vive en el frontend y los
permisos se validan en la API. Revisión: 28 de octubre de 2026.
Los nombres y la disposición de botones pueden variar con la versión instalada.

## Acceso y recuperación

Ingresar desde la dirección proporcionada por el responsable.
El entorno local usa el frontend en el puerto 3000.
El registro público y Google crean cuentas de cliente; el personal se incorpora
mediante administración autorizada. La recuperación utiliza el flujo del producto,
no contraseñas compartidas en documentación.

Consulta [ACCESOS.md](ACCESOS.md).

## Cliente

- Consultar catálogo y disponibilidad, reservar y seguir sus citas.
- Consultar historial, recibos, pedidos, puntos y membresía.
- Comprar productos, paquetes o regalos cuando estén habilitados.
- Gestionar preferencias y datos de su cuenta.
- Participar en reseñas y comentarios conforme a las reglas de moderación.

La reserva puede requerir condiciones adicionales, depósito o aceptación de
políticas según el entorno. Un pago pendiente y una cita pendiente no significan
lo mismo. La aplicación informa las condiciones de cancelación e inasistencia;
no se fija aquí un importe que pueda variar por configuración.

## Barbero

- Consultar su agenda y responder solicitudes de cita.
- Iniciar o completar servicios cuando las reglas lo permitan.
- Consultar perfil, horario, portafolio y desempeño propios.
- Atender avisos de tiempo y gestionar extensiones autorizadas.

Una cita confirmada puede no poder iniciarse si todavía no corresponde su horario
o falta resolver el pago. Cobrar no equivale siempre a completar el servicio.

## Recepción

- Gestionar agenda, atención y clientes dentro de sus permisos.
- Registrar/revisar cobros y comprobantes según el método.
- Gestionar pedidos y entrega.
- Consultar información operativa del turno y los recibos necesarios.

No debe modificar un precio enviado por el navegador como forma de saltarse
reglas de servicio, puntos, descuentos o saldo: el backend calcula el importe.

## Administración

- Gestionar usuarios, roles, perfiles, servicios y configuración.
- Gestionar productos, inventario, pedidos y operaciones autorizadas.
- Consultar reportes y analítica.
- Administrar campañas, moderación, planes o paquetes cuando estén habilitados.

Los cambios de configuración pueden afectar reservas, pagos y notificaciones.
Revisar su impacto antes de guardar; la interfaz no concede autorización para
cambiar infraestructura o credenciales externas.

## Ingeniero

Consultar los reportes, logs y estado del sistema que permitan sus permisos.
No se considera un administrador de negocio ni puede modificar recursos por
tener acceso a observabilidad.

## Pagos, recibos y membresías

Los métodos de cobro incluyen efectivo, transferencia y tarjeta mediante Stripe.
Tarjetas guardadas, depósitos, membresías y reembolsos siguen flujos y reglas
propios. Una transferencia puede necesitar revisión antes de considerarse pagada.
Un webhook de proveedor puede completar la conciliación de forma asíncrona.

Los PDF de recibos/reportes de UrbanBlade no se presentan como CFDI.
Las invoices externas de una suscripción no convierten el recibo de la aplicación
en una factura fiscal.

## Notificaciones y ayuda

La entrega depende de preferencias, canales configurados, workers, permisos del
dispositivo y disponibilidad del proveedor. No se garantiza por mostrar un mensaje
de operación exitosa.

Si una operación falla, comunicar rol, acción, fecha y mensaje sin incluir
contraseñas, tokens, comprobantes ni datos de otras personas.
Reportar vulnerabilidades por el [canal privado](../../SECURITY.md).

El módulo académico Spark es un consumidor separado; su panel no es requisito
para usar la agenda o los pagos de la aplicación.
