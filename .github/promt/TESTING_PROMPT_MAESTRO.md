# ══════════════════════════════════════════════════════════════════════
#  PROMPT MAESTRO DE TESTING — BarberPro Elite (Laravel 12 / PHP 8.4)
# ══════════════════════════════════════════════════════════════════════

## ROL Y CONTEXTO

Eres un Senior QA Engineer con especialidad en Laravel y PHP 8.4.
Tu misión es generar una suite de tests profesional, exhaustiva y ejecutable
para el proyecto **BarberPro Elite**, un sistema de gestión de barberías de lujo.

---

## STACK TECNOLÓGICO

- **Framework:** Laravel 12.56 / PHP 8.4
- **Frontend:** Blade + TailwindCSS 4 + Alpine.js + Vite
- **Base de datos:** MySQL 8.0 (tests con SQLite in-memory o RefreshDatabase)
- **Auth & Permisos:** Spatie Permission (roles: administrador, recepcionista, barbero, cliente)
- **IA:** Google Gemini API
- **PDF:** DomPDF
- **Excel:** Maatwebsite Excel (PhpSpreadsheet)
- **Auditoría:** Spatie Activity Log
- **Testing base:** PHPUnit + Laravel TestCase + RefreshDatabase
- **Roles del sistema:** `administrador`, `recepcionista`, `barbero`, `cliente`

---

## MODELOS PRINCIPALES

```
User           → name, email, password, email_verified_at (SoftDeletes)
Barber         → user_id, activo, foto, especialidades, descripcion
Client         → user_id, preferencias_notificacion (JSON)
Service        → nombre, categoria, precio, duracion_min, activo
Appointment    → client_id, barber_id, service_id, fecha, hora_inicio,
                 hora_fin, estado (pendiente|confirmada|en_proceso|completada|cancelada),
                 precio_cobrado, reminder_24h_sent_at
Payment        → appointment_id, monto, metodo_pago, propina,
                 created_by, comprobante_pdf
Product        → nombre, categoria, precio_compra, precio_venta,
                 stock_actual, stock_minimo, tipo (venta_cliente|insumo_trabajo)
InventoryMovement → product_id, tipo (entrada|salida), cantidad, motivo
Work           → barbero_id, title, description + WorkImage (image path)
```

---

## RUTAS CLAVE

```php
// Públicas
GET  /login, /register, /forgot-password

// Autenticadas + verificadas
GET  /dashboard
GET  /appointments/index         → appointments.index
POST /appointments               → appointments.store
GET  /payments                   → payments.index
POST /payments                   → payments.store
GET  /clients                    → clients.index
GET  /barbers                    → barbers.index
GET  /services                   → services.index
POST /services                   → services.store
GET  /inventory/products         → inventory.products.index
POST /inventory/movements        → inventory.movements.store
GET  /reports                    → reports.index
GET  /reports/export             → reports.export (params: type, format)
GET  /settings/edit              → settings.edit
GET  /logs                       → logs.index
GET  /users                      → users.index
POST /users                      → users.store

// Portal Barbero
GET  /barber/agenda              → barber.agenda
PATCH /barber/appointments/{id}/status → barber.appointments.status
PUT  /barber/profile             → barber.profile.update

// Portal Cliente
GET  /client/appointments        → client.appointments.index
POST /client/appointments        → client.appointments.store

// Notificaciones
GET  /notifications              → notifications.index
POST /notifications/read-all     → notifications.read-all

// Trabajos del barbero
POST /barbers/{barber}/works     → barbers.works.store
```

---

## SUITE DE TESTS A GENERAR

Genera cada categoría en su propio archivo con namespace correcto.
Todos los tests deben ser ejecutables con `php artisan test`.

---

### 1. 🔥 PRUEBAS DE HUMO (Smoke Tests)
**Archivo:** `tests/Feature/Smoke/CriticalPathSmokeTest.php`

Verifica que las rutas más importantes devuelven HTTP 200 o el redirect esperado.
No valides lógica de negocio, solo que el sistema arranca y responde.

Casos requeridos:
- [ ] GET `/login` → 200
- [ ] GET `/register` → 200
- [ ] GET `/dashboard` sin autenticar → redirect a login
- [ ] GET `/dashboard` autenticado + verificado → 200
- [ ] GET `/appointments` autenticado + rol recepcionista → 200
- [ ] GET `/reports` con rol administrador → 200
- [ ] GET `/barber/agenda` con rol barbero → 200
- [ ] GET `/client/appointments` con rol cliente → 200
- [ ] Ruta inexistente → 404

---

### 2. ⬛ PRUEBAS DE CAJA NEGRA (Black Box)
**Archivo:** `tests/Feature/BlackBox/AppointmentBlackBoxTest.php`

El tester no conoce la implementación, solo entradas y salidas esperadas.

Casos:
- [ ] Crear cita con datos válidos → redirect + registro en BD
- [ ] Crear cita con `hora_inicio` en formato inválido (texto, null) → error 422 o redirect con errores
- [ ] Crear cita con `barber_id` inexistente → error de validación
- [ ] Crear cita con fecha pasada → error de validación
- [ ] Crear cita solapada para el mismo barbero → error en `hora_inicio`
- [ ] Crear cita solapada para barbero diferente → OK (no hay conflicto)
- [ ] Crear cita con `hora_fin` anterior a `hora_inicio` → error de validación

---

### 3. ⬜ PRUEBAS DE CAJA BLANCA (White Box)
**Archivo:** `tests/Unit/WhiteBox/AppointmentOverlapLogicTest.php`

Prueba la lógica interna del servicio/validador de solapamientos directamente.

Casos (instanciar la clase/Rule directamente sin HTTP):
- [ ] Rango [10:00-10:30] no solapa con [10:30-11:00] → válido
- [ ] Rango [10:00-10:30] solapa con [09:45-10:15] → inválido
- [ ] Rango [10:00-10:30] solapa con [09:00-11:00] (contenido dentro) → inválido
- [ ] Rango [10:00-10:30] solapa con [10:00-10:30] (idéntico) → inválido
- [ ] Cita cancelada del mismo barbero no bloquea el slot → válido
- [ ] Stock a 0 unidades no permite salida → inválido
- [ ] Stock exacto permite salida exacta → válido

---

### 4. 🔗 PRUEBAS DE INTEGRACIÓN (Integration Tests)
**Archivo:** `tests/Feature/Integration/PaymentAppointmentIntegrationTest.php`

Verifica que múltiples capas del sistema (Controller → Service → Model → DB) funcionan juntas.

Casos:
- [ ] Registrar un pago actualiza `estado` de la cita a `completada` y guarda `precio_cobrado`
- [ ] Registrar un pago genera un PDF y lo guarda en storage (usar `Storage::fake('public')`)
- [ ] Crear movimiento de salida decrementa `stock_actual` del producto
- [ ] Crear movimiento de entrada incrementa `stock_actual` del producto
- [ ] Crear cita dispara notificación al cliente (usar `Notification::fake()`)
- [ ] El comando `appointments:send-reminders` envía notificación 24h y marca `reminder_24h_sent_at`
- [ ] Crear usuario con rol dispara registro en log de actividad (Spatie Activity Log)

---

### 5. ⚙️ PRUEBAS FUNCIONALES (Functional Tests)
**Archivo:** `tests/Feature/Functional/ServiceCRUDFunctionalTest.php`

Valida flujos completos de negocio desde la perspectiva del usuario.

Casos:
- [ ] Admin crea servicio → aparece en el listado
- [ ] Admin edita servicio → cambios persisten
- [ ] Admin desactiva servicio → no aparece en listado de activos
- [ ] Admin activa servicio inactivo → vuelve a aparecer
- [ ] Admin crea producto → stock inicial correcto
- [ ] Recepcionista registra salida → stock baja correctamente
- [ ] Recepcionista intenta registrar entrada → error 403 o validación
- [ ] Admin exporta reporte de ingresos en PDF → 200 con Content-Type PDF
- [ ] Admin exporta reporte de ingresos en Excel → 200 con Content-Type Excel

---

### 6. 🔁 PRUEBAS DE EXTREMO A EXTREMO (E2E Feature Tests)
**Archivo:** `tests/Feature/E2E/CompleteBookingFlowE2ETest.php`

Simula el flujo completo de un cliente agendando y pagando una cita.

Flujo 1 — "Happy path" completo:
- [ ] Usuario se registra → rol `cliente` asignado → perfil creado
- [ ] Cliente ve lista de barberos disponibles
- [ ] Cliente crea cita con barbero y servicio → estado `pendiente`
- [ ] Recepcionista confirma cita → estado `confirmada`
- [ ] Barbero cambia estado a `en_proceso`
- [ ] Recepcionista registra pago → estado `completada` + PDF generado
- [ ] Cliente ve la cita como `completada` en su portal

Flujo 2 — Cancelación:
- [ ] Cliente crea cita
- [ ] Recepcionista cancela la cita
- [ ] El slot queda libre para otra cita del mismo barbero

---

### 7. ✅ PRUEBAS DE ACEPTACIÓN (Acceptance / UAT Tests)
**Archivo:** `tests/Feature/Acceptance/UserStoryAcceptanceTest.php`

Basadas en criterios de aceptación de historias de usuario reales.

Historia 1: "Como recepcionista, quiero crear citas sin solapamientos"
- [ ] AC1: Puedo crear cita si el barbero está libre en ese horario
- [ ] AC2: El sistema me bloquea si intento agendar en horario ocupado
- [ ] AC3: Puedo ver todas las citas del día en el índice

Historia 2: "Como administrador, quiero gestionar el inventario"
- [ ] AC1: Puedo crear productos con stock inicial
- [ ] AC2: Recibo alerta visual si un producto está bajo el stock mínimo
- [ ] AC3: Puedo ver el historial de movimientos de un producto

Historia 3: "Como cliente, quiero gestionar mis propias citas"
- [ ] AC1: Solo veo mis citas, no las de otros clientes
- [ ] AC2: Puedo crear una cita desde mi portal
- [ ] AC3: Recibo notificación de confirmación al crear una cita

Historia 4: "Como barbero, quiero controlar mi agenda"
- [ ] AC1: Solo puedo cambiar el estado de mis propias citas
- [ ] AC2: Intentar modificar cita de otro barbero devuelve 403
- [ ] AC3: Puedo subir fotos de mis trabajos con múltiples imágenes

---

### 8. 🔐 PRUEBAS DE SEGURIDAD (Security Tests)
**Archivo:** `tests/Feature/Security/SecurityHardeningTest.php`

- [ ] Guest no puede acceder a ninguna ruta protegida (lista completa)
- [ ] Usuario sin verificar email es redirigido a verificación en rutas `verified`
- [ ] Recepcionista no puede acceder a rutas de administrador
- [ ] Cliente no puede ver citas de otros clientes (IDOR check)
- [ ] Barbero no puede modificar perfil de otro barbero
- [ ] Campos de formulario son sanitizados (XSS: `<script>alert(1)</script>` en nombre)
- [ ] CSRF token inválido en POST → 419
- [ ] Mass assignment: enviar campo `is_admin` no modifica el usuario

---

### 9. ⚡ PRUEBAS DE RENDIMIENTO (Performance Tests)
**Archivo:** `tests/Feature/Performance/DatabaseQueryPerformanceTest.php`

Usando `DB::listen` o el Query Counter de Laravel.

- [ ] Listado de citas (con 100 registros) no ejecuta más de 5 queries (N+1 check)
- [ ] Listado de clientes con 200 registros responde en menos de 500ms
- [ ] Dashboard con estadísticas no supera 10 queries
- [ ] Exportación de reporte con 500 pagos no supera 15 segundos
- [ ] Búsqueda de clientes por nombre usa índice (query < 50ms con 1000 registros)

Para medir tiempo:
```php
$start = microtime(true);
// acción
$elapsed = microtime(true) - $start;
$this->assertLessThan(0.5, $elapsed, "Respuesta tardó más de 500ms");
```

Para contar queries:
```php
$queryCount = 0;
DB::listen(fn() => $queryCount++);
// acción
$this->assertLessThanOrEqual(5, $queryCount, "Demasiadas queries (N+1 detectado)");
```

---

### 10. 🔔 PRUEBAS DE NOTIFICACIONES (Notification Tests)
**Archivo:** `tests/Feature/Notifications/NotificationSystemTest.php`

- [ ] Crear cita envía notificación in-app al cliente
- [ ] Crear cita envía email al cliente (si `preferencias_notificacion.email = true`)
- [ ] Cliente con `email: false` NO recibe email al crear cita
- [ ] Comando `appointments:send-reminders` solo envía a citas de mañana
- [ ] Comando no reenvía recordatorio si `reminder_24h_sent_at` ya tiene valor
- [ ] Marcar todas como leídas → `unreadNotifications()->count() === 0`
- [ ] Badge de notificaciones se muestra cuando hay no leídas
- [ ] Badge desaparece después de marcar como leídas

---

### 11. 📁 PRUEBAS DE ARCHIVOS Y STORAGE (File Tests)
**Archivo:** `tests/Feature/Storage/FileUploadTest.php`

Usar siempre `Storage::fake('public')`.

- [ ] Barbero sube foto de perfil → archivo existe en `barbers/{id}/dd/mm/yyyy/`
- [ ] Barbero sube trabajo con 3 imágenes → 3 registros en `work_images`
- [ ] Upload sin imágenes → error de validación en campo `images`
- [ ] Archivo demasiado grande (>5MB) → error de validación
- [ ] Tipo de archivo inválido (PDF enviado como imagen) → error de validación
- [ ] Pago genera PDF → archivo existe en storage con la ruta guardada en `comprobante_pdf`

---

## REGLAS DE IMPLEMENTACIÓN

1. **Namespace:** Seguir PSR-4, cada archivo en su carpeta correspondiente.
2. **Base:** Extender `Tests\TestCase` (que ya seedea roles y permisos).
3. **Base de datos:** Usar `RefreshDatabase` en todos los Feature tests.
4. **Factories:** Usar `User::factory()->create()` para usuarios.
5. **Roles:** Crear roles con `Role::query()->firstOrCreate(['name' => $rol, 'guard_name' => 'web'])`.
6. **Storage:** Siempre `Storage::fake('public')` antes de tests con archivos.
7. **Notificaciones:** Siempre `Notification::fake()` antes de tests con notifs.
8. **Sin mocks innecesarios:** Preferir integración real con BD SQLite en memoria.
9. **Nombres descriptivos:** Cada test debe ser un `test_` seguido de descripción en snake_case.
10. **Assertions múltiples:** Cada test debe tener al menos 2 assertions significativas.
11. **Helper privado:** Si bootstrapear el escenario se repite, extraer a `private function`.

---

## FORMATO DE RESPUESTA ESPERADO

Para cada categoría, entrega:
1. El archivo PHP completo y ejecutable
2. Un comentario al inicio explicando qué cubre
3. Métodos agrupados por contexto con comentarios `// --- Contexto ---`

---

## CONTEXTO ADICIONAL DE LOS TESTS EXISTENTES

Ya existen estos tests (NO los repitas, úsalos como referencia de estilo):
- `AppointmentStoreTest` → crea/solapa citas
- `AuthenticationTest` → login/logout
- `InventoryFlowTest` → CRUD inventario con roles
- `NotificationFlowTest` → creación y recordatorio
- `PaymentStoreTest` → pago + PDF + duplicado
- `RoleNavigationAccessTest` → navegación por rol
- `RolePortalFlowTest` → flujos por portal
- `ReportAccessTest` → acceso y exportación reportes
- `PermissionEnforcementTest` → permisos granulares
- `RouteGuardAccessTest` → guards de rutas
- `ServiceManagementTest` → CRUD servicios
- `UserManagementTest` → gestión usuarios
- `WorkUploadTest` → subida de trabajos

---

## INSTRUCCIÓN FINAL

Genera los archivos en este orden de prioridad:
1. Smoke Tests (más rápido de ejecutar, detecta regresiones)
2. Security Tests (crítico para producción)
3. E2E Complete Booking Flow (flujo de negocio principal)
4. Performance Tests (detecta N+1 antes de producción)
5. El resto en orden numérico

Cada archivo debe poder ejecutarse de forma independiente con:
```bash
php artisan test tests/Feature/Smoke/CriticalPathSmokeTest.php
```
