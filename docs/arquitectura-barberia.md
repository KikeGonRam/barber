# Arquitectura actual (Barberia + Chatbot IA)

## Estructura de carpetas

- `app/Http/Controllers` -> Controladores por modulo (`Admin`, `Barber`, `Reception`, `Client`, `Chatbot`).
- `app/Http/Requests` -> Validaciones por accion y modulo.
- `app/Http/Resources` -> Transformadores para respuestas API.
- `app/Repositories/Contracts` -> Interfaces de acceso a datos.
- `app/Repositories/Eloquent` -> Implementaciones Eloquent de los contratos.
- `app/Services` -> Casos de uso y logica de dominio.
- `app/Exceptions/Domain` -> Excepciones de negocio personalizadas.
- `app/Http/Middleware/Role` -> Middlewares de autorizacion por rol.
- `app/Models` -> Entidades del dominio con relaciones Eloquent.

## Arquitectura del chatbot

El chatbot esta orquestado desde `ChatbotController` y usa un flujo por prioridad de 5 niveles:

1. Historial local similar (respuesta rapida con contexto previo).
2. Inteligencia local de base de datos.
3. Logica manual de fallback.
4. Datos externos (Wikipedia/OpenStreetMap).
5. Gemini IA como ultima capa.

### Servicios involucrados

- `GeminiService`: comunicacion con Gemini y generacion de respuesta con prompt aumentado.
- `ChatbotIntelligenceService`: respuestas desde datos del negocio (tendencias, servicios, etc.).
- `ChatbotExternalDataService`: datos externos de Wikipedia y OpenStreetMap.
- `ChatbotContextService`: historial, resumen y deteccion de preguntas similares.
- `ChatbotUserProfileService`: perfil contextual del usuario y estilo conversacional.
- `ChatbotLearningService`: deteccion de intencion, aprendizaje de preguntas y feedback.

## Endpoints del chatbot

- `POST /chatbot/query`: consulta principal.
- `GET /chatbot/history`: historial de conversacion autenticado.
- `GET /chatbot/profile`: perfil contextual autenticado.
- `POST /chatbot/clear-history`: limpia historial y perfil autenticado.
- `GET /chatbot/learning-stats`: reporte de aprendizaje autenticado.
- `POST /chatbot/train-history`: entrenamiento desde historial autenticado.

## Convencion por modulo

- Citas: `AppointmentController`, `StoreAppointmentRequest`, `AppointmentRepositoryInterface`, `AppointmentService`.
- Servicios: `ServiceController`, `StoreServiceRequest`, `ServiceRepositoryInterface`, `ServiceService`.
- Pagos: `PaymentController`, `StorePaymentRequest`, `PaymentRepositoryInterface`, `PaymentService`.
- Inventario: `ProductController`, `InventoryMovementController`, requests y services respectivos.
- Chatbot IA: `ChatbotController` + servicios de contexto/inteligencia/aprendizaje.

## Idioma y UX

- Vistas Blade en espanol (Mexico).
- Frontend con TailwindCSS + Vite.
- Configuracion de zona horaria y locale en `config/app.php`.
