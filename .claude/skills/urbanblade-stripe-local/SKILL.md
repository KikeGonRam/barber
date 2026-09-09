---
name: urbanblade-stripe-local
description: Configura, audita o diagnostica los pagos de prueba y webhooks locales de Stripe en UrbanBlade. Úsalo cuando se mencionen claves Stripe, Payment Intents, Stripe CLI, whsec, errores 400/403/405 o pagos con tarjeta locales; no habilita producción ni Stripe Billing.
---

# Stripe local en UrbanBlade

Aplica este skill únicamente al repositorio `barber` y al entorno de pruebas. Lee
primero [`docs/STRIPE_PRUEBAS_LOCALES.md`](../../../docs/STRIPE_PRUEBAS_LOCALES.md)
para el procedimiento completo y los comandos de diagnóstico.

## Arquitectura que debes preservar

- El navegador usa una clave `pk_test_...` y `stripe.confirmCardPayment()`.
- `StripePaymentService` usa un `StripeClient` para crear y recuperar Payment Intents.
- El webhook `POST /api/stripe/webhook` valida la firma con `whsec_...`.
- Las facturas son PDFs internos de Laravel; no son Stripe Invoices.
- No existen llamadas a Stripe Billing, Customers, Prices, Products, Checkout Sessions
  ni Subscriptions. No concedas esos permisos sin un cambio de producto y código
  explícitamente solicitado.

La clave de backend preferida es restringida (`rk_test_...`) y debe tener solamente
`Payment Intents: Escritura`. Antes de ampliar permisos, vuelve a buscar todos los usos
del SDK de Stripe en el repositorio y justifica cada recurso adicional.

## Reglas operativas

1. Nunca muestres, registres ni escribas secretos en archivos versionados. Modifica
   únicamente `.env`; confirma que siga ignorado por Git.
2. No interpretes una autorización de pruebas como permiso para tocar modo activo.
   Detente antes de crear, rotar o eliminar credenciales externas si el usuario no lo
   autorizó explícitamente.
3. Verifica que Dashboard, claves API y `stripe whoami --format json` pertenezcan al
   mismo sandbox. Compara los `acct_...`; no confíes solo en el nombre del negocio.
4. Después de editar `.env`, recrea únicamente `app`, `worker` y `scheduler`, y limpia
   la caché de configuración. No ejecutes `docker compose down`, migraciones ni
   seeders.
5. Para pruebas automatizadas del repositorio usa exclusivamente `./test.ps1`; nunca
   ejecutes `php artisan test` directamente.
6. Trata `GET /api/stripe/webhook → 405` como comportamiento esperado. La prueba válida
   es un `POST` firmado reenviado por Stripe CLI.

## Flujo de verificación

1. Revisa los usos reales con `rg` en `app`, `config`, `routes` y `resources`.
2. Inspecciona las variables por prefijo o estado, sin imprimir sus valores.
3. Ejecuta `stripe listen --forward-to http://localhost:8000/api/stripe/webhook` y
   conserva el proceso abierto.
4. Si cambió el `whsec_...`, actualiza `.env`, recrea los tres servicios PHP y ejecuta:

   ```powershell
   docker compose exec -T app php artisan config:clear
   ```

5. Verifica con:

   ```powershell
   stripe trigger payment_intent.succeeded
   docker compose logs --since 2m web
   ```

6. Exige HTTP 200 en las entregas del webhook y una consulta exitosa de Payment
   Intents con la clave restringida. Un fixture sin `appointment_id` valida transporte
   y firma sin modificar una cita real.

## Límites

- No agregues Stripe Billing para soportar las facturas PDF actuales.
- No sustituyas credenciales de pruebas por claves estándar de acceso completo.
- No pruebes con dinero, tarjetas o claves reales.
- No incluyas valores `pk_`, `rk_`, `sk_` o `whsec_` reales en la respuesta final.
- Para producción, prepara un procedimiento separado con endpoint HTTPS, secreto de
  webhook del Dashboard, almacenamiento seguro de secretos y autorización explícita.
