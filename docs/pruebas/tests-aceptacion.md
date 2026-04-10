## 5. Módulo: Registro y Autenticación (Web/API)
*KPI: El usuario puede registrarse y autenticarse de forma segura y sin fricción.*

| # | Criterio de Aceptación | Prueba | Condición de Éxito |
| :--- | :--- | :--- | :--- |
| 5.1 | Registro exitoso con datos válidos. | POST /register (web/api) | El usuario es creado y puede iniciar sesión inmediatamente. |
| 5.2 | Registro falla con email ya registrado. | POST /register con email existente | El sistema muestra error y no crea usuario duplicado. |
| 5.3 | Registro falla con contraseña corta. | POST /register con password <8 | El sistema muestra error de validación. |
| 5.4 | Login exitoso con credenciales válidas. | POST /login (web/api) | El usuario accede al dashboard o recibe token. |
| 5.5 | Login falla con credenciales incorrectas. | POST /login con datos erróneos | El sistema muestra mensaje de error y no permite acceso. |
| 5.6 | Login con caracteres especiales en contraseña. | POST /login con password especial | Acceso exitoso si la contraseña es correcta. |
| 5.7 | Login tras registro inmediato. | Registrar y luego login | El usuario puede autenticarse sin esperar confirmación extra. |
| 5.8 | Mensajes de error claros y amigables. | Intentar login/register con datos inválidos | El sistema muestra mensajes comprensibles y no expone detalles técnicos. |
| 5.9 | Cambio de contraseña exitoso desde perfil. | POST /user/password/update | El usuario recibe confirmación y puede autenticarse con la nueva contraseña. |
| 5.10 | Recuperación de contraseña por email. | POST /forgot-password | El usuario recibe correo y puede restablecer su contraseña. |
| 5.11 | Edición de perfil (nombre, email, foto). | PUT /user/profile | Los cambios se reflejan inmediatamente y se valida unicidad de email. |
| 5.12 | Eliminación de cuenta con confirmación. | DELETE /user | El usuario debe confirmar con su contraseña y la cuenta se elimina correctamente. |
# Pruebas de Aceptación (Acceptance Tests)

Estas pruebas definen los criterios de satisfacción del negocio y del usuario final para considerar una funcionalidad como "Lista para Producción" (Definition of Done).

---

## 1. Módulo: Experiencia de Reserva (Cliente)
*KPI: Menos de 1 minuto para agendar una cita.*

| # | Criterio de Aceptación | Prueba | Condición de Éxito |
| :--- | :--- | :--- | :--- |
| 1.1 | El cliente puede ver barberos por especialidad. | Filtro en el catálogo. | Se muestran solo barberos que coinciden con el filtro. |
| 1.2 | El sistema bloquea horarios ya ocupados. | Intento de doble reserva. | El sistema rechaza con mensaje amigable. |
| 1.3 | El cliente recibe confirmación inmediata. | POST /appointment. | Recibe respuesta exitosa y notificación en < 30s. |
| 1.4 | Interfaz móvil rápida e intuitiva. | Uso en dispositivo real. | El flujo de reserva requiere menos de 5 clics. |
| 1.5 | Ayuda inteligente vía Chatbot. | Pregunta sobre horarios. | El bot responde con los slots libres reales del día. |
| 1.6 | Cancelación sin fricción. | Botón cancelar en el perfil. | El estado cambia y se libera el slot automáticamente. |

---

## 2. Módulo: Gestión de Inventario (Staff)
*KPI: Cero descuadres entre stock físico y digital.*

| # | Criterio de Aceptación | Prueba | Condición de Éxito |
| :--- | :--- | :--- | :--- |
| 2.1 | Visibilidad de productos en stock bajo. | Dashboard de inventario. | Al menos 1 badge rojo visible para ítems críticos. |
| 2.2 | Registro de entradas de mercadería. | POST /movements (entrada). | El stock aumenta y queda registrado en el historial. |
| 2.3 | Trazabilidad total de movimientos. | Listado de movimientos. | Se muestra quién, cuándo y por qué se movió el stock. |
| 2.4 | Filtros de productos por tipo/uso. | Selector de tipo. | Se separan claramente insumos de productos para venta. |
| 2.5 | Carga de imágenes para identificación. | Subida de archivos. | Se visualiza la foto del producto en el listado. |
| 2.6 | Reporte mensual de movimientos. | Exportar a Excel. | El archivo Excel generado es legible y profesional. |

---

## 3. Módulo: Reportes y Finanzas (Administrador)
*KPI: Visualización en tiempo real de los ingresos del negocio.*

| # | Criterio de Aceptación | Prueba | Condición de Éxito |
| :--- | :--- | :--- | :--- |
| 3.1 | Reporte de ingresos consolidado. | Dashboard financiero. | El total de pagos coincide con la suma de citas completadas. |
| 3.2 | Auditoría de cambios en el sistema. | Pantalla de ActivityLogs. | Se registran acciones de borrado o cambio de roles. |
| 3.3 | Control de seguridad por roles. | Login con diferentes roles. | Solo el Admin ve los ingresos totales y logs de staff. |
| 3.4 | Configuración flexible de la barbería. | Cambio de horario global. | El sistema aplica el nuevo horario a todos los barberos sin fallar. |
| 3.5 | Respaldo de datos (SoftDeletes). | Intento de borrado accidental. | Los datos son recuperables desde la base de datos (deleted_at). |
| 3.6 | Rendimiento estable bajo carga. | Uso simultáneo en hora pico. | Los tiempos de respuesta se mantienen bajo 1 segundo. |

---

## 4. Módulo: Aceptación del Menú Administrador (Web/API)
*KPI: El administrador ejecuta todas las acciones del menú sin fricción y con control total.*

| # | Criterio de Aceptación | Prueba | Condición de Éxito |
| :--- | :--- | :--- | :--- |
| 4.1 | Navegación integral del menú administrativo. | Recorrido Dashboard -> Operación -> Gestión -> Análisis. | Todas las rutas cargan sin errores 403/404 para admin autenticado. |
| 4.2 | CRUD crítico funcional en gestión. | Crear/editar/eliminar en Usuarios, Servicios y Productos. | Cambios persisten y se reflejan inmediatamente en listados. |
| 4.2a | CRUD de clientes desde recepción. | Crear/editar/eliminar clientes desde el módulo de recepción. | Cambios reflejados en CRM y acceso. |
| 4.2b | CRUD de citas desde recepción. | Crear/editar/eliminar citas desde el módulo de agenda. | Cambios reflejados en calendario y notificaciones. |
| 4.2c | CRUD de inventario desde administración. | Crear/editar/eliminar productos y movimientos. | Cambios reflejados en stock, historial y alertas. |
| 4.3 | Control de negocio con trazabilidad. | Registrar movimiento y revisar logs. | Cada acción queda auditada con actor y timestamp verificable. |
| 4.4 | Inteligencia operativa disponible. | Ejecutar query chatbot, revisar historial y limpiar. | El bot responde con contexto del negocio y permite gestión del historial. |
| 4.5 | Capacidad analítica ejecutiva. | Exportar reportes PDF/Excel con filtros. | Archivos descargados son correctos y listos para toma de decisiones. |
| 4.5a | Exportar reporte de ingresos en PDF/Excel. | Exportar desde módulo de reportes. | Archivos PDF/Excel contienen datos correctos y descargan sin error. |
| 4.6 | Seguridad de sesión administrativa. | Cerrar sesión desde menú y reintentar acceso. | Se invalida sesión y exige autenticación para reingresar a módulos. |
| 4.7 | Acceso y edición de configuración global solo para admin. | GET/PUT /settings | Solo admin puede ver y modificar settings; cambios reflejados en la app. |
| 4.8 | Observabilidad y logs de eventos críticos. | Acciones críticas generan logs y métricas accesibles para admin. | Logs y métricas disponibles y auditables. |
| 4.9 | Performance: tiempos de respuesta y queries. | Listados y reportes cumplen tiempos y límites de queries. | Todas las operaciones cumplen los KPIs de performance. |

---

## 5. Módulo: Aceptación de Recepcionista (Web/API)
*KPI: La recepción ejecuta operación diaria completa sin fricción y sin privilegios indebidos.*

| # | Criterio de Aceptación | Prueba | Condición de Éxito |
| :--- | :--- | :--- | :--- |
| 5.1 | Navegación operativa clara y estable. | Recorrido Dashboard -> Citas -> Clientes -> Pagos -> Movimientos. | Todas las pantallas cargan con tiempos aceptables y sin errores de permisos. |
| 5.2 | Gestión de citas en mostrador. | Crear/reprogramar/cancelar cita durante horario de atención. | Cambios persisten y notificaciones se disparan correctamente. |
| 5.3 | Cobro y conciliación básica. | Registrar pago posterior a cita completada. | El pago queda asociado, visible y contabilizable en el día. |
| 5.3a | Generar recibo PDF al registrar pago. | POST /payments genera PDF. | El recibo PDF es accesible y correcto. |
| 5.4 | Control de inventario operativo. | Registrar entrada/salida de insumos de trabajo. | El stock y la bitácora reflejan exactamente la operación realizada. |
| 5.5 | Soporte asistido por chatbot. | Ejecutar consulta operativa y revisar historial. | El chatbot responde en contexto y mantiene registro por usuario. |
| 5.5a | Chatbot responde a consulta operativa y aplica límites/fallback. | POST /chatbot/query, rate limit, error IA. | El sistema responde correctamente y registra eventos. |
| 5.6 | Seguridad de turno en estación compartida. | Cerrar sesión y reintentar acceso directo a módulo. | El sistema bloquea acceso y exige autenticación nuevamente. |

