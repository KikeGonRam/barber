# Pruebas de Caja Blanca (White Box / Structural Tests)

Estas pruebas verifican la estructura interna del código, la lógica de los servicios, el manejo de excepciones y la integridad de los datos a nivel de base de datos.

---

## 1. Módulo: Lógica de Servicios (Services & Repositories)
*Objetivo: Validar que los algoritmos de negocio y el acceso a datos sean correctos.*

| # | Escenario Técnico | Prueba Interna | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 1.1 | Inyección de dependencias en Controller. | __construct(Service) | El controlador debe recibir una instancia válida del servicio. | Sí |
| 1.2 | Algoritmo de cálculo de Slots Libres. | Loop de disponibilidad en Service | El loop debe generar slots de 30m sin solaparse. | Sí |
| 1.3 | Mapeo de Eloquent Relationships. | Appointment::with(['client']) | La relación debe devolver un objeto `Client` válido, no nulo. | Sí |
| 1.4 | Implementación de Interfaces. | Repositories implement Contracts | Cada repositorio debe cumplir con los métodos definidos en la interfaz. | Sí |
| 1.5 | Lógica de `lockForUpdate` en Inventario. | Simular ráfaga en transacción | El stock no debe bajar de 0 ante peticiones paralelas. | Sí |
| 1.6 | Manejo de Scopes Globales. | `SoftDeletes` en modelos | El `whereNull('deleted_at')` debe aplicarse automáticamente. | Sí |

---

## 2. Módulo: Manejo de Excepciones (Error Handling)
*Objetivo: Comprobar que el código lance las excepciones correctas ante fallos de dominio.*

| # | Escenario de Error Interno | Excepción Lanzada | Resultado del Catch | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 2.1 | Cita solapada detectada. | `AppointmentConflictException` | Se captura y devuelve un código 422 JSON. | Sí |
| 2.2 | Salida de stock insuficiente. | `InsufficientStockException` | Se captura en el service y lanza error descriptivo. | Sí |
| 2.3 | Recurso no encontrado (findOrFail). | `ModelNotFoundException` | Laravel maneja el 404 automáticamente. | Sí |
| 2.4 | Fallo de conexión con Gemini. | `HttpException` (mock) | Se captura y devuelve mensaje de "AI Offline". | Sí |
| 2.5 | Error en transacción de DB. | `QueryException` | Se ejecuta `DB::rollBack()` y los datos se mantienen íntegros. | Sí |
| 2.6 | Violación de Política (Policy). | `AuthorizationException` | El middleware responde con 403 Forbidden. | Sí |

---

## 3. Módulo: Datos y Transformaciones (Resources/DTOs)
*Objetivo: Validar que el formato de salida (JSON) sea consistente y profesional.*

| # | Transformación de Datos | Entrada (Model) | Salida (JSON) | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 3.1 | Formateo de fecha ISO 8601. | Carbon Object | `2026-03-02T10:00:00Z` | Sí |
| 3.2 | Conversión de Decimales (Precios). | 100.00 (Decimal) | `"$100.00"` (String formateado si aplica) | Sí |
| 3.3 | Transformación de Relaciones Anidadas. | Appointment -> Client -> User | JSON anidado correcto sin loops infinitos. | Sí |
| 3.4 | Sanitización de campos sensibles. | User Model | No debe incluir `password` ni `remember_token` en el JSON. | Sí |
| 3.5 | Inclusión de campos computados (Append). | `low_stock` boolean | El campo debe aparecer aunque no esté en la tabla física. | Sí |
| 3.6 | Paginación de Meta-Data. | LengthAwarePaginator | Incluye `total`, `current_page` y `last_page`. | Sí |

---

## 4. Módulo: Middleware y Permisos del Menú Administrador
*Objetivo: Verificar que cada acción del menú admin tenga protección estructural correcta.*

| # | Escenario Técnico | Punto de Código | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 4.1 | Rutas de gestión encapsuladas por rol administrador. | `routes/web.php` grupo `role.custom:administrador` | `users`, `services`, `inventory/products`, `settings`, `reports`, `logs` quedan aisladas para admin. | Sí |
| 4.2 | Operación compartida admin/recepción aislada correctamente. | `routes/web.php` grupo `role.custom:administrador,recepcionista` | `appointments`, `payments`, `clients`, `inventory/movements` respetan permisos por middleware. | Sí |
| 4.3 | Consistencia de menú vs rutas registradas. | `resources/views/layouts/navigation.blade.php` + `routes/web.php` | Cada opción del menú admin resuelve una ruta existente con `route(...)`. | Sí |
| 4.4 | Protección de telemetría y auditoría. | `permission.custom:reportes.ver` y `permission.custom:logs.ver` | Reportes y logs no son accesibles por roles no autorizados. | Sí |
| 4.5 | Integridad del flujo chatbot autenticado. | `ChatbotController` + grupo `auth` (`/chatbot/history`, `/chatbot/train-history`) | Endpoints sensibles del bot requieren sesión válida; no quedan públicos por error. | Sí |
| 4.6 | Coherencia web/API en dashboard y logs. | `DashboardController`, `Api\DashboardController`, `Api\LogController` | Contratos de datos mantienen estructura estable para panel admin y clientes API. | Sí |

---

## 5. Módulo: Middleware y Permisos del Menú Recepcionista
*Objetivo: Verificar aislamiento correcto de capacidades operativas para recepcionista.*

| # | Escenario Técnico | Punto de Código | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 5.1 | Recepcionista dentro de grupo operativo compartido. | `role.custom:administrador,recepcionista` | Accede a `appointments`, `clients`, `payments`, `inventory/movements`. | Sí |
| 5.2 | Recepcionista fuera de grupo de gestión admin. | Grupo `role.custom:administrador` | No obtiene acceso a `users`, `services`, `inventory/products`, `reports`, `logs`, `settings`. | Sí |
| 5.3 | Permisos granulares aplicados por módulo. | `permission.custom:*` en rutas web | Se respetan límites por permiso sin bypass por URL directa. | Sí |
| 5.4 | Consistencia del menú con rutas autorizadas. | `navigation.blade.php` + `web.php` | Solo aparecen opciones operativas permitidas para recepcionista. | Sí |
| 5.5 | Coherencia de endpoints API operativos. | `api.php` (`clients`, `payments`, `movements`, `appointments`) | API expone endpoints útiles para flujo de recepción sin privilegios de admin global. | Sí |
| 5.6 | Seguridad de sesión en chatbot autenticado. | Endpoints `/chatbot/*` bajo `auth/mobile.auth` | Historial y acciones del bot quedan ligados al usuario autenticado. | Sí |

