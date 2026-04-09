# Pruebas de Sistema (System / E2E Tests)

Estas pruebas validan los flujos completos de negocio (User Journeys) a través de múltiples módulos y roles, simulando el uso real de la aplicación.

---

## 1. Flujo: Experiencia del Cliente (Registro hasta Cita)
*Objetivo: Validar que un cliente nuevo pueda usar todo el sistema de forma fluida.*

| # | Paso del Flujo (Journey) | Acción | Módulo | Resultado Final |
| :--- | :--- | :--- | :--- | :--- |
| 1.1 | Registro público. | POST /register | Auth | User + ClientProfile creados. |
| 1.2 | Consulta de dudas al bot. | ¿Qué barberos recomiendas? | Chatbot | Bot responde con datos de `barbers`. |
| 1.3 | Verificación de horarios. | GET /availability/slots | Availability | Slots filtrados por barber_id y fecha. |
| 1.4 | Agendamiento de cita. | POST /appointments | Appointments | Cita en estado 'pendiente'. |
| 1.5 | Recepción de notificación. | Revisar Log de Notificaciones | Notifications | Email enviado confirmando la cita. |
| 1.6 | Consulta de citas próximas. | GET /dashboard (Mobile) | Dashboard | La cita agendada aparece en el panel. |

---

## 2. Flujo: Gestión Operativa (Cita hasta Pago/Venta)
*Objetivo: Validar el trabajo diario del Barbero y el Recepcionista.*

| # | Paso del Flujo (Journey) | Acción | Módulo | Resultado Final |
| :--- | :--- | :--- | :--- | :--- |
| 2.1 | Visualización de agenda. | GET /appointments | Appointments | El barbero ve su lista del día. |
| 2.2 | Cambio de estado de cita. | PATCH status: en_proceso | Appointments | El estado cambia a 'en_proceso' en tiempo real. |
| 2.3 | Finalización de servicio. | PATCH status: completada | Appointments | Se habilita la opción de registro de pago. |
| 2.4 | Venta de producto adicional. | POST /inventory/movements | Inventory | Stock del producto decrementado. |
| 2.5 | Registro de pago. | POST /payments | Payments | Ingreso registrado vinculado a la cita. |
| 2.6 | Generación de ticket/recibo. | GET /payments/{id}/pdf | Payments | PDF generado con los detalles del servicio y productos. |

---

## 3. Flujo: Administración y Auditoría
*Objetivo: Validar la capacidad de control del Administrador sobre el negocio.*

| # | Paso del Flujo (Journey) | Acción | Módulo | Resultado Final |
| :--- | :--- | :--- | :--- | :--- |
| 3.1 | Supervisión de actividades. | GET /logs | ActivityLog | Se visualizan cambios realizados por el staff. |
| 3.2 | Entrenamiento del Chatbot. | POST /train-history | Chatbot | Historial procesado para mejorar respuestas. |
| 3.3 | Análisis de bajo stock. | GET /dashboard | Dashboard | Alerta visual de productos por agotarse. |
| 3.4 | Ajuste de configuración global. | PATCH /settings | Settings | Cambio de horario de apertura de la barbería. |
| 3.5 | Verificación de ingresos. | GET /reports/finance | Reports | Reporte Excel generado con pagos del mes. |
| 3.6 | Gestión de roles de staff. | PATCH /users/{id}/roles | Auth/Permissions | Cambio de rol de Barbero a Recepcionista. |

---

## 4. Flujo: Operación Completa del Menú Administrador
*Objetivo: Validar el sistema completo desde navegación UI hasta persistencia de datos.*

| # | Paso del Flujo (Journey) | Acción | Módulo | Resultado Final |
| :--- | :--- | :--- | :--- | :--- |
| 4.1 | Supervisor abre Dashboard y detecta stock crítico. | GET /dashboard | Dashboard + Inventario | El panel muestra alerta y enlace funcional a productos/movimientos. |
| 4.2 | Admin ajusta producto y registra movimiento de entrada. | PUT /inventory/products/{id} + POST /inventory/movements | Inventario | Stock actualizado y trazabilidad completa del cambio. |
| 4.3 | Admin crea usuario operativo y valida permisos. | POST /users + login de validación | Usuarios/Roles | El nuevo usuario accede solo a módulos permitidos por su rol. |
| 4.4 | Admin exporta reporte y cruza contra pagos reales. | GET /reports/{type}/{format} | Reportes/Pagos | Totales del archivo coinciden con movimientos financieros del periodo. |
| 4.5 | Admin revisa logs por acciones recientes. | GET /logs | Auditoría | Se observan eventos de creación/edición con usuario, fecha y contexto. |
| 4.6 | Admin usa chatbot y luego limpia historial. | POST /chatbot/query + POST /chatbot/clear-history | Chatbot | Se responde consulta, se registra evento y limpieza deja historial vacío. |

---

## 5. Flujo: Operación End-to-End de Recepción
*Objetivo: Validar la jornada completa de recepcionista con módulos conectados.*

| # | Paso del Flujo (Journey) | Acción | Módulo | Resultado Final |
| :--- | :--- | :--- | :--- | :--- |
| 5.1 | Recepción inicia turno y revisa panel. | GET /dashboard | Dashboard Operativo | Visualiza citas del día, clientes nuevos y estado de operación. |
| 5.2 | Registra cliente walk-in y agenda cita. | POST /clients + POST /appointments | Clientes/Citas | Cliente queda creado y su cita confirmada para atención. |
| 5.3 | Cobra servicio y emite comprobante operativo. | POST /payments | Pagos | Ingreso se registra y se refleja en indicadores del día. |
| 5.4 | Registra consumo de insumo en cabina. | POST /inventory/movements | Inventario | Stock disminuye y trazabilidad del movimiento queda auditada. |
| 5.5 | Atiende incidencias mediante notificaciones. | GET /notifications + POST /notifications/read-all | Notificaciones | Alertas críticas se atienden y quedan marcadas como leídas. |
| 5.6 | Consulta chatbot para apoyo rápido y cierra sesión. | POST /chatbot/query + POST /logout | Chatbot/Seguridad | Obtiene respuesta útil y finaliza turno sin sesión abierta. |

