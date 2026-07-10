# Mapeo de Paridad Web vs API (Administrador)

Este documento compara la cobertura funcional del panel web con la API disponible para determinar si ambas superficies son equivalentes.

## 1. Criterio de Paridad

- `Igual`: Web y API exponen el mismo conjunto de acciones para el módulo.
- `Parcial`: API cubre solo una parte del flujo web.
- `No equivalente`: El flujo existe en web, pero no existe endpoint API equivalente en este proyecto.

## 2. Matriz de Paridad

| Módulo / Acción del Menú Admin | Web (routes/web.php) | API (routes/api.php) | Paridad |
| :--- | :--- | :--- | :--- |
| Dashboard | `GET /dashboard` | `GET /api/v1/dashboard` | Igual |
| Muro Inspiración | `GET /descubrir` + interacciones social | `GET /api/v1/social/feed` + `POST /api/v1/social/work/*` | Igual |
| Citas | Resource `/appointments` (index/create/store/edit/update/destroy) | `GET/POST/PUT/PATCH status/DELETE /api/v1/appointments` | Igual |
| Clientes | Resource `/clients` (CRUD completo sin show) | `GET/POST/PUT/DELETE /api/v1/clients` | Igual |
| Pagos | `GET/CREATE/STORE/DESTROY /payments` + `receipt` | `GET/POST/DELETE /api/v1/payments` + `GET /api/v1/payments/{id}/receipt` | Igual |
| Movimientos de inventario | `GET/CREATE/STORE /inventory/movements` | `GET/POST /api/v1/inventory/movements` | Igual |
| Barberos | `GET/EDIT/UPDATE /barbers` | `GET/PUT /api/v1/barbers/manage` (+ catálogo `GET /api/v1/barbers`) | Igual |
| Usuarios | Resource `/users` (CRUD completo sin show) | `GET/POST/PUT/DELETE /api/v1/users` | Igual |
| Servicios | Resource `/services` (CRUD completo sin show) + `GET /servicios` público | `GET /api/v1/services` + `GET/POST/PUT/DELETE /api/v1/services/manage` | Igual |
| Productos | Resource `/inventory/products` (CRUD completo sin show) | `GET/POST/PUT/DELETE /api/v1/inventory/products` | Igual |
| Configuración | `GET/PUT /settings` + `POST /settings/maintenance` | `GET/PUT /api/v1/settings` + `POST /api/v1/settings/maintenance` | Igual |
| Reportes | `GET /reports` + `GET /reports/{type}/{format}` | `GET /api/v1/reports` + `GET /api/v1/reports/{type}/{format}` | Igual |
| Logs | `GET /logs` | `GET /api/v1/logs` | Igual |
| Notificaciones | `GET /notifications` + `POST /notifications/read-all` | `GET /api/v1/notifications` + `POST /api/v1/notifications/read-all` | Igual |
| Chatbot | `POST /chatbot/query` + `GET/POST /chatbot/*` | `POST /api/v1/chatbot/query` + `GET/POST /api/v1/chatbot/*` | Igual |
| Cerrar sesión | `POST /logout` | `POST /api/v1/auth/logout` | Igual |

## 3. Veredicto General

La API fue actualizada para igualar la cobertura funcional del Web Admin.

- Módulos con paridad `Igual`: todos los módulos del menú administrador y acciones transversales.
- Módulos con paridad `Parcial`: ninguno en el estado actual.
- Módulos `No equivalente` en API v1: ninguno en el estado actual.

## 4. Brechas Funcionales Detectadas

1. Mantener sincronizadas validaciones entre controladores web y API cuando se agreguen reglas nuevas.
2. Verificar en CI que el listado de rutas web críticas tenga contraparte API y viceversa.
3. Añadir pruebas de regresión contractuales (schema JSON) para evitar drift de payloads.
4. Conservar permisos por rol en ambos canales (UI y API) para evitar escalación.

## 5. Recomendación QA

- Mantener dos suites de validación complementarias:
  - `Web Admin Suite`: experiencia de interfaz y flujos de navegación.
  - `API Contract Suite`: validación de contratos, permisos y payloads.
- Incorporar smoke diario sobre endpoints críticos (`dashboard`, `appointments`, `payments`, `inventory`, `chatbot`).
- Versionar cambios de API con changelog técnico para clientes móviles.

## 6. Matriz de Paridad Específica: Recepcionista

| Menú Recepcionista | Web (routes/web.php) | API (routes/api.php) | Paridad |
| :--- | :--- | :--- | :--- |
| Dashboard | `GET /dashboard` | `GET /api/v1/dashboard` | Igual |
| Muro Inspiración | `GET /descubrir` | `GET /api/v1/social/feed` | Igual |
| Citas | Resource `/appointments` (CRUD web) | `GET/POST/PUT/PATCH status/DELETE /api/v1/appointments` | Igual |
| Clientes | Resource `/clients` (CRUD web) | `GET/POST/PUT/DELETE /api/v1/clients` | Igual |
| Pagos | `GET/CREATE/STORE/DESTROY /payments` | `GET/POST/DELETE /api/v1/payments` | Igual |
| Movimientos | `GET/CREATE/STORE /inventory/movements` | `GET/POST /api/v1/inventory/movements` | Igual |
| Notificaciones | `GET /notifications` + `POST /notifications/read-all` | `GET /api/v1/notifications` + `POST /api/v1/notifications/read-all` | Igual |
| Chatbot | `POST /chatbot/query` + `/chatbot/*` autenticado | `POST /api/v1/chatbot/query` + `/api/v1/chatbot/*` | Igual |
| Cerrar sesión | `POST /logout` | `POST /api/v1/auth/logout` | Igual |

Veredicto recepcionista: la API ahora replica el flujo operativo de recepción con paridad funcional.
