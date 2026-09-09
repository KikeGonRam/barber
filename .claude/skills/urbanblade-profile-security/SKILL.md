---
name: urbanblade-profile-security
description: 'Fase 2 del roadmap UrbanBlade: usar antes de tocar perfiles, fotos, preferencias, notificaciones, permisos por objeto o endpoints de cuenta en barber. Exige pruebas de autorización, no exposición de PII y publicación coordinada con frontend-urban.'
---

# Fase 2: perfiles y seguridad de cuenta

## Objetivo

Completar los perfiles de cliente, barbero, administrador e ingeniero sin permitir acceso cruzado ni guardar datos fuera del modelo correcto.

## Reglas

- Usar `./test.ps1`; nunca `php artisan test` directo.
- No tocar Atlas con seeders, migraciones o resets.
- `users.role_id` es la fuente real de roles en MongoDB.
- Las fotos deben pasar por almacenamiento público controlado y validación de tipo/tamaño.
- Las respuestas deben omitir secretos y PII de otros usuarios.
- Cambios API son aditivos y requieren pruebas y Scribe si cambia el contrato.

## Alcance inicial

Auditar `ProfileController`, `UserResource`, `ClientController`, `BarberManagementController`, notificaciones y las páginas Nuxt de perfil. Confirmar autorización, campos editables, foto propia, preferencias, eliminación de cuenta y consistencia después de cambiar rol.

## Criterios de aceptación

- Cliente solo edita su perfil y preferencias.
- Barbero solo edita su perfil, foto, bio y agenda.
- Staff no puede modificar datos de otro rol fuera de endpoints administrativos autorizados.
- Fotos inválidas se rechazan y las válidas se reemplazan sin dejar basura.
- Pruebas de autorización y contrato pasan; Pint, ESLint y build pasan.
- Backend y frontend se publican en commits separados.
