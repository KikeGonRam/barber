# Auditoría de Seguridad de la API - BarberPro

Este documento detalla la implementación de controles de seguridad sobre los 59 endpoints de la API, alineados con el estándar **OWASP API Security Top 10**.

## 1. Protección contra Broken Object Level Authorization (BOLA/IDOR)
- **Superficie:** `/api/v1/appointments/{id}`, `/api/v1/payments/{id}`, `/api/v1/clients/{id}`.
- **Implementación:** Uso de **Laravel Policies**. Cada consulta de recurso verifica que el `user_id` del token coincida con el propietario del recurso o que el usuario tenga un rol administrativo.
- **Verificación:** `test_api_prevents_idor_on_appointments`.

## 2. Autenticación y Gestión de Sesiones
- **Tecnología:** **Laravel Sanctum** (Stateful API Tokens).
- **Hardening:**
  - Tokens con expiración configurada.
  - Revocación inmediata en `/logout`.
  - Hashing de contraseñas mediante **Argon2id** (estándar moderno).

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
- **Verificación:** `test_api_enforces_rate_limiting_on_login`.

## 6. Seguridad en la Capa de Transporte (Docker)
- **Arquitectura:** Nginx actúa como **Reverse Proxy**, ocultando el motor PHP-FPM del exterior.
- **Seguridad Alpine:** La imagen de producción utiliza Alpine Linux, eliminando binarios innecesarios y reduciendo la superficie de ataque del sistema operativo.

---
**Resultado de Auditoría Automatizada:**
- 100% de los endpoints protegidos por Middleware de autenticación (excepto Login/Registro).
- Cero vulnerabilidades críticas detectadas en la suite de Pentest.
