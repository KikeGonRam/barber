---
name: urbanblade-stripe-local
description: Revisar pagos y webhooks de Stripe en un sandbox autorizado.
---

# Stripe local

Consulta docs/STRIPE_PRUEBAS_LOCALES.md. Inspecciona usos actuales del SDK antes
de decidir permisos: el backend utiliza Payment Intents, Customers/tarjetas,
reembolsos y recursos de membresías/suscripciones. No conceder permisos generales
a partir de una guía antigua ni asumir que solo existen Payment Intents.

El navegador recibe exclusivamente la clave publicable. Los secretos de API y firma
permanecen en backend y fuera de Git. Verifica sandbox y cuenta sin imprimir valores.
La firma del webhook se comprueba en el servidor; GET 405 no es una prueba de entrega.

No crear o rotar claves, modificar .env, reiniciar servicios ni realizar pagos como
parte de una tarea documental. En una prueba autorizada, limita el listener a localhost
y trata su salida como sensible. Las pruebas automatizadas usan solo .\test.ps1.
Mantén sincronizadas las copias .claude/.agents.
