# UrbanBlade (barber) — JSON API + surviving public/account pages

Personal/school project (team: "Equipo UrbanBlade"). This repo used to be a full
Laravel+Blade admin panel (role-based dashboards for Admin, Recepcionista, Barbero,
Cliente). **That panel was retired on 2026-09-06**, once the sibling Nuxt frontend
(`frontend-urban`) reached confirmed functional parity with every screen it had —
appointments, payments, customers, inventory, reports, barber portfolios, the
client-facing store, social feed, reviews, and analytics all live in Nuxt now.

**Today this repo is, functionally, a JSON API for `frontend-urban`** (routes/api.php,
Bearer-token auth via `mobile_api_tokens`, **not** Sanctum), plus a short, closed list
of pages that still render as Blade because Nuxt doesn't cover them: the public
landing (`/`), all of `routes/auth.php` (login/register/password reset/email
verification), and the chatbot widget (`chatbot.query`, public; `chatbot.clear-history`,
session-gated). Every other page that used to be Blade-only now has full parity in
Nuxt and its old route is a plain redirect there, not a rendered view: `/servicios`,
`/equipo/{barber}`, `/notifications(/preferences)`, `/profile`, `backups/database`, and
`cliente/membresia/tarjeta` (see `routes/web.php`, all dated 2026-09-09). The named
routes stay registered on purpose — deleting them breaks `route()` calls still live in
`welcome.blade.php`, the global nav, and the command palette; see the 2026-09-06 lesson
in guardrail #18 below. See `.claude/skills/urbanblade-guardrails/SKILL.md` guardrail
#18 and its final report for the full retirement history — this is not a gap to
"finish," it's a closed list.

`frontend-urban` (`https://github.com/KikeGonRam/frontend_Urbanblade.git`,
`C:\Users\luis1\Documents\UrbanBlade\frontend-urban`) is where the actual product UI
now lives — most day-to-day feature work happens there, coordinated against this
repo's API via `.claude/skills/urbanblade-completion-roadmap/SKILL.md` (same skill name
in both repos).

Repo: `https://github.com/KikeGonRam/barber.git`, working branch `main` (the only
branch — history from `feature/mongodb-migration` was merged forward and the rest
deleted). This folder is one of several independent repos gathered under the
`UrbanBlade/` parent folder — see `../ACCESOS.md` for cross-project context.

## Stack

- PHP 8.3+, Laravel 13
- Database: MongoDB (via `mongodb/laravel-mongodb` ^5.0), Atlas-hosted `barber_db`,
  shared with the `spark/` analytics project (paused, not actively worked on)
- Redis (cache, sessions, queue — `queue:work`/`schedule:work` run as their own Docker
  services, see `docker-compose.yml`)
- Frontend build (for the surviving Blade pages only — the real product frontend is
  `frontend-urban`): Vite, Tailwind CSS 3, Alpine.js
- Notable packages: laravel/breeze (auth scaffolding), laravel/socialite (Google
  login), laravel/pulse (ops dashboard for the `ingeniero` role, own `sqlite`
  connection), spatie/laravel-permission, spatie/laravel-activitylog, stripe/stripe-php,
  barryvdh/laravel-dompdf, maatwebsite/excel, endroid/qr-code (legacy, read-only
  displays only — QR is not an active payment method), thiagoalessio/tesseract_ocr
  (OCR), sentry/sentry-laravel, knuckleswtf/scribe (API docs)
- Docker Compose: `app` (PHP-FPM), `web` (nginx, port 8000), `worker` (queue),
  `scheduler`, `redis`, `mongo-test` + `mongo-test-init` (local test replica set),
  `mailpit`, `ollama` (local chatbot provider)

## Structure

- `app/`, `routes/`, `resources/`, `database/` — standard Laravel layout. Most of the
  historical admin-panel controllers/views under these are gone — see the retirement
  note above before assuming a Blade page exists for some feature.
- `docs/` — project documentation: `DOCUMENTACION_TECNICA.md` (architecture), `ACCESOS.md`
  (credentials — single source of truth, don't duplicate), `MANUAL_USUARIO.md` (end-user
  guide), `DEMO_DEMOSTRACION.md` (presentation script), `STRIPE_PRUEBAS_LOCALES.md`.
- `setup.ps1` — PowerShell bootstrap script (requires a real `.env`, not committed)
- `docker-compose.yml`, `Dockerfile`, `.docker/` — containerized dev environment

## Run / build

```powershell
docker compose up -d --build
npm install && npm run build
docker compose exec app php artisan key:generate
docker compose exec app php artisan storage:link
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=RolePermissionSeeder
docker compose exec app php artisan db:seed --class=AdminUserSeeder
```

App (API + surviving pages): http://localhost:8000, Mailpit: http://localhost:8025.
For the actual product UI, also run `npm run dev` in `frontend-urban` (port 3000).

**Never run `php artisan migrate --seed`** (the full `DatabaseSeeder`) — it chains
`BarberSeeder`/`ClientSeeder` and is exactly how `barber_db` accumulated ~200k
synthetic rows before a full wipe on 2026-09-04. The two seeders above are the only
ones needed to boot; other team accounts are documented individually in
`docs/ACCESOS.md`.

- `composer run dev` — runs `php artisan serve` + queue listener + `npm run dev`
  concurrently (local convenience only; the real containers use `docker compose`).
- Tests: PHPUnit (`phpunit.xml` present) — run with `.\test.ps1` (or
  `docker exec --env-file .env.testing barber-app php artisan test`), **not**
  plain `php artisan test`. See "Test database" below for why.
- Lint/format JS: `eslint`, `prettier` (husky + lint-staged configured)
- Lint PHP: `laravel/pint`; static analysis: `larastan`/`phpstan` (see guardrail #21
  below about its result cache going stale inside the persistent container).

## Conventions / notes

- Never commit `.env` — MongoDB Atlas credentials live there only; use `.env.example`
  as the template.
- Roles and demo credentials for local testing are documented in `docs/ACCESOS.md`.
- Data model is shared with `spark/` (same `barber_db`); avoid conflicting schema changes.
- `routes/api.php` / `app/Http/Controllers/Api/**` are a real external contract for
  `frontend-urban` and a future native Android app — prefer additive changes, keep
  Scribe docs (`php artisan scribe:generate`) in sync.

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
(`--env-file .env.testing`). `test.ps1` does this for you — and, since 2026-09-09,
restores `config:cache`/`route:cache` with the real (Atlas) environment afterward too,
so a normal dev request right after a test run isn't left re-parsing config/routes on
every hit (this repo's `/var/www/html` is a Windows→Docker 9p/DrvFS bind mount, slower
than native Linux I/O, which makes that cost much more noticeable than it would be
elsewhere). Skipping `test.ps1` means tests run against the shared Atlas database.

## CI

`.github/workflows/ci.yml` runs on every push/PR to `main`: `backend` job
(Mongo as a single-node replica set + Redis, `pint --test`, Larastan, `php artisan
test`) and `frontend` job (`eslint`, `npm run build`, `npm audit
--audit-level=high`) for the surviving Blade-adjacent JS. Unlike local dev, the runner
starts clean with no baked-in `.env`, so env vars are set directly in the workflow (no
`--env-file` dance needed there).

## Guardrails (read before running tests, migrations, seeders, or `docker compose down`)

This is a condensed pointer, not the full list — **read
`.claude/skills/urbanblade-guardrails/SKILL.md` before touching tests, migrations,
seeders, `make`/`docker compose down`, payments/inventory code, `routes/api.php`, or
any `.md` file in this repo.** (Both `.claude/skills/` and `.agents/skills/` carry the
identical, actively-maintained version — pick whichever your tool reads by convention.)
Highlights, all covered in much more depth there:

- **Never run `php artisan test` directly** — only `.\test.ps1`. Real incident
  2026-08-28: ran the suite against shared Atlas and deleted real data.
- `make setup`/`make seed`/`make migrate`/`composer run setup`, and any
  `docker compose down --volumes`, can hit the real Atlas DB or destroy local volumes —
  confirm what `.env` points to and confirm with the user first.
- **Never `git push` without a clean, passing `.\test.ps1` run first** (plus
  `pint --test` and Larastan for PHP changes, `eslint`+`npm run build` for JS). Hard
  rule from the project owner, not a suggestion.
- `barber_db` was fully wiped and reseeded on 2026-09-04 after accumulating ~200k
  synthetic rows from repeated full-seeder runs — **never run the full `DatabaseSeeder`
  again** without explicit confirmation.
- **Never trust a client-supplied price/amount for money or inventory** — always
  reread the source of truth server-side inside the service layer. Payment methods are
  exactly three: efectivo, transferencia, tarjeta (beta, real Stripe charge) — no QR as
  an active option.
- `spark` is paused (don't propose work there unprompted); `mobil` (Expo) is fully
  discontinued (a native Android app is being built separately to replace it).
- Docs live in specific places — `docs/ACCESOS.md` is the single source of truth for
  credentials. Before editing/creating any `.md`, grep the whole repo for related
  content first — this repo has real history of docs drifting into duplicate,
  independently-stale copies.
- This guardrails list (both the condensed version here and the full skill) can go
  stale within hours of something changing — if what you observe in the actual
  code/data/docs contradicts it, trust what you observe and say so.
