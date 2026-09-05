# Accesos UrbanBlade — Credenciales reales del equipo

> ⚠️ Este repositorio es PÚBLICO en GitHub. Este archivo se subió aquí a
> pedido explícito del dueño del proyecto, con el riesgo de exposición ya
> conocido y aceptado. Si eso cambia, mover esto a un repo privado o a otro
> canal y resetear las contraseñas de abajo.
>
> URL local: http://localhost:8000
> Actualizado: 2026-09-04 — tras limpieza completa de `barber_db` (se eliminaron
> ~214,623 citas y ~323,095 transacciones de lealtad sintéticas/de prueba que
> quedaron de una siembra masiva anterior; el listado de abajo refleja las
> ÚNICAS 4 cuentas que existen ahora mismo en la base compartida de Atlas).
> Contraseñas generadas aleatoriamente, cada una visible solo aquí — no se
> pueden volver a mostrar sin resetearlas.

## Administrador

| Campo | Valor |
|-------|-------|
| Email | `kikeramirez160418@gmail.com` |
| Contraseña | `\|3]GUn3O>%\d0osL` |
| Panel | http://localhost:8000/dashboard |

---

## Recepcionista

| Campo | Valor |
|-------|-------|
| Nombre | Valeria Villanueva |
| Email | `valeria.villanueva69@gmail.com` |
| Contraseña | `p%03Q[-[7!z_OE` |

---

## Barbero

| Campo | Valor |
|-------|-------|
| Email | `barbero@urbanblade.mx` |
| Contraseña | `,9*ZApz20nZ8hs` |

---

## Cliente

| Campo | Valor |
|-------|-------|
| Email | `cliente@urbanblade.mx` |
| Contraseña | `Z(&I\-lKE%\A~5` |

---

## Notas

- Base de datos: `barber_db` (MongoDB Atlas, compartida con `spark/` — coordinar
  cualquier cambio de esquema con ese proyecto).
- **La base se limpió por completo el 2026-09-04.** Ya no hay datos de demo
  masivos (antes: 50 barberos, 1500 clientes, miles de citas/pagos sintéticos).
  A partir de ahora el equipo debe ir cargando solo información real del
  negocio — no volver a correr `BarberSeeder`/`ClientSeeder` completos (crean
  50 y 1500 registros falsos respectivamente) salvo que de verdad se quiera
  repoblar con datos de prueba masivos.
- Respaldo de lo que había antes de la limpieza (30 clientes, 8 barberos,
  184 pagos, 41 productos, 330 citas reales, etc., en JSON) guardado fuera del
  repo, fuera de este equipo, solo si Luis lo pide expresamente — no vive en
  ningún repo ni carpeta del proyecto.
- Para crear más cuentas de equipo, pedirle a Claude Code (o correr
  manualmente) crear un `User` + perfil (`Client`/`Barber`) y asignar el rol
  con `assignRole()` — no usar los seeders masivos para esto.
