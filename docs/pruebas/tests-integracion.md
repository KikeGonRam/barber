# Pruebas de Integración (Integration Tests)

Estas pruebas verifican que la comunicación entre diferentes módulos del sistema (Citas, Inventario, Notificaciones, Chatbot) sea correcta y consistente.

---

## 1. Flujo: Agendamiento y Disponibilidad (Citas ↔ Horarios)
*Objetivo: Verificar que una cita creada bloquee correctamente el horario para otros clientes.*

| # | Escenario de Integración | Módulos Involucrados | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 1.1 | Bloqueo de slot tras reserva exitosa | `Appointments` ↔ `AvailabilityService` | Al crear cita a las 10:00, el slot desaparece de `availability/slots`. | Sí |
| 1.2 | Respeto de duración de servicio | `Services` ↔ `AppointmentService` | Si un servicio dura 60m, bloquea dos slots de 30m seguidos. | Sí |
| 1.3 | Solapamiento de citas (Conflicto) | `Appointments` ↔ `Database` | Intento de agendar en slot bloqueado devuelve `422 AppointmentConflict`. | Sí |
| 1.4 | Liberación de horario tras cancelación | `Appointments` (Status) ↔ `Availability` | Al cambiar estado a `cancelada`, el slot vuelve a aparecer en disponibilidad. | Sí |
| 1.5 | Fallback a horario global | `BarbershopSettings` ↔ `AppointmentService` | Si el barbero no tiene horario definido, usa el de la barbería. | Sí |
| 1.6 | Margen de tiempo para citas hoy | `System Clock` ↔ `Availability` | No se muestran slots para "ahora mismo" si faltan menos de 10 min. | Sí |

---

## 2. Flujo: Notificaciones y Usuarios (Citas ↔ Email)
*Objetivo: Asegurar que las acciones del sistema disparen las alertas correctas a los usuarios.*

| # | Escenario de Integración | Módulos Involucrados | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 2.1 | Notificación de confirmación al cliente | `AppointmentService` ↔ `Notifications` | Se registra `confirmation_sent_at` en la cita tras el POST. | Sí |
| 2.2 | Notificación de cancelación | `AppointmentController` ↔ `Notifications` | Al ejecutar `destroy`, se dispara email al cliente con el motivo. | Sí |
| 2.3 | Usuario sin perfil de cliente | `Users` ↔ `Clients` ↔ `Appointments` | El sistema crea el perfil `Client` automáticamente si no existe al agendar. | Sí |
| 2.4 | Tracking de notificación fallida | `Notifications` ↔ `Logging` | Si el driver de email falla, el error se captura en `storage/logs/laravel.log`. | Sí |
| 2.5 | Multi-rol: Barbero-Cliente | `Auth` ↔ `ACL` ↔ `Appointments` | Un barbero no puede agendarse a sí mismo como cliente desde su panel. | Sí |
| 2.6 | Recordatorio de cita (Batch) | `Console Commands` ↔ `Notifications` | El comando `appointment:remind` envía alertas a citas del día siguiente. | Sí |

---

## 3. Flujo: Inventario y Operaciones (Movimientos ↔ Productos)
*Objetivo: Validar que los movimientos de stock afecten el conteo real y detecten niveles bajos.*

| # | Escenario de Integración | Módulos Involucrados | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 3.1 | Salida de stock por venta | `InventoryService` ↔ `Products` | El `stock_actual` disminuye exactamente la cantidad registrada. | Sí |
| 3.2 | Bloqueo de salida sin stock | `InventoryService` ↔ `Exceptions` | Lanza `InsufficientStockException` si se intenta vender más de lo que hay. | Sí |
| 3.3 | Alerta de Stock Bajo (Low Stock) | `Products` ↔ `DashboardService` | Si `stock_actual <= stock_minimo`, el producto aparece en alertas de dashboard. | Sí |
| 3.4 | Auditoría de movimientos | `InventoryMovements` ↔ `Users` | Cada movimiento registra el `user_id` del recepcionista que lo hizo. | Sí |
| 3.5 | Entrada de stock (Reposición) | `InventoryService` ↔ `Products` | Al registrar `entrada`, el stock aumenta y se guarda el motivo "Reposición". | Sí |
| 3.6 | Transaccionalidad de stock | `DB::transaction` ↔ `Inventory` | Si falla el registro del movimiento, el stock del producto no cambia (Rollback). | Sí |

---

## 4. Flujo: Chatbot e Inteligencia (AI ↔ Business Logic)
*Objetivo: Verificar que el bot responda con datos reales de la base de datos.*

| # | Escenario de Integración | Módulos Involucrados | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 4.1 | Consulta de catálogo al bot | `GeminiService` ↔ `CatalogController` | El bot extrae precios de la tabla `services` para responder. | Sí |
| 4.2 | Sugerencia de barbero por especialidad | `GeminiService` ↔ `Barber Model` | El bot identifica especialidades del barbero en la respuesta. | Sí |
| 4.3 | Verificación de citas por el bot | `GeminiService` ↔ `Appointment Model` | El bot confirma al cliente sus citas próximas leyendo su perfil. | Sí |
| 4.4 | Aprendizaje desde el historial | `ChatbotLearningService` ↔ `Settings` | Las consultas frecuentes se marcan para mejorar el entrenamiento. | Sí |
| 4.5 | Límite de tokens de la API AI | `GeminiService` ↔ `Config` | El bot trunca respuestas largas para no exceder costos de API. | Sí |
| 4.6 | Fallo de conexión con Gemini | `GeminiService` ↔ `Error Handler` | Si falla la API, el bot responde: "No puedo ayudarte ahora, contacta a la recepción". | Sí |

---

## 5. Flujo: Menú Administrador y Consistencia Web/API
*Objetivo: Confirmar que el menú admin navega a módulos acoplados correctamente con su backend.*

| # | Escenario de Integración | Módulos Involucrados | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 5.1 | Dashboard + datos agregados de operación. | `DashboardController` ↔ `Appointments/Payments/Inventory` | KPIs mostrados coinciden con consultas agregadas de DB/API. | Sí |
| 5.2 | Gestión de usuarios y control de roles/permisos. | `UserController` ↔ `Spatie Roles/Permissions` | Alta/edición de usuarios impacta acceso inmediato en navegación. | Sí |
| 5.3 | Productos y movimientos sincronizados. | `ProductController` ↔ `InventoryMovementController` | Cada movimiento refleja stock actualizado en productos y dashboard. | Sí |
| 5.4 | Reportes y exportaciones conectadas a filtros reales. | `ReportController` ↔ `Payments/Appointments/Clients` | Archivo exportado respeta rango de fechas, barbero y tipo. | Sí |
| 5.5 | Logs y auditoría de acciones administrativas. | `ActivityLogController` ↔ acciones CRUD web/api | Se registran acciones de admin con metadata suficiente para trazabilidad. | Sí |
| 5.6 | Chatbot con contexto operativo del negocio. | `ChatbotController` ↔ `GeminiService` ↔ catálogos/citas | Respuestas usan datos actuales y mantienen historial por usuario. | Sí |

---

## 6. Flujo: Integración Operativa de Recepción
*Objetivo: Verificar acoplamiento real entre módulos que usa recepcionista en jornada diaria.*

| # | Escenario de Integración | Módulos Involucrados | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 6.1 | Alta de cliente y uso inmediato en cita. | `ClientController` ↔ `AppointmentController` | Cliente nuevo aparece disponible para agendar en la misma sesión. | Sí |
| 6.2 | Cita completada y registro de pago. | `Appointments` ↔ `Payments` | El pago queda vinculado a cita y reflejado en dashboard. | Sí |
| 6.3 | Movimiento de inventario por consumo operativo. | `InventoryMovementController` ↔ `Products` | Cada salida impacta stock y alertas en tiempo real. | Sí |
| 6.4 | Notificaciones por cambios de estado de cita. | `Appointments` ↔ `Notifications` | Cliente/barbero reciben aviso correspondiente al cambio. | Sí |
| 6.5 | Consulta de dashboard con datos de operación. | `DashboardController` ↔ `Appointments/Clients/Payments` | KPIs operativos coinciden con transacciones recientes. | Sí |
| 6.6 | Chatbot como apoyo en atención. | `ChatbotController` ↔ `Catalog/Availability` | Respuestas de bot son consistentes con servicios y disponibilidad real. | Sí |

