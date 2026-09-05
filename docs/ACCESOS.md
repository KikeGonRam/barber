# Accesos reales del equipo — UrbanBlade

> ⚠️ Este repositorio es PÚBLICO en GitHub. Publicar contraseñas reales aquí
> fue una decisión explícita del dueño del proyecto, con el riesgo de
> exposición ya conocido y aceptado — no un descuido. Si eso cambia, mover
> esto a un repo privado/canal privado y rotar estas contraseñas.
>
> **Actualizado 2026-09-04**: `barber_db` (MongoDB Atlas, compartida con
> `spark/`) se limpió por completo — se eliminaron ~214,623 citas y ~323,095
> transacciones de lealtad sintéticas que quedaron de una siembra masiva
> anterior, junto con ~4,767 usuarios de sobra y todo el resto de datos de
> demo (30 clientes, 8 barberos, 184 pagos, 41 productos, etc.). Las 4 cuentas
> de abajo son las ÚNICAS que existen ahora mismo en la base. A partir de
> ahora el equipo va cargando solo información real del negocio.

URL de acceso:

- http://localhost:8000/login

![Login de acceso](assets/login.png)

## Credenciales reales (una por rol)

| Rol | Correo | Contraseña |
| --- | --- | --- |
| Administrador | `kikeramirez160418@gmail.com` | `\|3]GUn3O>%\d0osL` |
| Recepción | `valeria.villanueva69@gmail.com` | `p%03Q[-[7!z_OE` |
| Barbero | `barbero@urbanblade.mx` | `,9*ZApz20nZ8hs` |
| Cliente | `cliente@urbanblade.mx` | `Z(&I\-lKE%\A~5` |

Contraseñas generadas aleatoriamente — cada una visible solo aquí, no se
pueden volver a mostrar sin resetearlas.

## Qué puede ver cada rol

- **Administrador**: dashboard con KPIs globales, gestión de citas, clientes, pagos, reportes, inventario, campañas y analítica.
- **Recepción**: panel operativo, agenda del turno, clientes, cobros, pedidos y flujo acelerado de atención.
- **Barbero**: agenda personal, aprobación o rechazo de citas, perfil, horario, portafolio y analítica individual.
- **Cliente**: reserva de citas, historial, tienda, carrito, facturas y membresía.

## Notas importantes

- No volver a correr `BarberSeeder`/`ClientSeeder` completos (crean 50
  barberos y 1500 clientes falsos respectivamente) salvo que de verdad se
  quiera repoblar con datos de prueba masivos — así fue como se acumuló la
  basura que se acaba de limpiar.
- Para crear más cuentas de equipo: crear un `User` + perfil
  (`Client`/`Barber`) y asignar el rol con `assignRole()`, no usar los
  seeders masivos.
- Respaldo de lo que había antes de la limpieza (JSON de las ~30
  clientes/8 barberos/184 pagos/330 citas reales que sí existían) se guardó
  fuera de este repo — pedirlo explícitamente si se necesita.

## Rotación de contraseñas

Si necesitas cambiar estas credenciales:

```bash
docker exec barber-app php artisan tinker --execute="
foreach (['kikeramirez160418@gmail.com','valeria.villanueva69@gmail.com','barbero@urbanblade.mx','cliente@urbanblade.mx'] as \$email) {
    \$u = \App\Models\User::where('email', \$email)->first();
    if (\$u) { \$u->password = bcrypt(bin2hex(random_bytes(16))); \$u->save(); echo \"rotated: \$email\n\"; }
}
"
```

## Documentación relacionada

- [README.md](../README.md)
- [DEMO_DEMOSTRACION.md](DEMO_DEMOSTRACION.md)
