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

Each seeder populates exactly **one** MongoDB collection (except `RolePermissionSeeder`,
which seeds the tightly-coupled `roles` + `permissions`). `DatabaseSeeder` runs them in
this order — each step depends on the previous one already existing:

1. `RolePermissionSeeder` — roles, permissions
2. `AdminUserSeeder` — users (the one admin account)
3. `BarbershopSettingSeeder` — barbershop_settings
4. `ServiceSeeder` — services (20 real services)
5. `ProductSeeder` — products (15 sale + 15 work supplies)
6. `UserSeeder` — users (1 receptionist + 25 barbero users + 1000 cliente users, roles assigned)
7. `BarberSeeder` — barbers (professional profile per barbero user)
8. `BarberScheduleSeeder` — barber_schedules (weekly schedule per barber)
9. `ClientSeeder` — clients (profile per cliente user)
10. `AppointmentSeeder` — appointments (~12,500 historical appointments)
11. `PaymentSeeder` — payments (one per completed appointment)
12. `LoyaltyTransactionSeeder` — loyalty_transactions (+ recalculates client points/level/total_citas)
13. `WorkSeeder` — works (portfolio)
14. `WorkImageSeeder` — work_images
15. `CommentSeeder` — comments (portfolio)
16. `ReactionSeeder` — reactions (portfolio)

All of these use `updateOrCreate`/`firstOrCreate` (or check for existing records before
inserting), so re-running the full chain is safe and won't duplicate data.

- `TestUsersSeeder` — (optional, not in the default chain) creates sample accounts with fixed
  credentials (`recepcionista@test.com`, `barbero@test.com`, `cliente@test.com`) for manual QA.
- `MassiveDataSeeder` — (optional, not in the default chain) generates very large datasets for load
  testing (100k appointments). **Requires existing barbers/clients/services** — run only after the
  official `DatabaseSeeder` chain. Use with caution: it does not assign roles to existing users.

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
docker compose exec barber-app php artisan db:seed --force

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
- Recommended for a fully populated environment: `php artisan migrate:fresh --seed` which runs the
  official `DatabaseSeeder` chain (roles, admin, 25 barbers, 1000 clients, 20 services, 30 products,
  +10,000 historical appointments/payments, portfolio). This is also what correctly assigns roles
  to every created user — running individual seeders out of order can leave users without a role.
- For heavy load tests on top of an already-seeded database, `MassiveDataSeeder` can add tens of
  thousands of extra appointment records, but requires higher memory/runtime and does **not**
  assign roles — only run it after `migrate:fresh --seed` has completed.

Example from container:

```bash
docker compose exec barber-app php artisan migrate:fresh --seed --force
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
- Run seeders: `php artisan db:seed --force` (or `migrate:fresh --seed --force` for a clean reset)
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

_Last updated: 2026-05-27_
