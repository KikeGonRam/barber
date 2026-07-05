# 🔐 Credenciales de Acceso - UrbanBlade

> **⚠️ IMPORTANTE:** Estas credenciales son solo para desarrollo y testing.
> **No usar en producción sin cambiar las contraseñas.**
> Las contraseñas por defecto se pueden sobreescribir con las variables de
> entorno `ADMIN_PASSWORD`, `RECEP_PASSWORD` y `BARBER_PASSWORD` (ver
> `database/seeders/AdminUserSeeder.php` y `UserSeeder.php`).

---

## 🌐 URL de Acceso

```
Frontend:    http://localhost:8000
Mailpit:     http://localhost:8025
```

---

## 👨‍💼 Cuentas creadas por el seed por defecto (`php artisan migrate:fresh --seed`)

Estas SIEMPRE existen después de correr la cadena oficial de `DatabaseSeeder`.

| Rol | Email | Contraseña | Notas |
|---|---|---|---|
| **Administrador** | `al222310427@gmail.com` | `Admin@Urban2025!` | Único admin (`AdminUserSeeder`) |
| **Recepcionista** | `recepcion@urbanblade.com` | `Recep@Urban2025!` | Única recepcionista (`UserSeeder`) |
| **Barbero** | `barbero1@urbanblade.com` … `barbero25@urbanblade.com` | `Barber@Urban2025!` | 25 cuentas, nombres generados con Faker (`UserSeeder` + `BarberSeeder`) |
| **Cliente** | `cliente1@urbanblade.com` … `cliente1000@urbanblade.com` | `Cliente@Urban2025!` | 1000 cuentas, nombres generados con Faker (`UserSeeder` + `ClientSeeder`) |

---

## 🧪 Cuentas extra para QA manual (opcional — `TestUsersSeeder`, no corre por defecto)

Solo existen si corres explícitamente:
```bash
docker exec barber-app php artisan db:seed --class=TestUsersSeeder
```

| Rol | Email | Contraseña |
|---|---|---|
| Recepcionista | `recepcionista@test.com`, `recepcionista1@test.com`, `recepcionista2@test.com` | `Recep@Urban2025!` |
| Barbero | `barbero@test.com`, `barbero1@test.com`, `barbero2@test.com` | `Barber@Urban2025!` |
| Cliente | `cliente@test.com`, `cliente1@test.com`, `cliente2@test.com` | `Cliente@Urban2025!` |

No usan una contraseña distinta a las cuentas del seed por defecto — mismo patrón `Rol@Urban2025!` por rol, solo cambia el dominio del correo (`@test.com` en vez de `@urbanblade.com`) para no chocar con las 1026 cuentas que ya crea `UserSeeder`.

---

## 🗄️ Base de Datos

| Campo | Valor |
|-------|-------|
| **Motor** | MongoDB (Atlas) |
| **Conexión** | `MONGODB_URI` en `.env` |
| **Base de Datos** | `barber_db` (producción/desarrollo), `barber_db_test` (tests) |

No hay Adminer/PHPMyAdmin — para inspeccionar datos usa MongoDB Compass con la misma `MONGODB_URI`, o `php artisan tinker` dentro del contenedor (`docker exec -it barber-app php artisan tinker`).

---

## ✅ Procedimiento de Login

1. Ir a http://localhost:8000
2. Hacer clic en "Iniciar Sesión"
3. Ingresar email y contraseña de alguna de las tablas de arriba

---

## 📱 Permisos por Rol

| Funcionalidad | Admin | Recepcionista | Barbero | Cliente |
|---|:---:|:---:|:---:|:---:|
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| Gestionar Usuarios | ✅ | ❌ | ❌ | ❌ |
| Gestionar Barberos | ✅ | ✅ | ❌ | ❌ |
| Gestionar Clientes | ✅ | ✅ | ❌ | ❌ |
| Ver Citas | ✅ | ✅ | ✅ | ✅ |
| Crear Citas | ✅ | ✅ | ❌ | ✅ |
| Reportes | ✅ | ❌ | ❌ | ❌ |
| Configuración | ✅ | ❌ | ❌ | ❌ |
