---
name: appsec-backend
description: Lista de revisión de seguridad para cambios del backend Laravel y MongoDB de UrbanBlade: autenticación por token, autorización e IDOR, inyección NoSQL, datos personales, pagos y límites de tasa. Úsalo al revisar un cambio de barber antes de pedir la fusión.
---
<!-- Generado desde urbanblade-equipo. No editar aquí: cambia el origen y vuelve a sincronizar. -->

# Revisión de seguridad del backend (barber)

Complementa `backend-security-hardening`, `urbanblade-profile-security`, `urbanblade-guardrails` y `urbanblade-stripe-local`: léelos cuando el cambio los toque. Revisas el diff con `git diff origin/main...HEAD`; solo lectura.

## Lista de revisión
1. **Autenticación.** El token Bearer es propio (`mobile_api_tokens`): ¿se registra el token en claro, se compara en tiempo constante, se invalida al cerrar sesión o cambiar contraseña? Nada de sustituirlo por Sanctum sin decisión explícita.
2. **Autorización y propiedad.** Cada endpoint comprueba el rol y que el recurso pertenece al usuario (un cliente no lee citas, pedidos ni perfiles de otro). Busca rutas nuevas sin middleware de autenticación y cualquier ID tomado del cuerpo en lugar del usuario autenticado (IDOR).
3. **Entrada y NoSQL.** Validación en el servidor; sin pasar arreglos del usuario directo a consultas de MongoDB (operadores como `$ne` o `$where`); límites y tipos en filtros y ordenamientos.
4. **Datos personales y secretos.** Que las respuestas no incluyan campos de más (contraseñas, tokens, datos bancarios, datos de otros usuarios); que los logs no guarden PII ni tokens.
5. **Pagos e inventario.** Importes y descuentos calculados en el servidor; los webhooks verifican firma; reintentos idempotentes; sin cobro automático al completar una cita.
6. **Abuso.** Límites de tasa (`throttle`) en autenticación, catálogo y envíos; doble envío y condiciones de carrera en reservas.
7. **Configuración.** Nada de depuración activa, claves en el código ni valores sensibles en `.env.example`.

## Cómo reportar
Por hallazgo: archivo y línea, qué podría hacer un atacante, cómo reproducirlo en pocas palabras y la corrección mínima. Severidad crítica o alta bloquea. Termina con lo que no revisaste.
