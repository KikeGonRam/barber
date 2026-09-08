---
name: backend-security-hardening
description: 'Directrices estrictas de blindaje de seguridad, prevención de fuga de datos (PII, secretos, tokens), protección contra inyección NoSQL, mitigación de IDOR y control de autorización en Laravel 13 + MongoDB (UrbanBlade). Consultar antes de agregar/modificar controladores de API, modelos de MongoDB, endpoints de autenticación, pagos o lógica de negocio.'
---

# Blindaje Estricto de Seguridad y Prevención de Fuga de Datos en Laravel 13 + MongoDB (UrbanBlade)

## Objetivo

Garantizar la protección hermética de la información sensible del negocio y de los usuarios (PII, contraseñas, tokens de sesión, códigos OTP, secretos de pasarela Stripe), eliminando cualquier vector de fuga de datos, previniendo inyecciones de operadores NoSQL en MongoDB, mitigando accesos directos inseguros a objetos (IDOR) y asegurando la integridad transaccional del backend.

---

## 1. Principios Innegociables

1. **Cero Retorno de Modelos Crudos (Zero Data Leakage):**
   - **Queda estrictamente prohibido** retornar modelos Eloquent o colecciones de MongoDB crudas directamente en `response()->json($model)`.
   - Todo endpoint de API debe transformar los datos utilizando un `JsonResource` dedicado (ej. `AppointmentResource`, `UserResource`) o una proyección explícita en arreglo asociativo (`->map(fn ($m) => [...])`).
   - Esto previene que nuevos campos añadidos a la base de datos o atributos internos se filtren inadvertidamente a clientes web o apps móviles.
2. **Defensa en Profundidad en `$hidden` en Modelos:**
   - Todo modelo de MongoDB que contenga secretos, tokens o credenciales debe declararlos obligatoriamente en la propiedad `protected $hidden`:
     - `User`: `password`, `remember_token`, `verification_code`, `verification_code_expires_at`.
     - `MobileApiToken`: `token_hash`.
     - `Payment`: `stripe_payment_id`, `ocr_texto`, `ocr_monto_detectado`.
3. **Blindaje contra Inyección de Operadores NoSQL (MongoDB Injection):**
   - En MongoDB con PHP, si un atacante envía un objeto JSON anidado en lugar de un escalar (ej. `{"email": {"$gt": ""}}` o `{"status": {"$ne": "cancelada"}}`), el driver de MongoDB evalúa el operador si la entrada no fue validada como string.
   - **Regla:** Todos los parámetros de consulta y cuerpo deben validarse estrictamente con tipos escalares en `FormRequest` (`['required', 'string']`, `['integer']`, `['boolean']`).
   - Prohibido pasar arreglos asociativos no tipados o `$request->all()` directamente a métodos `$query->where('campo', $valor)`.
4. **Mitigación Estricta de IDOR (Insecure Direct Object Reference):**
   - Jamás asumir que un usuario tiene derecho a ver o editar un registro solo por conocer su `_id` de MongoDB.
   - En consultas de clientes: filtrar siempre por `->where('client_id', (string) $user->clientProfile->id)`.
   - En consultas de barberos: filtrar siempre por `->where('barber_id', (string) $user->barberProfile->id)`.
   - En rutas con Route Model Binding o IDs de URL (`/appointments/{id}`): verificar pertenencia o autorizar con Policy / Gate antes de retornar o mutar el registro.
5. **Autoridad Absoluta del Servidor en Dinero e Inventario (Guardrail #13):**
   - Jamás confiar en precios, montos o balances enviados desde el cliente/frontend.
   - El backend siempre recalcula el importe autorizado consultando directamente el precio oficial en la base de datos (`Service::precio`, `Product::precio_venta`, `Appointment::precio_cobrado`).
   - Transacciones atómicas de MongoDB (`DB::transaction`) son mandatorias en todo cobro, movimiento de stock o redención de puntos.

---

## 2. Prevención de Fuga de Datos (Data Leakage)

### A. Transformación Mandatoria con `JsonResource`
```php
// MAL: Retorna todos los campos del documento BSON (fuga potencial de datos)
return response()->json(User::all());

// BIEN: Exposición explícita de campos mediante JsonResource
return UserResource::collection($users);
```

### B. Proyección Segura en Consultas Directas
Si se utiliza un controlador que retorna estructuras mapeadas a medida:
```php
return response()->json([
    'data' => $clients->map(fn (Client $c) => [
        'id' => $c->id,
        'telefono' => $c->telefono,
        'name' => $c->user?->name,
        // OMITIR: passwords, hashes, tokens, notas privadas de administracion
    ]),
]);
```

---

## 3. Prevención de Inyección NoSQL y Manipulación de Parámetros

### A. Validación Estricta en FormRequests
Todo endpoint debe exigir tipos de datos escalares:
```php
// app/Http/Requests/Appointment/StoreAppointmentRequest.php
public function rules(): array
{
    return [
        // Tipo string estricto previene que viajen objetos {'$regex': '...'} o {'$ne': null}
        'barber_id' => ['required', 'string', 'max:50'],
        'service_id' => ['required', 'string', 'max:50'],
        'fecha' => ['required', 'date_format:Y-m-d'],
        'hora_inicio' => ['required', 'date_format:H:i:s'],
        'notas' => ['nullable', 'string', 'max:500'],
    ];
}
```

### B. Uso de `$request->validated()`, Jamás `$request->all()`
Para prevenir ataques de asignación masiva (Mass Assignment):
```php
// MAL: Permite al cliente inyectar 'role_id', 'puntos' o 'is_admin'
$user->update($request->all());

// BIEN: Solo procesa los campos permitidos y validados por la regla de negocio
$user->update($request->validated());
```

---

## 4. Autorización y Multi-Tenant por Rol (Mitigación IDOR)

### A. Aislamiento por Perfil en Controladores
```php
// Ejemplo en AppointmentController
$user = $request->user();

if ($user->hasRole('cliente')) {
    // El cliente SOLO accede a sus propias citas
    $appointments = Appointment::where('client_id', (string) $user->clientProfile->id)->get();
} elseif ($user->hasRole('barbero')) {
    // El barbero SOLO accede a las citas asignadas a su agenda
    $appointments = Appointment::where('barber_id', (string) $user->barberProfile->id)->get();
} elseif ($user->hasAnyRole(['administrador', 'recepcionista'])) {
    // Solo el staff administrativo tiene acceso global
    $appointments = Appointment::all();
} else {
    abort(403, 'No autorizado.');
}
```

### B. Autorización de Operaciones Específicas
Antes de cancelar, reprogramar o pagar una cita:
```php
public function cancel(Request $request, Appointment $appointment): JsonResponse
{
    $user = $request->user();

    if ($user->hasRole('cliente') && (string) $appointment->client_id !== (string) $user->clientProfile?->id) {
        abort(403, 'No tienes permiso para cancelar esta cita.');
    }

    // Continuar con la cancelacion segura...
}
```

---

## 5. Rate Limiting y Control de Abuso (Throttling)

En `routes/api.php`, todo endpoint público o con impacto computacional/financiero debe incluir rate limiting:

| Endpoint | Límite Recomendado | Propósito |
| :--- | :--- | :--- |
| `POST /api/v1/auth/login` | `throttle:login` (5 intentos/min) | Mitigar fuerza bruta en credenciales |
| `POST /api/v1/auth/register` | `throttle:6,1` | Prevenir spam de creación de cuentas |
| `POST /api/v1/auth/forgot-password` | `throttle:6,1` | Prevenir bombardeo de correos / SMS |
| `POST /api/v1/chatbot/query` | `throttle:10,1` | Proteger cuota de GPU/Ollama local |
| `GET /api/v1/availability/slots` | `throttle:30,1` | Evitar DoS en cálculo de slots de agenda |
| `POST /api/v1/payments/stripe-intent` | `throttle:10,1` | Mitigar fraudes de prueba de tarjetas (carding) |

---

## 6. Ocultamiento de Trazas Técnicas y Manejo de Errores

1. **Configuración de Producción:**
   - `APP_DEBUG=false` obligatorio fuera de entornos locales de desarrollo.
2. **Manejo Centralizado de Excepciones:**
   - En `bootstrap/app.php`, interceptar excepciones de dominio (`AppointmentConflictException`, `InsufficientStockException`, `PaymentException`) y retornar mensajes de negocio limpios con código HTTP 422.
   - Las excepciones de conexión a MongoDB (`MongoDB\Driver\Exception\*`) o errores de sintaxis jamás deben enviarse al cliente en JSON; deben ser registradas en los logs (`Log::error()`) o Sentry, devolviendo al usuario un genérico `500 Server Error`.

---

## 7. Checklist de Seguridad para PRs y Commits

- [ ] ¿El endpoint retorna los datos mediante un `JsonResource` o mapeo explícito de campos?
- [ ] ¿Se verificó que los modelos relevantes tengan atributos sensibles ocultos en `$hidden`?
- [ ] ¿Los inputs del `FormRequest` están fuertemente tipados como escalares (`string`, `integer`, `boolean`)?
- [ ] ¿Se utiliza `$request->validated()` para escrituras en base de datos?
- [ ] ¿Se validó que el usuario autenticado sea el dueño del recurso (mitigación de IDOR)?
- [ ] ¿Los montos y precios son recalculados en el servidor y nunca tomados del payload del cliente?
- [ ] ¿El endpoint cuenta con middleware de `throttle` adecuado?
- [ ] ¿Se verificó que ninguna excepción cruda o traza de base de datos se exponga en la respuesta HTTP?
- [ ] ¿La suite de pruebas pasó al 100% con `.\test.ps1`?
