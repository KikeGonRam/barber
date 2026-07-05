# Auditoría de Seguridad de la API - BarberPro

Este documento detalla la implementación de controles de seguridad sobre los 59 endpoints de la API, alineados con el estándar **OWASP API Security Top 10**.

## 1. Protección contra Broken Object Level Authorization (BOLA/IDOR)
- **Superficie:** `/api/v1/appointments/{id}`, `/api/v1/payments/{id}`, `/api/v1/clients/{id}`.
- **Implementación:** No se usan Laravel Policies (el proyecto no tiene `app/Policies/`). La verificación de propiedad se hace manualmente en cada controlador con `abort_if()`, comparando el `client_id`/`user_id` del recurso contra el usuario autenticado, o permitiendo el acceso si el rol es `administrador`. Ver por ejemplo `App\Http\Controllers\Api\AppointmentController::destroy()`.
- **Verificación:** cubierto en `tests/Feature/Appointments/ClientBookingTest.php` (`test_client_cannot_manage_another_clients_appointment`).

## 2. Autenticación y Gestión de Sesiones
- **Tecnología:** sistema de tokens **propio**, no Laravel Sanctum. `App\Models\User::issueMobileApiToken()` genera un token aleatorio de 32 bytes, guarda su hash SHA-256 en `App\Models\MobileApiToken` (colección `mobile_api_tokens`) y devuelve el token en texto plano una sola vez. El middleware `App\Http\Middleware\AuthenticateMobileApiToken` valida cada request contra ese hash.
- **Hardening:**
  - Tokens con expiración opcional (`expires_at`) y revocación en `/logout` (se elimina el registro de `MobileApiToken`).
  - Hashing de contraseñas: `Hash::make()` con el driver por defecto de Laravel (**bcrypt**) — no hay override en `config/hashing.php`, así que no usa Argon2id.

## 3. Prevención de Inyección (SQLi, XSS)
- **SQLi:** El 100% de las consultas utilizan **Eloquent ORM** o **Query Builder** con parametrización de datos. No se concatenan strings en queries.
- **XSS:** La API solo responde en formato `application/json`. Los datos de salida son escapados automáticamente y el navegador no interpreta el JSON como HTML.

## 4. Control de Acceso Granular (RBAC)
- **Motor:** `spatie/laravel-permission`.
- **Estrategia:** Middlewares específicos por endpoint:
  - `role:administrador` para `/users` y `/settings`.
  - `role:recepcionista|administrador` para `/inventory`.
  - `role:cliente` para agendamiento propio.

## 5. Limitación de Recursos y Rate Limiting
- **Protección DoS:** 
  - Login limitado a 5 intentos por minuto por IP.
  - Chatbot limitado a ráfagas de 10 peticiones para prevenir costos excesivos de API y agotamiento de recursos del servidor.
- **Verificación:** cubierto en `tests/Feature/Auth/AuthenticationTest.php` (`test_login_is_rate_limited_after_too_many_attempts`).

## 6. Seguridad en la Capa de Transporte (Docker)
- **Arquitectura:** Nginx actúa como **Reverse Proxy**, ocultando el motor PHP-FPM del exterior.
- **Seguridad Alpine:** La imagen de producción utiliza Alpine Linux, eliminando binarios innecesarios y reduciendo la superficie de ataque del sistema operativo.

---
**Nota:** los porcentajes y "cero vulnerabilidades" de una auditoría automatizada anterior se retiraron de este documento por no poder verificarse contra el código actual. Las secciones 1-6 arriba sí están verificadas contra la implementación real al momento de esta revisión.
