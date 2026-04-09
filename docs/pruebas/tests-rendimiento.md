# Pruebas de Rendimiento (Performance Tests)

Estas pruebas miden la velocidad de respuesta, el consumo de recursos y la estabilidad del sistema bajo diferentes cargas de trabajo.

---

## 1. Módulo: Tiempos de Respuesta de Endpoints Críticos
*Objetivo: Mantener una latencia baja para una experiencia de usuario fluida.*

| # | Endpoint Evaluado | Carga | Métrica Objetivo | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 1.1 | POST /api/v1/auth/login | 1 Petición | < 500ms | Sí |
| 1.2 | GET /api/v1/services | 1 Petición | < 300ms | Sí |
| 1.3 | GET /api/v1/availability/slots | 1 Petición | < 600ms (Incluye lógica de bloqueos) | Sí |
| 1.4 | GET /api/v1/inventory/products | 1 Petición | < 400ms (Sin filtros pesados) | Sí |
| 1.5 | POST /api/v1/appointments | 1 Petición | < 1s (Incluye escritura y email) | Sí |
| 1.6 | GET /api/v1/dashboard | 1 Petición | < 500ms (Múltiples conteos DB) | Sí |

---

## 2. Módulo: Escalabilidad y Concurrencia
*Objetivo: Verificar que el sistema no se bloquee ante el uso masivo simultáneo.*

| # | Escenario de Carga | Usuarios Concurrentes | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 2.1 | Agendamiento masivo (Día de apertura) | 50 Usuarios/seg | No deben ocurrir colisiones de `ID` ni errores 500. | Sí |
| 2.2 | Consulta de disponibilidad simultánea | 100 Usuarios/seg | El `AvailabilityService` debe responder sin bloqueos de tabla. | Sí |
| 2.3 | Movimientos de inventario paralelos | 20 Usuarios/seg | El uso de `lockForUpdate()` debe evitar stock negativo. | Sí |
| 2.4 | Lectura de catálogo bajo estrés | 200 Usuarios/seg | El sistema debe usar el cache de Eloquent si está activo. | Sí |
| 2.5 | Peticiones de Chatbot ráfaga | 10 Consultas/seg | El sistema debe encolar las peticiones a la API de Google Gemini. | Sí |
| 2.6 | Generación de PDF de reportes pesados | 5 Reportes/seg | El servidor no debe quedarse sin memoria (DOMPDF memory usage). | Sí |

---

## 3. Módulo: Manejo de Grandes Volúmenes de Datos (Big Data)
*Objetivo: Asegurar que el rendimiento no degrade al crecer la base de datos.*

| # | Escenario de Datos | Volumen | Impacto Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 3.1 | Búsqueda en tabla de Logs | 100,000 Registros | Búsqueda por fecha debe ser rápida mediante índices. | Sí |
| 3.2 | Listado de Clientes | 50,000 Usuarios | La paginación (limit/offset) debe ser inmediata. | Sí |
| 3.3 | Historial de citas acumulado | 200,000 Citas | La carga del dashboard no debe exceder los 2 segundos. | Sí |
| 3.4 | Tabla de Movimientos de Inventario | 1,000,000 Filas | El reporte anual debe generarse sin timeout de PHP (30s). | Sí |
| 3.5 | Archivos adjuntos (fotos de trabajos) | 1,000 Imágenes | El listado de `WorkImage` debe usar miniaturas o Lazy Loading. | Sí |
| 3.6 | Búsqueda de productos por texto | 10,000 Items | El uso de `LIKE '%term%'` debe ser eficiente con índices adecuados. | Sí |

---

## 4. Módulo: Procesamiento de IA y Latencia Externa
*Objetivo: Mitigar el impacto del tiempo de respuesta de servicios externos.*

| # | Escenario IA | Dependencia | Tiempo Máximo Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 4.1 | Respuesta de texto fluida | Google Gemini | < 3s (Latencia de red + procesamiento) | Sí |
| 4.2 | Entrenamiento de bot (Batch) | Gemini Learning | Proceso en segundo plano (Queue) sin afectar API. | Sí |
| 4.3 | Consulta de contexto histórico | Database ↔ AI | < 4s (Extracción de contexto + Prompting) | Sí |
| 4.4 | Timeout del servicio externo | API caída | El sistema debe fallar en < 5s con error controlado. | Sí |
| 4.5 | Límite de caracteres en Prompt | Max Context | No debe causar errores de "Payload too large" (413). | Sí |
| 4.6 | Caché de respuestas frecuentes | Redis/Memcached | < 100ms para preguntas comunes repetidas. | Sí |

---

## 5. Módulo: Rendimiento de Menú Administrador (Web/API)
*Objetivo: Medir estabilidad de acciones administrativas en hora pico.*

| # | Escenario de Carga | Endpoint/Ruta | Umbral Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 5.1 | Dashboard con widgets y telemetría simultánea. | `GET /dashboard` + `GET /api/v1/dashboard` | TTFB < 800ms con datos cacheados. | Sí |
| 5.2 | Búsqueda y paginación masiva de usuarios. | `GET /users?page=10&search=*` | Respuesta < 1.5s con 10k registros. | Sí |
| 5.3 | Inventario de productos con filtros combinados. | `GET /inventory/products` | Respuesta < 1.2s con índices activos. | Sí |
| 5.4 | Exportación concurrente de reportes por admin. | `GET /reports/{type}/{format}` | 5 exportaciones concurrentes sin timeout > 30s. | Sí |
| 5.5 | Consulta de logs con alto volumen diario. | `GET /logs` + `GET /api/v1/logs` | Listado inicial < 2s con paginación estable. | Sí |
| 5.6 | Ráfaga de consultas de chatbot desde panel admin. | `POST /chatbot/query` | p95 < 3.5s y tasa de error < 2%. | Sí |

---

## 6. Módulo: Rendimiento de Recepción (Web/API)
*Objetivo: Asegurar continuidad operativa en hora pico de atención presencial.*

| # | Escenario de Carga | Endpoint/Ruta | Umbral Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 6.1 | Dashboard operativo bajo alta rotación de citas. | `GET /dashboard` | Render inicial < 1s con datos actualizados. | Sí |
| 6.2 | Alta concurrente de clientes en mostrador. | `POST /clients` | 20 altas concurrentes sin errores de bloqueo. | Sí |
| 6.3 | Registro continuo de pagos en caja. | `POST /payments` + `GET /api/v1/payments` | p95 < 1.5s y consistencia de folios. | Sí |
| 6.4 | Movimientos de inventario durante jornada pico. | `POST /inventory/movements` | p95 < 1.2s y sin inconsistencias de stock. | Sí |
| 6.5 | Búsqueda/paginación de citas masivas. | `GET /appointments?page=n` | Respuesta < 1.5s con 5k+ registros. | Sí |
| 6.6 | Consultas de apoyo al chatbot en recepción. | `POST /chatbot/query` | Respuesta < 3s y tasa de error < 3%. | Sí |

