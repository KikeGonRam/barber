# BarberPro Elite — Project Documentation

> Comprehensive overview of the repository, modules, architecture, and developer workflows.

## Table of Contents
- Project Overview
- Tech Stack
- Repository Structure
- Key Modules (explanations)
- Authentication, Roles & Permissions
- Database, Seeders & Factories
- Frontend (Vite / Tailwind / Assets)
- Background Workers, Scheduler & Queues
- Docker / Local Development
- Testing
- Useful Commands
- Contributing & Notes

---

## Project Overview
BarberPro Elite is a Laravel 12-based appointment and management platform for barber shops. The application includes multi-role support (admin, receptionist, barber, client), appointment booking, inventory, payments, reporting and a polished Tailwind-based UI.

This documentation explains the main folders, modules and how to run, test and extend the project locally.

## Tech Stack
- Backend: PHP 8.4, Laravel 12
- Frontend: Tailwind CSS v3, Vite, Alpine.js (minimal), Node.js (for assets)
- Database: MySQL (containerized)
- Queue / Cache: Redis
- Testing: PHPUnit
- Env / Orchestration: Docker Compose

## Repository Structure (top-level)
- `app/` — Core Laravel app code (controllers, models, services, policies, notifications, providers)
- `bootstrap/` — framework bootstrap files
- `config/` — configuration files
- `database/` — migrations, factories and seeders
- `resources/` — Blade views, Tailwind CSS and JS source files
  - `resources/css/app.css` — project component styles (custom utilities like `.ui-input`)
  - `resources/views/` — Blade templates (auth, dashboard, admin views)
- `routes/` — route definitions (`web.php`, `api.php`, `auth.php`)
- `public/` — built assets and entry `index.php`
- `docker/` & `.docker/` — Docker images/configs used by compose
- `scripts/` — helper scripts (PowerShell or bash)
- `tests/` — PHPUnit feature and unit tests
- `docs/` — documentation (this file)

## Key Modules and Files
Explaination of important folders and files inside `app/` and other key areas.

**app/Http/**
- Controllers and form requests. Routes under `routes/web.php` and `routes/api.php` map to these controllers.

**app/Models/**
- Eloquent models representing domain entities: `User`, `Barber`, `Client`, `Appointment`, `Service`, `Product`, `Payment`, etc.

**app/Repositories/**
- Data-access abstraction layers (if present). Used to encapsulate DB queries and keep controllers thin.

**app/Services/**
- Business logic (payments, scheduling, notifications) implemented as services to keep controllers and models lean.

**app/Notifications/**
- Laravel Notifications used for email/in-app alerts.

**app/Policies/**
- Authorization policies controlling abilities per model.

**app/Providers/**
- Service providers that register bindings, Blade directives and global policies.

**database/migrations/**
- Schema definitions for all database tables.

**database/factories/**
- Model factories used by seeders and tests to generate realistic data.

**database/seeders/**
- Seeders available include:
  - `RolePermissionSeeder` — creates roles and permissions (Spatie package)
  - `AdminUserSeeder` — creates the documented admin user
  - `TestUsersSeeder` — creates sample receptionist, barbers and clients
  - `DemoDataSeeder` — populates services, products, appointments and payments for a realistic demo
  - `MassiveDataSeeder` — (optional) generates very large datasets for load testing (100k appointments). Use with caution.

## Authentication, Roles & Permissions
- Project uses Spatie `laravel-permission` to manage roles and permissions.
- Roles present: `admin`, `recepcionista`, `barbero`, `cliente`.
- A documented admin account exists in `docs/ACCESO.md` (email `al222310427@gmail.com`, password `password`) for development.
- Login/Registration views live under `resources/views/auth` and use `x-guest-layout` Blade components.

## Frontend & Styling
- Tailwind is configured in `tailwind.config.js` with extended colors and utility classes including custom variables.
- `resources/css/app.css` defines shared UI component classes: `.ui-input`, `.ui-btn`, `.ui-card`, etc.
- Vite is used to bundle assets. `vite.config.js` contains project asset settings.

## Background Jobs, Scheduler & Worker
- Queue driver configured to Redis. Background workers run inside `barber-worker` container.
- Scheduled tasks configured (check `app/Console/Kernel.php` or `bootstrap/` for registration).
- Worker/scheduler containers exist in compose and are named `barber-worker`, `barber-scheduler`.

## Docker & Local Development
The project ships with `docker-compose.yml` to run the whole stack locally. Key services:
- `mysql` (DB), `redis`, `app` (PHP-FPM), `web` (Nginx), `mailpit`, `adminer`, `worker`, `scheduler`.

Typical local workflow:

```bash
# build and run containers
docker compose up -d --build

# check containers
docker compose ps

# run artisan commands inside app container
docker compose exec barber-app php artisan migrate --force
docker compose exec barber-app php artisan db:seed --class=DemoDataSeeder --force

# build frontend assets inside web (or node container if present)
docker compose exec barber-web npm install
docker compose exec barber-web npm run build
# or for dev hot reload
docker compose exec barber-web npm run dev
```

Notes:
- Container names differ in environments. Use `docker compose ps` to confirm service names.
- On Windows ensure Docker Desktop is running and port mappings don't conflict.

## Database Seeding & Filling Data
- Recommended seeders for a populated demo: `php artisan db:seed --class=DemoDataSeeder` which creates barbers, clients, services, products, appointments (30 days) and inventory movements.
- For heavy load tests, `MassiveDataSeeder` can generate tens of thousands of records but requires higher memory and long runtime.

Example from container:

```bash
docker compose exec barber-app php artisan migrate:fresh --seed --force
# or selective seeder
docker compose exec barber-app php artisan db:seed --class=DemoDataSeeder --force
```

## Testing
- Run unit/feature tests with PHPUnit or Artisan:

```bash
docker compose exec barber-app php artisan test --filter=SomeTest
# or
docker compose exec barber-app vendor/bin/phpunit --testsuite=Feature
```

## Useful Artisan / Docker Commands
- Start containers: `docker compose up -d --build`
- Stop containers: `docker compose down`
- Exec into app container: `docker compose exec barber-app bash` (or sh)
- Run migrations: `php artisan migrate --force`
- Run seeders: `php artisan db:seed --class=DemoDataSeeder --force`
- Rebuild assets: `npm run build` or `npm run dev`

## Environment Variables
- `.env` stores database credentials, Redis, mail and other runtime configuration. Check `docker-compose.yml` for effective runtime variables used in containers.

## Tests & CI
- The project includes PHPUnit configuration in `phpunit.xml` and GitHub Actions or CI config may exist in `.github/` (check repository). Ensure to run targeted tests after changes.

## Contributing & Extending
- Follow existing patterns for controllers, services and repositories when adding features.
- Add tests for important business flows and run `php artisan test` before creating PRs.
- When adding frontend components, prefer existing Tailwind utilities and `resources/css/app.css` conventions.

## Appendix — Important Files Map
- [routes/web.php](routes/web.php) — main web routes
- [resources/views/auth/register.blade.php](resources/views/auth/register.blade.php) and [login.blade.php](resources/views/auth/login.blade.php)
- [resources/css/app.css](resources/css/app.css) — shared UI component classes
- [database/seeders/DatabaseSeeder.php](database/seeders/DatabaseSeeder.php) — entry point for seeders
- [docker-compose.yml](docker-compose.yml) — container orchestration

---

If you want, puedo:
- Añadir secciones más detalladas por controlador/servicio (ej. `AppointmentController`, `PaymentService`).
- Generar un README reducido con pasos rápidos de setup.
- Ejecutar los seeders dentro del entorno Docker ahora para poblar la base (indica si deseas el Seeder `DemoDataSeeder` o también `MassiveDataSeeder`).

---

_Last updated: 2026-05-27_
