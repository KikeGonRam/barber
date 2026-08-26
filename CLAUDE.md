# UrbanBlade (barber) — Barbershop management system

Personal/school project (team: "Equipo UrbanBlade"). Laravel admin dashboard and
operational system for a barbershop: role-based panels (Admin, Recepcionista,
Barbero, Cliente), appointments, payments, customers, inventory, reports, barber
portfolios, a client-facing store, and an analytics center fed by exported
findings from the sibling `spark/` project.

Repo: `https://github.com/KikeGonRam/barber.git`, working branch
`feature/mongodb-migration` (not `main`). This folder is one of several
independent repos gathered under the `UrbanBlade/` parent folder — see
`../CLONAR_PROYECTOS.md` and `../ACCESOS.md` for cross-project context.

## Stack

- PHP 8.2+, Laravel 12
- Database: MongoDB (via `mongodb/laravel-mongodb` ^5.0), Atlas-hosted `barber_db`,
  shared with the `spark/` analytics project
- Redis (cache, sessions, queue)
- Frontend build: Vite, Tailwind CSS 3, Alpine.js, Chart.js, FullCalendar
- Notable packages: laravel/breeze, spatie/laravel-permission, spatie/laravel-activitylog,
  stripe/stripe-php, barryvdh/laravel-dompdf, maatwebsite/excel, endroid/qr-code,
  thiagoalessio/tesseract_ocr (OCR), sentry/sentry-laravel, knuckleswtf/scribe (API docs)
- Docker Compose (app, Mailpit for local mail)

## Structure

- `app/`, `routes/`, `resources/`, `database/` — standard Laravel layout
- `docs/` — project documentation (see `docs/PROJECT_DOCUMENTATION.md`)
- `setup.ps1` — PowerShell bootstrap script (requires a real `.env`, not committed)
- `docker-compose.yml`, `Dockerfile` — containerized dev environment

## Run / build

```powershell
docker compose up -d --build
npm install && npm run build
docker compose exec app php artisan key:generate
docker compose exec app php artisan storage:link
docker compose exec app php artisan migrate --seed
```
App: http://localhost:8000, Mailpit: http://localhost:8025

- `composer run dev` — runs `php artisan serve` + queue listener + `npm run dev` concurrently
- Tests: PHPUnit (`phpunit.xml` present) — `php artisan test` or `vendor/bin/phpunit`
- Lint/format JS: `eslint`, `prettier` (husky + lint-staged configured)
- Lint PHP: `laravel/pint`

## Conventions / notes

- Never commit `.env` — MongoDB Atlas credentials live there only; use `.env.example`
  as the template.
- Roles and demo credentials for local testing are documented in `../ACCESOS.md`.
- Data model is shared with `spark/` (same `barber_db`); avoid conflicting schema changes.
