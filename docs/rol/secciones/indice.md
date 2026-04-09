# API: Índice de Endpoints Documentados

Esta sección resume todos los endpoints REST documentados para la API de la barbería. Cada recurso tiene su propio archivo con ejemplos y todos los campos.

## Endpoints y Documentación

- [Autenticación (auth)](auth.md)
  - POST /api/v1/auth/login
  - POST /api/v1/auth/register
  - GET /api/v1/auth/me
  - POST /api/v1/auth/logout

- [Usuarios (users)](users.md)
  - GET /api/v1/users

- [Clientes (clients)](clients.md)
  - GET /api/v1/clients

- [Citas (appointments)](appointments.md)
  - GET /api/v1/appointments
  - POST /api/v1/appointments
  - PATCH /api/v1/appointments/{appointment}/status
  - DELETE /api/v1/appointments/{appointment}

- [Inventario (inventory)](inventory.md)
  - GET /api/v1/inventory/products
  - GET /api/v1/inventory/movements

- [Pagos (payments)](payments.md)
  - GET /api/v1/payments
  - POST /api/v1/payments

- [Logs (logs)](logs.md)
  - GET /api/v1/logs

- [Catálogo (catalog)](catalog.md)
  - GET /api/v1/services
  - GET /api/v1/barbers

- [Disponibilidad (availability)](availability.md)
  - GET /api/v1/availability/slots

- [Dashboard (dashboard)](dashboard.md)
  - GET /api/v1/dashboard

- [Chatbot (chatbot)](chatbot.md)
  - POST /api/v1/chatbot/query
  - GET /api/v1/chatbot/history
  - POST /api/v1/chatbot/clear-history
  - GET /api/v1/chatbot/profile

---

Cada archivo contiene ejemplos de request y response, descripción de parámetros y todos los campos esperados.
