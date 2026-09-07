---
name: urbanblade-completion-roadmap
description: 'Plan por fases para completar y endurecer el contrato API de UrbanBlade y su integración con frontend-urban. Usar antes de tocar auth, perfiles, contratos JSON, permisos, pagos, citas, notificaciones o pruebas E2E; cada fase exige pruebas, revisión de regresiones, commit y push separado en barber y frontend-urban.'
---

# UrbanBlade completion roadmap

## Objetivo

Completar el backend Laravel y el frontend Nuxt como un producto coordinado, sin romper el contrato API que también consumirá la futura app Android.

## Estado base verificado

- Backend: Laravel 13 + MongoDB Atlas en desarrollo, 145 rutas bajo `/api/v1`.
- Frontend: Nuxt con pantallas para autenticación, dashboards, citas, clientes, pagos, pedidos, inventario, reportes, servicios, usuarios, social y ajustes.
- Última línea base conocida: suite backend completa verde (`340 tests`, `1320 assertions`), Pint, ESLint y build de Nuxt verdes.
- Los repositorios `barber` y `frontend-urban` tienen historiales y pushes independientes.

## Reglas de ejecución

1. No tocar `.env`, secretos ni credenciales.
2. No ejecutar tests con `php artisan test`; usar siempre `./test.ps1`.
3. No ejecutar migraciones, seeders completos, resets ni comandos destructivos contra Atlas.
4. Los cambios en `routes/api.php` y `app/Http/Controllers/Api/**` son contratos públicos: preferir cambios aditivos y actualizar Scribe cuando cambie la API.
5. Cada fase debe tener una hipótesis verificable, una prueba focalizada, una prueba de regresión y validación de frontend cuando corresponda.
6. Cada fase aprobada se publica con commits separados: uno en `barber` y otro en `frontend-urban`.
7. No mezclar cambios ajenos o artefactos generados en los commits de una fase.

## Fases

### Fase 1: contrato API y autenticación

- Inventariar endpoints realmente usados por Nuxt y compararlos con rutas Laravel.
- Estandarizar recursos de usuario, paginación, validación y errores sin cambiar respuestas existentes de forma silenciosa.
- Auditar login/password, Google OAuth, avatar, completar perfil, tokens, logout, refresh y CORS de producción.
- Añadir pruebas de contrato y de regresión para cada cambio.

Aceptación: todos los endpoints consumidos tienen respuesta documentada, errores previsibles y prueba; `./test.ps1`, Pint, ESLint y build pasan.

### Fase 2: perfiles, permisos y seguridad de cuenta

- Completar perfil por rol, foto propia, preferencias y notificaciones.
- Revisar autorización objeto por objeto y exposición de PII.
- Verificar que los cambios de rol mantengan `users.role_id` como fuente MongoDB.

Aceptación: matrices de rol cubiertas por pruebas y sin acceso cruzado entre usuarios.

### Fase 3: citas y disponibilidad

- Auditar conflictos, zona horaria, estados, cancelaciones, reprogramaciones, recordatorios y calendario.
- Cubrir carreras y límites de negocio con pruebas de integración.

Aceptación: ningún flujo permite doble reserva o transición inválida.

### Fase 4: pagos, pedidos e inventario

- Revisar Stripe, transferencias, recibos, reembolsos, precios server-side, stock y transacciones.
- Mantener MongoDB en replica set para pruebas.

Aceptación: importes y stock se calculan en servidor y webhooks son idempotentes.

### Fase 5: notificaciones y operación

- Revisar email, push, colas, reintentos, cumpleaños, citas, stock, pagos y logs.
- Añadir observabilidad accionable sin PII innecesaria.

Aceptación: fallos quedan registrados, reintentan según política y no rompen la petición principal.

### Fase 6: pruebas E2E y producción

- Añadir recorridos E2E críticos para login, Google, completar perfil, reserva y pago.
- Verificar variables de Vercel/backend, CORS, storage, health checks y CI.

Aceptación: flujo crítico probado en build de producción y ambos repositorios sincronizados.

## Ciclo por fase

1. Leer la skill de la fase y el código propietario.
2. Documentar hipótesis, alcance y prueba discriminante.
3. Implementar el mínimo cambio aditivo.
4. Ejecutar prueba focalizada.
5. Reparar y repetir en la misma fase si falla.
6. Ejecutar regresión backend/frontend.
7. Revisar diff y archivos incluidos.
8. Crear commit y hacer push de ambos repositorios.
9. Registrar el resultado y continuar con la siguiente fase.
