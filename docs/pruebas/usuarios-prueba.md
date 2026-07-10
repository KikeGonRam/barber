# Usuarios de Prueba

| Rol | Email | Password | Responsabilidad |
| :--- | :--- | :--- | :--- |
| **Administrador** | al222310427@gmail.com | Admin@Urban2025! | Acceso total, logs y pagos. |
| **Recepcionista** | recepcionista@test.com | Recep@Urban2025! | Gestión de citas, clientes, inventario. |
| **Recepcionista** | recepcionista1@test.com | Recep@Urban2025! | Gestión de citas, clientes, inventario. |
| **Recepcionista** | recepcionista2@test.com | Recep@Urban2025! | Gestión de citas, clientes, inventario. |
| **Barbero** | barbero@test.com | Barber@Urban2025! | Cambiar estados de citas (updateStatus). |
| **Barbero** | barbero1@test.com | Barber@Urban2025! | Cambiar estados de citas (updateStatus). |
| **Barbero** | barbero2@test.com | Barber@Urban2025! | Cambiar estados de citas (updateStatus). |
| **Cliente** | cliente@test.com | Cliente@Urban2025! | Agendar y consultar citas. |
| **Cliente** | cliente1@test.com | Cliente@Urban2025! | Agendar y consultar citas. |
| **Cliente** | cliente2@test.com | Cliente@Urban2025! | Agendar y consultar citas. |

> **Nota:** El sistema valida roles mediante middleware. El rol `staff` en el código se refiere a la capacidad de gestionar inventario (Admin o Recepcionista).

> Las cuentas `@test.com` NO existen por defecto — se crean corriendo el seeder opcional
> `php artisan db:seed --class=TestUsersSeeder` (no forma parte de la cadena principal de `DatabaseSeeder`).
> El administrador sí existe siempre, lo crea `AdminUserSeeder` en el seed por defecto.
