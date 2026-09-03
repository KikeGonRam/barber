# UrbanBlade (barber) — Barbershop management system

Personal/school project (team: "Equipo UrbanBlade"). Laravel admin dashboard and
operational system for a barbershop: role-based panels (Admin, Recepcionista,
Barbero, Cliente), appointments, payments, customers, inventory, reports, barber
portfolios, a client-facing store, and an analytics center fed by exported
findings from the sibling `spark/` project.

Repo: `https://github.com/KikeGonRam/barber.git`, working branch `main` (the
only branch — history from `feature/mongodb-migration` was merged forward and
the rest deleted). This folder is one of several independent repos gathered
under the `UrbanBlade/` parent folder — see `../CLONAR_PROYECTOS.md` and
`../ACCESOS.md` for cross-project context.

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
- Tests: PHPUnit (`phpunit.xml` present) — run with `.\test.ps1` (or
  `docker exec --env-file .env.testing barber-app php artisan test`), **not**
  plain `php artisan test`. See "Test database" below for why.
- Lint/format JS: `eslint`, `prettier` (husky + lint-staged configured)
- Lint PHP: `laravel/pint`

## Conventions / notes

- Never commit `.env` — MongoDB Atlas credentials live there only; use `.env.example`
  as the template.
- Roles and demo credentials for local testing are documented in `../ACCESOS.md`.
- Data model is shared with `spark/` (same `barber_db`); avoid conflicting schema changes.

## Test database

Integration/Feature tests run against a **local** MongoDB container
(`mongo-test` service in `docker-compose.yml`, port 27018 on the host),
never against the Atlas `barber_db` shared with `spark/`. Config lives in
`.env.testing` (`MONGO_DATABASE=barber_db_test`). `mongo-test` runs as a
single-node **replica set** (not standalone), initialized by the one-shot
`mongo-test-init` service (`rs.initiate()`, idempotent — safe on every
`docker compose up`): `PaymentService`/`InventoryService` wrap writes in
`DB::transaction()`, and MongoDB only supports multi-document transactions
inside a replica set.

**Always run tests via `.\test.ps1`**, not bare `docker exec barber-app php
artisan test` or `php artisan test`. Reason: `docker compose`'s
`env_file: .env` bakes the Atlas credentials into the `app` container as real
OS environment variables at container-creation time; Laravel's Dotenv never
overrides an already-set environment variable, so `.env.testing` is silently
ignored unless the override happens at the `docker exec` layer itself
(`--env-file .env.testing`). `test.ps1` does this for you. Skipping it means
tests run against the shared Atlas database.

## CI

`.github/workflows/ci.yml` runs on every push/PR to `main`: `backend` job
(Mongo as a single-node replica set + Redis, `pint --test`, `php artisan
test`) and `frontend` job (`eslint`, `npm run build`, `npm audit
--audit-level=high`). Unlike local dev, the runner starts clean with no
baked-in `.env`, so env vars are set directly in the workflow (no
`--env-file` dance needed there).

## Guardrails (read before running tests, migrations, seeders, or `docker compose down`)

- **Never run `php artisan test` directly** (with or without `docker exec`) — only
  `.\test.ps1`. `docker-compose.yml`'s `env_file: .env` bakes real Atlas credentials into
  the `app` container, which silently defeats `.env.testing`. This already caused a real
  incident on 2026-08-28 where the full suite ran against the shared Atlas `barber_db`
  and deleted real data via test `tearDown()`. See the comment block at the top of
  `test.ps1`.
- **`make setup`/`make seed`/`make migrate`/`composer run setup`** all run
  `migrate`/`db:seed` against the `app` container, which is wired to the real `.env`
  unless verified otherwise — confirm what `.env` points to before running these.
- **`make clean`** runs `docker compose down --remove-orphans --volumes` — destructive to
  local volumes, confirm with the user first.
- **`setup.ps1` defaults to the wrong branch** (`feature/mongodb-migration` instead of
  `main`) — pass `-Branch main` explicitly.
- `barber_db` is shared with the sibling `spark/` repo — coordinate schema changes.
- **Never `git push` without a clean, passing `.\test.ps1` run first** (and
  `pint --test` / `eslint`+`npm run build` for PHP/frontend changes). This is a hard
  rule from the project owner (2026-09-02), not a suggestion — if something fails, fix
  it or ask before pushing anyway.
- Active work is scoped to this repo only for now. `spark` is paused. `mobil` (the Expo
  app) is fully discontinued — a native Android Studio app is being built separately to
  replace it; don't propose or start work in either unprompted.
- `routes/api.php` / `app/Http/Controllers/Api/**` are now a real external contract for
  that future native app, not internal-only code — prefer additive changes, avoid
  silently reshaping existing routes/responses, and keep `knuckleswtf/scribe` docs
  (`php artisan scribe:generate`) in sync when the API changes.
- Full detail and the cross-repo incident writeup: see
  `.claude/skills/urbanblade-guardrails/SKILL.md` in this repo.
