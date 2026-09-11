# Accesos reales del equipo — UrbanBlade

> **Actualizado 2026-09-11**: `barber_db` (MongoDB Atlas, compartida con
> `spark/`) perdió por completo las colecciones `clients`, `barbers` y
> `services` en un incidente de operación (un comando de limpieza pensado
> para datos de prueba locales corrió por error contra Atlas). No existe
> backup de este vaciado — a diferencia de la limpieza del 2026-09-04, esta
> vez no hay JSON de respaldo que restaurar. Las cuentas y credenciales que
> antes vivían en este archivo ya no corresponden a nada real: los perfiles
> de cliente/barbero detrás de esos correos desaparecieron junto con las
> colecciones. Se retiraron de aquí en vez de dejarlas apuntando a datos
> inexistentes.

URL de acceso:

- http://localhost:3000/login (frontend-urban/Nuxt — el login real; `:8000/login`
  redirige aquí desde el 2026-09-09, `barber` ya no renderiza ninguna página)

![Login de acceso](assets/login.png)

## Estado actual

No hay cuentas de equipo documentadas: `clients`, `barbers` y `services`
están vacías. La colección `users` no se tocó en el incidente, pero
cualquier `User` que dependía de un perfil `Client`/`Barber` ahora está
huérfano (sin `clientProfile`/`barberProfile`), así que sus roles no
funcionan hasta recrear el perfil correspondiente.

## Cómo crear cuentas de equipo nuevas

No usar los seeders masivos (`BarberSeeder`/`ClientSeeder`/`DatabaseSeeder`
completo) — ver la advertencia del `README.md` sobre por qué eso ya causó
acumulación de datos sintéticos dos veces. Crear cada cuenta individualmente:

```bash
docker exec barber-app php artisan tinker --execute="
\$u = \App\Models\User::create(['name' => 'Nombre', 'email' => 'correo@ejemplo.com', 'password' => bcrypt('elige-una-contraseña-fuerte')]);
\$u->forceFill(['email_verified_at' => now()])->save();
\$u->assignRole('administrador'); // o recepcionista/barbero/cliente
"
```

Para barbero/cliente, además crear el perfil correspondiente
(`Barber::create([...])` / `Client::create([...])`) y enlazarlo con
`user_id`.

## Qué puede ver cada rol

- **Administrador**: dashboard con KPIs globales, gestión de citas, clientes, pagos, reportes, inventario, campañas y analítica.
- **Recepción**: panel operativo, agenda del turno, clientes, cobros, pedidos y flujo acelerado de atención.
- **Barbero**: agenda personal, aprobación o rechazo de citas, perfil, horario, portafolio y analítica individual.
- **Cliente**: reserva de citas, historial, tienda, carrito, facturas y membresía.

## Documentación relacionada

- [README.md](../README.md)
- [DEMO_DEMOSTRACION.md](DEMO_DEMOSTRACION.md)
