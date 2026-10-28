# Análisis y Requerimientos

Problema, alcance, actores, requisitos funcionales y no funcionales, reglas de negocio y trazabilidad de UrbanBlade.

| Dato | Valor |
| --- | --- |
| Documento | Análisis y Requerimientos de UrbanBlade |
| Versión | 2.0 · 28 de octubre de 2026 |
| Audiencia | Docentes, Product Owner, equipo de desarrollo y QA |
| Fuente | Product Backlog (UBv2, 41 historias de usuario) y el código de `barber` y `frontend-urban` |
| Clasificación | Uso académico e interno |

## 1. Contexto del proyecto

UrbanBlade es un **sistema de gestión de citas para barbería**. Se desarrolla como proyecto integrador de la **Universidad Tecnológica del Valle de Toluca (UTVT)**, programa de **Ingeniería en Desarrollo y Gestión de Software (IDGS)**, con metodología **Scrum** y estimación en escala Fibonacci (1, 3, 5), donde un punto de historia equivale a 8 horas de trabajo.

| Integrante | Rol en el proyecto |
| --- | --- |
| Alan Ruiz Vilchis | Product Owner y desarrollador backend |
| Miguel Ángel Mena Garduño | QA y desarrollador frontend |
| Elías García Nolasco | Scrum Master y desarrollador frontend |
| Luis Enrique González Ramírez | Desarrollador full stack y móvil |
| María Isabel Cruz Flores | Desarrolladora backend y administradora de base de datos |

## 2. Problema y objetivos

### 2.1 Problema

Una barbería administra sus citas, cobros, inventario y clientes frecuentes con herramientas dispersas (agenda en papel, mensajes, hojas de cálculo). Eso produce **citas duplicadas o perdidas, cobros sin control, faltas de producto sin aviso y ninguna visibilidad del negocio**.

### 2.2 Objetivo general

Entregar una plataforma web y móvil que permita **reservar, atender y cobrar citas**, vender productos, fidelizar a los clientes y dar al negocio información para decidir, con una API única y segura.

### 2.3 Objetivos específicos

1. Permitir que el cliente reserve en línea con disponibilidad real.
2. Dar a barberos y recepción una agenda operativa con estados de cita claros.
3. Registrar pagos (efectivo, transferencia y tarjeta) y conciliarlos con la cita.
4. Controlar inventario y pedidos de tienda.
5. Fidelizar con niveles, puntos, membresías y sorteos.
6. Ofrecer reportes y analítica al administrador.
7. Proteger los datos con roles, autenticación y validación en el servidor.

## 3. Alcance

### 3.1 Incluido

| Área | Qué abarca |
| --- | --- |
| Citas y agenda | Disponibilidad, reserva, estados, lista de espera, enlaces de gestión, avisos de servicio. |
| Cobros | Efectivo, transferencia, tarjeta (Stripe), propinas, depósitos, cargos por inasistencia, cierre de caja. |
| Tienda e inventario | Catálogo, carrito, pedidos, movimientos y alertas de stock. |
| Fidelización | Niveles, puntos, membresías, paquetes, gift cards, referidos y sorteo mensual. |
| Comunicación | Correo, push (web y Android), SMS y WhatsApp opcionales, campañas, asistente virtual. |
| Social | Portafolio de barberos con reacciones, comentarios y moderación. |
| Administración | Usuarios, roles, servicios, configuración, reportes, analítica, bitácora. |
| Plataformas | Web instalable (Nuxt), aplicación Android nativa y API Laravel. |

### 3.2 Fuera de alcance

- Facturación fiscal (**CFDI**): los recibos de la aplicación no son facturas fiscales.
- Producción con datos reales: el entorno desplegado es de **pruebas (staging)**.
- Pagos reales de tarjeta: Stripe opera en modo de pruebas.
- Aplicación iOS: los repositorios solo contienen web y Android.

## 4. Actores y roles

| Rol | Descripción | Alcance |
| --- | --- | --- |
| Cliente | Persona que reserva y compra. | Sus propias citas, pagos, pedidos, recibos y beneficios. |
| Barbero | Profesional que atiende. | Su agenda, horario, portafolio y desempeño. |
| Recepcionista | Opera el día a día. | Agenda, clientes, cobros, pedidos e inventario de turno. |
| Administrador | Dueño o gerente. | Configuración y gestión completa del negocio. |
| Ingeniero | Soporte técnico. | Solo lectura de estado, reportes y bitácoras. |
| Sistemas externos | Stripe, Google, correo, push, Twilio, IA local. | Se integran por la API. |

## 5. Requisitos funcionales

Los requisitos nacen de las **41 historias de usuario** del Product Backlog, agrupadas en 18 épicas. Cada requisito funcional (RF) se traza a su historia (HU).

| RF | Requisito | HU |
| --- | --- | --- |
| RF-01 | El sistema debe registrar clientes con correo, teléfono y contraseña, y verificar el correo. | HU-01 |
| RF-02 | El sistema debe autenticar con token Bearer y por Google, y permitir recuperar la contraseña. | HU-02, HU-03 |
| RF-03 | El cliente debe consultar el catálogo de servicios y barberos. | HU-04, HU-06 |
| RF-04 | El administrador debe administrar servicios y barberos. | HU-05, HU-07 |
| RF-05 | El barbero debe configurar su horario y el sistema calcular la disponibilidad real. | HU-08, HU-09 |
| RF-06 | El cliente debe reservar, consultar y cancelar sus citas. | HU-10, HU-11, HU-12 |
| RF-07 | El personal debe administrar citas bajo una máquina de estados válida. | HU-13 |
| RF-08 | El usuario debe consultar y editar su perfil. | HU-14, HU-15 |
| RF-09 | El sistema debe enviar recordatorios de cita. | HU-16 |
| RF-10 | El sistema debe registrar el pago de la cita (efectivo, transferencia y tarjeta). | HU-17 |
| RF-11 | El administrador debe administrar clientes, usuarios y roles. | HU-18, HU-19 |
| RF-12 | El administrador debe contar con un panel de indicadores. | HU-20 |
| RF-13 | El sistema debe generar reportes de citas e ingresos. | HU-21, HU-22 |
| RF-14 | El sistema debe proteger la información y validar los datos. | HU-23, HU-24 |
| RF-15 | Debe existir una aplicación móvil Android y una plataforma web. | HU-25, HU-26 |
| RF-16 | La web, Android y el backend deben comunicarse por una API y persistir en MongoDB. | HU-27, HU-28 |
| RF-17 | El sistema debe contar con pruebas funcionales y de integración. | HU-29, HU-30 |
| RF-18 | El sistema debe prepararse para producción y estar documentado. | HU-31, HU-32 |
| RF-19 | La infraestructura debe codificarse con Terraform. | HU-33 a HU-37 |
| RF-20 | Debe existir integración y entrega continuas. | HU-38 a HU-41 |

Además, el producto implementa capacidades que **crecieron durante los sprints** y que se documentan en el Manual Técnico: lealtad y niveles, membresías, paquetes y gift cards, referidos, sorteo, tienda y pedidos, campañas, asistente virtual, muro social con moderación, lista de espera y cierre de caja.

## 6. Reglas de negocio

| ID | Regla |
| --- | --- |
| RN-01 | Una cita nueva queda **pendiente** hasta ser confirmada. |
| RN-02 | Estados válidos: pendiente → confirmada o cancelada; confirmada → en proceso, completada, no asistió o cancelada; en proceso → completada o cancelada. Completada, cancelada y no asistió son terminales. |
| RN-03 | Solo se **cobra** una cita confirmada, en proceso o completada. Cobrar no equivale a completar. |
| RN-04 | **Iniciar** un servicio exige fecha y margen válidos y el pago resuelto. |
| RN-05 | Los **importes, descuentos, puntos y saldos los calcula el servidor**; el valor que envía el navegador no es fuente de verdad. |
| RN-06 | El **nivel de lealtad** sube por citas completadas (5, 10 y 20) con descuentos de 5, 10 y 15 %. |
| RN-07 | Una cita completada da 10 puntos y una reseña 5; el canje cubre hasta el 50 % del total. |
| RN-08 | Sin citas completadas por 180 días baja un nivel; a los 365 días caducan los puntos. |
| RN-09 | Un pedido no recogido en 3 días se cancela y el stock vuelve al inventario. |
| RN-10 | El registro público y Google solo crean clientes; los roles de personal los asigna un administrador. |
| RN-11 | Cada recurso se valida por **pertenencia**: un cliente solo ve sus citas, pagos y recibos. |
| RN-12 | Los comprobantes de pago se sirven con URL temporal; no son públicos. |

## 7. Requisitos no funcionales

| Categoría | Requisito | Cómo se atiende |
| --- | --- | --- |
| Seguridad | Autenticación, autorización y protección de datos. | Tokens con hash SHA-256, roles y permisos, validación de pertenencia, bloqueo de operadores Mongo, CSP y HSTS en el frontend. |
| Privacidad | Consentimiento para analítica. | Banner de cookies que carga analítica solo tras aceptar. |
| Rendimiento | Respuesta ágil en operaciones frecuentes. | Consultas acotadas, caché, imágenes optimizadas, registro de peticiones lentas. |
| Disponibilidad | Servicio continuo en pruebas. | Contenedores con verificación de salud y reposición automática. |
| Usabilidad | Interfaz clara por rol y adaptable. | Menú por rol, cuatro temas, diseño responsive, PWA instalable. |
| Accesibilidad | Controles con nombre accesible y foco visible. | Criterios de interfaz y auditoría UI/UX. |
| Compatibilidad | Web moderna y Android. | Nuxt 4 con PWA y aplicación nativa Kotlin. |
| Mantenibilidad | Código modular y probado. | Servicios por dominio, pruebas UT, SIT, UAT y OAT, CI en cada PR. |
| Trazabilidad | Registro de acciones. | Bitácora de actividad (`activitylog`) y Pulse. |
| Portabilidad | Entornos reproducibles. | Docker Compose para desarrollo y pruebas; imágenes de staging. |

## 8. Restricciones y supuestos

- **Tecnológicas:** API en Laravel 13 (PHP 8.3+), web en Nuxt 4, Android en Kotlin y datos en MongoDB.
- **Académicas:** seis sprints entre el 14 de septiembre y el 4 de diciembre de 2026.
- **Datos:** la base de datos operativa es compartida entre web y Android a través de la misma API.
- **Despliegue:** el entorno disponible es de pruebas en AWS; no hay producción.
- **Supuesto:** el proveedor de pagos opera en modo de pruebas y los datos de ejemplo son ficticios.

## 9. Casos de uso principales

| Caso de uso | Actor | Resultado |
| --- | --- | --- |
| CU-01 Reservar una cita | Cliente | Cita pendiente con barbero, servicio y hora libres. |
| CU-02 Confirmar y atender | Barbero o recepción | La cita pasa a confirmada, en proceso y completada. |
| CU-03 Cobrar | Recepción o cliente | Pago registrado y asociado a la cita; recibo emitido. |
| CU-04 Comprar en la tienda | Cliente | Pedido creado, stock descontado, entrega en recepción. |
| CU-05 Acumular beneficios | Cliente | Puntos, nivel y descuento actualizados al completar citas. |
| CU-06 Cerrar la caja | Recepción | Cierre del turno con los cobros del día. |
| CU-07 Analizar el negocio | Administrador | Reportes, indicadores y hallazgos automáticos. |
| CU-08 Revisar el estado técnico | Ingeniero | Consulta de salud, bitácoras y rendimiento. |

## 10. Trazabilidad

| Épica | Historias | Módulos del backend | Pruebas de referencia |
| --- | --- | --- | --- |
| EP-01 Autenticación y usuarios | HU-01 a HU-03 | `Auth`, `MobileApiToken` | `AuthApiTest`, `ApiTokenSecurityTest` |
| EP-02, EP-03 Servicios y barberos | HU-04 a HU-07 | `Service`, `Barber` | `ServiceManagementApiTest`, `BarberManagementApiTest` |
| EP-04, EP-05 Horarios y citas | HU-08 a HU-13 | `Appointment*Service` | `AvailabilityApiTest`, `AppointmentApiTest`, `AppointmentStartRulesTest` |
| EP-06 Perfil | HU-14, HU-15 | `Profile` | `ProfileApiTest` |
| EP-07 Notificaciones | HU-16 | `Notifications`, `MessagingService` | `AppointmentNotificationChannelsTest`, `PushApiTest` |
| EP-08 Pagos | HU-17 | `Payment*Service` | `PaymentApiTest`, `StripeWebhookControllerTest` |
| EP-09, EP-10 Administración y reportes | HU-18 a HU-22 | `Admin`, `ReportService`, `DashboardService` | `ClientAdminApiTest`, `DashboardApiTest`, `ReportDocumentsApiTest` |
| EP-11 Seguridad | HU-23, HU-24 | Middleware, `Requests` | `RoleAuthorizationTest`, `MaliciousInputTest` |
| EP-12, EP-13 Móvil y web | HU-25, HU-26 | Repos `UrbanBladeMobile` y `frontend-urban` | Playwright, Cypress, Vitest |
| EP-14 Integración y base de datos | HU-27, HU-28 | `config/database.php`, contrato OpenAPI | `ApiContractTest`, `DataEnvironmentGuardTest` |
| EP-15, EP-16 Calidad y documentación | HU-29 a HU-32 | `tests/`, `docs/` | Suite completa (UT, SIT, UAT, OAT) |
| EP-17, EP-18 Terraform y CI/CD | HU-33 a HU-41 | Repo de Terraform, `.github/workflows` | Pipeline de CI |

## 11. Estado del avance

El Product Backlog UBv2 sumaba **41 historias, 145 tareas y 247 puntos**, con 173 puntos completados (70 %) en la revisión del 23 de septiembre de 2026. Las épicas de Terraform (EP-17) y CI/CD (EP-18) eran las de menor avance en ese corte.

> **Nota** El avance cambia cada sprint. La fuente vigente es el libro de Product Backlog del equipo en la carpeta `Documentacion/`; este capítulo conserva el corte indicado, no una cifra actual.

## 12. Riesgos

| Riesgo | Efecto | Mitigación |
| --- | --- | --- |
| Entorno solo de pruebas | No hay evidencia con carga o datos reales. | Documentar el alcance y no presentar staging como producción. |
| Dependencia de proveedores | Stripe, Google o correo pueden fallar. | Estados explícitos, conciliación por webhook y reintentos. |
| Escalar la API a varias réplicas | El planificador y los límites de intentos se duplican por réplica. | `onOneServer()` con un almacén compartido (`SCHEDULE_CACHE_STORE`) y `CACHE_LIMITER_STORE=mongodb`. |
| Respaldos no ensayados | Recuperación incierta. | Ensayar restauraciones en un destino aislado. |

## 13. Anexo: historias de usuario y criterios de aceptación

Las siguientes historias provienen del Product Backlog UBv2. El estado corresponde al corte del 23 de septiembre de 2026.

### EP-01 · Autenticación y usuarios

**HU-01 · Registro de usuario** (6 puntos, Completada)

Como cliente, quiero registrarme en Urban Blade, para poder utilizar el sistema y reservar citas.

Criterios de aceptación:

- El formulario solicita nombre, correo, teléfono y contraseña, y valida los campos obligatorios.
- No se permite registrar un correo ya existente.
- El usuario queda guardado en MongoDB con el rol Cliente y se muestra un mensaje de confirmación.

**HU-02 · Inicio de sesión** (5 puntos, Completada)

Como usuario, quiero iniciar sesión, para acceder a las funciones correspondientes de Urban Blade.

Criterios de aceptación:

- Con credenciales válidas la API devuelve un token Bearer y el usuario accede a las funciones de su rol.
- Con credenciales inválidas se muestra un mensaje de error sin revelar qué dato es incorrecto.
- La sesión se conserva hasta que el usuario cierra sesión o el token expira.

**HU-03 · Recuperación de contraseña** (3 puntos, Completada)

Como usuario, quiero recuperar mi contraseña, para poder ingresar nuevamente a mi cuenta.

Criterios de aceptación:

- El usuario solicita la recuperación con su correo registrado.
- El sistema envía un enlace con token de vigencia limitada.
- La nueva contraseña se valida y permite iniciar sesión; la anterior deja de funcionar.

### EP-02 · Servicios

**HU-04 · Consultar servicios** (3 puntos, Completada)

Como cliente, quiero consultar los servicios disponibles, para conocer sus precios y duración.

Criterios de aceptación:

- Se listan los servicios activos con nombre, precio y duración.
- La información se obtiene de la API.
- Si no hay servicios, se muestra un mensaje informativo.

**HU-05 · Administrar servicios** (5 puntos, Completada)

Como administrador, quiero administrar los servicios de la barbería, para mantener actualizada su información.

Criterios de aceptación:

- El administrador puede crear, editar, desactivar y eliminar servicios.
- Precio y duración se validan como valores positivos.
- Los cambios se reflejan de inmediato en la consulta del cliente.

### EP-03 · Barberos

**HU-06 · Consultar barberos** (3 puntos, Completada)

Como cliente, quiero consultar los barberos disponibles, para seleccionar al barbero de mi preferencia.

Criterios de aceptación:

- Se listan los barberos activos con nombre, foto y especialidad.
- El cliente puede seleccionar un barbero para continuar con la reserva.
- Los barberos inactivos no se muestran.

**HU-07 · Administrar barberos** (5 puntos, Completada)

Como administrador, quiero administrar la información de los barberos, para mantener actualizado el personal disponible.

Criterios de aceptación:

- El administrador puede crear, editar y desactivar barberos.
- Se valida la información obligatoria y el estado (activo/inactivo).
- Un barbero desactivado deja de aparecer para reservas nuevas.

### EP-04 · Horarios y agenda

**HU-08 · Configurar horarios** (6 puntos, Completada)

Como administrador, quiero configurar los horarios de atención, para establecer la disponibilidad de la barbería.

Criterios de aceptación:

- El administrador define días laborales, horario de apertura y cierre y descansos por barbero.
- No se permiten horarios con hora final menor o igual a la inicial.
- La configuración se guarda en MongoDB y se usa para calcular la disponibilidad.

**HU-09 · Consultar disponibilidad** (5 puntos, Completada)

Como cliente, quiero consultar la disponibilidad de horarios, para seleccionar un horario libre para mi cita.

Criterios de aceptación:

- Solo se muestran horarios dentro del horario laboral del barbero.
- Los horarios ocupados por citas existentes no se ofrecen.
- La disponibilidad considera la duración del servicio seleccionado.

### EP-05 · Citas

**HU-10 · Reservar cita** (6 puntos, Completada)

Como cliente, quiero reservar una cita, para obtener un horario para recibir un servicio de barbería.

Criterios de aceptación:

- El cliente selecciona servicio, barbero, fecha y horario disponible.
- El backend rechaza citas duplicadas, traslapadas o fuera del horario laboral.
- La cita se registra en MongoDB y se muestra una confirmación con sus datos.

**HU-11 · Consultar citas** (3 puntos, Completada)

Como cliente, quiero consultar mis citas, para conocer los servicios, fechas y horarios reservados.

Criterios de aceptación:

- El cliente ve sus citas próximas e históricas.
- Cada cita muestra fecha, horario, servicio, barbero y estado.
- Un cliente no puede consultar citas de otro usuario.

**HU-12 · Cancelar cita** (3 puntos, Completada)

Como cliente, quiero cancelar una cita, para poder liberar un horario que ya no utilizaré.

Criterios de aceptación:

- El cliente puede cancelar solo sus propias citas.
- Se aplica la política de anticipación mínima para cancelar.
- La cita cambia a estado Cancelada y el horario vuelve a estar disponible.

**HU-13 · Administrar citas** (5 puntos, Completada)

Como administrador, quiero administrar las citas, para controlar y organizar la agenda de la barbería.

Criterios de aceptación:

- El administrador consulta las citas con filtros por fecha, barbero y estado.
- Puede confirmar, reprogramar o cancelar citas.
- Los cambios respetan las mismas validaciones de disponibilidad.

### EP-06 · Perfil

**HU-14 · Consultar perfil** (1 puntos, Completada)

Como usuario, quiero consultar mi perfil, para revisar la información registrada en el sistema.

Criterios de aceptación:

- El usuario autenticado ve su nombre, correo, teléfono y rol.
- La información se obtiene de la API.
- Un usuario no puede consultar el perfil de otro.

**HU-15 · Editar perfil** (3 puntos, Completada)

Como usuario, quiero editar mi perfil, para mantener actualizada mi información personal.

Criterios de aceptación:

- El usuario puede modificar sus datos personales.
- Los datos se validan antes de guardarse.
- Se muestra la confirmación y los cambios persisten al volver a iniciar sesión.

### EP-07 · Notificaciones

**HU-16 · Recordatorio de citas** (11 puntos, En progreso)

Como usuario, quiero recibir recordatorios de mis citas, para evitar olvidar la fecha y hora de mi servicio.

Criterios de aceptación:

- Se envía un recordatorio 24 h y 2 h antes de la cita con fecha, hora, servicio y barbero.
- Las citas canceladas no generan recordatorio y el envío queda registrado para evitar duplicados.
- La app Android recibe el recordatorio como notificación push.

### EP-08 · Pagos

**HU-17 · Pago de la cita** (7 puntos, En progreso)

Como cliente, quiero pagar mi cita en efectivo, por transferencia o con tarjeta (Stripe en modo prueba), para dejar registrado el pago asociado a mi cita.

Criterios de aceptación:

- El cliente paga en efectivo, por transferencia o con tarjeta (Stripe en modo prueba).
- El monto se toma del precio del servicio en el servidor, no del cliente.
- El pago queda asociado a la cita y la cita muestra su estado de pago.

### EP-09 · Administración

**HU-18 · Administrar clientes** (6 puntos, Completada)

Como administrador, quiero administrar los clientes, para consultar y mantener actualizada la información de los usuarios.

Criterios de aceptación:

- El administrador lista, busca y consulta el detalle de los clientes.
- Puede actualizar o desactivar clientes.
- Las consultas usan índices y responden de forma paginada.

**HU-19 · Administrar usuarios y roles** (5 puntos, Completada)

Como administrador, quiero administrar usuarios y roles, para controlar los permisos de acceso al sistema.

Criterios de aceptación:

- El administrador asigna los roles Cliente, Barbero y Administrador.
- Cada endpoint valida el permiso del rol.
- Un usuario sin permiso recibe una respuesta de acceso denegado.

**HU-20 · Dashboard administrativo** (5 puntos, Completada)

Como administrador, quiero consultar un dashboard, para visualizar información general de la barbería.

Criterios de aceptación:

- El dashboard muestra totales de citas, clientes, servicios e ingresos.
- La información se presenta con tarjetas y gráficas.
- Solo el administrador puede acceder al dashboard.

### EP-10 · Reportes

**HU-21 · Reporte de citas** (8 puntos, Completada)

Como administrador, quiero generar reportes de citas, para analizar la actividad de la barbería.

Criterios de aceptación:

- El reporte de citas se filtra por rango de fechas, barbero y servicio.
- Muestra totales por estado de la cita.
- Los datos provienen de consultas de agregación en MongoDB.

**HU-22 · Reporte de ingresos** (5 puntos, Completada)

Como administrador, quiero generar reportes de ingresos, para conocer el comportamiento de los pagos realizados.

Criterios de aceptación:

- El reporte de ingresos se filtra por rango de fechas.
- Muestra totales por servicio y por barbero.
- Los montos coinciden con los pagos registrados.

### EP-11 · Seguridad

**HU-23 · Protección de información** (5 puntos, Completada)

Como Desarrollador Backend, quiero proteger la información del sistema, para evitar accesos no autorizados.

Criterios de aceptación:

- Los endpoints protegidos rechazan peticiones sin token Bearer válido.
- Las contraseñas se almacenan cifradas.
- Se validan los permisos de acceso por rol y por propietario del recurso.

**HU-24 · Validación de datos** (3 puntos, Completada)

Como Desarrollador Backend, quiero validar los datos recibidos, para evitar información incorrecta en el sistema.

Criterios de aceptación:

- Toda petición se valida en el backend antes de guardarse.
- Los errores de validación se devuelven por campo con un mensaje claro.
- La interfaz muestra los mensajes junto al campo correspondiente.

### EP-12 · Aplicación móvil

**HU-25 · Aplicación móvil Android** (23 puntos, En progreso)

Como Desarrollador Mobile, quiero desarrollar la aplicación Android nativa (Kotlin + Jetpack Compose) de Urban Blade, para permitir a los clientes y al personal gestionar servicios y citas desde un dispositivo móvil.

Criterios de aceptación:

- La app Android permite registro, inicio de sesión, consulta de servicios, barberos y disponibilidad.
- Permite reservar, consultar y cancelar citas, pagar y editar el perfil.
- Consume la API con Retrofit, maneja errores de red y se prueba en dispositivo o emulador.

### EP-13 · Aplicación web

**HU-26 · Plataforma web administrativa** (5 puntos, Completada)

Como Desarrollador Frontend, quiero desarrollar la plataforma web administrativa, para permitir la gestión de Urban Blade desde un navegador.

Criterios de aceptación:

- La plataforma web permite administrar servicios, barberos, horarios, citas, clientes y roles.
- Consume la misma API que la app móvil.
- La navegación restringe los módulos según el rol del usuario.

### EP-14 · Integración y base de datos

**HU-27 · Comunicación frontend-backend** (22 puntos, En progreso)

Como Desarrollador Full Stack, quiero conectar las aplicaciones cliente con el backend, para permitir el intercambio de información del sistema. La app Android y la plataforma web (Nuxt) consumen la misma API de Laravel y la misma base MongoDB; no existe conexión directa entre ambas (App Android -> API/Backend -> MongoDB <- API/Backend <- Plataforma web).

Criterios de aceptación:

- La app Android y la plataforma web se comunican solo a través de la API de Laravel.
- Una cita creada en la app se visualiza en la web y viceversa.
- La autenticación por token funciona igual en ambas aplicaciones.

**HU-28 · Integración con MongoDB** (6 puntos, Completada)

Como Administrador de Bases de Datos, quiero integrar MongoDB con el backend, para almacenar y consultar la información de Urban Blade.

Criterios de aceptación:

- El backend se conecta a MongoDB Atlas mediante variables de entorno.
- Los modelos Eloquent (laravel-mongodb) definen la estructura de la información.
- Las consultas frecuentes cuentan con índices y se garantiza la integridad de los datos.

### EP-15 · Pruebas y calidad

**HU-29 · Pruebas funcionales** (5 puntos, En progreso)

Como Tester QA, quiero realizar pruebas funcionales, para verificar que las funciones del sistema cumplan con los requisitos establecidos.

Criterios de aceptación:

- Existen casos de prueba documentados para cada funcionalidad principal.
- Se ejecutan las pruebas de registro, inicio de sesión, servicios, citas y pagos.
- Los errores encontrados se registran y se da seguimiento a su corrección.

**HU-30 · Pruebas de integración** (5 puntos, En progreso)

Como Tester QA, quiero realizar pruebas de integración, para verificar la comunicación correcta entre los componentes del sistema.

Criterios de aceptación:

- Se prueban los flujos app móvil → API → MongoDB y web → API → MongoDB.
- Se documentan los resultados de cada escenario.
- Se verifica que las correcciones aplicadas resuelvan los errores.

### EP-16 · Despliegue y documentación

**HU-31 · Preparar sistema para producción** (5 puntos, En progreso)

Como Desarrollador Full Stack, quiero preparar el sistema para producción, para permitir su ejecución en un entorno estable.

Criterios de aceptación:

- Las variables y parámetros de producción están definidos fuera del código.
- Backend, base de datos y aplicaciones funcionan en el entorno de producción.
- Las pruebas finales se ejecutan sin errores críticos.

**HU-32 · Documentación del proyecto** (5 puntos, En progreso)

Como Scrum Master, quiero documentar el proyecto, para facilitar su instalación, mantenimiento y utilización.

Criterios de aceptación:

- Existe documentación de arquitectura, base de datos y API.
- Existe guía de instalación y de uso de las funcionalidades.
- La documentación final está revisada y organizada.

### EP-17 · Infraestructura como código (Terraform)

**HU-33 · Configuración inicial de Terraform** (3 puntos, Pendiente)

Como Desarrollador Backend, quiero configurar la estructura inicial del proyecto Terraform, para definir la infraestructura de Urban Blade como código versionado.

Criterios de aceptación:

- Existe la estructura del proyecto Terraform con archivos .tf base (main, providers, variables y outputs).
- Los proveedores tienen versiones fijadas.
- terraform init se ejecuta sin errores.

**HU-34 · Gestión del estado de Terraform** (2 puntos, Pendiente)

Como equipo de desarrollo, quiero una estrategia para el estado de Terraform, para evitar conflictos cuando varios integrantes modifiquen la infraestructura.

Criterios de aceptación:

- El estado de Terraform se almacena de forma remota y con bloqueo.
- Existe una convención de ambientes documentada.
- Dos integrantes no pueden aplicar cambios al mismo tiempo sobre el mismo estado.

**HU-35 · Provisionamiento de recursos** (10 puntos, Pendiente)

Como Desarrollador Backend, quiero codificar en Terraform los recursos que hoy existen a mano en AWS y MongoDB Atlas, para tener ambientes reproducibles para la API, la plataforma web y la base de datos.

Criterios de aceptación:

- ALB, ECR, ECS Fargate, CloudFront, S3 y Secrets Manager del staging están definidos en Terraform.
- MongoDB Atlas (clúster, usuarios y acceso de red) está definido en Terraform.
- Ningún secreto se guarda en los archivos .tf ni en el repositorio.

**HU-36 · Gestión y validación de la infraestructura** (5 puntos, Pendiente)

Como Tester QA, quiero ejecutar y validar el flujo init, validate, plan y apply de Terraform, para comprobar la infraestructura y documentarla antes del despliegue.

Criterios de aceptación:

- terraform validate y terraform plan se ejecutan sin errores.
- Los recursos existentes se importan y terraform plan no propone recrearlos.
- La infraestructura queda documentada con diagrama, variables y outputs.

**HU-37 · Integración de la infraestructura con backend y base de datos** (5 puntos, Pendiente)

Como Desarrollador Full Stack, quiero desplegar el backend y la plataforma web sobre la infraestructura gestionada por Terraform y conectarlos con MongoDB Atlas, para dejar el sistema listo para el despliegue continuo.

Criterios de aceptación:

- El backend/API funciona sobre la infraestructura provisionada.
- El backend se conecta a MongoDB en ese ambiente.
- La app móvil y la plataforma web consumen la API desplegada correctamente.

### EP-18 · CI/CD y Continuous Delivery

**HU-38 · Pipeline de integración continua** (10 puntos, En progreso)

Como Scrum Master, quiero un pipeline de integración continua en los tres repositorios, para validar automáticamente cada cambio antes de integrarlo a la rama principal.

Criterios de aceptación:

- barber, frontend-urban y la app Android ejecutan su pipeline en cada push y pull request.
- El pipeline instala dependencias y valida el código (lint y análisis estático).
- El pipeline ejecuta las pruebas y bloquea la integración si fallan.

**HU-39 · Construcción automática de las aplicaciones** (5 puntos, Pendiente)

Como Desarrollador Full Stack, quiero construir automáticamente las imágenes del backend y la plataforma web y el build de la app Android, para generar artefactos listos para desplegar.

Criterios de aceptación:

- El pipeline construye las imágenes Docker de barber y frontend-urban.
- El pipeline genera el APK/AAB firmado de la app Android.
- Un error de construcción marca el pipeline como fallido.

**HU-40 · Despliegue continuo (Continuous Delivery)** (8 puntos, Pendiente)

Como Product Owner, quiero automatizar el despliegue de Urban Blade, para entregar versiones validadas de forma repetible y segura.

Criterios de aceptación:

- Tras un build exitoso en main, las imágenes se publican en ECR y los servicios de ECS se actualizan.
- Las credenciales y secretos se toman del pipeline, nunca del código.
- Después del despliegue se ejecutan pruebas de humo y el resultado queda registrado.

**HU-41 · Integración de Terraform con CI/CD** (6 puntos, Pendiente)

Como Desarrollador Backend, quiero integrar Terraform en el pipeline, para validar y revisar los cambios de infraestructura antes de aplicarlos.

Criterios de aceptación:

- El pipeline ejecuta terraform fmt, validate y plan en cada cambio de infraestructura.
- El resultado del plan queda disponible para revisión.
- terraform apply requiere aprobación antes de ejecutarse.
