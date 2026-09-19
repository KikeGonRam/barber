# Accesos del equipo — UrbanBlade

> **Actualizado 2026-09-18.** Este archivo ya **no lista correos ni contraseñas**.
> Las cuentas que documentaba (una por rol, de septiembre) dejaron de existir en la
> base tras los incidentes del 2026-09-04, 2026-09-11 y 2026-09-18, y mantener
> contraseñas en un repositorio público solo genera copias desactualizadas. Las
> credenciales vigentes las administra el dueño del proyecto fuera del repositorio.

URL de acceso:

- Local: http://localhost:3000/login (frontend-urban/Nuxt — el login real; `:8000/login`
  redirige aquí, `barber` ya no renderiza ninguna página).
- Staging AWS: la URL de CloudFront del frontend
  (ver [DESPLIEGUE_AWS_STAGING.md](DESPLIEGUE_AWS_STAGING.md)).

![Login de acceso](assets/login.png)

## Qué cuentas hay hoy en `barber_db`

Estado verificado el 2026-09-18 (solo lectura sobre Atlas), después de eliminar las
cuentas de demostración:

| Rol | Cuentas | Notas |
|---|---|---|
| administrador | 2 | cuentas reales del equipo |
| ingeniero | 1 | acceso al panel de estado del servidor |
| barbero | 1 | sin perfil de barbero todavía: no aparece en el catálogo hasta crearlo |
| cliente | 0 | los clientes reales se registran desde el frontend |
| recepcionista | 0 | no existe ninguna; crearla cuando se necesite |

Las 26 cuentas de demostración (`*.demoN@urbanblade.test`: 6 barberos y 20 clientes) y
sus perfiles se eliminaron el 2026-09-18. Sus citas (100) y pagos (64) siguen en la
base pero ya no tienen cliente ni barbero asociado; el catálogo de barberos queda
vacío hasta crear perfiles reales. Hay un respaldo local de esas cuentas en
`storage/app/backups/demo-accounts-2026-09-18.json` (ignorado por Git).

> Los números cambian: para el estado real, consultar la colección `users` en lugar de
> confiar en esta tabla.

## Cómo crear una cuenta nueva

No usar los seeders masivos (`BarberSeeder`/`ClientSeeder`/`DatabaseSeeder` completo)
— ver la advertencia del [README.md](../README.md): ya causaron acumulación de datos
sintéticos dos veces. Crear cada cuenta individualmente:

```bash
docker exec barber-app php artisan tinker --execute="
\$u = \App\Models\User::create(['name' => 'Nombre', 'email' => 'correo@ejemplo.com', 'password' => bcrypt('elige-una-contraseña-larga')]);
\$u->forceFill(['email_verified_at' => now()])->save();
\$u->assignRole('administrador'); // o recepcionista/barbero/cliente/ingeniero
"
```

Para barbero/cliente, además crear el perfil correspondiente
(`Barber::create([...])` / `Client::create([...])`) enlazado con `user_id`; sin perfil,
el rol no funciona.

Notas:
- Los borrados de usuarios son lógicos (`SoftDeletes`): un correo "borrado" sigue
  ocupando el índice único hasta un `forceDelete()`.
- Si el comando corre contra Atlas, es una escritura en la base compartida: confirmar
  primero a qué apunta `.env`.

## Qué puede ver cada rol

- **Administrador**: dashboard con KPIs globales, gestión de citas, clientes, pagos, reportes, inventario, campañas y analítica.
- **Recepción**: panel operativo, agenda del turno, clientes, cobros, pedidos y flujo acelerado de atención.
- **Barbero**: agenda personal, aprobación o rechazo de citas, perfil, horario, portafolio y analítica individual.
- **Cliente**: reserva de citas, historial, tienda, carrito, facturas y membresía.
- **Ingeniero**: panel de estado del servidor (Laravel Pulse).

## Documentación relacionada

- [README.md](../README.md)
- [DEMO_DEMOSTRACION.md](DEMO_DEMOSTRACION.md)
- [MONGODB_ATLAS.md](MONGODB_ATLAS.md) — usuarios de base de datos (no confundir con cuentas de la app)
