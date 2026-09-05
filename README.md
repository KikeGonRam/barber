# UrbanBlade

<p align="center">
  <img src="docs/assets/landing.png" alt="UrbanBlade landing" width="1000" />
</p>

UrbanBlade es una plataforma operativa y analítica para barberías, pensada para centralizar administración, atención al cliente, agenda, pagos, inventario y decisiones basadas en datos. El sistema combina un dashboard premium para el negocio con experiencias diferenciadas para administrador, recepcionista, barbero y cliente.

## ✨ ¿Qué hace UrbanBlade?

- Gestiona citas, clientes, pagos e inventario desde un mismo panel.
- Separa flujos por rol para cada perfil del negocio.
- Ofrece analítica accionable con insights operativos y de negocio.
- Alinea la experiencia del cliente con reservas, historial, tienda y membresía.
- Está preparado para demo local, validación y presentación comercial.

## 🏗️ Stack

- PHP 8.3+
- Laravel 13
- MongoDB con mongodb/laravel-mongodb
- Redis para caché, sesiones y cola
- Vite + Tailwind CSS 3 + Alpine.js
- Chart.js, FullCalendar y componentes de dashboard premium
- Docker Compose para entorno local

## 🧭 Roles del sistema

| Rol           | Visión principal                                                                   |
| ------------- | ---------------------------------------------------------------------------------- |
| Administrador | Dashboard global, reportes, KPIs, clientes, pagos, inventario y control operativo. |
| Recepcionista | Agenda del día, atención rápida, cobros y gestión de clientes.                     |
| Barbero       | Horario personal, citas, perfil, portafolio y seguimiento de actividad.            |
| Cliente       | Reservas, historial, tienda, facturas y membresía.                                 |

## 📸 Vista previa

<p align="center">
  <img src="docs/assets/login.png" alt="Login UrbanBlade" width="900" />
</p>

<p align="center">
  <img src="docs/assets/dashboard-admin.png" alt="Dashboard UrbanBlade" width="1000" />
</p>

## 🚀 Arranque rápido

```powershell
git clone https://github.com/KikeGonRam/barber.git
cd barber
Copy-Item .env.example .env
```

Edita el archivo `.env` con los valores locales de tu entorno y luego levanta el proyecto:

```powershell
docker compose up -d --build
npm install
npm run build
docker compose exec app php artisan key:generate
docker compose exec app php artisan storage:link
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=RolePermissionSeeder
docker compose exec app php artisan db:seed --class=AdminUserSeeder
```

> ⚠️ **No uses `migrate --seed`** (siembra el `DatabaseSeeder` completo): eso
> incluye `BarberSeeder`/`ClientSeeder`, que generan 50 barberos y 1500
> clientes falsos, además de miles de citas/pagos/transacciones sintéticas —
> así fue como `barber_db` terminó con más de 200,000 registros de basura que
> hubo que limpiar. Los dos seeders de arriba son los únicos necesarios para
> que la app arranque (roles/permisos + una cuenta admin); el resto de
> cuentas de equipo se documentan en [docs/ACCESOS.md](docs/ACCESOS.md).

Abre la aplicación en:

- http://localhost:8000
- Mailpit: http://localhost:8025

## 🔐 Demo y acceso

Las credenciales reales del equipo (una cuenta por rol) viven en un único
lugar para no desincronizarse: **[docs/ACCESOS.md](docs/ACCESOS.md)**. La
guía de presentación está en [docs/DEMO_DEMOSTRACION.md](docs/DEMO_DEMOSTRACION.md).

Ruta de login:

- http://localhost:8000/login

> `barber_db` ya no viene precargada con datos de demo masivos (se limpió por
> completo el 2026-09-04) — solo existen las 4 cuentas documentadas en
> [docs/ACCESOS.md](docs/ACCESOS.md). No correr `BarberSeeder`/`ClientSeeder`
> completos salvo que de verdad se quiera repoblar con datos de prueba a gran
> escala (crean 50 barberos y 1500 clientes falsos respectivamente).

## 🧪 Validación y pruebas

Importante: en este proyecto no se deben ejecutar pruebas con `php artisan test` directo. La configuración real usa la base local de pruebas y la forma recomendada es:

```powershell
./test.ps1
```

Esto evita que Laravel use accidentalmente la base Atlas compartida con Spark.

## 🛠️ Comandos útiles

```powershell
docker compose ps
docker compose logs -f app
docker compose logs -f web
docker compose exec app php artisan validate:user-roles
docker compose exec app composer audit
docker compose exec app ./vendor/bin/pint --test
npm run build
```

También puedes usar Make:

```bash
make setup
make validate
make logs
make shell
```

## ⚙️ Configuración extra

### Chatbot con Ollama

```env
CHATBOT_AI_PROVIDER=ollama
OLLAMA_URL=http://host.docker.internal:11434
OLLAMA_MODEL=qwen2.5:3b
```

### Validación antes de entregar

```powershell
./test.ps1
docker compose exec app php artisan validate:user-roles
docker compose exec app php artisan view:cache
docker compose exec app composer audit
docker compose exec app composer validate --strict
docker compose exec app ./vendor/bin/pint --test
npm run build
```

Si todo queda en verde, el proyecto está listo para demo local.

## 📚 Documentación relevante

- [docs/ACCESOS.md](docs/ACCESOS.md)
- [docs/DEMO_DEMOSTRACION.md](docs/DEMO_DEMOSTRACION.md)
- [docs/DOCUMENTACION_TECNICA.md](docs/DOCUMENTACION_TECNICA.md)
- [docs/MANUAL_USUARIO.md](docs/MANUAL_USUARIO.md)

## 🧩 Estado del repositorio

- Rama actual: `main`
- El proyecto comparte datos operativos con `spark` mediante MongoDB
- Las pruebas de integración usan una base local de pruebas configurada en `.env.testing`

---

UrbanBlade está pensado para presentar una barbería como una operación real, moderna y basada en métricas, no como un simple calendario de citas.
