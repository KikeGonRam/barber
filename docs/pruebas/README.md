# Plan Maestro de Pruebas - BarberPro Management System

Este documento define la jerarquía y el alcance de las pruebas para el sistema de gestión integral de barberías **BarberPro**.

## 1. Jerarquía de Módulos (Estructura Admin)

### I. Principal & Social
- **Dashboard Operativo:** Telemetría en vivo, ingresos, ocupación de estaciones.
- **Muro Inspiración:** Feed social de trabajos y tendencias.

### II. Operación Diaria
- **Citas:** Agendamiento y control de estados.
- **Clientes:** CRM y perfiles de usuario.
- **Pagos:** Registro de transacciones y conciliación.
- **Movimientos:** Trazabilidad de entradas/salidas de productos.

### III. Gestión & Almacén
- **Almacén Central:** Gestión técnica de stock e insumos críticos.
- **Catálogos:** Barberos, Usuarios, Servicios y Productos.

### IV. Análisis & Configuración
- **Reportes:** Exportaciones PDF/Excel de rendimiento.
- **Logs & Telemetría:** Auditoría de sistema y métricas del Chatbot (Latencia/Costos).
- **Modo Mantenimiento:** Control global de disponibilidad del sistema.

---

## 2. Matriz de Roles (BarberPro Edition)
| Rol | Acceso Principal |
| :--- | :--- |
| **Administrador** | **Acceso Total:** Telemetría, Almacén Central, Gestión de Usuarios y Configuración. |
| **Recepcionista** | **Operación:** Pagos, Citas, Clientes y Movimientos de Inventario. |
| **Barbero** | **Gestión:** Muro Inspiración, Dashboard (sus citas) y Mi Agenda. |
| **Cliente** | **Reserva:** App Móvil, Chatbot y Catálogo de servicios. |

---

## 3. Mapeo Oficial: Menú Administrador -> Endpoints Web/API

La siguiente matriz alinea exactamente las acciones visibles del menú del administrador con sus rutas del sistema y, cuando aplica, su endpoint de API equivalente.

| Menú Administrador | Ruta Web (UI) | Endpoint API Relacionado | Cobertura QA |
| :--- | :--- | :--- | :--- |
| Dashboard | GET `/dashboard` | GET `/api/v1/dashboard` | Caja Negra, Humo, Seguridad, Rendimiento |
| Muro Inspiración | GET `/descubrir` | N/A (flujo web/social) | Caja Negra, Integración, Sistema |
| Citas | Resource `/appointments` | GET/POST/PATCH/DELETE `/api/v1/appointments*` | CRUD completo + Regresión |
| Clientes | Resource `/clients` | GET `/api/v1/clients` | CRUD web + consumo API |
| Pagos | GET/POST/DELETE `/payments*` | GET/POST `/api/v1/payments` | Operación + aceptación |
| Movimientos | GET/POST `/inventory/movements*` | GET `/api/v1/inventory/movements` | Inventario + integración |
| Barberos | GET/PUT `/barbers*` | GET `/api/v1/barbers` | Gestión y permisos |
| Usuarios | Resource `/users` | GET `/api/v1/users` | CRUD administrativo |
| Servicios | Resource `/services` | GET `/api/v1/services` | Catálogo + regresión |
| Productos | Resource `/inventory/products` | GET `/api/v1/inventory/products` | Inventario estratégico |
| Almacén Central | Resource `/almacen` | N/A (flujo web interno) | Sistema + caja negra |
| Configuración | GET/PUT `/settings` + POST `/settings/maintenance` | N/A | Seguridad + sistema |
| Reportes | GET `/reports` + GET `/reports/{type}/{format}` | N/A | Aceptación + rendimiento |
| Logs | GET `/logs` | GET `/api/v1/logs` | Auditoría + seguridad |
| Notificaciones | GET `/notifications` + POST `/notifications/read-all` | N/A | Sistema + aceptación |
| Chatbot | POST `/chatbot/query` + endpoints `/chatbot/*` | POST `/api/v1/chatbot/query` + `/api/v1/chatbot/*` | Funcional + AI Safety |
| Cerrar Sesión | POST `/logout` | POST `/api/v1/auth/logout` | Seguridad de sesión |

---

## 4. Validación de Paridad Web vs API

Se generó un mapeo independiente de paridad para responder si la API es lo mismo que la Web (Administrador y Recepcionista):

- Archivo de referencia: `docs/pruebas/mapeo-web-vs-api.md`
- Veredicto actual: **Paridad funcional alcanzada**.
- La API v1 ya cubre acciones de menú y operaciones por rol para Administrador y Recepcionista.
- Se mantiene monitoreo de contratos para evitar divergencia en futuras iteraciones.

---

## 5. Resultados de Ejecución de Pruebas Automatizadas (2026-04-10)

- **Total de pruebas ejecutadas:** 160
- **Pruebas pasadas:** 156
- **Pruebas fallidas:** 4

### Pruebas fallidas y diagnóstico

1. **Tests\Feature\Api\MobileApiParityAccessTest > admin can manage settings and reports from mobile api**
   - **Causa:** El test espera que el endpoint `/api/v1/settings/maintenance` active el modo mantenimiento y que el cambio se refleje en la base de datos (`maintenance_mode`). Sin embargo, la aserción `BarbershopSetting::query()->firstOrFail()->maintenance_mode` es falsa, lo que indica que el cambio no se persistió correctamente.
   - **Solución propuesta:** Revisar el controlador y el modelo para asegurar que el endpoint realmente actualiza el campo `maintenance_mode` y que no hay problemas de caché o transacción. Verificar que el entorno de pruebas no esté sobrescribiendo la base de datos tras la petición.

2. **Tests\Feature\Auth\RegistrationTest > new users can register**
   - **Causa:** El test espera que tras el registro el usuario esté autenticado y sea redirigido al dashboard. El error indica que el usuario no está autenticado tras el registro (`$this->assertAuthenticated()` falla).
   - **Solución propuesta:** Verificar el flujo de registro en el controlador, especialmente el middleware y el evento de login tras crear el usuario. Asegurarse de que no haya cambios recientes en el guard, eventos o listeners que impidan el login automático tras registro.

3. **Tests\Feature\E2E\CompleteBookingFlowE2ETest > complete booking happy path from register to payment completion**
   - **Causa:** El test espera un código de respuesta de redirección tras el registro, pero recibe un 500 (error interno). Esto sugiere un fallo en el flujo de registro o en la creación del usuario/cliente.
   - **Solución propuesta:** Revisar los logs de error de Laravel (`storage/logs/laravel.log`) para identificar la excepción exacta. Es probable que esté relacionado con el mismo problema del test de registro anterior.

4. **Tests\Feature\Profiles\RolePortalFlowTest > registration assigns cliente role and profile**
   - **Causa:** Similar al anterior, el test espera un código de redirección tras el registro, pero recibe un 500. Esto indica un fallo en el flujo de registro y asignación de rol/perfil.
   - **Solución propuesta:** Revisar el flujo de creación de usuario y perfil de cliente, así como los listeners/eventos asociados al registro. Verificar migraciones y seeders para asegurar que los roles y relaciones existen.

---

### Próximos pasos
- Se recomienda revisar y corregir los flujos de registro y mantenimiento descritos arriba.
- Una vez corregidos, volver a ejecutar la suite completa y actualizar este documento con los nuevos resultados.
- Para ejecutar todas las pruebas por sección, ver archivo `docs/pruebas/comandos-ejecucion.md`.
