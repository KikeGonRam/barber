# Arquitectura base propuesta

## Estructura de carpetas

- `app/Http/Controllers` → Controladores por módulo (`Admin`, `Barber`, `Reception`, `Client`).
- `app/Http/Requests` → Validaciones por acción y módulo.
- `app/Http/Resources` → Transformadores para respuestas API futuras.
- `app/Repositories/Contracts` → Interfaces de acceso a datos.
- `app/Repositories/Eloquent` → Implementaciones Eloquent de los contratos.
- `app/Services` → Casos de uso/lógica de dominio.
- `app/Exceptions/Domain` → Excepciones de negocio personalizadas.
- `app/Http/Middleware/Role` → Middlewares personalizados de autorización por rol.
- `app/Models` → Entidades del dominio con relaciones Eloquent.

## Convención sugerida por módulo

- Citas: `AppointmentController`, `StoreAppointmentRequest`, `AppointmentRepositoryInterface`, `AppointmentService`.
- Servicios: `ServiceController`, `StoreServiceRequest`, `ServiceRepositoryInterface`, `ServiceService`.
- Pagos: `PaymentController`, `StorePaymentRequest`, `PaymentRepositoryInterface`, `PaymentService`.
- Inventario: `ProductController`, `InventoryMovementController`, requests y services respectivos.

## Idioma y UX

- Vistas Blade en español (México).
- Componentes UI con Bootstrap 5.
- Configuración de zona horaria y locale en `config/app.php`.
