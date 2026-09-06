# Stripe en UrbanBlade: pagos y webhooks locales

Esta guía explica cómo preparar Stripe para desarrollar y probar pagos con tarjeta
en UrbanBlade. Está dirigida tanto al equipo humano como a los asistentes de IA que
trabajen en este repositorio.

> Este procedimiento es exclusivamente para el entorno de pruebas de Stripe. No
> configura producción y no autoriza crear, rotar o sustituir credenciales reales.

## Qué usa actualmente UrbanBlade

UrbanBlade procesa pagos únicos con Stripe Elements y Payment Intents:

- El navegador usa `STRIPE_KEY` (`pk_test_...`) para mostrar el formulario y confirmar
  el pago con `stripe.confirmCardPayment()`.
- Laravel usa `STRIPE_SECRET` (`rk_test_...`) para crear y consultar Payment Intents.
- Stripe CLI reenvía eventos locales a `POST /api/stripe/webhook`.
- Laravel verifica cada evento con `STRIPE_WEBHOOK_SECRET` (`whsec_...`).
- Cuando recibe `payment_intent.succeeded`, el sistema concilia el pago usando
  `PaymentService`.

Las facturas visibles en UrbanBlade son PDFs generados por Laravel con DomPDF. El
proyecto no usa Stripe Billing, Stripe Invoices, suscripciones, Customers, Prices ni
Products. No se deben conceder permisos para esos recursos mientras el código no los
utilice.

Archivos principales:

- `app/Services/Payment/StripePaymentService.php`
- `app/Http/Controllers/Api/Payment/PaymentController.php`
- `app/Http/Controllers/Api/Payment/StripeWebhookController.php`
- `resources/views/payments/create.blade.php`
- `config/services.php`
- `routes/api.php`

## Requisitos

- UrbanBlade ejecutándose en Docker en `http://localhost:8000`.
- Acceso al sandbox de Stripe asignado al equipo.
- PowerShell en Windows 10 u 11 de 64 bits.
- Permiso explícito del responsable antes de crear, rotar o eliminar credenciales.

## 1. Instalar Stripe CLI manualmente en Windows

La instalación validada por el equipo utilizó Stripe CLI `1.50.10`. En versiones
posteriores, descarga el asset equivalente para Windows x86-64 desde los releases
oficiales de Stripe CLI:

```text
stripe_<version>_windows_x86_64.zip
```

Para la versión validada, el archivo fue:

```text
stripe_1.50.10_windows_x86_64.zip
```

1. Descarga y extrae el ZIP.
2. Mueve `stripe.exe` a una carpeta permanente:

   ```text
   C:\Program Files\Stripe\
   ```

3. Agrega `C:\Program Files\Stripe\` a la variable de entorno `Path`.
4. Cierra y abre PowerShell para recargar el `Path`.
5. Verifica la instalación:

   ```powershell
   stripe --version
   ```

## 2. Autorizar Stripe CLI y verificar el sandbox

Ejecuta:

```powershell
stripe login
```

Abre la URL que muestre Stripe, introduce el código de verificación y confirma la
autorización. La salida final indica el negocio, el tipo de entorno y el identificador
`acct_...` activo.

Antes de continuar, compara ese `acct_...` con la cuenta abierta en el Dashboard. Las
claves API, Stripe CLI y el listener deben pertenecer al mismo sandbox. Que dos cuentas
tengan el mismo nombre visible no garantiza que sean el mismo entorno.

Para consultar posteriormente el contexto de la CLI:

```powershell
stripe whoami --format json
```

## 3. Crear la clave restringida de pruebas

En el Dashboard del mismo sandbox:

1. Abre **Desarrolladores → Claves de API**.
2. Selecciona **Crear clave restringida**.
3. Elige que se utilizará en una integración propia.
4. Selecciona permisos personalizados.
5. Asigna un nombre identificable, por ejemplo:

   ```text
   UrbanBlade local test payments
   ```

6. Concede únicamente:

   ```text
   Payment Intents: Escritura
   ```

7. Mantén el resto de los recursos en `Ninguno` y crea la clave.

El permiso de escritura de Payment Intents cubre las operaciones de creación y
consulta que realiza `StripePaymentService`. No habilites la plantilla de pagos únicos
completa ni Stripe Billing solamente por comodidad.

## 4. Configurar `.env`

Obtén la clave publicable y la clave restringida del mismo sandbox y configura:

```dotenv
STRIPE_KEY=pk_test_REEMPLAZAR
STRIPE_SECRET=rk_test_REEMPLAZAR
STRIPE_WEBHOOK_SECRET=whsec_REEMPLAZAR
```

Reglas de seguridad:

- Nunca copies valores reales a esta guía, `.env.example`, issues, commits o chats.
- `.env` debe permanecer ignorado por Git.
- No uses `sk_live_`, `rk_live_` ni `pk_live_` durante este procedimiento.
- No uses una clave secreta estándar si una clave restringida cubre el caso.

## 5. Iniciar el listener local

Ejecuta y conserva abierta esta terminal durante las pruebas:

```powershell
stripe listen --forward-to http://localhost:8000/api/stripe/webhook
```

La CLI mostrará un secreto `whsec_...`. Copia ese valor a
`STRIPE_WEBHOOK_SECRET`. Si una ejecución posterior de `stripe listen` muestra un
secreto distinto, actualiza `.env` antes de seguir probando.

Abrir `http://localhost:8000/api/stripe/webhook` en un navegador produce
`405 Method Not Allowed`. Es el comportamiento correcto: el navegador envía `GET` y
el endpoint acepta únicamente `POST` firmado por Stripe.

## 6. Recargar Laravel después de cambiar credenciales

`docker-compose.yml` inyecta `.env` al crear los contenedores y el entrypoint optimiza
la configuración de Laravel. Por eso editar `.env` no basta. Recarga solamente los
servicios PHP, sin bajar MongoDB ni ejecutar migraciones o seeders:

```powershell
docker compose up -d --force-recreate app worker scheduler
docker compose exec -T app php artisan config:clear
```

No uses `docker compose down`, `make clean`, `migrate` ni `db:seed` como parte de esta
configuración.

## 7. Probar el webhook

Con `stripe listen` todavía activo, abre otra terminal y ejecuta:

```powershell
stripe trigger payment_intent.succeeded
```

La terminal del listener debe mostrar eventos como:

```text
payment_intent.created
payment_intent.succeeded
charge.succeeded
POST http://localhost:8000/api/stripe/webhook [200]
```

El fixture de Stripe no contiene un ID de cita real de UrbanBlade, por lo que valida la
entrega y la firma sin conciliar una cita de la base de datos.

También puede confirmarse la respuesta del servidor con:

```powershell
docker compose logs --since 2m web
```

## Diagnóstico rápido

| Síntoma | Causa probable | Acción |
|---|---|---|
| `GET .../api/stripe/webhook` devuelve 405 | Se abrió el endpoint en un navegador | No es un fallo; prueba con Stripe CLI |
| El listener muestra HTTP 400 | `STRIPE_WEBHOOK_SECRET` no coincide o Laravel conserva caché | Copia el `whsec_...` actual, recrea los servicios y ejecuta `config:clear` |
| Stripe devuelve 403 al crear el intento | La clave restringida no tiene el permiso requerido | Verifica `Payment Intents: Escritura` en el mismo sandbox |
| El trigger funciona pero los pagos de la app no llegan | CLI y claves API usan cuentas `acct_...` distintas | Vuelve a autorizar o genera las claves en un único sandbox |
| El formulario de tarjeta no aparece | Falta `STRIPE_KEY` o no comienza con `pk_test_` | Configura la clave publicable del mismo sandbox y recarga Laravel |

## Lista de entrega para el equipo

- [ ] `stripe --version` funciona.
- [ ] `stripe login` está autorizado en el sandbox correcto.
- [ ] Dashboard y CLI muestran el mismo `acct_...`.
- [ ] La clave restringida solo tiene `Payment Intents: Escritura`.
- [ ] Las tres variables Stripe están configuradas en `.env` y no están versionadas.
- [ ] `app`, `worker` y `scheduler` fueron recreados después del cambio.
- [ ] `stripe listen` permanece abierto durante la prueba.
- [ ] `stripe trigger payment_intent.succeeded` produce respuestas HTTP 200.
- [ ] No se habilitaron permisos de Billing, suscripciones o facturas de Stripe.

## Producción

Producción requiere credenciales distintas, un endpoint HTTPS público y un secreto de
firma generado para ese endpoint en el Dashboard. Nunca reutilices las claves de
prueba ni el secreto temporal de Stripe CLI. Cualquier configuración de producción
debe revisarse y autorizarse por separado.
