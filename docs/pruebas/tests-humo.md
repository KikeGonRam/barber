# Pruebas de Humo (Smoke Tests)

Estas pruebas verifican que los endpoints críticos respondan correctamente (200 OK) y manejen los errores básicos de forma estable.

---

## 1. Módulo: Autenticación & Registro (Auth)
### Endpoint: POST /api/v1/auth/login
*Acceso: Público*

| # | Caso de Prueba | Entrada | Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 1.1 | Login exitoso con credenciales válidas | `email`, `password` | 200 OK + Bearer Token | Sí |
| 1.2 | Login fallido con contraseña errónea | `email`, `wrong_pass` | 401 Unauthorized | Sí |
| 1.3 | Login fallido con email inexistente | `no_existe@test.com`, `pass` | 401 Unauthorized | Sí |
| 1.4 | Validación de campos obligatorios | `{}` (vacío) | 422 Unprocessable Entity | Sí |
| 1.5 | Formato de email inválido | `email-sin-formato`, `pass` | 422 Unprocessable Entity | Sí |
| 1.6 | Tiempo de respuesta del servidor | Datos válidos | Respuesta en < 1s | Sí |

### Endpoint: POST /api/v1/auth/register
*Acceso: Público (Crea perfil de Cliente)*

| # | Caso de Prueba | Entrada | Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 2.1 | Registro de cliente nuevo exitoso | `name`, `email`, `password` | 201 Created | Sí |
| 2.2 | Registro con email ya registrado | `email_duplicado` | 422 Error (Email ya existe) | Sí |
| 2.3 | Contraseña demasiado corta | `pass: 123` | 422 Error (Min 8 caracteres) | Sí |
| 2.4 | Falta de campo obligatorio (nombre) | `{email, password}` | 422 Error (Name is required) | Sí |
| 2.5 | Verificación de perfil generado | `email` nuevo | Registro en tabla `clients` | Sí |
| 2.6 | Respuesta ante ataque de spam (rate limit) | 10 peticiones seguidas | 429 Too Many Requests | Sí |

---

## 2. Módulo: Gestión de Citas (Appointments)
### Endpoint: POST /api/v1/appointments
*Acceso: Rol [Cliente]*

| # | Caso de Prueba | Entrada | Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 3.1 | Agendar cita con datos válidos | `barber_id`, `service_id`, `fecha`, `hora` | 201 Created | Sí |
| 3.2 | Agendar en horario de conflicto (ocupado) | `misma_fecha`, `misma_hora` | 422 Conflict (Cita ya agendada) | Sí |
| 3.3 | Agendar con barber_id inexistente | `barber_id: 9999` | 422 Error (Barber not found) | Sí |
| 3.4 | Agendar con fecha en el pasado | `fecha: 2020-01-01` | 422 Error (Date must be future) | Sí |
| 3.5 | Intento de agendar siendo un Barbero | Token de Barbero | 403 Forbidden | Sí |
| 3.6 | Cálculo automático de hora_fin | Service Duración: 30m | `hora_fin` = `hora_inicio` + 30m | Sí |

### Endpoint: PATCH /api/v1/appointments/{id}/status
*Acceso: Rol [Barbero]*

| # | Caso de Prueba | Entrada | Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 4.1 | Barbero completa su propia cita | `status: completada` | 200 OK | Sí |
| 4.2 | Barbero intenta cambiar cita ajena | ID de cita de otro barbero | 403 Forbidden | Sí |
| 4.3 | Estado inválido enviado | `status: "en_vacaciones"` | 422 Unprocessable Entity | Sí |
| 4.4 | Cliente intenta cambiar su estado | Token de Cliente | 403 Forbidden | Sí |
| 4.5 | Cambio a estado 'cancelada' | `status: cancelada` | 200 OK + `cancelada_en` fecha | Sí |
| 4.6 | Notas opcionales en el cambio | `status: completada`, `notas: ok` | 200 OK + Notas guardadas | Sí |

---

## 3. Módulo: Inventario (Products)
### Endpoint: GET /api/v1/inventory/products
*Acceso: Rol [Recepcionista / Admin]*

| # | Caso de Prueba | Entrada | Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 5.1 | Listar todos los productos | GET sin filtros | 200 OK + Array de productos | Sí |
| 5.2 | Filtrar por categoría válida | `?categoria=Ceras` | 200 OK (Solo items de Ceras) | Sí |
| 5.3 | Filtrar por tipo (insumo_trabajo) | `?tipo=insumo_trabajo` | 200 OK (Solo insumos) | Sí |
| 5.4 | Acceso denegado a Clientes | Token de Cliente | 403 Forbidden | Sí |
| 5.5 | Verificación de bandera `low_stock` | Item con stock <= min | `low_stock: true` | Sí |
| 5.6 | Paginación de resultados | `?page=2` | 200 OK (Meta: page 2) | Sí |

---

## 4. Módulo: Inteligencia Artificial (Chatbot)
### Endpoint: POST /api/v1/chatbot/query
*Acceso: Público (o Cliente autenticado)*

| # | Caso de Prueba | Entrada | Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 6.1 | Consulta de precios al bot | `¿Qué precio tiene el corte?` | Respuesta con catálogo real | Sí |
| 6.2 | Consulta de disponibilidad | `¿Hay citas para hoy?` | Respuesta con slots libres | Sí |
| 6.3 | Consulta fuera de contexto | `¿Cómo se hace un pastel?` | Respuesta de cortesía/negación | Sí |
| 6.4 | Manejo de historial de chat | GET `/history` después de query | Historial con el último mensaje | Sí |
| 6.5 | Borrado de historial | POST `/clear-history` | 200 OK + Historial vacío | Sí |
| 6.6 | Error de Gemini (Simulado) | API Key inválida (mock) | 500 Error amigable | Sí |

---

## 5. Módulo: Smoke Admin Menu (Web + API)
*Objetivo: Asegurar que las acciones principales del menú administrador estén vivas y operativas.*

| # | Caso de Prueba | Endpoint/Ruta | Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 5.1 | Carga inicial del panel administrativo. | `GET /dashboard` + `GET /api/v1/dashboard` | 200 OK, render completo de KPIs y gráficas. | Sí |
| 5.2 | Apertura del módulo Usuarios. | `GET /users` + `GET /api/v1/users` | 200 OK con tabla paginada de usuarios. | Sí |
| 5.3 | Apertura del módulo Productos. | `GET /inventory/products` + `GET /api/v1/inventory/products` | 200 OK con stock y categorías visibles. | Sí |
| 5.4 | Exportación rápida de reporte financiero. | `GET /reports/ingresos/pdf` | Descarga de PDF válida (< 3s en escenario base). | Sí |
| 5.5 | Consulta de auditoría de sistema. | `GET /logs` + `GET /api/v1/logs` | 200 OK con registros recientes. | Sí |
| 5.6 | Acción chatbot desde sesión admin. | `POST /chatbot/query` + `GET /chatbot/history` | Respuesta AI y persistencia de historial sin error. | Sí |

---

## 6. Módulo: Smoke Recepcionista (Web + API)
*Objetivo: Validar disponibilidad inmediata de acciones críticas de recepción.*

| # | Caso de Prueba | Endpoint/Ruta | Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 6.1 | Carga del dashboard operativo. | `GET /dashboard` + `GET /api/v1/dashboard` | 200 OK y widgets operativos visibles. | Sí |
| 6.2 | Apertura de agenda de citas. | `GET /appointments` + `GET /api/v1/appointments` | 200 OK con citas listadas y filtros activos. | Sí |
| 6.3 | Alta rápida de cliente. | `POST /clients` (+ validación API `GET /api/v1/clients`) | Cliente guardado y visible en listado. | Sí |
| 6.4 | Registro de pago. | `POST /payments` + `POST /api/v1/payments` | Pago creado sin errores de validación. | Sí |
| 6.5 | Registro de movimiento de inventario. | `POST /inventory/movements` | Movimiento persistido y reflejado en historial. | Sí |
| 6.6 | Consulta operativa al chatbot. | `POST /chatbot/query` | Respuesta recibida y almacenada en historial. | Sí |

