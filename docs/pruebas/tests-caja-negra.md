# Pruebas de Caja Negra (Interfaz de Usuario - UI/UX)

Estas pruebas se realizan directamente sobre la interfaz gráfica. El objetivo es validar la experiencia del usuario (UX) y que los elementos visuales respondan correctamente a las interacciones.

---

## 1. Módulo: Dashboard Administrativo (Vista Ejecutiva)
*Rol: Administrador / Dueño*

| # | Acción del Usuario (Interfaz) | Resultado Visual Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- |
| 1.1 | Visualizar las KPI Cards (Hoy, Semana, Mes). | Los datos de ingresos y citas deben cargarse dinámicamente según el periodo. | Sí |
| 1.2 | Activar/Desactivar el "Modo Mantenimiento". | El toggle debe cambiar de color y el sistema debe mostrar el badge de estado global. | Sí |
| 1.3 | Observar el "Estado de Estaciones en Vivo". | Cada estación de barbero debe mostrar un punto verde (libre) o rojo (ocupado). | Sí |
| 1.4 | Revisar la "Tendencia de Ingresos" (Gráfica). | La gráfica debe renderizarse correctamente con datos reales de los últimos 7 días. | Sí |
| 1.5 | Consultar la "Demanda de Servicios" (Donut Chart). | La distribución porcentual debe sumar 100% basándose en las citas del catálogo. | Sí |
| 1.6 | Visualizar la Telemetría del Chatbot. | Deben mostrarse 4 KPIs: Eventos, Error Rate (%), Latencia (ms) y Costo ($). | Sí |

---

## 2. Módulo: Almacén Central & Muro Inspiración
*Rol: Administrador / Recepcionista / Barbero*

| # | Acción del Usuario (Interfaz) | Resultado Visual Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- |
| 2.1 | Acceder al listado de "Almacén Central". | Se debe mostrar una ficha técnica por cada producto con stock e imagen. | Sí |
| 2.2 | Visualizar el "Muro de Inspiración". | Se debe ver un feed de imágenes (Social Feed) con los últimos trabajos realizados. | Sí |
| 2.3 | Usar el Atajo de Teclado (Ctrl + K). | Debe abrirse la paleta de comandos para búsqueda rápida en el sistema. | Sí |
| 2.4 | Filtrar el Almacén por "Stock Crítico". | Deben listarse solo los insumos que requieren reposición inmediata. | Sí |
| 2.5 | Cargar una foto al Muro de Inspiración. | La imagen debe aparecer en el feed con el nombre del barbero asignado. | Sí |
| 2.6 | Ver detalle de Telemetría (Top Fuentes). | Debe listar los canales (Web, WhatsApp, App) que más consultan al bot. | Sí |

---

## 3. Módulo: Asistente Inteligente (Chatbot Widget)
*Rol: Público / Cliente*

| # | Acción del Usuario (Interfaz) | Resultado Visual Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- |
| 3.1 | Abrir el widget de chat desde el botón flotante. | La ventana de chat debe deslizarse suavemente desde la esquina inferior derecha. | Sí |
| 3.2 | Enviar una pregunta mientras el bot procesa la anterior. | Se debe mostrar una animación de "tres puntos" (typing indicator) indicando actividad. | Sí |
| 3.3 | Hacer clic en el botón "Limpiar Historial". | Todos los globos de texto deben desaparecer con una animación de desvanecimiento. | Sí |
| 3.4 | Enviar un mensaje vacío (solo espacios). | El botón de enviar debe estar deshabilitado o no realizar ninguna acción. | Sí |
| 3.5 | Recibir un link del bot (ej. catálogo de servicios). | El enlace debe ser clicable y abrirse en una nueva pestaña (target="_blank"). | Sí |
| 3.6 | Minimizar la ventana de chat durante una respuesta. | Al volver a abrir, el historial debe mantenerse intacto en la misma posición. | Sí |

---

## 4. Módulo: Seguridad y Autenticación
*Rol: Todos*

| # | Acción del Usuario (Interfaz) | Resultado Visual Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- |
| 4.1 | Hacer clic en el icono del "Ojo" en el campo de contraseña. | El texto `****` debe cambiar a texto plano y viceversa. | Sí |
| 4.2 | Intentar acceder a `/admin/logs` siendo un Cliente. | La interfaz debe redirigir a una página 403 personalizada con estética de la barbería. | Sí |
| 4.3 | Dejar la sesión inactiva por más de 2 horas. | Al intentar cualquier acción, debe aparecer un modal de "Sesión Expirada" con botón de login. | Sí |
| 4.4 | Registrarse con un email que ya tiene cuenta. | El campo de email debe mostrar un mensaje de error: "Este correo ya está en uso". | Sí |
| 4.7 | Registrar usuario con contraseña válida (>=8, letras y números). | El registro es exitoso, usuario puede iniciar sesión. | Sí |
| 4.8 | Registrar usuario con contraseña corta (<8). | El sistema muestra error: "La contraseña debe tener al menos 8 caracteres". | Sí |
| 4.9 | Registrar usuario con email inválido. | El sistema muestra error: "El email debe ser válido". | Sí |
| 4.10 | Login con credenciales correctas. | Acceso exitoso, dashboard visible. | Sí |
| 4.11 | Login con contraseña incorrecta. | El sistema muestra error: "Las credenciales no son válidas". | Sí |
| 4.12 | Login con email no registrado. | El sistema muestra error: "Las credenciales no son válidas". | Sí |
| 4.13 | Login con email correcto y contraseña con caracteres especiales válidos. | Acceso exitoso si la contraseña es correcta. | Sí |
| 4.14 | Login con campos vacíos. | El sistema muestra error de campos requeridos. | Sí |
| 4.15 | Login tras registro exitoso. | El usuario puede iniciar sesión inmediatamente. | Sí |
| 4.5 | Guardar cambios en el perfil (Nombre/Foto). | Debe aparecer una notificación "Toast" en la parte superior que desaparece en 3s. | Sí |
| 4.6 | Recuperación de contraseña (Enviar link). | El botón debe cambiar a "Enviado..." y deshabilitarse para evitar duplicados. | Sí |

---

## 5. Módulo: Menú Administrador (Mapeo Web -> Endpoint)
*Rol: Administrador*

| # | Acción del Usuario (Interfaz) | Endpoint Relacionado | Resultado Visual Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 5.1 | Abrir Dashboard desde el menú lateral. | `GET /dashboard` + `GET /api/v1/dashboard` | Debe cargar KPIs, telemetría y gráficas sin estado de error. | Sí |
| 5.2 | Entrar a Gestión de Usuarios y crear un usuario nuevo. | `GET /users`, `POST /users` (+ lectura `GET /api/v1/users`) | El usuario aparece inmediatamente en el listado con rol correcto. | Sí |
| 5.3 | Entrar a Servicios y crear/editar uno existente. | `GET /services`, `POST /services`, `PUT /services/{id}` | El servicio se ve actualizado en catálogo y formularios de citas. | Sí |
| 5.4 | Acceder a Productos y registrar ajuste de inventario. | `GET /inventory/products` (+ `GET /api/v1/inventory/products`) | Se refleja nuevo stock y badge de alerta si aplica. | Sí |
| 5.5 | Abrir Reportes y exportar PDF/Excel. | `GET /reports`, `GET /reports/{type}/{format}` | Se descarga archivo válido sin romper la sesión. | Sí |
| 5.6 | Revisar Logs desde Analítica. | `GET /logs` + `GET /api/v1/logs` | Se muestran eventos recientes con actor, acción y timestamp. | Sí |
| 5.7 | Usar Notificaciones y marcar todo como leído. | `GET /notifications`, `POST /notifications/read-all` | El contador de no leídas vuelve a 0 en la UI. | Sí |
| 5.8 | Consultar Chatbot y limpiar historial. | `POST /chatbot/query`, `GET /chatbot/history`, `POST /chatbot/clear-history` | El historial se actualiza en tiempo real y se limpia correctamente. | Sí |

---

## 6. Módulo: Menú Recepcionista (Mapeo Web -> Endpoint)
*Rol: Recepcionista*

| # | Acción del Usuario (Interfaz) | Endpoint Relacionado | Resultado Visual Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 6.1 | Abrir Dashboard operativo desde menú. | `GET /dashboard` + `GET /api/v1/dashboard` | Carga KPIs operativos de citas/clientes/pagos sin error visual. | Sí |
| 6.2 | Gestionar agenda de Citas desde operación. | `GET/POST/PUT/DELETE /appointments` (+ API appointments) | La cita creada/editada aparece en listado y calendario. | Sí |
| 6.3 | Registrar y editar perfil de Cliente. | `GET/POST/PUT/DELETE /clients` + `GET /api/v1/clients` | Los cambios se reflejan de inmediato en el CRM. | Sí |
| 6.4 | Registrar cobro en módulo Pagos. | `GET/POST /payments` + `GET/POST /api/v1/payments` | El pago queda registrado y visible en historial. | Sí |
| 6.5 | Registrar movimiento de inventario. | `GET/POST /inventory/movements` + `GET /api/v1/inventory/movements` | El movimiento aparece con tipo y responsable correctos. | Sí |
| 6.6 | Abrir notificaciones y limpiar pendientes. | `GET /notifications`, `POST /notifications/read-all` | El badge de notificaciones se normaliza en tiempo real. | Sí |
| 6.7 | Consultar chatbot para soporte operativo. | `POST /chatbot/query`, `GET /chatbot/history` | El asistente responde y conserva contexto de sesión. | Sí |

