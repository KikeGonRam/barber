# Accesos de demostración — UrbanBlade

> Este archivo contiene las credenciales de prueba preparadas para una presentación del sistema.
> Son usuarios reales sembrados en la base de datos local y están pensado únicamente para una demo o validación rápida.
>
> **Importante**: después de la presentación, rota o elimina estas contraseñas para no dejar accesos conocidos en cuentas reales.

URL de acceso:

- http://localhost:8000/login

![Login de acceso](assets/login.png)

## Credenciales demo

| Rol           | Correo                              | Contraseña      |
| ------------- | ----------------------------------- | --------------- |
| Administrador | kikermairez160418@gmail.com         | UrbanBlade2026! |
| Recepción     | manuela.andres78@gmail.com          | UrbanBlade2026! |
| Barbero       | omar.tamayo.juan.b1@outlook.com     | UrbanBlade2026! |
| Cliente       | jordi.curiel.medina.c1@yahoo.com.mx | UrbanBlade2026! |

## Qué puede ver cada rol

- **Administrador**: dashboard con KPIs globales, gestión de citas, clientes, pagos, reportes, inventario, campañas y analítica.
- **Recepción**: panel operativo, agenda del turno, clientes, cobros, pedidos y flujo acelerado de atención.
- **Barbero**: agenda personal, aprobación o rechazo de citas, perfil, horario, portafolio y analítica individual.
- **Cliente**: reserva de citas, historial, tienda, carrito, facturas y membresía.

## Rotación de contraseñas

Si deseas cambiar estas credenciales después de la demo:

```bash
docker compose exec app php artisan tinker --execute="
foreach (['kikermairez160418@gmail.com','manuela.andres78@gmail.com','omar.tamayo.juan.b1@outlook.com','jordi.curiel.medina.c1@yahoo.com.mx'] as \$email) {
    \$u = \App\Models\User::where('email', \$email)->first();
    if (\$u) { \$u->password = bcrypt(bin2hex(random_bytes(16))); \$u->save(); echo \"rotated: \$email\n\"; }
}
"
```

## Documentación relacionada

- [README.md](../README.md)
- [DEMO_DEMOSTRACION.md](DEMO_DEMOSTRACION.md)
