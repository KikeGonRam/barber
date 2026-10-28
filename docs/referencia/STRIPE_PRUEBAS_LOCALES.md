# Stripe: arquitectura y pruebas locales

Revisión del código: 28 de octubre de 2026.
Esta guía se limita a sandbox y backend local aislado. No autoriza cobros,
cambios de claves, suscripciones o reembolsos de producción.

## Funciones implementadas

[StripePaymentService](../../app/Services/Payment/StripePaymentService.php) utiliza:

| Función | Recursos o comportamiento |
| --- | --- |
| Cobros y depósitos | Crear/consultar Payment Intents y conciliar con servicios de negocio. |
| Tarjetas guardadas | Customers y métodos de pago; cargos posteriores cuando el flujo lo permite. |
| Cancelaciones y devoluciones | Refunds asociados al intento original. |
| Membresías | Productos/precios, Customers, suscripciones y consulta de invoices. |
| Renovaciones y cambios | Eventos de suscripción e invoice procesados por el webhook. |

[MembershipService](../../app/Services/Membership/MembershipService.php) coordina
membresías. [StripeWebhookController](../../app/Http/Controllers/Api/Payment/StripeWebhookController.php)
procesa pagos, fallos, reembolsos, disputas y eventos de suscripción.

La antigua instrucción de conceder únicamente Payment Intents y excluir siempre
Billing/Customers estaba desactualizada. Define los permisos mínimos por las
funciones que se probarán, revisando los métodos efectivamente usados.
No habilitar recursos ajenos a ese alcance.

## Configuración y custodia

| Variable | Uso |
| --- | --- |
| STRIPE_KEY | Clave publicable del entorno de pruebas. |
| STRIPE_SECRET | Credencial secreta/restringida del backend. |
| STRIPE_WEBHOOK_SECRET | Firma del listener o endpoint correspondiente. |
| NUXT_PUBLIC_STRIPE_KEY | Clave publicable utilizada por Nuxt, en el frontend. |

No hay valores de ejemplo con formato de clave en esta guía.
Obtén los valores del sandbox autorizado y guárdalos fuera de Git.
Las cuatro configuraciones deben corresponder al mismo entorno/cuenta.
Nunca enviar la clave secreta al navegador ni pegar valores en comandos,
capturas, fixtures o informes.

Los contenedores conservan su entorno inicial y Laravel puede cachear configuración.
Una modificación de variables exige revisar qué proceso debe recrearse y con qué
Compose; no reemplazar servicios conectados a Atlas por seguir una receta local.

## Preparación

1. Levantar el backend contra Mongo local siguiendo [clonado](CLONAR_PROYECTOS.md).
2. Utilizar un sandbox Stripe autorizado y comprobar el contexto de la CLI.
3. Configurar las variables sin mostrar sus valores.
4. Tener el frontend local y datos ficticios para los escenarios que lo necesiten.
5. Mantener redirigido el correo a un capturador local y no usar teléfonos reales.

```powershell
stripe --version
stripe whoami --format json
stripe listen --forward-to http://127.0.0.1:8000/api/stripe/webhook
```

El listener muestra un secreto de firma temporal: no copiar su salida a
documentación. Configurarlo solo en el backend de prueba.

## Qué valida cada prueba

```powershell
# Con el listener abierto y el sandbox verificado
stripe trigger payment_intent.succeeded
```

Un trigger valida entrega/firma y manejo del evento. Si no contiene metadatos de
una operación de UrbanBlade, no demuestra que una cita o membresía se conciliara.

Para validar una compra completa, crearla desde el producto en entorno aislado y
comprobar importe calculado por servidor, respuesta de error, evento recibido,
estado final y ausencia de duplicados al repetir el evento.
Los objetos creados en sandbox siguen siendo escrituras en una cuenta externa;
esa prueba necesita alcance autorizado.

La suite PHPUnit cubre el webhook y servicios mediante dobles de prueba y
datos locales; consulta [PRUEBAS.md](PRUEBAS.md).

## Diagnóstico

| Síntoma | Comprobación |
| --- | --- |
| GET del webhook devuelve 405 | Es un endpoint POST; no se prueba abriéndolo en un navegador. |
| Evento rechazado por firma | Revisar secreto del endpoint/listener y configuración efectiva, sin imprimirlo. |
| Operación rechazada por Stripe | Revisar sandbox, recurso y permiso concreto requerido. |
| HTTP 200 sin cambio de negocio | Revisar tipo de evento y metadatos; un fixture genérico no identifica una cita. |
| Membresía sin actualizar | Revisar eventos de suscripción/invoice y MembershipService, no solo Payment Intents. |
| Formulario de tarjeta ausente | Revisar la clave publicable del frontend y el entorno utilizado. |

Los recibos y reportes PDF del backend no deben describirse como CFDI.
La existencia de invoices de Stripe para membresías no cambia esa limitación fiscal.

## Producción

Las credenciales y firmas de producción son diferentes de las del sandbox.
No crear o rotar claves, cambiar webhooks, modificar planes ni reiniciar servicios
en esta revisión. Consulta [gestión de credenciales](PLAN_ROTACION_CREDENCIALES.md).
