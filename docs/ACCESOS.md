# Accesos de demostración — UrbanBlade

> **Uso**: credenciales de prueba para evaluar los 4 roles del sistema.
> Corresponden a usuarios reales ya sembrados en la base de datos (no cuentas
> nuevas), con la contraseña fijada manualmente solo para esta demo.
>
> **Importante**: rotar estas contraseñas (o eliminarlas) después de la
> presentación — no dejar una contraseña conocida en cuentas reales por
> tiempo indefinido.

URL: http://localhost:8000/login

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | `kikermairez160418@gmail.com` | `UrbanBlade2026!` |
| Recepción | `manuela.andres78@gmail.com` | `UrbanBlade2026!` |
| Barbero | `omar.tamayo.juan.b1@outlook.com` | `UrbanBlade2026!` |
| Cliente | `jordi.curiel.medina.c1@yahoo.com.mx` | `UrbanBlade2026!` |

## Qué puede ver cada rol

- **Administrador**: dashboard con KPIs globales, gestión de citas, clientes,
  pagos, reportes con gráficas (Excel/PDF/web), inventario, campañas,
  analítica (`/analitica`), modo mantenimiento y backups.
- **Recepción**: panel operativo, citas del turno, walk-in rápido, cobro en
  un paso, bandeja de pedidos de tienda, gestión de clientes.
- **Barbero**: agenda propia (`Mi Agenda`), aprobación/rechazo de citas,
  portafolio, horario, analítica personal, cronómetro de servicio.
- **Cliente**: reserva de citas, historial (`Mis Citas`), tienda y carrito,
  tarjeta de membresía con QR, facturas, muro de inspiración.

## Rotar las contraseñas después de usarlas

```bash
docker compose exec app php artisan tinker --execute="
foreach (['kikermairez160418@gmail.com','manuela.andres78@gmail.com','omar.tamayo.juan.b1@outlook.com','jordi.curiel.medina.c1@yahoo.com.mx'] as \$email) {
    \$u = \App\Models\User::where('email', \$email)->first();
    if (\$u) { \$u->password = bcrypt(bin2hex(random_bytes(16))); \$u->save(); echo \"rotated: \$email\n\"; }
}
"
```
