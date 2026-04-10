# Pruebas Negativas (Error Handling & Edge Cases)

Estas pruebas verifican que el sistema maneje correctamente las entradas inválidas, los estados de error y los intentos de romper las reglas de negocio sin colapsar.

---

## 1. Módulo: Validación de Entradas (Formularios y API)
*Objetivo: Comprobar que el middleware de validación rechace datos corruptos o incompletos.*

| # | Caso de Prueba Negativo | Entrada | Respuesta Esperada | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 1.1 | Registrar usuario sin email. | `{name, password}` | 422 Unprocessable Entity - Email required. | Sí |
| 1.2 | Contraseña con menos de 8 caracteres. | `pass: 123` | 422 Unprocessable Entity - Min length 8. | Sí |
| 1.13 | Cambiar contraseña con confirmación incorrecta. | `password: nueva, confirmation: otra` | 422 Unprocessable Entity - Confirmación no coincide. | Sí |
| 1.14 | Cambiar contraseña con contraseña actual incorrecta. | `current_password: mala` | 422 Unprocessable Entity - Contraseña actual inválida. | Sí |
| 1.15 | Editar perfil con email duplicado. | `email: admin@test.com` | 422 Unprocessable Entity - Email already taken. | Sí |
| 1.16 | Eliminar cuenta con contraseña incorrecta. | `password: mala` | 422 Unprocessable Entity - Contraseña inválida. | Sí |
| 1.7 | Registrar usuario con email inválido. | `{name, email: 'noemail', password}` | 422 Unprocessable Entity - Email must be valid. | Sí |
| 1.8 | Registrar usuario con email ya registrado. | `{name, email: 'cliente@test.com', password}` | 422 Unprocessable Entity - Email already taken. | Sí |
| 1.9 | Login con email no registrado. | `{email: 'noexiste@test.com', password}` | 422 Unprocessable Entity - Invalid credentials. | Sí |
| 1.10 | Login con contraseña incorrecta. | `{email: 'cliente@test.com', password: 'mala'}` | 422 Unprocessable Entity - Invalid credentials. | Sí |
| 1.11 | Login con campos vacíos. | `{}` | 422 Unprocessable Entity - Required fields. | Sí |
| 1.12 | Login con email válido y contraseña con caracteres especiales. | `{email: 'cliente@test.com', password: 'pass!@#123'}` | 200 OK si la contraseña es correcta. | Sí |
---

## Soluciones y recomendaciones para errores frecuentes
| Error | Solución recomendada |
| :--- | :--- |
| Email ya registrado | Usar la función de recuperación de contraseña o elegir otro email. |
| Contraseña corta | Ingresar una contraseña de al menos 8 caracteres. |
| Email inválido | Verificar el formato del email antes de enviar el formulario. |
| Credenciales incorrectas | Revisar email y contraseña, o usar "Olvidé mi contraseña". |
| Campos vacíos | Completar todos los campos requeridos antes de enviar. |
| 1.3 | Fecha de cita con formato inválido. | `fecha: 32/13/2026` | 422 Unprocessable Entity - Invalid date format. | Sí |
| 1.4 | Precio de servicio negativo. | `precio: -100.00` | 422 Unprocessable Entity - Price must be positive. | Sí |
| 1.5 | Cantidad de inventario no numérica. | `cantidad: "diez"` | 422 Unprocessable Entity - Quantity must be integer. | Sí |
| 1.6 | Notas de cita excesivamente largas (>1000). | `notas: lorem_ipsum...` | 422 Unprocessable Entity - Max 1000 characters. | Sí |

---

## 2. Módulo: Conflictos de Negocio (Business Rules)
*Objetivo: Validar que no se violen las reglas lógicas de la barbería.*

| # | Escenario de Conflicto | Acción | Respuesta Esperada | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 2.1 | Agendar cita en horario ya ocupado. | POST /appointments | 422 Conflict - Barber busy at this time. | Sí |
| 2.2 | Agendar cita en el pasado. | `fecha: 2020-01-01` | 422 Conflict - Date cannot be in the past. | Sí |
| 2.3 | Registrar salida de stock sin existencias. | `salida: 50` (Stock: 2) | 422 Error - Insufficient stock in inventory. | Sí |
| 2.4 | Cambiar estado de cita ya completada. | `PATCH status: pendiente` | 422 Error - Completed appointments cannot be reverted. | Sí |
| 2.5 | Borrar un producto que tiene movimientos. | DELETE /product/1 | El sistema debe bloquear el borrado o usar SoftDeletes. | Sí |
| 2.7 | Editar usuario con email duplicado. | PUT /users/{id} | 422 Unprocessable Entity - Email already taken. | Sí |
| 2.8 | Eliminar usuario inexistente. | DELETE /users/99999 | 404 Not Found - Record not found. | Sí |
| 2.9 | Editar cita con fecha pasada. | PUT /appointments/{id} fecha: 2020-01-01 | 422 Conflict - Date cannot be in the past. | Sí |
| 2.10 | Eliminar cita inexistente. | DELETE /appointments/99999 | 404 Not Found - Record not found. | Sí |
| 2.11 | Editar movimiento de inventario con cantidad negativa. | PUT /inventory/movements/{id} cantidad: -5 | 422 Validation Error (cantidad > 0). | Sí |
| 2.12 | Eliminar movimiento inexistente. | DELETE /inventory/movements/99999 | 404 Not Found - Entry invalid. | Sí |
| 2.6 | Crear combo sin servicios asociados. | POST /combos | 422 Error - Combo must have at least 1 service. | Sí |
| 2.13 | Exportar reporte con tipo inválido. | GET /reports/invalid/pdf | 404 Not Found - Route not defined. | Sí |
| 2.14 | Acceso a reportes sin permisos. | GET /reports | 403 Forbidden - Acceso denegado. | Sí |
| 2.15 | Registrar pago duplicado para misma cita. | POST /payments (dos veces) | 422 Validation Error - Pago duplicado. | Sí |
| 2.16 | Acceso a pagos sin permisos. | GET /payments | 403 Forbidden - Acceso denegado. | Sí |
| 2.17 | Exceso de consultas al chatbot (rate limit). | POST /chatbot/query (muchas veces) | 429 Too Many Requests - Rate limit active. | Sí |
| 2.18 | Fallback de chatbot por error de IA. | POST /chatbot/query (error IA) | Mensaje de fallback y log registrado. | Sí |

---

## 3. Módulo: Recursos Inexistentes (404 Not Found)
*Objetivo: Asegurar que el sistema responda limpiamente ante IDs inválidos.*

| # | Escenario de Recurso | Entrada (ID) | Respuesta Esperada | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 3.1 | Consultar detalle de cita inexistente. | GET /appointments/99999 | 404 Not Found - Record not found. | Sí |
| 3.2 | Ver perfil de barbero eliminado. | GET /barbers/500 | 404 Not Found - Barber no longer available. | Sí |
| 3.3 | Borrar movimiento de inventario inexistente. | DELETE /movement/abc | 404 Not Found - Entry invalid. | Sí |
| 3.4 | Acceder a endpoint mal escrito. | GET /api/v1/serviciosss | 404 Not Found - Route not defined. | Sí |
| 3.5 | Filtrar productos por categoría vacía. | `?categoria=Marcianos` | 200 OK - Lista vacía (No es error 404). | Sí |
| 3.6 | Consultar disponibilidad de barbero ID:0. | `barber_id: 0` | 422 Error - The selected barber id is invalid. | Sí |

---

## 4. Módulo: Errores de Sistema y Resiliencia (500/Timeout)
*Objetivo: Verificar el comportamiento ante fallos críticos de infraestructura.*

| # | Escenario de Sistema | Acción | Respuesta Esperada | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 4.1 | Desconexión de Base de Datos. | Simular Down | Error 500 controlado sin exponer StackTrace. | Sí |
| 4.2 | Fallo en envío de email SMTP. | Driver erróneo | La cita se crea, pero el log registra el fallo de envío. | Sí |
| 4.3 | Límite de cuota de Gemini AI. | API Limit Exceeded | El Chatbot responde: "Por favor intenta más tarde". | Sí |
| 4.4 | Subida de archivo corrupto/inválido. | `.exe` en lugar de `.jpg` | 422 Unprocessable Entity - Invalid file type. | Sí |
| 4.5 | Timeout en generación de reporte PDF. | Reporte de 5 años | El sistema debe cancelar el proceso antes de agotar CPU. | Sí |
| 4.6 | Petición masiva (DDoS básico). | 1000 req/min | 429 Too Many Requests - Rate limit active. | Sí |

---

## 5. Módulo: Negativas del Menú Administrador (CRUD + Chatbot)
*Objetivo: Validar rechazos correctos en acciones críticas de administración.*

| # | Escenario Negativo | Endpoint/Ruta | Respuesta Esperada | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 5.1 | Crear usuario con rol inexistente desde panel admin. | `POST /users` | 422 Validation Error (rol inválido). | Sí |
| 5.2 | Actualizar producto con stock negativo. | `PUT /inventory/products/{id}` | 422 Validation Error (stock >= 0). | Sí |
| 5.3 | Exportar reporte con tipo no permitido. | `GET /reports/fraude/pdf` | 404 Not Found por `whereIn(type, ...)`. | Sí |
| 5.4 | Marcar notificaciones sin sesión activa. | `POST /notifications/read-all` | 401 Unauthorized / redirección a login. | Sí |
| 5.5 | Enviar consulta vacía al chatbot desde admin. | `POST /chatbot/query` | 422 Validation Error (mensaje requerido). | Sí |
| 5.6 | Activar mantenimiento con payload inválido. | `POST /settings/maintenance` | 422 Validation Error sin alterar estado global. | Sí |
| 5.7 | Acceso a perfil de otro usuario sin permisos. | `GET /profile/{otro}` | 403 Forbidden - Acceso denegado. | Sí |
| 5.8 | Editar perfil de otro usuario sin permisos. | `PUT /profile/{otro}` | 403 Forbidden - Acceso denegado. | Sí |

---

## 6. Módulo: Negativas de Recepción (Operación)
*Objetivo: Validar fallos controlados en acciones críticas de recepcionista.*

| # | Escenario Negativo | Endpoint/Ruta | Respuesta Esperada | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 6.1 | Crear cita con barbero no disponible. | `POST /appointments` | 422 Conflict / validación de disponibilidad. | Sí |
| 6.2 | Registrar cliente con email duplicado. | `POST /clients` | 422 Validation Error por unicidad de email. | Sí |
| 6.3 | Registrar pago con cita inexistente. | `POST /payments` | 422 Validation Error / referencia inválida. | Sí |
| 6.4 | Crear movimiento con cantidad negativa. | `POST /inventory/movements` | 422 Validation Error (cantidad > 0). | Sí |
| 6.5 | Editar cita con estado inválido. | `PUT /appointments/{id}` | 422 Validation Error (estado no permitido). | Sí |
| 6.6 | Enviar consulta vacía al chatbot de recepción. | `POST /chatbot/query` | 422 Validation Error sin impactar historial. | Sí |

